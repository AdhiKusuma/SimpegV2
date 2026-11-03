<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbKantor;
use App\Models\PayrollChangeRequest;
use App\Models\PayrollImport;
use App\Models\PayrollOfficeSummary;
use App\Models\PayrollSalaryMaster;
use App\Services\NotificationService;
use App\Services\PayrollEntryValidationService;
use App\Services\PayrollWorkbookService;
use App\Services\RbacService;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Shuchkin\SimpleXLSX;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollController extends Controller
{
    private const INPUT_AMOUNT_FIELDS = [
        ...PayrollEntryValidationService::EARNING_FIELDS,
        'golongan_pegawai',
        ...PayrollEntryValidationService::DEDUCTION_FIELDS,
    ];

    private const ENTRY_AMOUNT_FIELDS = [
        ...self::INPUT_AMOUNT_FIELDS,
        'jumlah_gaji', 'jumlah_potongan', 'gaji_bersih', 'total',
    ];

    private const SUMMARY_AMOUNT_FIELDS = self::ENTRY_AMOUNT_FIELDS;

    private const PAYROLL_HEADERS = [
        'kodekantor' => 'office_code',
        'idpeg' => 'employee_id',
        'idpegawai' => 'employee_id',
        'kodekantorrekening' => 'bank_office_code',
        'norekeningtabungan' => 'rekening_tabungan',
        'norekening' => 'no_rekening',
        'nomerrekening' => 'no_rekening',
        'namanasabah' => 'nama_nasabah',
        'jabatan' => 'source_jabatan',
        'unitkerja' => 'source_unit_kerja',
        'status' => 'source_status',
        'rekeningtabungan' => 'rekening_tabungan',
        'nohpkeris' => 'no_hp_keris',
        'gajipokok' => 'gaji_pokok',
        'golonganpegawai' => 'golongan_pegawai',
        'nominalgolonganpegawai' => 'golongan_pegawai',
        'tunjsuamiistri' => 'tunj_suami_istri',
        'tunjanak' => 'tunj_anak',
        'tunjjabatan' => 'tunj_jabatan',
        'tunjoperasional' => 'tunj_ops',
        'tunjpajak' => 'tunj_pajak',
        'tunjkinerja' => 'tunj_kinerja',
        'tunjfungsional' => 'tunj_fungsional',
        'tunjmasakerja' => 'tunj_masa_kerja',
        'honorkontrak' => 'honor_kontrak',
        'tenagaharianlepas' => 'thl',
        'tunjuangmakan' => 'uang_makan_kontrak',
        'uangmakankontrak' => 'uang_makan_kontrak',
        'bpjskesehatan' => 'bpjs_kesehatan',
        'bpjsketenagakerjaan' => 'bpjs_tenaga_kerja',
        'bpjstenagakerja' => 'bpjs_tenaga_kerja',
        'jumlahgaji' => 'jumlah_gaji',
        'absensi' => 'absensi',
        'angsurankredit' => 'angsuran_kredit',
        'potongankeris' => 'keris',
        'keris' => 'keris',
        'jumlahpotongan' => 'jumlah_potongan',
        'gajibersih' => 'gaji_bersih',
        'total' => 'total',
        'tanggalgajian' => 'tgl_gajian',
        'tglgajian' => 'tgl_gajian',
    ];

    public function index(Request $request, RbacService $rbac): Response
    {
        abort_unless($rbac->can($request, 'payroll.view'), 403);
        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'office' => ['nullable', 'string', 'max:20'],
            'view' => ['nullable', 'in:rekap,rincian'],
            'stage' => ['nullable', 'in:draft,potongan,final'],
        ]);

        $scope = $this->officeScope($request);
        $latestImportQuery = PayrollImport::query()->where('is_current', true);
        if ($scope['restricted']) {
            $latestImportQuery->whereHas('officeSummaries', fn ($query) => $query->whereIn('office_code', $scope['codes']));
        }
        $latestImport = $latestImportQuery->orderByDesc('id')->first();
        $year = (int) ($validated['tahun'] ?? $latestImport?->tahun ?? now()->year);
        $month = (int) ($validated['bulan'] ?? $latestImport?->bulan ?? now()->month);
        $view = $validated['view'] ?? 'rekap';
        $stage = $validated['stage'] ?? 'final';
        $payrollImport = PayrollImport::query()
            ->where('is_current', true)
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->orderByDesc('id')
            ->first();

        $summaryQuery = $payrollImport
            ? $payrollImport->officeSummaries()->orderBy('office_code')
            : PayrollOfficeSummary::query()->whereRaw('1 = 0');
        if ($scope['restricted']) {
            $summaryQuery->whereIn('office_code', $scope['codes']);
        }

        $allSummaries = $summaryQuery->get();
        if ($scope['restricted'] && $allSummaries->isEmpty()) {
            $payrollImport = null;
        }
        $officeIndex = TbKantor::query()
            ->get(['kode_kantor_detail', 'kode_cabang', 'nama_kantor', 'level'])
            ->keyBy(fn (TbKantor $office): string => (string) $office->kode_kantor_detail);
        $officeOptions = $allSummaries->map(function (PayrollOfficeSummary $summary) use ($officeIndex): array {
            $office = $officeIndex->get((string) $summary->office_code);

            return [
                'value' => (string) $summary->office_code,
                'label' => trim((string) ($office?->nama_kantor ?: $summary->source_label ?: 'Kantor tidak terdaftar')),
                'code' => (string) $summary->office_code,
                'branch_code' => (string) ($office?->kode_cabang ?? ''),
            ];
        })->values()->all();

        $selectedOffice = (string) ($validated['office'] ?? '');
        if ($scope['restricted'] && $selectedOffice !== '' && ! in_array($selectedOffice, $scope['codes'], true)) {
            $selectedOffice = '';
        }
        $summaryRows = $allSummaries
            ->when($selectedOffice !== '', fn ($rows) => $rows->where('office_code', $selectedOffice))
            ->map(function (PayrollOfficeSummary $summary) use ($officeIndex): array {
                $office = $officeIndex->get((string) $summary->office_code);
                $item = [
                    'office_code' => (string) $summary->office_code,
                    'office_name' => trim((string) ($office?->nama_kantor ?: $summary->source_label ?: 'Kantor tidak terdaftar')),
                    'branch_code' => (string) ($office?->kode_cabang ?? ''),
                    'employee_count' => (int) $summary->employee_count,
                ];
                foreach (self::SUMMARY_AMOUNT_FIELDS as $field) {
                    $item[$field] = $summary->{$field};
                }

                return $item;
            })->values();

        $summaryTotals = ['employee_count' => 0];
        foreach (self::SUMMARY_AMOUNT_FIELDS as $field) {
            $summaryTotals[$field] = '0.00';
        }
        foreach ($summaryRows as $summary) {
            $summaryTotals['employee_count'] += $summary['employee_count'];
            foreach (self::SUMMARY_AMOUNT_FIELDS as $field) {
                $summaryTotals[$field] = $this->addAmounts((string) $summaryTotals[$field], (string) $summary[$field]);
            }
        }

        $entries = null;
        if ($view === 'rincian' && $payrollImport) {
            $entryQuery = DB::table('payroll_entries as e')
                ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'e.employee_id')
                ->where('e.payroll_import_id', $payrollImport->id)
                ->select([
                    'e.id', 'e.office_code', 'e.employee_id', 'e.source_row_number', 'e.bank_office_code',
                    'e.no_rekening', 'e.nama_nasabah', 'e.tgl_gajian', 'p.nama as employee_name',
                    ...array_map(fn (string $field): string => 'e.'.$field, self::ENTRY_AMOUNT_FIELDS),
                ])
                ->orderBy('p.nama')
                ->orderBy('e.employee_id');
            if ($scope['restricted']) {
                $entryQuery->whereIn('e.office_code', $scope['codes']);
            }
            if ($selectedOffice !== '') {
                $entryQuery->where('e.office_code', $selectedOffice);
            }
            $entries = $entryQuery->paginate(25)->withQueryString()->through(function (object $entry): array {
                $item = [
                    'id' => (int) $entry->id,
                    'office_code' => (string) $entry->office_code,
                    'employee_id' => (string) $entry->employee_id,
                    'employee_name' => (string) ($entry->employee_name ?: 'Nama pegawai tidak ditemukan'),
                    'source_row_number' => (int) $entry->source_row_number,
                    'bank_office_code' => (string) ($entry->bank_office_code ?? ''),
                    'no_rekening' => (string) ($entry->no_rekening ?? ''),
                    'nama_nasabah' => (string) ($entry->nama_nasabah ?? ''),
                    'tgl_gajian' => $entry->tgl_gajian ? (string) $entry->tgl_gajian : null,
                ];
                foreach (self::ENTRY_AMOUNT_FIELDS as $field) {
                    $item[$field] = (string) $entry->{$field};
                }

                return $item;
            });
        }

        $importedByName = $payrollImport?->imported_by
            ? DB::table('tb_user')->where('id_user', $payrollImport->imported_by)->value('nama_user')
            : null;
        $canClearHistory = $this->canClearDevelopmentHistory($request, $rbac);

        return Inertia::render('Payroll/Index', [
            'filters' => ['tahun' => $year, 'bulan' => $month, 'office' => $selectedOffice, 'view' => $view, 'stage' => $stage],
            'stage' => $stage,
            'years' => tap(PayrollImport::query()->where('is_current', true), function ($query) use ($scope): void {
                if ($scope['restricted']) {
                    $query->whereHas('officeSummaries', fn ($summaries) => $summaries->whereIn('office_code', $scope['codes']));
                }
            })->distinct()->orderByDesc('tahun')->pluck('tahun')->map(fn ($item): int => (int) $item)->values()->all(),
            'offices' => $officeOptions,
            'summaryRows' => $summaryRows->all(),
            'summaryTotals' => $summaryTotals,
            'entries' => $entries,
            'payrollImport' => $payrollImport ? [
                'id' => (int) $payrollImport->id,
                'source_filename' => $payrollImport->source_filename,
                'visible_employee_count' => (int) $summaryRows->sum('employee_count'),
                'imported_at' => $payrollImport->created_at?->format('d-m-Y H:i'),
                'imported_by_name' => $importedByName ?: $payrollImport->imported_by,
            ] : null,
            'canImport' => $this->canImportPayroll($request, $rbac),
            'canEditMaster' => $rbac->can($request, 'payroll.master.edit'),
            'canClearHistory' => $canClearHistory,
            'payrollHistoryCount' => $canClearHistory ? PayrollImport::query()->count() : 0,
            'scopeLabel' => $scope['label'],
            'scopeLocked' => $scope['restricted'] && count($scope['codes']) <= 1,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function importForm(Request $request, RbacService $rbac): Response
    {
        $this->authorizePayrollImport($request, $rbac);

        return Inertia::render('Payroll/Import', [
            'preview' => null,
            'filters' => ['tahun' => now()->year, 'bulan' => now()->month],
            'willRequireApproval' => $this->willRequireApproval($request, $rbac),
            'flash' => ['error' => $request->session()->get('error')],
        ]);
    }

    public function previewImportGet(Request $request, RbacService $rbac): RedirectResponse
    {
        if (! $this->canImportPayroll($request, $rbac)) {
            abort_unless($rbac->can($request, 'payroll.view'), 403);

            return to_route('payroll.index')->with(
                'error',
                'Halaman preview hanya tersedia setelah file XLSX dipilih. Hak akses Anda hanya mencakup melihat data payroll.',
            );
        }

        return to_route('payroll.import')->with(
            'error',
            'Preview dibuat setelah file XLSX diunggah. Pilih file, lalu tekan Periksa dan tampilkan preview.',
        );
    }

    public function downloadTemplate(Request $request, RbacService $rbac, PayrollWorkbookService $workbooks): BinaryFileResponse
    {
        $this->authorizePayrollImport($request, $rbac);
        $path = $workbooks->template();

        return response()->download($path, 'Template_Import_Payroll.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ])->deleteFileAfterSend(true);
    }

    public function export(Request $request, RbacService $rbac, PayrollWorkbookService $workbooks, PayrollEntryValidationService $entryValidator): BinaryFileResponse
    {
        abort_unless($rbac->can($request, 'payroll.view'), 403);
        $validated = $request->validate([
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'office' => ['nullable', 'string', 'max:20'],
        ]);

        $scope = $this->officeScope($request);
        $officeCode = trim((string) ($validated['office'] ?? ''));
        abort_if($scope['restricted'] && $officeCode !== '' && ! in_array($officeCode, $scope['codes'], true), 403);

        $payrollImport = PayrollImport::query()
            ->where('is_current', true)
            ->where('tahun', $validated['tahun'])
            ->where('bulan', $validated['bulan'])
            ->when($scope['restricted'], fn ($query) => $query->whereHas(
                'officeSummaries',
                fn ($summaries) => $summaries->whereIn('office_code', $scope['codes']),
            ))
            ->orderByDesc('id')
            ->firstOrFail();

        $entryQuery = $payrollImport->entries()
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'payroll_entries.employee_id')
            ->select([
                ...array_map(fn (string $field): string => 'payroll_entries.'.$field, ['office_code', 'bank_office_code', 'no_rekening', 'nama_nasabah', ...self::ENTRY_AMOUNT_FIELDS]),
                'p.status_kepeg as employment_status',
            ])
            ->orderBy('payroll_entries.office_code')
            ->orderBy('payroll_entries.employee_id');
        if ($scope['restricted']) {
            $entryQuery->whereIn('office_code', $scope['codes']);
        }
        if ($officeCode !== '') {
            $entryQuery->where('office_code', $officeCode);
        }

        $entries = $entryQuery->get()->map(function ($entry) use ($entryValidator): array {
            $amounts = $entryValidator->applyAttendanceDeductionToExportAllowance(
                $entry->toArray(),
                (string) ($entry->employment_status ?? ''),
            );

            return $entryValidator->applyGradeToBaseSalaryForExport($amounts);
        })->all();
        $path = $workbooks->export($entries);
        $filename = sprintf('Payroll_%04d_%02d%s.xlsx', $validated['tahun'], $validated['bulan'], $officeCode !== '' ? '_'.$officeCode : '');

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function previewImport(Request $request, RbacService $rbac, PayrollEntryValidationService $entryValidator): Response
    {
        $this->authorizePayrollImport($request, $rbac);
        $validated = $request->validate([
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'file_xlsx' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ]);

        $file = $request->file('file_xlsx');
        $parsed = $this->parseWorkbook($file->getRealPath(), $request, $entryValidator);
        $preview = [
            'tahun' => (int) $validated['tahun'],
            'bulan' => (int) $validated['bulan'],
            'source_filename' => basename($file->getClientOriginalName()),
            'row_count' => count($parsed['entries']),
            'office_count' => count($parsed['summaries']),
            'errors' => array_slice($parsed['errors'], 0, 20),
            'error_count' => count($parsed['errors']),
            'replaces_current' => PayrollImport::query()
                ->where('is_current', true)
                ->where('tahun', $validated['tahun'])
                ->where('bulan', $validated['bulan'])
                ->exists(),
        ];

        if ($preview['row_count'] === 0 && $preview['errors'] === []) {
            $preview['errors'][] = ['row' => null, 'messages' => ['Sheet payroll belum memiliki baris data.']];
            $preview['error_count'] = 1;
        }

        if ($preview['error_count'] === 0) {
            $this->cleanExpiredPreviews($request);
            $token = (string) Str::uuid();
            $storedPath = $file->storeAs('payroll-previews', $token.'.xlsx', 'local');
            $previews = $request->session()->get('payroll_previews', []);
            $previews[$token] = [
                'path' => $storedPath,
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'filename' => basename($file->getClientOriginalName()),
                'year' => (int) $validated['tahun'],
                'month' => (int) $validated['bulan'],
                'user_id' => (string) $request->session()->get('id_user'),
                'expires_at' => now()->addMinutes(20)->timestamp,
            ];
            while (count($previews) > 3) {
                $oldToken = array_key_first($previews);
                Storage::disk('local')->delete($previews[$oldToken]['path'] ?? '');
                unset($previews[$oldToken]);
            }
            $request->session()->put('payroll_previews', $previews);
            $preview['token'] = $token;
        }

        return Inertia::render('Payroll/Import', [
            'preview' => $preview,
            'filters' => ['tahun' => (int) $validated['tahun'], 'bulan' => (int) $validated['bulan']],
            'willRequireApproval' => $this->willRequireApproval($request, $rbac),
            'flash' => ['error' => $request->session()->get('error')],
        ]);
    }

    public function import(Request $request, RbacService $rbac, NotificationService $notifications, PayrollEntryValidationService $entryValidator): RedirectResponse
    {
        $this->authorizePayrollImport($request, $rbac);
        $validated = $request->validate(['preview_token' => ['required', 'uuid']]);
        $previews = $request->session()->get('payroll_previews', []);
        $preview = $previews[$validated['preview_token']] ?? null;
        abort_unless(
            is_array($preview)
            && (string) ($preview['user_id'] ?? '') === (string) $request->session()->get('id_user')
            && (int) ($preview['expires_at'] ?? 0) >= now()->timestamp,
            422,
            'Preview import tidak berlaku. Unggah kembali file payroll untuk melanjutkan.',
        );

        $path = (string) ($preview['path'] ?? '');
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 422, 'File preview tidak ditemukan. Silakan unggah ulang.');
        $fullPath = Storage::disk('local')->path($path);
        abort_unless(hash_file('sha256', $fullPath) === ($preview['sha256'] ?? ''), 422, 'File preview berubah. Silakan unggah ulang file payroll.');

        $parsed = $this->parseWorkbook(
            $fullPath,
            $request,
            $entryValidator,
            (int) $preview['year'],
            (int) $preview['month'],
        );
        if ($parsed['errors'] !== [] || $parsed['entries'] === []) {
            throw ValidationException::withMessages([
                'preview_token' => 'Validasi file berubah sejak preview. Unggah ulang dan periksa baris yang bermasalah.',
            ]);
        }

        $userId = (string) $request->session()->get('id_user');
        $requiresApproval = $this->willRequireApproval($request, $rbac);

        if ($requiresApproval) {
            $changeRequest = DB::transaction(function () use ($parsed, $preview, $userId): PayrollChangeRequest {
                $officeCodes = array_values(array_map('strval', array_keys($parsed['summaries'])));
                $changeRequest = PayrollChangeRequest::query()->create([
                    'change_type' => 'import',
                    'tahun' => $preview['year'],
                    'bulan' => $preview['month'],
                    'source_filename' => $preview['filename'],
                    'source_sha256' => $preview['sha256'],
                    'requested_by' => $userId,
                    'office_codes' => $officeCodes,
                    'summary_data' => ['employee_count' => count($parsed['entries']), 'office_count' => count($officeCodes)],
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]);

                $employeeIds = array_values(array_unique(array_map(fn (array $entry): string => (string) $entry['employee_id'], $parsed['entries'])));
                $masters = PayrollSalaryMaster::query()->whereIn('employee_id', $employeeIds)->get()->keyBy('employee_id');
                $now = now();
                $entries = array_map(function (array $entry) use ($changeRequest, $masters, $now): array {
                    $master = $masters->get((string) $entry['employee_id']);
                    $oldData = $master
                        ? array_intersect_key($master->getAttributes(), array_flip(self::ENTRY_AMOUNT_FIELDS))
                        : null;
                    $newData = $entry;
                    unset($newData['employee_id'], $newData['office_code'], $newData['source_row_number']);

                    return [
                        'payroll_change_request_id' => $changeRequest->id,
                        'employee_id' => (string) $entry['employee_id'],
                        'office_code' => (string) $entry['office_code'],
                        'source_row_number' => (int) $entry['source_row_number'],
                        'old_data' => $oldData === null ? null : json_encode($oldData, JSON_THROW_ON_ERROR),
                        'new_data' => json_encode($newData, JSON_THROW_ON_ERROR),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $parsed['entries']);

                foreach (array_chunk($entries, 500) as $chunk) {
                    DB::table('payroll_change_request_entries')->insert($chunk);
                }

                return $changeRequest;
            });

            $notifications->notifyPayrollRequestCreated($changeRequest);
            Storage::disk('local')->delete($path);
            unset($previews[$validated['preview_token']]);
            $request->session()->put('payroll_previews', $previews);

            return to_route('payroll.index', ['tahun' => $preview['year'], 'bulan' => $preview['month']])
                ->with('success', 'Import payroll diajukan untuk approval supervisor kantor terkait. Data belum diterapkan sampai disetujui.');
        }

        $now = now();
        DB::transaction(function () use ($parsed, $preview, $now, $userId): void {
            PayrollImport::query()
                ->where('tahun', $preview['year'])
                ->where('bulan', $preview['month'])
                ->where('is_current', true)
                ->lockForUpdate()
                ->update(['is_current' => false, 'updated_at' => $now]);

            $payrollImport = PayrollImport::query()->create([
                'tahun' => $preview['year'],
                'bulan' => $preview['month'],
                'source_filename' => $preview['filename'],
                'source_sha256' => $preview['sha256'],
                'source_row_count' => count($parsed['entries']),
                'source_summary_count' => count($parsed['summaries']),
                'is_current' => true,
                'imported_by' => $userId,
            ]);

            foreach (array_chunk($parsed['entries'], 500) as $chunk) {
                $records = array_map(fn (array $entry): array => [
                    ...$entry,
                    'payroll_import_id' => $payrollImport->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);
                DB::table('payroll_entries')->insert($records);
            }

            foreach (array_chunk(array_values($parsed['summaries']), 500) as $chunk) {
                $records = array_map(fn (array $summary): array => [
                    ...$summary,
                    'payroll_import_id' => $payrollImport->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk);
                DB::table('payroll_office_summaries')->insert($records);
            }

            $masterRecords = array_map(function (array $entry) use ($payrollImport, $userId, $now): array {
                $record = [
                    ...$entry,
                    'source_import_id' => $payrollImport->id,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                unset($record['source_row_number']);

                return $record;
            }, $parsed['entries']);
            foreach (array_chunk($masterRecords, 500) as $chunk) {
                DB::table('payroll_salary_masters')->upsert(
                    $chunk,
                    ['employee_id'],
                    ['office_code', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian', 'rekening_tabungan', 'no_hp_keris', ...self::ENTRY_AMOUNT_FIELDS, 'source_import_id', 'updated_by', 'updated_at'],
                );
            }
        });

        Storage::disk('local')->delete($path);
        unset($previews[$validated['preview_token']]);
        $request->session()->put('payroll_previews', $previews);

        return to_route('payroll.index', ['tahun' => $preview['year'], 'bulan' => $preview['month']])
            ->with('success', 'Data payroll berhasil diimpor. Batch sebelumnya untuk periode ini tetap tersimpan sebagai riwayat.');
    }

    public function clearDevelopmentHistory(Request $request, RbacService $rbac): RedirectResponse
    {
        abort_unless($this->isDevelopmentEnvironment() && $this->isDevelopmentDatabase(), 404);
        $this->authorizePayrollImport($request, $rbac);
        $request->validate(['confirmation' => ['required', 'in:HAPUS']]);

        $batchCount = DB::transaction(function (): int {
            $count = PayrollImport::query()->count();
            PayrollImport::query()->delete();

            return $count;
        });

        $previews = $request->session()->pull('payroll_previews', []);
        $userId = (string) $request->session()->get('id_user');
        foreach ($previews as $preview) {
            if (is_array($preview) && (string) ($preview['user_id'] ?? '') === $userId) {
                $path = (string) ($preview['path'] ?? '');
                if (str_starts_with($path, 'payroll-previews/')) {
                    Storage::disk('local')->delete($path);
                }
            }
        }

        return to_route('payroll.index')->with(
            'success',
            "Riwayat payroll pengujian dibersihkan: {$batchCount} batch. Data pegawai dan master data tidak diubah.",
        );
    }

    private function parseWorkbook(
        string $path,
        Request $request,
        PayrollEntryValidationService $entryValidator,
        ?int $year = null,
        ?int $month = null,
    ): array {
        $xlsx = SimpleXLSX::parse($path);
        if ($xlsx === false) {
            throw ValidationException::withMessages(['file_xlsx' => 'File XLSX tidak dapat dibaca. '.SimpleXLSX::parseError()]);
        }

        $requiredFields = $this->requiredPayrollFields();
        $rows = [];
        $header = null;
        $hasPartialPayrollHeader = false;

        foreach ($xlsx->sheetNames() as $sheetIndex => $sheetName) {
            $sheetRows = $xlsx->rows($sheetIndex);
            $candidateHeader = $this->findPayrollHeader($sheetRows);
            if ($candidateHeader === null) {
                continue;
            }

            $missingFields = array_diff($requiredFields, array_keys($candidateHeader['fields']));
            if ($missingFields !== []) {
                $hasPartialPayrollHeader = true;

                continue;
            }

            $rows = $sheetRows;
            $header = $candidateHeader;
            break;
        }

        if ($header === null && $hasPartialPayrollHeader) {
            throw ValidationException::withMessages([
                'file_xlsx' => 'Struktur kolom sheet payroll belum lengkap. Unduh template terbaru dan pastikan kolom payroll inti tersedia. Kode kantor, jabatan, unit/divisi, dan status pegawai boleh dikosongkan.',
            ]);
        }
        if ($header === null) {
            throw ValidationException::withMessages([
                'file_xlsx' => 'Header payroll tidak ditemukan. Pastikan salah satu sheet memiliki kolom sesuai template payroll.',
            ]);
        }

        $year ??= (int) $request->input('tahun');
        $month ??= (int) $request->input('bulan');
        $normalizedRows = [];
        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex <= $header['row_index'] || $this->payrollRowIsBlank($row, $header['fields'])) {
                continue;
            }

            $normalized = ['row_number' => $rowIndex + 1];
            foreach ($header['fields'] as $field => $columnIndex) {
                $normalized[$field] = $row[$columnIndex] ?? '';
            }
            $normalizedRows[] = $normalized;
        }

        $validation = $entryValidator->validateRows(
            $normalizedRows,
            $this->officeScope($request),
            $year,
            $month,
        );
        $summaries = [];
        foreach ($validation['entries'] as $entry) {
            $officeCode = (string) $entry['office_code'];
            $officeName = (string) ($validation['directory']['by_detail']->get($officeCode)?->nama_kantor ?? '');
            $summaries[$officeCode] ??= $this->emptyOfficeSummary($officeCode, $officeName);
            $summaries[$officeCode]['employee_count']++;
            foreach (self::SUMMARY_AMOUNT_FIELDS as $field) {
                $summaries[$officeCode][$field] = $this->addAmounts(
                    (string) $summaries[$officeCode][$field],
                    (string) $entry[$field],
                );
            }
        }

        $errors = $validation['errors'];
        if ($normalizedRows === [] && $errors === []) {
            $errors[] = ['row' => null, 'messages' => ['Sheet payroll belum memiliki baris data.']];
        }

        return [
            'entries' => $validation['entries'],
            'summaries' => $summaries,
            'errors' => $errors,
        ];
    }

    private function requiredPayrollFields(): array
    {
        return [
            'employee_id', 'bank_office_code', 'no_rekening', 'nama_nasabah',
            ...PayrollEntryValidationService::EARNING_FIELDS,
            ...PayrollEntryValidationService::DEDUCTION_FIELDS,
            'jumlah_gaji', 'jumlah_potongan', 'gaji_bersih',
            'rekening_tabungan', 'no_hp_keris', 'tgl_gajian',
        ];
    }

    private function findPayrollHeader(array $rows): ?array
    {
        foreach (array_slice($rows, 0, 12, true) as $rowIndex => $row) {
            $fields = [];
            foreach ($row as $columnIndex => $value) {
                $field = self::PAYROLL_HEADERS[$this->normaliseHeader((string) $value)] ?? null;
                if ($field !== null) {
                    $fields[$field] = $columnIndex;
                }
            }
            if (isset($fields['employee_id'], $fields['no_rekening'])) {
                return ['row_index' => $rowIndex, 'fields' => $fields];
            }
        }

        return null;
    }

    private function payrollRowIsBlank(array $row, array $fieldColumns): bool
    {
        foreach ($fieldColumns as $column) {
            $text = trim((string) ($row[$column] ?? ''));
            if ($text !== '' && ! str_starts_with($text, '=')) {
                return false;
            }
        }

        return true;
    }

    private function emptyOfficeSummary(string $officeCode, ?string $officeName = null): array
    {
        $summary = [
            'office_code' => $officeCode,
            'source_label' => $officeName,
            'employee_count' => 0,
        ];
        foreach (self::SUMMARY_AMOUNT_FIELDS as $field) {
            $summary[$field] = '0.00';
        }

        return $summary;
    }

    private function payrollOfficeMatchesEmployee(string $payrollOfficeCode, string $employeeOfficeCode, array $directory): bool
    {
        if ($payrollOfficeCode === $employeeOfficeCode) {
            return true;
        }

        $payrollOffice = $directory['by_detail']->get($payrollOfficeCode);
        $employeeOffice = $directory['by_detail']->get($employeeOfficeCode);

        return $payrollOffice !== null
            && strtoupper((string) $payrollOffice->level) === 'KC'
            && $payrollOffice->kode_cabang !== null
            && (string) $payrollOffice->kode_cabang === (string) $employeeOffice?->kode_cabang;
    }

    private function payrollNamesMatch(string $employeeName, string $accountName): bool
    {
        $employee = $this->normalisePayrollName($employeeName);
        $account = $this->normalisePayrollName($accountName);

        return $employee !== '' && $account !== '' && $employee === $account;
    }

    private function normalisePayrollName(string $name): string
    {
        $name = mb_strtoupper($name, 'UTF-8');
        $name = preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $name) ?? '';
        $name = trim(preg_replace('/\\s+/u', ' ', $name) ?? '');
        $degreeSuffixes = [
            'S E SY', 'S KOM', 'S SOS', 'S PD', 'S SI', 'S AK', 'S PSI', 'S FARM', 'S KED', 'S GZ',
            'M KOM', 'M SOS', 'M PD', 'M SI', 'M AK', 'M HUM', 'M KES', 'M FARM', 'M T', 'M E', 'M M',
            'S E', 'S H', 'S T', 'DRS', 'DRA', 'DR',
        ];

        do {
            $previous = $name;
            foreach ($degreeSuffixes as $suffix) {
                $name = preg_replace('/\\s+'.preg_quote($suffix, '/').'$/u', '', $name) ?? $name;
            }
            $name = trim($name);
        } while ($name !== $previous);

        return $name;
    }

    private function parsePayrollDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || str_starts_with($text, '=')) {
            return null;
        }

        if (is_numeric($text) && (float) $text > 0 && (float) $text < 100000) {
            try {
                return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) floor((float) $text).' days')->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        foreach (['!d-m-Y', '!d/m/Y', '!Y-m-d', '!d.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $text);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new DateTimeImmutable($text))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function officeDirectory(): array
    {
        $offices = TbKantor::query()->get(['kode_kantor_detail', 'kode_cabang', 'nama_kantor', 'level']);
        $byDetail = $offices->keyBy(fn (TbKantor $office): string => (string) $office->kode_kantor_detail);
        $branchOffice = $offices
            ->filter(fn (TbKantor $office): bool => strtoupper((string) $office->level) === 'KC')
            ->keyBy(fn (TbKantor $office): string => (string) $office->kode_cabang);

        return ['offices' => $offices, 'by_detail' => $byDetail, 'branch_office' => $branchOffice];
    }

    private function normaliseOfficeCode(mixed $rawCode, array $directory): ?string
    {
        $raw = trim((string) $rawCode);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw) && ! preg_match('/^\d+$/', $raw)) {
            $raw = sprintf('%.0f', (float) $raw);
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        $byDetail = $directory['by_detail'];
        if ($byDetail->has($digits)) {
            return $digits;
        }
        $branchCode = strlen($digits) <= 3 ? str_pad($digits, 3, '0', STR_PAD_LEFT) : $digits;
        if (strlen($digits) <= 3 && $directory['branch_office']->has($branchCode)) {
            return (string) $directory['branch_office']->get($branchCode)->kode_kantor_detail;
        }
        if (strlen($digits) < 6) {
            $padded = str_pad($digits, 6, '0', STR_PAD_LEFT);
            if ($byDetail->has($padded)) {
                return $padded;
            }
        }
        if ($directory['branch_office']->has($branchCode)) {
            return (string) $directory['branch_office']->get($branchCode)->kode_kantor_detail;
        }

        return null;
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

        return [
            'restricted' => true,
            'codes' => $codes,
            'label' => 'Wilayah: '.trim((string) $office->nama_kantor),
        ];
    }

    private function authorizePayrollImport(Request $request, RbacService $rbac): void
    {
        abort_unless($this->canImportPayroll($request, $rbac),
            403,
            'Akun Anda belum diberi izin untuk mengimpor payroll.',
        );
    }

    private function canImportPayroll(Request $request, RbacService $rbac): bool
    {
        return $rbac->can($request, 'payroll.import');
    }

    private function willRequireApproval(Request $request, RbacService $rbac): bool
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));

        return $role !== 'admin' && ! $rbac->can($request, 'payroll.approval.bypass');
    }

    private function canClearDevelopmentHistory(Request $request, RbacService $rbac): bool
    {
        return $this->isDevelopmentEnvironment()
            && $this->isDevelopmentDatabase()
            && $this->canImportPayroll($request, $rbac);
    }

    private function isDevelopmentEnvironment(): bool
    {
        return app()->environment('local', 'testing');
    }

    private function isDevelopmentDatabase(): bool
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if (app()->environment('testing') && $driver === 'sqlite' && $database === ':memory:') {
            return true;
        }

        return preg_match('/(?:^|[_-])(?:dummy|dev|development|local|testing)$/i', $database) === 1;
    }

    private function cleanExpiredPreviews(Request $request): void
    {
        $previews = $request->session()->get('payroll_previews', []);
        foreach ($previews as $token => $preview) {
            if ((int) ($preview['expires_at'] ?? 0) < now()->timestamp) {
                Storage::disk('local')->delete($preview['path'] ?? '');
                unset($previews[$token]);
            }
        }
        $request->session()->put('payroll_previews', $previews);
    }

    private function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normaliseHeader(string $header): string
    {
        $header = mb_strtolower(str_replace(["\n", "\r", '\\'], ' ', trim($header)));

        return preg_replace('/[^a-z0-9]+/u', '', $header) ?? '';
    }

    private function parseAmount(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return '0.00';
        }
        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        $amount = trim((string) $value);
        if (str_starts_with($amount, '=')) {
            return null;
        }
        $negative = str_contains($amount, '(') && str_contains($amount, ')');
        $amount = preg_replace('/[^0-9,\.\-]/u', '', $amount) ?? '';
        if ($amount === '' || $amount === '-') {
            return null;
        }
        if (str_contains($amount, ',') && str_contains($amount, '.')) {
            $decimalSeparator = strrpos($amount, ',') > strrpos($amount, '.') ? ',' : '.';
            $thousandsSeparator = $decimalSeparator === ',' ? '.' : ',';
            $amount = str_replace($thousandsSeparator, '', $amount);
            $amount = str_replace($decimalSeparator, '.', $amount);
        } elseif (substr_count($amount, ',') > 1 || (str_contains($amount, ',') && preg_match('/,\d{3}$/', $amount))) {
            $amount = str_replace(',', '', $amount);
        } elseif (str_contains($amount, ',')) {
            $amount = str_replace(',', '.', $amount);
        } elseif (substr_count($amount, '.') > 1 || (str_contains($amount, '.') && preg_match('/\.\d{3}$/', $amount))) {
            $amount = str_replace('.', '', $amount);
        }
        if (! is_numeric($amount) || abs((float) $amount) > 9999999999999999) {
            return null;
        }
        $amount = number_format((float) $amount, 2, '.', '');

        return $negative && ! str_starts_with($amount, '-') ? '-'.$amount : $amount;
    }

    private function addAmounts(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, 2);
        }

        return number_format((float) $left + (float) $right, 2, '.', '');
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }
}
