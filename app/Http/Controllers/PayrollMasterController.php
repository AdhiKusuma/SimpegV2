<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbKantor;
use App\Models\PayrollChangeRequest;
use App\Models\PayrollChangeRequestEntry;
use App\Models\PayrollImport;
use App\Services\NotificationService;
use App\Services\PayrollEntryValidationService;
use App\Services\PayrollPublicationService;
use App\Services\PayrollWorkbookService;
use App\Services\RbacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollMasterController extends Controller
{
    private const INPUT_AMOUNT_FIELDS = [
        ...PayrollEntryValidationService::EARNING_FIELDS,
        'golongan_pegawai',
        ...PayrollEntryValidationService::DEDUCTION_FIELDS,
    ];

    private const AMOUNT_FIELDS = [
        ...self::INPUT_AMOUNT_FIELDS,
        'jumlah_gaji', 'jumlah_potongan', 'gaji_bersih', 'total',
    ];

    private const DETAIL_FIELDS = ['rekening_tabungan', 'no_hp_keris'];

    public function index(Request $request, RbacService $rbac): Response
    {
        abort_unless($rbac->can($request, 'payroll.view'), 403);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $scope = $this->officeScope($request);
        $query = DB::table('payroll_salary_masters as m')
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'm.employee_id')
            ->leftJoin('tb_kantor as k', 'k.kode_kantor_detail', '=', 'm.office_code')
            ->leftJoin('tb_user as u', 'u.id_user', '=', 'm.updated_by')
            ->select(['m.employee_id', 'm.office_code', 'm.total', 'm.updated_at', 'm.updated_by', 'p.nama as employee_name', 'k.nama_kantor as office_name', 'u.nama_user as updated_by_name'])
            ->orderBy('p.nama')
            ->orderBy('m.employee_id');
        $this->applyOfficeScope($query, $scope, 'm.office_code');
        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $search = trim((string) $filters['search']);
            $query->where(fn ($builder) => $builder
                ->where('p.nama', 'like', '%'.$search.'%')
                ->orWhere('m.employee_id', 'like', '%'.$search.'%'));
        }

        $masters = $query->paginate(25)->withQueryString()->through(fn (object $row): array => [
            'employee_id' => (string) $row->employee_id,
            'employee_name' => (string) ($row->employee_name ?: 'Nama pegawai tidak ditemukan'),
            'office_code' => (string) $row->office_code,
            'office_name' => (string) ($row->office_name ?: 'Kantor tidak terdaftar'),
            'total' => (string) $row->total,
            'updated_at' => $row->updated_at ? date('d-m-Y H:i', strtotime((string) $row->updated_at)) : null,
            'updated_by' => $row->updated_by ? (string) ($row->updated_by_name ?: $row->updated_by) : null,
        ]);

        return Inertia::render('Payroll/Master', [
            'masters' => $masters,
            'filters' => ['search' => (string) ($filters['search'] ?? '')],
            'canEdit' => $rbac->can($request, 'payroll.master.edit'),
            'canDelete' => $rbac->can($request, 'payroll.master.delete'),
            'deleteRequiresApproval' => $this->willRequireApproval($request, $rbac),
            'canImport' => $rbac->can($request, 'payroll.import'),
            'canTransfer' => $rbac->can($request, 'payroll.import'),
            'transferDefaults' => [
                'tahun' => (int) now()->year,
                'bulan' => (int) now()->month,
                'tgl_gajian' => now()->format('Y-m-d'),
            ],
            'scopeLabel' => $scope['label'],
            'flash' => ['success' => $request->session()->get('success'), 'error' => $request->session()->get('error')],
        ]);
    }

    public function create(Request $request, RbacService $rbac, PayrollEntryValidationService $entryValidator): Response
    {
        abort_unless($rbac->can($request, 'payroll.master.edit'), 403);
        $scope = $this->officeScope($request);
        $directory = $entryValidator->officeDirectory();
        $activeOffices = [];
        $activeJobs = [];

        foreach (DB::table('tb_jabatan as j')
            ->leftJoin('tb_kantor as office', 'j.unit_kerja', '=', 'office.kode_kantor_detail')
            ->where('j.status_jab', 'Aktif')
            ->orderByDesc('j.tmt_jabatan')
            ->orderByDesc('j.id_jab')
            ->get(['j.id_peg', 'j.unit_kerja', 'j.jabatan', 'office.nama_kantor as division_name']) as $job) {
            $employeeId = (string) $job->id_peg;
            if (isset($activeOffices[$employeeId])) {
                continue;
            }

            $officeCode = $entryValidator->normaliseOfficeCode($job->unit_kerja, $directory);
            if ($officeCode !== null) {
                $activeOffices[$employeeId] = $officeCode;
                $activeJobs[$employeeId] = $job;
            }
        }

        $employeeRecords = DB::table('tb_pegawai')
            ->where('status_aktif', '1')
            ->whereIn('id_peg', array_keys($activeOffices))
            ->orderBy('nama')
            ->get(['id_peg', 'nama', 'nik', 'status_kepeg', 'tmt_kerja'])
            ->filter(fn (object $employee): bool => ! $scope['restricted'] || in_array($activeOffices[(string) $employee->id_peg], $scope['codes'], true))
            ->values();

        $employeeIds = $employeeRecords->pluck('id_peg')->map(fn ($id): string => (string) $id)->all();
        $countFamily = fn (string $table) => $employeeIds === []
            ? collect()
            : DB::table($table)->select('id_peg', DB::raw('COUNT(*) as total'))
                ->whereIn('id_peg', $employeeIds)->groupBy('id_peg')->pluck('total', 'id_peg');
        $spouseCounts = $countFamily('tb_suamiistri');
        $childCounts = $countFamily('tb_anak');
        $latestPayrollImport = $employeeIds === []
            ? collect()
            : DB::table('payroll_entries')->select('employee_id', DB::raw('MAX(payroll_import_id) as latest_import_id'))
                ->whereIn('employee_id', $employeeIds)->groupBy('employee_id');
        $latestPayrollByEmployee = $employeeIds === []
            ? collect()
            : DB::table('payroll_entries as entry')->joinSub($latestPayrollImport, 'latest', function ($join): void {
                $join->on('latest.employee_id', '=', 'entry.employee_id')
                    ->on('latest.latest_import_id', '=', 'entry.payroll_import_id');
            })->get([
                'entry.employee_id', 'entry.bank_office_code', 'entry.no_rekening', 'entry.nama_nasabah',
                'entry.rekening_tabungan', 'entry.no_hp_keris',
            ])->keyBy(fn (object $entry): string => (string) $entry->employee_id);

        $employees = $employeeRecords
            ->map(function (object $employee) use ($activeOffices, $activeJobs, $directory, $spouseCounts, $childCounts, $latestPayrollByEmployee, $entryValidator): array {
                $employeeId = (string) $employee->id_peg;
                $officeCode = $activeOffices[(string) $employee->id_peg];
                $office = $directory['by_detail']->get($officeCode);
                $job = $activeJobs[$employeeId] ?? null;
                $payroll = $latestPayrollByEmployee->get($employeeId);
                $accountNumber = (string) ($payroll?->no_rekening ?? '');

                return [
                    'id_peg' => $employeeId,
                    'nama' => (string) $employee->nama,
                    'nik' => (string) ($employee->nik ?? ''),
                    'employment_status' => (string) ($employee->status_kepeg ?? ''),
                    'payroll_field_rules' => $entryValidator->fieldRulesForStatus((string) ($employee->status_kepeg ?? '')),
                    'office_code' => $officeCode,
                    'office_name' => (string) ($office?->nama_kantor ?? 'Kantor tidak terdaftar'),
                    'position' => trim((string) ($job?->jabatan ?? '')),
                    'work_unit' => trim((string) ($job?->division_name ?? '')),
                    'start_date' => $employee->tmt_kerja ? date('Y-m-d', strtotime((string) $employee->tmt_kerja)) : '',
                    'spouse_count' => (int) ($spouseCounts->get($employeeId) ?? 0),
                    'child_count' => (int) ($childCounts->get($employeeId) ?? 0),
                    'bank_office_code' => (string) ($payroll?->bank_office_code ?? ''),
                    'no_rekening' => $accountNumber,
                    'nama_nasabah' => trim((string) ($payroll?->nama_nasabah ?? '')) ?: (string) $employee->nama,
                    'rekening_tabungan' => (string) ($payroll?->rekening_tabungan ?? ''),
                    'no_hp_keris' => (string) ($payroll?->no_hp_keris ?? ''),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Payroll/CreateMaster', [
            'employees' => $employees,
            'scopeLabel' => $scope['label'],
            'willRequireApproval' => $this->willRequireApproval($request, $rbac),
            'flash' => ['error' => $request->session()->get('error')],
        ]);
    }

    public function store(Request $request, RbacService $rbac, PayrollEntryValidationService $entryValidator, NotificationService $notifications, PayrollPublicationService $publications): RedirectResponse
    {
        abort_unless($rbac->can($request, 'payroll.master.edit'), 403);
        $rules = [
            'employee_id' => ['required', 'string', 'max:12'],
            'office_code' => ['required', 'string', 'max:20'],
            'bank_office_code' => ['required', 'string', 'max:3'],
            'no_rekening' => ['required', 'string', 'max:64'],
            'nama_nasabah' => ['required', 'string', 'max:150'],
            'rekening_tabungan' => ['nullable', 'string', 'max:64'],
            'no_hp_keris' => ['nullable', 'string', 'max:32', 'regex:/\A[0-9+().\s-]*\z/'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tgl_gajian' => ['required', 'date_format:Y-m-d'],
        ];
        foreach (self::INPUT_AMOUNT_FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:32'];
        }
        $validated = $request->validate($rules);
        $scope = $this->officeScope($request);
        $row = ['row_number' => 1];
        foreach (['office_code', 'employee_id', 'bank_office_code', 'no_rekening', 'nama_nasabah', ...self::DETAIL_FIELDS, ...self::INPUT_AMOUNT_FIELDS, 'tgl_gajian'] as $field) {
            $row[$field] = $validated[$field] ?? '';
        }
        $result = $entryValidator->validateRows([$row], $scope, (int) $validated['tahun'], (int) $validated['bulan']);
        if ($result['errors'] !== [] || count($result['entries']) !== 1) {
            $messages = collect($result['errors'])->flatMap(fn (array $error): array => $error['messages'])->unique()->values()->all();
            throw ValidationException::withMessages([
                'payroll' => $messages === [] ? 'Data payroll tidak lolos validasi.' : implode(' ', $messages),
            ]);
        }

        $entry = $result['entries'][0];
        $employeeId = (string) $entry['employee_id'];
        $officeCode = (string) $entry['office_code'];
        $newData = $entry;
        unset($newData['employee_id'], $newData['office_code'], $newData['source_row_number']);
        $userId = (string) $request->session()->get('id_user');
        $bypass = ! $this->willRequireApproval($request, $rbac);

        $changeRequest = DB::transaction(function () use ($entry, $employeeId, $officeCode, $newData, $validated, $userId, $bypass): PayrollChangeRequest {
            DB::table('tb_pegawai')->where('id_peg', $employeeId)->lockForUpdate()->first();
            if (DB::table('payroll_salary_masters')->where('employee_id', $employeeId)->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'Master Gaji untuk pegawai ini sudah ada. Gunakan menu Edit Payroll.']);
            }

            $pendingCreateExists = DB::table('payroll_change_request_entries as e')
                ->join('payroll_change_requests as r', 'r.id', '=', 'e.payroll_change_request_id')
                ->where('r.change_type', 'manual_create')
                ->where('r.status', 'pending')
                ->where('e.employee_id', $employeeId)
                ->exists();
            if ($pendingCreateExists) {
                throw ValidationException::withMessages(['employee_id' => 'Pengajuan Master Gaji untuk pegawai ini masih menunggu keputusan.']);
            }

            $change = PayrollChangeRequest::query()->create([
                'change_type' => 'manual_create',
                'tahun' => (int) $validated['tahun'],
                'bulan' => (int) $validated['bulan'],
                'requested_by' => $userId,
                'office_codes' => [$officeCode],
                'summary_data' => ['employee_count' => 1],
                'status' => $bypass ? 'approved' : 'pending',
                'decision_reason' => $bypass ? 'Dibuat langsung sesuai izin bypass approval.' : null,
                'decided_by' => $bypass ? $userId : null,
                'submitted_at' => now(),
                'decided_at' => $bypass ? now() : null,
            ]);
            $change->entries()->create([
                'employee_id' => $employeeId,
                'office_code' => $officeCode,
                'old_data' => null,
                'new_data' => $newData,
            ]);

            if ($bypass) {
                $payrollImport = $this->appendManualEntryToPeriod(
                    $entry,
                    (int) $validated['tahun'],
                    (int) $validated['bulan'],
                    $userId,
                    (int) $change->id,
                );

                DB::table('payroll_salary_masters')->insert([
                    'employee_id' => $employeeId,
                    'office_code' => $officeCode,
                    ...$newData,
                    'source_import_id' => $payrollImport->id,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $change;
        });

        if (! $bypass) {
            $notifications->notifyPayrollRequestCreated($changeRequest);

            return to_route('payroll.index', ['tahun' => $changeRequest->tahun, 'bulan' => $changeRequest->bulan])->with('success', 'Data Master Gaji diajukan. Data payroll akan masuk ke rekap setelah disetujui supervisor kantor terkait.');
        }

        $publishedImport = PayrollImport::query()
            ->where('tahun', $changeRequest->tahun)
            ->where('bulan', $changeRequest->bulan)
            ->where('is_current', true)
            ->orderByDesc('id')
            ->first();
        if ($publishedImport !== null) {
            $publications->createForImport($publishedImport, 'scheduled', null, (string) $request->session()->get('id_user'));
        }

        return to_route('payroll.index', ['tahun' => $changeRequest->tahun, 'bulan' => $changeRequest->bulan])->with('success', 'Data Master Gaji berhasil dibuat dan ditambahkan ke payroll periode ini.');
    }

    public function export(Request $request, RbacService $rbac, PayrollWorkbookService $workbooks, PayrollEntryValidationService $entryValidator): BinaryFileResponse
    {
        abort_unless($rbac->can($request, 'payroll.view'), 403);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'employee_id' => ['nullable', 'string', 'max:12'],
        ]);
        $scope = $this->officeScope($request);
        $query = DB::table('payroll_salary_masters as m')
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'm.employee_id')
            ->select([
                'm.employee_id', 'm.office_code', 'm.bank_office_code', 'm.no_rekening', 'm.nama_nasabah', 'm.tgl_gajian',
                ...array_map(fn (string $field): string => 'm.'.$field.' as '.$field, self::AMOUNT_FIELDS),
                'p.status_kepeg as employment_status',
            ])
            ->orderBy('p.nama')
            ->orderBy('m.employee_id');
        $this->applyOfficeScope($query, $scope, 'm.office_code');
        if (trim((string) ($filters['employee_id'] ?? '')) !== '') {
            $query->where('m.employee_id', trim((string) $filters['employee_id']));
        } elseif (trim((string) ($filters['search'] ?? '')) !== '') {
            $search = trim((string) $filters['search']);
            $query->where(fn ($builder) => $builder
                ->where('p.nama', 'like', '%'.$search.'%')
                ->orWhere('m.employee_id', 'like', '%'.$search.'%'));
        }

        $entries = $query->get()->map(function (object $entry) use ($entryValidator): array {
            $amounts = $entryValidator->applyAttendanceDeductionToExportAllowance(
                (array) $entry,
                (string) ($entry->employment_status ?? ''),
            );

            return $entryValidator->applyGradeToBaseSalaryForExport($amounts);
        })->all();
        $path = $workbooks->exportMaster($entries);

        return response()->download($path, 'Master_Gaji_SIMPEG.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Membuat batch payroll dari Master Gaji tanpa membutuhkan file XLSX.
     * Jalur ini sengaja menggunakan validator, approval, snapshot, dan publikasi
     * yang sama dengan import XLSX agar flow lama tetap berjalan.
     */
    public function transfer(Request $request, RbacService $rbac, PayrollEntryValidationService $entryValidator, NotificationService $notifications, PayrollPublicationService $publications): RedirectResponse
    {
        abort_unless($rbac->can($request, 'payroll.import'), 403);

        $validated = $request->validate([
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tgl_gajian' => ['required', 'date_format:Y-m-d'],
        ]);

        $payDate = Carbon::createFromFormat('Y-m-d', (string) $validated['tgl_gajian'], 'Asia/Jakarta');
        if ((int) $payDate->year !== (int) $validated['tahun'] || (int) $payDate->month !== (int) $validated['bulan']) {
            throw ValidationException::withMessages([
                'tgl_gajian' => 'Tanggal gajian harus berada pada tahun dan bulan yang dipilih.',
            ]);
        }

        $year = (int) $validated['tahun'];
        $month = (int) $validated['bulan'];
        $payDate = $payDate->format('Y-m-d');
        $scheduledAt = Carbon::createFromFormat('Y-m-d', $payDate, 'Asia/Jakarta')
            ->setTime(8, 0)
            ->setTimezone(config('app.timezone'))
            ->toDateTimeString();
        $scope = $this->officeScope($request);
        $actorId = (string) $request->session()->get('id_user');
        $requiresApproval = $this->willRequireApproval($request, $rbac);
        $sourceFilename = sprintf('Transfer Master Gaji %02d-%04d', $month, $year);

        $result = DB::transaction(function () use ($scope, $entryValidator, $year, $month, $payDate, $scheduledAt, $actorId, $requiresApproval, $sourceFilename): array {
            $query = DB::table('payroll_salary_masters as m')
                ->select('m.*')
                ->orderBy('m.employee_id')
                ->lockForUpdate();
            $this->applyOfficeScope($query, $scope, 'm.office_code');
            $masters = $query->get();

            if ($masters->isEmpty()) {
                throw ValidationException::withMessages([
                    'transfer' => 'Belum ada Master Gaji yang dapat ditransfer pada cakupan kantor akun ini.',
                ]);
            }

            $rows = $masters->values()->map(function (object $master, int $index) use ($payDate): array {
                $row = [
                    'row_number' => $index + 1,
                    'office_code' => (string) $master->office_code,
                    'employee_id' => (string) $master->employee_id,
                    'bank_office_code' => (string) ($master->bank_office_code ?? ''),
                    'no_rekening' => (string) ($master->no_rekening ?? ''),
                    'nama_nasabah' => (string) ($master->nama_nasabah ?? ''),
                    'tgl_gajian' => $payDate,
                    'rekening_tabungan' => (string) ($master->rekening_tabungan ?? ''),
                    'no_hp_keris' => (string) ($master->no_hp_keris ?? ''),
                ];

                foreach (self::INPUT_AMOUNT_FIELDS as $field) {
                    $row[$field] = (string) ($master->{$field} ?? '');
                }

                foreach (['jumlah_gaji', 'jumlah_potongan', 'gaji_bersih', 'total'] as $field) {
                    $row[$field] = (string) ($master->{$field} ?? '');
                }

                return $row;
            })->all();
            $validatedRows = $entryValidator->validateRows($rows, $scope, $year, $month);
            if ($validatedRows['errors'] !== [] || count($validatedRows['entries']) !== count($rows)) {
                throw ValidationException::withMessages([
                    'transfer' => $this->formatTransferValidationErrors($validatedRows['errors']),
                ]);
            }

            $entries = $validatedRows['entries'];
            $sourceHash = hash('sha256', json_encode([
                'tahun' => $year,
                'bulan' => $month,
                'tgl_gajian' => $payDate,
                'entries' => $entries,
            ], JSON_THROW_ON_ERROR));

            $duplicateCurrent = PayrollImport::query()
                ->where('tahun', $year)
                ->where('bulan', $month)
                ->where('is_current', true)
                ->where('source_sha256', $sourceHash)
                ->exists();
            $duplicatePending = PayrollChangeRequest::query()
                ->where('change_type', 'import')
                ->where('tahun', $year)
                ->where('bulan', $month)
                ->where('source_sha256', $sourceHash)
                ->where('status', 'pending')
                ->exists();
            if ($duplicateCurrent || $duplicatePending) {
                return [
                    'duplicate' => $duplicateCurrent
                        ? 'Master Gaji sudah sama dengan batch payroll aktif pada periode ini. Batch baru tidak dibuat.'
                        : 'Transfer Master Gaji yang sama masih menunggu approval. Batch baru tidak dibuat.',
                    'change' => null,
                    'import' => null,
                ];
            }

            $officeCodes = array_values(array_unique(array_map(fn (array $entry): string => (string) $entry['office_code'], $entries)));
            if ($requiresApproval) {
                $change = PayrollChangeRequest::query()->create([
                    'change_type' => 'import',
                    'tahun' => $year,
                    'bulan' => $month,
                    'source_filename' => $sourceFilename,
                    'source_sha256' => $sourceHash,
                    'requested_by' => $actorId,
                    'office_codes' => $officeCodes,
                    'summary_data' => [
                        'employee_count' => count($entries),
                        'office_count' => count($officeCodes),
                        'publication_mode' => 'scheduled',
                        'scheduled_at' => $scheduledAt,
                        'source_type' => 'master_gaji',
                    ],
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]);

                $mastersByEmployee = $masters->keyBy(fn (object $master): string => (string) $master->employee_id);
                $now = now();
                $requestEntries = array_map(function (array $entry) use ($change, $mastersByEmployee, $now): array {
                    $master = $mastersByEmployee->get((string) $entry['employee_id']);
                    $oldData = $master
                        ? array_intersect_key((array) $master, array_flip(['office_code', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian', ...self::DETAIL_FIELDS, ...self::AMOUNT_FIELDS]))
                        : null;
                    $newData = $entry;
                    unset($newData['employee_id'], $newData['office_code'], $newData['source_row_number']);

                    return [
                        'payroll_change_request_id' => $change->id,
                        'employee_id' => (string) $entry['employee_id'],
                        'office_code' => (string) $entry['office_code'],
                        'source_row_number' => (int) $entry['source_row_number'],
                        'old_data' => $oldData === null ? null : json_encode($oldData, JSON_THROW_ON_ERROR),
                        'new_data' => json_encode($newData, JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $entries);
                foreach (array_chunk($requestEntries, 500) as $chunk) {
                    DB::table('payroll_change_request_entries')->insert($chunk);
                }

                return ['duplicate' => null, 'change' => $change, 'import' => null];
            }

            $import = $this->createTransferPayrollSnapshot($entries, $year, $month, $sourceFilename, $sourceHash, $actorId);

            return ['duplicate' => null, 'change' => null, 'import' => $import];
        });

        if ($result['duplicate'] !== null) {
            return to_route('payroll.master.index')->with('success', $result['duplicate']);
        }

        if ($result['change'] instanceof PayrollChangeRequest) {
            $notifications->notifyPayrollRequestCreated($result['change']);

            return to_route('payroll.master.index')->with('success', 'Transfer Master Gaji diajukan untuk approval. Payroll akan dibuat dan dijadwalkan setelah disetujui.');
        }

        /** @var PayrollImport $import */
        $import = $result['import'];
        $publications->createForImport($import, 'scheduled', $scheduledAt, $actorId);

        return to_route('payroll.master.index')->with('success', 'Master Gaji berhasil ditransfer ke payroll dan dijadwalkan sesuai tanggal gajian.');
    }

    private function createTransferPayrollSnapshot(array $entries, int $year, int $month, string $sourceFilename, string $sourceHash, string $actorId): PayrollImport
    {
        $now = now();
        PayrollImport::query()
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->where('is_current', true)
            ->lockForUpdate()
            ->update(['is_current' => false, 'updated_at' => $now]);

        $import = PayrollImport::query()->create([
            'tahun' => $year,
            'bulan' => $month,
            'source_filename' => $sourceFilename,
            'source_sha256' => $sourceHash,
            'source_row_count' => count($entries),
            'source_summary_count' => count(array_unique(array_column($entries, 'office_code'))),
            'is_current' => true,
            'imported_by' => $actorId,
        ]);

        foreach (array_chunk($entries, 500) as $chunk) {
            DB::table('payroll_entries')->insert(array_map(fn (array $entry): array => [
                ...$entry,
                'payroll_import_id' => $import->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $amountSql = implode(', ', array_map(fn (string $field): string => 'SUM('.$field.') as '.$field, self::AMOUNT_FIELDS));
        $aggregates = DB::table('payroll_entries')
            ->where('payroll_import_id', $import->id)
            ->selectRaw('office_code, COUNT(*) as employee_count, '.$amountSql)
            ->groupBy('office_code')
            ->get();
        $officeNames = TbKantor::query()
            ->whereIn('kode_kantor_detail', $aggregates->pluck('office_code'))
            ->pluck('nama_kantor', 'kode_kantor_detail');
        $summaries = $aggregates->map(function (object $row) use ($officeNames, $import, $now): array {
            $summary = [
                'payroll_import_id' => $import->id,
                'office_code' => (string) $row->office_code,
                'source_label' => $officeNames[$row->office_code] ?? null,
                'employee_count' => (int) $row->employee_count,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach (self::AMOUNT_FIELDS as $field) {
                $summary[$field] = (string) $row->{$field};
            }

            return $summary;
        })->all();
        foreach (array_chunk($summaries, 500) as $chunk) {
            DB::table('payroll_office_summaries')->insert($chunk);
        }

        DB::table('payroll_salary_masters')
            ->whereIn('employee_id', array_values(array_unique(array_map(fn (array $entry): string => (string) $entry['employee_id'], $entries))))
            ->update(['source_import_id' => $import->id]);

        return $import;
    }

    private function formatTransferValidationErrors(array $errors): string
    {
        $messages = collect($errors)
            ->flatMap(fn (array $error): array => array_map(
                fn (string $message): string => isset($error['row']) ? 'Baris '.$error['row'].': '.$message : $message,
                $error['messages'] ?? [],
            ))
            ->unique()
            ->values();
        $total = $messages->count();
        $summary = $messages->take(8)->implode(' ');

        if ($summary === '') {
            return 'Data Master Gaji tidak lolos validasi payroll.';
        }

        return $total > 8 ? $summary.' Dan '.($total - 8).' masalah lainnya.' : $summary;
    }

    public function edit(Request $request, string $employeeId, RbacService $rbac, PayrollEntryValidationService $entryValidator): Response
    {
        abort_unless($rbac->can($request, 'payroll.master.edit'), 403);
        $scope = $this->officeScope($request);
        $query = DB::table('payroll_salary_masters as m')
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'm.employee_id')
            ->leftJoin('tb_kantor as k', 'k.kode_kantor_detail', '=', 'm.office_code')
            ->where('m.employee_id', $employeeId);
        $this->applyOfficeScope($query, $scope, 'm.office_code');
        $master = $query->first(['m.*', 'p.nama as employee_name', 'k.nama_kantor as office_name']);
        abort_if($master === null, 404);

        $amounts = [];
        foreach (self::AMOUNT_FIELDS as $field) {
            $amounts[$field] = (string) $master->{$field};
        }
        foreach (self::DETAIL_FIELDS as $field) {
            $amounts[$field] = (string) ($master->{$field} ?? '');
        }
        $employmentStatus = (string) DB::table('tb_pegawai')->where('id_peg', $employeeId)->value('status_kepeg');

        return Inertia::render('Payroll/Edit', [
            'employee' => [
                'employee_id' => (string) $master->employee_id,
                'employee_name' => (string) ($master->employee_name ?: 'Nama pegawai tidak ditemukan'),
                'office_code' => (string) $master->office_code,
                'office_name' => (string) ($master->office_name ?: 'Kantor tidak terdaftar'),
                'employment_status' => $employmentStatus,
                'payroll_field_rules' => $entryValidator->fieldRulesForStatus($employmentStatus),
                'amounts' => $amounts,
            ],
            'willRequireApproval' => $this->willRequireApproval($request, $rbac),
            'canExport' => $rbac->can($request, 'payroll.view'),
            'flash' => ['error' => $request->session()->get('error')],
        ]);
    }

    public function update(Request $request, string $employeeId, RbacService $rbac, NotificationService $notifications, PayrollEntryValidationService $entryValidator, PayrollPublicationService $publications): RedirectResponse
    {
        abort_unless($rbac->can($request, 'payroll.master.edit'), 403);
        $scope = $this->officeScope($request);
        $master = DB::table('payroll_salary_masters')->where('employee_id', $employeeId)->first();
        abort_if($master === null, 404);
        abort_if($scope['restricted'] && ! in_array((string) $master->office_code, $scope['codes'], true), 403);
        $pendingDelete = DB::table('payroll_change_request_entries as e')
            ->join('payroll_change_requests as r', 'r.id', '=', 'e.payroll_change_request_id')
            ->where('e.employee_id', $employeeId)
            ->where('r.change_type', 'manual_delete')
            ->where('r.status', 'pending')
            ->exists();
        if ($pendingDelete) {
            throw ValidationException::withMessages([
                'employee_id' => 'Perubahan tidak dapat diajukan selama penghapusan Master Gaji pegawai ini menunggu persetujuan.',
            ]);
        }

        $employmentStatus = (string) DB::table('tb_pegawai')->where('id_peg', $employeeId)->value('status_kepeg');
        $rules = array_fill_keys(self::INPUT_AMOUNT_FIELDS, ['nullable', 'regex:/\A\d{1,16}(?:\.\d{1,2})?\z/']);
        $rules['rekening_tabungan'] = ['nullable', 'string', 'max:64'];
        $rules['no_hp_keris'] = ['nullable', 'string', 'max:32', 'regex:/\A[0-9+().\s-]*\z/'];
        $validated = $request->validate($rules);
        $calculated = $entryValidator->calculateAmounts($validated, $employmentStatus);
        if ($calculated['errors'] !== []) {
            throw ValidationException::withMessages(['payroll' => implode(' ', $calculated['errors'])]);
        }

        $phone = trim((string) ($validated['no_hp_keris'] ?? ''));
        $newData = [
            ...$calculated['amounts'],
            'rekening_tabungan' => trim((string) ($validated['rekening_tabungan'] ?? '')) ?: null,
            'no_hp_keris' => $phone === '' ? null : preg_replace('/[\s+().-]/', '', $phone),
        ];
        $oldData = [];
        foreach ([...self::AMOUNT_FIELDS, ...self::DETAIL_FIELDS] as $field) {
            $oldData[$field] = $master->{$field} ?? null;
        }
        $hasChanges = false;
        foreach ($newData as $field => $value) {
            $matches = in_array($field, self::AMOUNT_FIELDS, true)
                ? $this->normaliseAmount((string) ($oldData[$field] ?? '0')) === $this->normaliseAmount((string) $value)
                : (string) ($oldData[$field] ?? '') === (string) ($value ?? '');
            $hasChanges = $hasChanges || ! $matches;
        }
        if (! $hasChanges) {
            throw ValidationException::withMessages([
                'payroll' => 'Tidak ada perubahan nilai yang perlu disimpan.',
            ]);
        }

        $userId = (string) $request->session()->get('id_user');
        $bypass = ! $this->willRequireApproval($request, $rbac);
        [$changeRequest, $payrollSnapshotUpdated] = DB::transaction(function () use ($master, $oldData, $newData, $userId, $bypass): array {
            $lockedMaster = DB::table('payroll_salary_masters')
                ->where('employee_id', $master->employee_id)
                ->where('office_code', $master->office_code)
                ->lockForUpdate()
                ->first();
            abort_if($lockedMaster === null, 409, 'Master Gaji berubah atau tidak ditemukan. Muat ulang data sebelum menyimpan.');

            foreach ($oldData as $field => $oldValue) {
                $currentValue = $lockedMaster->{$field} ?? null;
                $matches = in_array($field, self::AMOUNT_FIELDS, true)
                    ? $this->normaliseAmount((string) $currentValue) === $this->normaliseAmount((string) $oldValue)
                    : (string) ($currentValue ?? '') === (string) ($oldValue ?? '');
                abort_unless($matches, 409, 'Master Gaji telah berubah sejak halaman dibuka. Muat ulang data dan ajukan perubahan baru.');
            }

            $change = PayrollChangeRequest::query()->create([
                'change_type' => 'edit',
                'requested_by' => $userId,
                'office_codes' => [(string) $lockedMaster->office_code],
                'status' => $bypass ? 'approved' : 'pending',
                'decision_reason' => $bypass ? 'Disimpan langsung sesuai izin bypass approval.' : null,
                'decided_by' => $bypass ? $userId : null,
                'submitted_at' => now(),
                'decided_at' => $bypass ? now() : null,
            ]);

            $change->entries()->create([
                'employee_id' => (string) $lockedMaster->employee_id,
                'office_code' => (string) $lockedMaster->office_code,
                'old_data' => $oldData,
                'new_data' => $newData,
            ]);

            $payrollImport = null;
            if ($bypass) {
                $payrollImport = $this->appendEditedMasterToCurrentPeriod($lockedMaster, $newData, $userId, (int) $change->id);
                $masterUpdate = [
                    ...$newData,
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ];
                if ($payrollImport !== null) {
                    $masterUpdate['source_import_id'] = $payrollImport->id;
                }

                DB::table('payroll_salary_masters')
                    ->where('employee_id', $lockedMaster->employee_id)
                    ->where('office_code', $lockedMaster->office_code)
                    ->update($masterUpdate);
            }

            return [$change, $payrollImport !== null];
        });

        if (! $bypass) {
            $notifications->notifyPayrollRequestCreated($changeRequest);

            return to_route('payroll.master.index')->with('success', 'Perubahan Master Gaji diajukan. Data akan diperbarui setelah disetujui supervisor kantor terkait.');
        }

        $message = $payrollSnapshotUpdated
            ? 'Master Gaji dan rekap payroll periode aktif berhasil diperbarui. Versi payroll sebelumnya tetap tersimpan.'
            : 'Master Gaji berhasil diperbarui. Belum ada rincian payroll aktif untuk pegawai ini yang perlu diperbarui.';

        if ($payrollSnapshotUpdated) {
            $originImport = PayrollImport::query()->find((int) ($master->source_import_id ?? 0));
            $publishedImport = $originImport
                ? PayrollImport::query()->where('tahun', $originImport->tahun)->where('bulan', $originImport->bulan)->where('is_current', true)->orderByDesc('id')->first()
                : null;
            if ($publishedImport !== null) {
                $publications->createForImport($publishedImport, 'scheduled', null, (string) $request->session()->get('id_user'));
            }
        }

        return to_route('payroll.master.index')->with('success', $message);
    }

    public function destroy(Request $request, string $employeeId, RbacService $rbac, NotificationService $notifications): RedirectResponse
    {
        abort_unless($rbac->can($request, 'payroll.master.delete'), 403);
        $request->validate(['confirmation' => ['required', 'in:HAPUS']]);

        $scope = $this->officeScope($request);
        $userId = (string) $request->session()->get('id_user');
        $bypass = ! $this->willRequireApproval($request, $rbac);
        $changeRequest = DB::transaction(function () use ($employeeId, $scope, $userId, $bypass): PayrollChangeRequest {
            $query = DB::table('payroll_salary_masters')->where('employee_id', $employeeId);
            $this->applyOfficeScope($query, $scope, 'office_code');
            $master = $query->lockForUpdate()->first();
            abort_if($master === null, 404);

            $hasPendingRequest = DB::table('payroll_change_request_entries as e')
                ->join('payroll_change_requests as r', 'r.id', '=', 'e.payroll_change_request_id')
                ->where('e.employee_id', $employeeId)
                ->where('r.status', 'pending')
                ->lockForUpdate()
                ->exists();
            if ($hasPendingRequest) {
                throw ValidationException::withMessages([
                    'confirmation' => 'Selesaikan pengajuan payroll pegawai ini yang masih menunggu persetujuan sebelum menghapus Master Gaji.',
                ]);
            }

            $oldData = [];
            foreach (['office_code', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian', ...self::DETAIL_FIELDS, ...self::AMOUNT_FIELDS] as $field) {
                $oldData[$field] = $master->{$field};
            }

            $change = PayrollChangeRequest::query()->create([
                'change_type' => 'manual_delete',
                'requested_by' => $userId,
                'office_codes' => [(string) $master->office_code],
                'summary_data' => ['employee_count' => 1],
                'status' => $bypass ? 'approved' : 'pending',
                'decision_reason' => $bypass ? 'Dihapus langsung sesuai izin payroll.' : null,
                'decided_by' => $bypass ? $userId : null,
                'submitted_at' => now(),
                'decided_at' => $bypass ? now() : null,
            ]);
            $change->entries()->create([
                'employee_id' => (string) $master->employee_id,
                'office_code' => (string) $master->office_code,
                'old_data' => $oldData,
                'new_data' => [],
            ]);

            if ($bypass) {
                DB::table('payroll_salary_masters')
                    ->where('employee_id', $master->employee_id)
                    ->where('office_code', $master->office_code)
                    ->delete();
            }

            return $change;
        });

        if (! $bypass) {
            $notifications->notifyPayrollRequestCreated($changeRequest);

            return to_route('payroll.master.index')->with('success', 'Penghapusan Master Gaji diajukan dan menunggu persetujuan supervisor kantor terkait.');
        }

        return to_route('payroll.master.index')->with('success', 'Master Gaji berhasil dihapus. Riwayat payroll yang sudah tercatat tetap tersimpan.');
    }

    public function approvals(Request $request, RbacService $rbac): Response
    {
        abort_unless($rbac->can($request, 'payroll.approval.manage'), 403);
        $scope = $this->officeScope($request);
        $query = PayrollChangeRequest::query()
            ->where('status', 'pending')
            ->where('requested_by', '<>', (string) $request->session()->get('id_user'))
            ->orderByDesc('submitted_at');
        $this->applyRequestScope($query, $scope);

        $approvals = $query->paginate(20)->withQueryString()->through(function (PayrollChangeRequest $change): array {
            $requesterName = DB::table('tb_user')->where('id_user', $change->requested_by)->value('nama_user') ?: $change->requested_by;
            $employeeIds = $change->entries()->limit(3)->pluck('employee_id')->all();
            $employeeNames = DB::table('tb_pegawai')->whereIn('id_peg', $employeeIds)->pluck('nama', 'id_peg');
            $sample = array_map(fn (string $id): string => (string) ($employeeNames[$id] ?? $id), $employeeIds);

            return [
                'id' => (int) $change->id,
                'type' => $change->change_type,
                'period' => $change->tahun && $change->bulan ? sprintf('%02d-%04d', $change->bulan, $change->tahun) : null,
                'filename' => $change->source_filename,
                'requester' => (string) $requesterName,
                'submitted_at' => $change->submitted_at?->format('d-m-Y H:i'),
                'employee_count' => $change->entries()->count(),
                'employee_sample' => $sample,
                'office_names' => $this->officeLabels($change->office_codes ?? []),
            ];
        });

        return Inertia::render('Payroll/Approvals', [
            'approvals' => $approvals,
            'scopeLabel' => $scope['label'],
            'flash' => ['success' => $request->session()->get('success'), 'error' => $request->session()->get('error')],
        ]);
    }

    public function showApproval(Request $request, int $id, RbacService $rbac): Response
    {
        abort_unless($rbac->can($request, 'payroll.approval.manage'), 403);
        $change = PayrollChangeRequest::query()->withCount('entries')->findOrFail($id);
        $this->authorizeReview($request, $change);
        $entries = $change->entries()
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'payroll_change_request_entries.employee_id')
            ->select(['payroll_change_request_entries.*', 'p.nama as employee_name'])
            ->orderBy('p.nama')
            ->orderBy('payroll_change_request_entries.employee_id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (object $entry): array => [
                'employee_id' => (string) $entry->employee_id,
                'employee_name' => (string) ($entry->employee_name ?: 'Nama pegawai tidak ditemukan'),
                'office_code' => (string) $entry->office_code,
                'old_data' => $this->safeAmounts($entry->old_data),
                'new_data' => $this->safeAmounts($entry->new_data ?? []),
            ]);

        return Inertia::render('Payroll/ApprovalDetail', [
            'approval' => [
                'id' => (int) $change->id,
                'type' => $change->change_type,
                'period' => $change->tahun && $change->bulan ? sprintf('%02d-%04d', $change->bulan, $change->tahun) : null,
                'filename' => $change->source_filename,
                'requester' => (string) (DB::table('tb_user')->where('id_user', $change->requested_by)->value('nama_user') ?: $change->requested_by),
                'submitted_at' => $change->submitted_at?->format('d-m-Y H:i'),
                'status' => $change->status,
                'decision_reason' => $change->decision_reason,
                'employee_count' => (int) $change->entries_count,
                'office_names' => $this->officeLabels($change->office_codes ?? []),
                'summary' => $this->safeSummary($change),
            ],
            'entries' => $entries,
            'canDecide' => $change->status === 'pending',
            'flash' => ['error' => $request->session()->get('error')],
        ]);
    }

    public function decide(Request $request, int $id, RbacService $rbac, NotificationService $notifications, PayrollEntryValidationService $entryValidator, PayrollPublicationService $publications): RedirectResponse
    {
        abort_unless($rbac->can($request, 'payroll.approval.manage'), 403);
        $validated = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject'],
        ]);
        if ($validated['decision'] === 'reject' && mb_strlen(trim((string) $validated['reason'])) < 3) {
            throw ValidationException::withMessages(['reason' => 'Alasan penolakan minimal 3 karakter.']);
        }

        $change = DB::transaction(function () use ($request, $id, $validated, $entryValidator): PayrollChangeRequest {
            $change = PayrollChangeRequest::query()->lockForUpdate()->findOrFail($id);
            $this->authorizeReview($request, $change);
            abort_unless($change->status === 'pending', 409, 'Pengajuan ini sudah diproses. Muat ulang daftar approval.');
            $approverId = (string) $request->session()->get('id_user');

            if ($validated['decision'] === 'approve') {
                if ($change->change_type === 'import') {
                    $this->applyApprovedImport($change, $approverId);
                } elseif ($change->change_type === 'manual_create') {
                    $this->applyApprovedManualCreate($change, $approverId, $entryValidator);
                } elseif ($change->change_type === 'manual_delete') {
                    $this->applyApprovedDelete($change);
                } else {
                    $this->applyApprovedEdit($change, $approverId, $entryValidator);
                }
                $change->status = 'approved';
                $change->decision_reason = trim((string) ($validated['reason'] ?? '')) ?: null;
            } else {
                $change->status = 'rejected';
                $change->decision_reason = trim((string) $validated['reason']);
            }

            $change->decided_by = $approverId;
            $change->decided_at = now();
            $change->save();

            return $change;
        });

        $notifications->notifyPayrollRequestResult($change);
        if ($change->status === 'approved' && $change->change_type !== 'manual_delete' && $change->tahun && $change->bulan) {
            $publishedImport = PayrollImport::query()
                ->where('tahun', $change->tahun)
                ->where('bulan', $change->bulan)
                ->where('is_current', true)
                ->orderByDesc('id')
                ->first();
            if ($publishedImport !== null) {
                $summary = $change->summary_data ?? [];
                $publications->createForImport(
                    $publishedImport,
                    'scheduled',
                    $summary['scheduled_at'] ?? null,
                    (string) $change->decided_by,
                );
            }
        }
        $message = $change->status === 'approved'
            ? ($change->change_type === 'manual_delete'
                ? 'Pengajuan penghapusan Master Gaji disetujui. Riwayat payroll tetap tersimpan.'
                : 'Pengajuan payroll disetujui dan Master Gaji diperbarui.')
            : 'Pengajuan payroll ditolak. Data payroll tidak diubah.';

        return to_route('payroll.approvals.index')->with('success', $message);
    }

    private function applyApprovedEdit(PayrollChangeRequest $change, string $approverId, PayrollEntryValidationService $entryValidator): void
    {
        foreach ($change->entries()->get() as $entry) {
            $newData = $entry->new_data ?? [];
            $master = DB::table('payroll_salary_masters')
                ->where('employee_id', $entry->employee_id)
                ->where('office_code', $entry->office_code)
                ->lockForUpdate()
                ->first();
            abort_if($master === null, 409, 'Data Master Gaji berubah atau tidak ditemukan. Periksa ulang sebelum memproses pengajuan.');

            foreach (($entry->old_data ?? []) as $field => $oldValue) {
                $currentValue = $master->{$field} ?? null;
                $matches = in_array($field, self::AMOUNT_FIELDS, true)
                    ? $this->normaliseAmount((string) $currentValue) === $this->normaliseAmount((string) $oldValue)
                    : (string) ($currentValue ?? '') === (string) ($oldValue ?? '');
                if (! $matches) {
                    abort(409, 'Master Gaji telah berubah sejak pengajuan dibuat. Muat ulang data dan ajukan perubahan baru.');
                }
            }

            $employmentStatus = (string) DB::table('tb_pegawai')->where('id_peg', $entry->employee_id)->value('status_kepeg');
            $calculated = $entryValidator->calculateAmounts($newData, $employmentStatus);
            abort_if($calculated['errors'] !== [], 409, 'Nilai payroll atau status pegawai berubah dan tidak lagi lolos validasi. Minta pengaju mengirim ulang perubahan.');
            $newData = [...$newData, ...$calculated['amounts']];

            $payrollImport = $this->appendEditedMasterToCurrentPeriod($master, $newData, $approverId, (int) $change->id);
            $masterUpdate = [...$newData, 'updated_by' => $approverId, 'updated_at' => now()];
            if ($payrollImport !== null) {
                $masterUpdate['source_import_id'] = $payrollImport->id;
            }

            DB::table('payroll_salary_masters')
                ->where('employee_id', $entry->employee_id)
                ->where('office_code', $entry->office_code)
                ->update($masterUpdate);
        }
    }

    private function applyApprovedManualCreate(PayrollChangeRequest $change, string $approverId, PayrollEntryValidationService $entryValidator): void
    {
        foreach ($change->entries()->get() as $entry) {
            $newData = $entry->new_data ?? [];
            $row = [
                ...$newData,
                'row_number' => 1,
                'employee_id' => (string) $entry->employee_id,
                'office_code' => (string) $entry->office_code,
            ];
            $validation = $entryValidator->validateRows(
                [$row],
                ['restricted' => true, 'codes' => array_values(array_map('strval', $change->office_codes ?? []))],
                (int) $change->tahun,
                (int) $change->bulan,
            );
            abort_if($validation['errors'] !== [] || count($validation['entries']) !== 1, 409, 'Data pegawai atau kantor berubah sejak pengajuan. Minta pengaju mengirim ulang data Master Gaji.');
            abort_if(DB::table('payroll_salary_masters')->where('employee_id', $entry->employee_id)->lockForUpdate()->exists(), 409, 'Master Gaji untuk pegawai ini sudah dibuat. Pengajuan tidak dapat diterapkan lagi.');

            $validatedEntry = $validation['entries'][0];
            unset($validatedEntry['source_row_number']);
            $payrollEntry = $validation['entries'][0];
            $payrollImport = $this->appendManualEntryToPeriod(
                $payrollEntry,
                (int) $change->tahun,
                (int) $change->bulan,
                $approverId,
                (int) $change->id,
            );

            DB::table('payroll_salary_masters')->insert([
                ...$validatedEntry,
                'source_import_id' => $payrollImport->id,
                'created_by' => (string) $change->requested_by,
                'updated_by' => $approverId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function appendEditedMasterToCurrentPeriod(object $master, array $newData, string $actorId, int $changeRequestId): ?PayrollImport
    {
        $originImport = ! empty($master->source_import_id)
            ? PayrollImport::query()->find($master->source_import_id)
            : null;

        if ($originImport !== null) {
            $year = (int) $originImport->tahun;
            $month = (int) $originImport->bulan;
        } else {
            $payDate = date_create_immutable((string) ($master->tgl_gajian ?? ''));
            if ($payDate === false) {
                return null;
            }

            $year = (int) $payDate->format('Y');
            $month = (int) $payDate->format('n');
        }

        $current = PayrollImport::query()
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->where('is_current', true)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
        if ($current === null) {
            return null;
        }

        $fields = ['office_code', 'employee_id', 'source_row_number', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian', ...self::DETAIL_FIELDS, ...self::AMOUNT_FIELDS];
        $entry = $current->entries()
            ->where('employee_id', (string) $master->employee_id)
            ->first($fields);
        if ($entry === null) {
            return null;
        }

        $incoming = [...$entry->getAttributes(), ...$newData];

        return $this->appendManualEntryToPeriod($incoming, $year, $month, $actorId, $changeRequestId, 'edit');
    }

    private function appendManualEntryToPeriod(array $incoming, int $year, int $month, string $actorId, int $changeRequestId, string $operation = 'create'): PayrollImport
    {
        $fields = ['office_code', 'employee_id', 'source_row_number', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian', ...self::DETAIL_FIELDS, ...self::AMOUNT_FIELDS];
        $incoming = array_intersect_key($incoming, array_flip($fields));
        $incoming['source_row_number'] = max(1, (int) ($incoming['source_row_number'] ?? 1));

        $current = PayrollImport::query()
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->where('is_current', true)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $entriesByEmployee = [];
        if ($current) {
            foreach ($current->entries()->get($fields) as $entry) {
                $entriesByEmployee[(string) $entry->employee_id] = $entry->toArray();
            }
        }
        $entriesByEmployee[(string) $incoming['employee_id']] = $incoming;
        $entries = array_values($entriesByEmployee);

        if ($current) {
            $current->forceFill(['is_current' => false, 'updated_at' => now()])->save();
        }

        $now = now();
        $import = PayrollImport::query()->create([
            'tahun' => $year,
            'bulan' => $month,
            'source_filename' => ($operation === 'edit' ? 'Perubahan Master Gaji ' : 'Input manual ').$incoming['employee_id'],
            'source_sha256' => hash('sha256', 'manual-'.$operation.':'.$changeRequestId),
            'source_row_count' => count($entries),
            'source_summary_count' => count(array_unique(array_column($entries, 'office_code'))),
            'is_current' => true,
            'imported_by' => $actorId,
        ]);

        foreach (array_chunk($entries, 500) as $chunk) {
            DB::table('payroll_entries')->insert(array_map(fn (array $entry): array => [
                ...$entry,
                'payroll_import_id' => $import->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $amountSql = implode(', ', array_map(fn (string $field): string => 'SUM('.$field.') as '.$field, self::AMOUNT_FIELDS));
        $aggregates = DB::table('payroll_entries')
            ->where('payroll_import_id', $import->id)
            ->selectRaw('office_code, COUNT(*) as employee_count, '.$amountSql)
            ->groupBy('office_code')
            ->get();
        $officeNames = TbKantor::query()
            ->whereIn('kode_kantor_detail', $aggregates->pluck('office_code'))
            ->pluck('nama_kantor', 'kode_kantor_detail');
        $summaries = $aggregates->map(function (object $row) use ($officeNames, $import, $now): array {
            $summary = [
                'payroll_import_id' => $import->id,
                'office_code' => (string) $row->office_code,
                'source_label' => $officeNames[$row->office_code] ?? null,
                'employee_count' => (int) $row->employee_count,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach (self::AMOUNT_FIELDS as $field) {
                $summary[$field] = (string) $row->{$field};
            }

            return $summary;
        })->all();

        foreach (array_chunk($summaries, 500) as $chunk) {
            DB::table('payroll_office_summaries')->insert($chunk);
        }

        return $import;
    }

    private function applyApprovedDelete(PayrollChangeRequest $change): void
    {
        foreach ($change->entries()->get() as $entry) {
            $master = DB::table('payroll_salary_masters')
                ->where('employee_id', $entry->employee_id)
                ->where('office_code', $entry->office_code)
                ->lockForUpdate()
                ->first();
            abort_if($master === null, 409, 'Master Gaji telah dihapus atau berubah. Pengajuan tidak dapat diterapkan.');

            foreach (($entry->old_data ?? []) as $field => $oldValue) {
                $currentValue = $master->{$field} ?? null;
                $matches = in_array($field, self::AMOUNT_FIELDS, true)
                    ? $this->normaliseAmount((string) $currentValue) === $this->normaliseAmount((string) $oldValue)
                    : (string) ($currentValue ?? '') === (string) ($oldValue ?? '');
                abort_unless($matches, 409, 'Master Gaji telah berubah sejak pengajuan dibuat. Muat ulang data dan ajukan penghapusan baru.');
            }

            DB::table('payroll_salary_masters')
                ->where('employee_id', $entry->employee_id)
                ->where('office_code', $entry->office_code)
                ->delete();
        }
    }

    private function applyApprovedImport(PayrollChangeRequest $change, string $approverId): void
    {
        $incoming = $change->entries()->get()->map(function (PayrollChangeRequestEntry $entry): array {
            $data = $entry->new_data ?? [];
            $data['employee_id'] = (string) $entry->employee_id;
            $data['office_code'] = (string) $entry->office_code;
            $data['source_row_number'] = (int) ($entry->source_row_number ?? 0);

            return $data;
        });
        $current = PayrollImport::query()
            ->where('tahun', $change->tahun)
            ->where('bulan', $change->bulan)
            ->where('is_current', true)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $mergedByEmployee = [];
        $replacedOfficeCodes = array_fill_keys(array_map('strval', $change->office_codes ?? []), true);
        if ($current) {
            $fields = ['office_code', 'employee_id', 'source_row_number', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian', ...self::DETAIL_FIELDS, ...self::AMOUNT_FIELDS];
            foreach ($current->entries()->get($fields) as $entry) {
                if (isset($replacedOfficeCodes[(string) $entry->office_code])) {
                    continue;
                }
                $mergedByEmployee[(string) $entry->employee_id] = $entry->toArray();
            }
        }
        foreach ($incoming as $entry) {
            $mergedByEmployee[(string) $entry['employee_id']] = $entry;
        }
        $merged = array_values($mergedByEmployee);

        if ($current) {
            $current->forceFill(['is_current' => false, 'updated_at' => now()])->save();
        }
        $import = PayrollImport::query()->create([
            'tahun' => $change->tahun,
            'bulan' => $change->bulan,
            'source_filename' => $change->source_filename ?: 'Pengajuan payroll',
            'source_sha256' => $change->source_sha256 ?: hash('sha256', (string) $change->id),
            'source_row_count' => count($merged),
            'source_summary_count' => count(array_unique(array_column($merged, 'office_code'))),
            'is_current' => true,
            'imported_by' => $approverId,
        ]);

        $now = now();
        foreach (array_chunk($merged, 500) as $chunk) {
            DB::table('payroll_entries')->insert(array_map(fn (array $row): array => [
                ...$row,
                'payroll_import_id' => $import->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }

        $amountSql = implode(', ', array_map(fn (string $field): string => 'SUM('.$field.') as '.$field, self::AMOUNT_FIELDS));
        $aggregates = DB::table('payroll_entries')
            ->where('payroll_import_id', $import->id)
            ->selectRaw('office_code, COUNT(*) as employee_count, '.$amountSql)
            ->groupBy('office_code')
            ->get();
        $officeNames = TbKantor::query()->whereIn('kode_kantor_detail', $aggregates->pluck('office_code'))->pluck('nama_kantor', 'kode_kantor_detail');
        $summaries = $aggregates->map(function (object $row) use ($officeNames, $import, $now): array {
            $summary = [
                'payroll_import_id' => $import->id,
                'office_code' => (string) $row->office_code,
                'source_label' => $officeNames[$row->office_code] ?? null,
                'employee_count' => (int) $row->employee_count,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            foreach (self::AMOUNT_FIELDS as $field) {
                $summary[$field] = (string) $row->{$field};
            }

            return $summary;
        })->all();
        foreach (array_chunk($summaries, 500) as $chunk) {
            DB::table('payroll_office_summaries')->insert($chunk);
        }

        foreach (array_chunk($incoming->all(), 500) as $chunk) {
            $records = array_map(function (array $entry) use ($import, $approverId, $now): array {
                $record = [
                    ...$entry,
                    'source_import_id' => $import->id,
                    'created_by' => $approverId,
                    'updated_by' => $approverId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                unset($record['source_row_number']);

                return $record;
            }, $chunk);
            DB::table('payroll_salary_masters')->upsert(
                $records,
                ['employee_id'],
                ['office_code', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian', ...self::DETAIL_FIELDS, ...self::AMOUNT_FIELDS, 'source_import_id', 'updated_by', 'updated_at'],
            );
        }
    }

    private function authorizeReview(Request $request, PayrollChangeRequest $change): void
    {
        abort_if((string) $change->requested_by === (string) $request->session()->get('id_user'), 403, 'Pengaju tidak dapat menyetujui pengajuannya sendiri.');
        $scope = $this->officeScope($request);
        if ($scope['restricted']) {
            $requestedOffices = array_values(array_unique(array_map('strval', $change->office_codes ?? [])));
            abort_if($requestedOffices === [] || array_diff($requestedOffices, $scope['codes']) !== [], 403, 'Sebagian kantor pada pengajuan ini berada di luar cakupan kantor Anda.');
        }
    }

    private function willRequireApproval(Request $request, RbacService $rbac): bool
    {
        return strtolower((string) $request->session()->get('hak_akses')) !== 'admin'
            && ! $rbac->can($request, 'payroll.approval.bypass');
    }

    private function applyOfficeScope($query, array $scope, string $column): void
    {
        if ($scope['restricted']) {
            $query->whereIn($column, $scope['codes']);
        }
    }

    private function applyRequestScope($query, array $scope): void
    {
        if (! $scope['restricted']) {
            return;
        }
        if ($scope['codes'] === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(function ($builder) use ($scope): void {
            foreach ($scope['codes'] as $index => $code) {
                $method = $index === 0 ? 'whereJsonContains' : 'orWhereJsonContains';
                $builder->{$method}('office_codes', (string) $code);
            }
        });
    }

    private function officeScope(Request $request): array
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return ['restricted' => false, 'codes' => [], 'label' => null];
        }

        $officeCode = $role === 'kepala'
            ? (string) $request->session()->get('kode_kantor', '')
            : (string) TbJabatan::query()
                ->where('id_peg', (string) $request->session()->get('id_pegawai', ''))
                ->where('status_jab', 'Aktif')
                ->orderByDesc('id_jab')
                ->value('unit_kerja');
        $office = TbKantor::query()->where('kode_kantor_detail', $officeCode)->first(['kode_kantor_detail', 'kode_cabang', 'nama_kantor', 'level']);
        if (! $office) {
            return ['restricted' => true, 'codes' => [], 'label' => 'Kantor akun belum tercatat'];
        }

        $codes = strtoupper((string) $office->level) === 'KC' && $office->kode_cabang
            ? TbKantor::query()->where('kode_cabang', $office->kode_cabang)->pluck('kode_kantor_detail')->map(fn ($code): string => (string) $code)->all()
            : [(string) $office->kode_kantor_detail];

        return ['restricted' => true, 'codes' => $codes, 'label' => 'Wilayah: '.trim((string) $office->nama_kantor)];
    }

    private function officeLabels(array $officeCodes): array
    {
        return TbKantor::query()
            ->whereIn('kode_kantor_detail', array_values(array_map('strval', $officeCodes)))
            ->orderBy('nama_kantor')
            ->get(['kode_kantor_detail', 'nama_kantor'])
            ->map(fn (TbKantor $office): array => ['code' => (string) $office->kode_kantor_detail, 'name' => (string) $office->nama_kantor])
            ->values()
            ->all();
    }

    private function safeAmounts(mixed $data): ?array
    {
        if ($data === null) {
            return null;
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($data)) {
            return null;
        }

        return array_intersect_key($data, array_flip(self::AMOUNT_FIELDS));
    }

    private function safeSummary(PayrollChangeRequest $change): array
    {
        if ($change->change_type !== 'import') {
            return [];
        }

        return ['employee_count' => $change->entries()->count()];
    }

    private function normaliseAmount(string $amount): string
    {
        [$integer, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';

        return $integer.'.'.str_pad($fraction, 2, '0');
    }
}
