<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRefJabatan;
use App\Services\RbacService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

class JabatanController extends Controller
{
    private const IMPORT_HEADERS = [
        'ID Pegawai (Wajib)',
        'Kode Jabatan (Opsional)',
        'Nama Jabatan (Wajib)',
        'Kode Unit Kerja',
        'No SK',
        'Tgl SK (DD-MM-YYYY)',
        'Status (Aktif/Non)',
    ];

    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $search = $request->string('search')->trim()->value();
        $filterUnit = $request->string('unit')->trim()->value();
        $filterJabatan = $request->string('jabatan')->trim()->value();

        $query = TbJabatan::query()
            ->from('tb_jabatan as j')
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'j.id_peg')
            ->leftJoin('tb_kantor as k', 'k.kode_kantor_detail', '=', 'j.unit_kerja')
            ->where('j.status_jab', 'Aktif')
            ->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))
            ->when($filterUnit !== '', fn (Builder $builder) => $builder->where(function (Builder $scope) use ($filterUnit): void {
                $scope->where('j.unit_kerja', $filterUnit)
                    ->orWhere('k.nama_kantor', $filterUnit);
            }))
            ->when($filterJabatan !== '', fn (Builder $builder) => $builder->where(function (Builder $scope) use ($filterJabatan): void {
                $scope->where('j.jabatan', $filterJabatan)
                    ->orWhere('j.kode_jabatan', $filterJabatan);
            }))
            ->when($search !== '', fn (Builder $builder) => $builder->where(function (Builder $scope) use ($search): void {
                $like = "%{$search}%";
                $scope->where('p.nama', 'like', $like)
                    ->orWhere('j.id_peg', 'like', $like)
                    ->orWhere('j.jabatan', 'like', $like)
                    ->orWhere('j.kode_jabatan', 'like', $like)
                    ->orWhere('k.nama_kantor', 'like', $like)
                    ->orWhere('j.unit_kerja', 'like', $like)
                    ->orWhere('j.no_sk', 'like', $like);
            }));

        $items = $query
            ->select([
                'j.id_jab',
                'j.id_peg',
                'p.nama',
                'j.kode_jabatan',
                'j.jabatan',
                'j.unit_kerja',
                'k.nama_kantor',
                'j.tmt_jabatan',
                'j.sampai_tgl',
                'j.no_sk',
                'j.tgl_sk',
                'j.status_jab',
            ])
            ->orderBy('j.tmt_jabatan')
            ->orderBy('p.nama')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn ($item): array => [
                'id_jab' => $item->id_jab,
                'id_peg' => $item->id_peg,
                'nama' => $item->nama,
                'kode_jabatan' => $item->kode_jabatan,
                'jabatan' => $item->jabatan,
                'unit_kerja' => $item->nama_kantor ?: $item->unit_kerja,
                'tmt_jabatan' => $this->formatDate($item->tmt_jabatan),
                'sampai_tgl' => $item->status_jab === 'Aktif' ? 'Sekarang' : $this->formatDate($item->sampai_tgl),
                'no_sk' => $item->no_sk,
                'tgl_sk' => $this->formatDate($item->tgl_sk),
                'status' => $item->status_jab,
            ]);

        $units = TbJabatan::query()
            ->from('tb_jabatan as j')
            ->leftJoin('tb_kantor as k', 'k.kode_kantor_detail', '=', 'j.unit_kerja')
            ->where('j.status_jab', 'Aktif')
            ->when($unit !== '', fn (Builder $builder) => $builder->where('j.unit_kerja', $unit))
            ->whereNotNull('j.unit_kerja')
            ->selectRaw('j.unit_kerja as kode, COALESCE(k.nama_kantor, j.unit_kerja) as nama')
            ->distinct()
            ->orderBy('nama')
            ->get()
            ->map(fn ($item): array => ['kode' => $item->kode, 'nama' => $item->nama])
            ->values();

        $positions = TbJabatan::query()
            ->where('status_jab', 'Aktif')
            ->when($unit !== '', fn (Builder $builder) => $builder->where('unit_kerja', $unit))
            ->whereNotNull('jabatan')
            ->where('jabatan', '<>', '')
            ->select(['kode_jabatan', 'jabatan'])
            ->distinct()
            ->orderBy('jabatan')
            ->get();

        return Inertia::render('Jabatan/Index', [
            'items' => $items,
            'units' => $units,
            'positions' => $positions,
            'filters' => [
                'search' => $search,
                'unit' => $filterUnit,
                'jabatan' => $filterJabatan,
            ],
            'role' => $role,
            'canManage' => $this->canManage($request),
            'canImport' => app(RbacService::class)->can($request, 'positions.import'),
        ]);
    }

    public function importForm(Request $request): Response
    {
        $this->ensureCanImport($request);

        return Inertia::render('Jabatan/Import');
    }

    public function downloadImportTemplate(Request $request): BinaryFileResponse
    {
        $this->ensureCanImport($request);
        $path = public_path('templates/Template_Import_Jabatan_Smart.xlsx');

        abort_unless(is_file($path), 404, 'Template import jabatan tidak ditemukan.');

        return response()->download($path, 'Template_Import_Jabatan_Smart.xlsx');
    }

    public function previewImport(Request $request): JsonResponse
    {
        $this->ensureCanImport($request);
        $request->validate([
            'file_xlsx' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ]);

        $file = $request->file('file_xlsx');
        $parsed = $this->parseJabatanImport($file->getRealPath());
        if ($parsed['total'] === 0 && $parsed['invalid_count'] === 0) {
            $parsed['file_errors'][] = 'File belum memiliki baris data jabatan.';
        }

        $result = [
            'source_filename' => basename($file->getClientOriginalName()),
            'total' => $parsed['total'],
            'valid_count' => $parsed['total'] - $parsed['invalid_count'],
            'invalid_count' => $parsed['invalid_count'],
            'invalid_rows' => array_slice($parsed['invalid_rows'], 0, 20),
            'preview' => array_slice($parsed['rows'], 0, 20),
            'file_errors' => $parsed['file_errors'],
        ];

        if ($result['invalid_count'] === 0 && $result['file_errors'] === []) {
            $this->cleanJabatanImportPreviews($request);
            $token = (string) Str::uuid();
            $storedPath = $file->storeAs('jabatan-import-previews', $token.'.xlsx', 'local');
            $previews = $request->session()->get('jabatan_import_previews', []);
            $previews[$token] = [
                'path' => $storedPath,
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'filename' => basename($file->getClientOriginalName()),
                'user_id' => (string) $request->session()->get('id_user'),
                'expires_at' => now()->addMinutes(20)->timestamp,
            ];

            while (count($previews) > 3) {
                $oldToken = array_key_first($previews);
                Storage::disk('local')->delete($previews[$oldToken]['path'] ?? '');
                unset($previews[$oldToken]);
            }

            $request->session()->put('jabatan_import_previews', $previews);
            $result['token'] = $token;
        }

        return response()->json($result);
    }

    public function importXlsx(Request $request): RedirectResponse
    {
        $this->ensureCanImport($request);
        $validated = $request->validate(['preview_token' => ['required', 'uuid']]);
        $previews = $request->session()->get('jabatan_import_previews', []);
        $preview = $previews[$validated['preview_token']] ?? null;

        abort_unless(
            is_array($preview)
            && (string) ($preview['user_id'] ?? '') === (string) $request->session()->get('id_user')
            && (int) ($preview['expires_at'] ?? 0) >= now()->timestamp,
            422,
            'Preview import tidak berlaku. Unggah kembali file jabatan untuk melanjutkan.',
        );

        $path = (string) ($preview['path'] ?? '');
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 422, 'File preview tidak ditemukan. Silakan unggah ulang.');
        $fullPath = Storage::disk('local')->path($path);
        abort_unless(hash_file('sha256', $fullPath) === ($preview['sha256'] ?? ''), 422, 'File preview berubah. Silakan unggah ulang file jabatan.');

        $parsed = $this->parseJabatanImport($fullPath);
        if ($parsed['invalid_count'] > 0 || $parsed['total'] === 0 || $parsed['file_errors'] !== []) {
            throw ValidationException::withMessages([
                'preview_token' => 'Data jabatan berubah atau tidak lagi valid sejak preview. Unggah ulang dan periksa file.',
            ]);
        }

        $rows = $parsed['rows'];
        $operator = $this->operator($request);
        $importedAt = now();

        DB::transaction(function () use ($rows, $operator, $importedAt): void {
            $employeeIds = array_values(array_unique(array_map(fn (array $row): string => $row['id_peg'], $rows)));
            $employees = TbPegawai::query()
                ->whereIn('id_peg', $employeeIds)
                ->lockForUpdate()
                ->get(['id_peg', 'status_aktif'])
                ->keyBy('id_peg');

            foreach ($rows as $row) {
                if (! $employees->has($row['id_peg']) || (int) $employees->get($row['id_peg'])->status_aktif !== 1) {
                    throw ValidationException::withMessages([
                        'preview_token' => "Pegawai {$row['id_peg']} tidak lagi aktif. Unggah ulang file untuk memeriksa data.",
                    ]);
                }
            }

            foreach ($rows as $row) {
                if ($row['status'] === 'Aktif') {
                    TbJabatan::query()
                        ->where('id_peg', $row['id_peg'])
                        ->where('status_jab', 'Aktif')
                        ->update([
                            'status_jab' => 'Non',
                            'sampai_tgl' => $row['tgl_sk'],
                            'updated_at' => $importedAt,
                            'updated_by' => $operator,
                        ]);
                }

                TbJabatan::query()->create([
                    'id_peg' => $row['id_peg'],
                    'kode_jabatan' => $row['kode_jabatan'],
                    'jabatan' => $row['nama_jabatan'],
                    'unit_kerja' => $row['unit_kerja'],
                    'tmt_jabatan' => $row['tgl_sk'],
                    'sampai_tgl' => null,
                    'status_jab' => $row['status'],
                    'no_sk' => $row['no_sk'],
                    'tgl_sk' => $row['tgl_sk'],
                    'date_reg' => $importedAt->toDateString(),
                    'created_by' => $operator,
                ]);
            }
        });

        Storage::disk('local')->delete($path);
        unset($previews[$validated['preview_token']]);
        $request->session()->put('jabatan_import_previews', $previews);

        return to_route('jabatan.index')->with('success', count($rows).' data jabatan berhasil diimpor.');
    }

    private function parseJabatanImport(string $path): array
    {
        $xlsx = SimpleXLSX::parse($path);
        if ($xlsx === false || $xlsx->sheetNames() === []) {
            throw ValidationException::withMessages([
                'file_xlsx' => 'File XLSX tidak dapat dibaca. '.SimpleXLSX::parseError(),
            ]);
        }

        $rawRows = $xlsx->rows(0);
        $header = array_map(fn ($value): string => trim((string) $value), array_shift($rawRows) ?: []);
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        while ($header !== [] && end($header) === '') {
            array_pop($header);
        }

        $expected = array_map(fn (string $value): string => $this->normaliseImportHeader($value), self::IMPORT_HEADERS);
        $actual = array_map(fn (string $value): string => $this->normaliseImportHeader($value), $header);
        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'file_xlsx' => 'Kolom atau urutan header tidak sesuai. Unduh template import jabatan resmi dan jangan mengubah header.',
            ]);
        }

        $employees = TbPegawai::query()->where('status_aktif', 1)->get(['id_peg', 'nama'])->keyBy('id_peg');
        $positions = TbRefJabatan::query()->get(['kode_jabatan', 'jabatan']);
        $positionsByCode = [];
        $positionsByName = [];
        foreach ($positions as $position) {
            $positionsByCode[$this->normaliseCode((string) $position->kode_jabatan)] = $position;
            $positionsByName[$this->normaliseName((string) $position->jabatan)][] = $position;
        }

        $units = TbKantor::query()->get(['kode_kantor_detail', 'nama_kantor']);
        $unitsByCode = [];
        foreach ($units as $unit) {
            $unitsByCode[$this->normaliseCode((string) $unit->kode_kantor_detail)] = $unit;
        }

        $rows = [];
        $invalidRows = [];
        $seenEmployees = [];

        foreach ($rawRows as $index => $rawRow) {
            $values = array_map(fn ($value): string => trim((string) ($value ?? '')), array_slice(array_pad($rawRow, count(self::IMPORT_HEADERS), ''), 0, count(self::IMPORT_HEADERS)));
            if (count(array_filter($values, fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            [$employeeId, $positionCode, $positionName, $unitCode, $skNumber, $skDateRaw, $statusRaw] = $values;
            $errors = [];
            $employee = $employees->get($employeeId);
            if ($employeeId === '') {
                $errors[] = 'ID pegawai wajib diisi.';
            } elseif (! $employee) {
                $errors[] = 'ID pegawai tidak ditemukan atau statusnya tidak aktif.';
            }
            $employeeKey = $this->normaliseCode($employeeId);
            if ($employeeId !== '' && isset($seenEmployees[$employeeKey])) {
                $errors[] = 'ID pegawai muncul lebih dari sekali dalam file.';
            }
            if ($employeeId !== '') {
                $seenEmployees[$employeeKey] = true;
            }

            $position = null;
            if ($positionCode !== '') {
                $position = $positionsByCode[$this->normaliseCode($positionCode)] ?? null;
                if (! $position) {
                    $errors[] = 'Kode jabatan tidak ditemukan di master jabatan.';
                }
            }
            if ($positionName !== '') {
                $matchingPositions = $positionsByName[$this->normaliseName($positionName)] ?? [];
                if (count($matchingPositions) === 0) {
                    $errors[] = 'Nama jabatan tidak ditemukan di master jabatan.';
                } elseif (count($matchingPositions) > 1) {
                    $errors[] = 'Nama jabatan memiliki lebih dari satu kode. Isi Kode Jabatan agar tidak ambigu.';
                } elseif ($position && (string) $position->kode_jabatan !== (string) $matchingPositions[0]->kode_jabatan) {
                    $errors[] = 'Kode jabatan dan nama jabatan tidak merujuk ke data master yang sama.';
                } else {
                    $position ??= $matchingPositions[0];
                }
            }
            if ($positionCode === '' && $positionName === '') {
                $errors[] = 'Isi Kode Jabatan atau Nama Jabatan.';
            }

            $unit = $unitCode === '' ? null : ($unitsByCode[$this->normaliseCode($unitCode)] ?? null);
            if ($unitCode === '') {
                $errors[] = 'Kode unit kerja wajib diisi.';
            } elseif (! $unit) {
                $errors[] = 'Kode unit kerja tidak ditemukan.';
            }
            if ($skNumber === '') {
                $errors[] = 'Nomor SK wajib diisi.';
            }

            $skDate = $this->parseJabatanDate($skDateRaw);
            if ($skDate === null) {
                $errors[] = 'Tanggal SK tidak valid. Gunakan format DD-MM-YYYY.';
            }

            $status = $this->normaliseJobStatus($statusRaw);
            if ($status === null) {
                $errors[] = 'Status harus Aktif atau Non.';
            }

            $row = [
                'row_number' => $index + 2,
                'id_peg' => $employeeId,
                'nama_pegawai' => $employee?->nama,
                'kode_jabatan' => $position ? (string) $position->kode_jabatan : $positionCode,
                'nama_jabatan' => $position?->jabatan ?: $positionName,
                'unit_kerja' => $unit ? (string) $unit->kode_kantor_detail : $unitCode,
                'nama_unit' => $unit?->nama_kantor,
                'no_sk' => $skNumber,
                'tgl_sk' => $skDate,
                'status' => $status ?? $statusRaw,
                'errors' => $errors,
            ];
            $rows[] = $row;

            if ($errors !== []) {
                $invalidRows[] = ['row_number' => $row['row_number'], 'errors' => $errors];
            }
        }

        return [
            'rows' => $rows,
            'total' => count($rows),
            'invalid_count' => count($invalidRows),
            'invalid_rows' => $invalidRows,
            'file_errors' => [],
        ];
    }

    private function cleanJabatanImportPreviews(Request $request): void
    {
        $previews = $request->session()->get('jabatan_import_previews', []);
        foreach ($previews as $token => $preview) {
            if ((int) ($preview['expires_at'] ?? 0) < now()->timestamp) {
                Storage::disk('local')->delete($preview['path'] ?? '');
                unset($previews[$token]);
            }
        }
        $request->session()->put('jabatan_import_previews', $previews);
    }

    private function normaliseImportHeader(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function normaliseName(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function normaliseCode(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if ($value !== '' && ctype_digit($value)) {
            return ltrim($value, '0') ?: '0';
        }

        return $value;
    }

    private function normaliseJobStatus(string $value): ?string
    {
        return match (mb_strtolower(trim($value))) {
            'aktif' => 'Aktif',
            'non', 'non aktif', 'non-aktif', 'nonaktif', 'tidak aktif' => 'Non',
            default => null,
        };
    }

    private function parseJabatanDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value >= 1 && (float) $value <= 100000) {
            return date('Y-m-d', strtotime('1899-12-30 +'.(int) floor((float) $value).' days'));
        }

        foreach (['!d-m-Y', '!d/m/Y', '!Y-m-d', '!d-m-y', '!d/m/y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $issues = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($issues === false || ($issues['warning_count'] === 0 && $issues['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        $id = $request->string('id_peg')->trim()->value();
        $employee = $id === '' ? null : TbPegawai::query()->where('id_peg', $id)->where('status_aktif', 1)->firstOrFail();
        $currentJob = $employee?->jabatan()->where('status_jab', 'Aktif')->latest('tmt_jabatan')->first();

        return Inertia::render('Jabatan/Form', [
            'mode' => 'create',
            'formMode' => $currentJob ? 'history' : 'active',
            'employee' => $employee ? ['id_peg' => $employee->id_peg, 'nik' => $employee->nik, 'nama' => $employee->nama] : null,
            'currentJob' => $currentJob ? $this->presentJob($currentJob) : null,
            'employees' => $this->availableEmployees(),
            'positions' => $this->masterPositions(),
            'units' => $this->units(),
            'record' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate([
            'id_peg' => ['required', 'exists:tb_pegawai,id_peg'],
            'form_mode' => ['required', 'in:active,history'],
            'kode_jabatan' => ['required', 'string', 'max:20', 'exists:tb_ref_jabatan,kode_jabatan'],
            'unit_kerja' => ['required', 'string', 'max:64', 'exists:tb_kantor,kode_kantor_detail'],
            'no_sk' => ['required', 'string', 'max:64'],
            'tgl_sk' => ['required', 'date'],
            'tmt_mulai' => ['required', 'date'],
        ]);

        $employee = TbPegawai::query()->where('id_peg', $validated['id_peg'])->where('status_aktif', 1)->firstOrFail();
        $position = $this->resolvePosition($validated['kode_jabatan']);

        DB::transaction(function () use ($request, $validated, $position): void {
            if ($validated['form_mode'] === 'active') {
                TbJabatan::query()
                    ->where('id_peg', $validated['id_peg'])
                    ->where('status_jab', 'Aktif')
                    ->update([
                        'status_jab' => 'Non',
                        'sampai_tgl' => $validated['tmt_mulai'],
                        'updated_at' => now(),
                        'updated_by' => $this->operator($request),
                    ]);
            }

            TbJabatan::query()->create([
                'id_peg' => $validated['id_peg'],
                'kode_jabatan' => $position['kode'],
                'jabatan' => $position['nama'],
                'unit_kerja' => $validated['unit_kerja'],
                'tmt_jabatan' => $validated['tmt_mulai'],
                'sampai_tgl' => null,
                'status_jab' => $validated['form_mode'] === 'history' ? 'Non' : 'Aktif',
                'no_sk' => $validated['no_sk'],
                'tgl_sk' => $validated['tgl_sk'],
                'date_reg' => now()->toDateString(),
                'created_by' => $this->operator($request),
            ]);
        });

        return to_route('jabatan.index')->with('success', 'Data jabatan berhasil disimpan.');
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbJabatan::query()->findOrFail($id);
        $employee = TbPegawai::query()->find($record->id_peg);

        return Inertia::render('Jabatan/Form', [
            'mode' => 'edit',
            'formMode' => 'edit',
            'employee' => $employee ? ['id_peg' => $employee->id_peg, 'nik' => $employee->nik, 'nama' => $employee->nama] : null,
            'currentJob' => null,
            'employees' => [],
            'positions' => $this->masterPositions(),
            'units' => $this->units(),
            'record' => [
                'id_jab' => $record->id_jab,
                'id_peg' => $record->id_peg,
                'kode_jabatan' => $record->kode_jabatan,
                'jabatan' => $record->jabatan,
                'unit_kerja' => $record->unit_kerja,
                'tmt_jabatan' => $record->tmt_jabatan?->format('Y-m-d'),
                'sampai_tgl' => $record->sampai_tgl?->format('Y-m-d'),
                'no_sk' => $record->no_sk,
                'tgl_sk' => $record->tgl_sk?->format('Y-m-d'),
                'status_jab' => $record->status_jab,
            ],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbJabatan::query()->findOrFail($id);
        $validated = $request->validate([
            'kode_jabatan' => ['required', 'string', 'max:20', 'exists:tb_ref_jabatan,kode_jabatan'],
            'unit_kerja' => ['required', 'string', 'max:64', 'exists:tb_kantor,kode_kantor_detail'],
            'tmt_jabatan' => ['required', 'date'],
            'no_sk' => ['required', 'string', 'max:64'],
            'tgl_sk' => ['required', 'date'],
        ]);
        $position = $this->resolvePosition($validated['kode_jabatan']);
        $record->update([
            'kode_jabatan' => $position['kode'],
            'jabatan' => $position['nama'],
            'unit_kerja' => $validated['unit_kerja'],
            'tmt_jabatan' => $validated['tmt_jabatan'],
            'no_sk' => $validated['no_sk'],
            'tgl_sk' => $validated['tgl_sk'],
            'updated_at' => now(),
            'updated_by' => $this->operator($request),
        ]);

        return to_route('jabatan.index')->with('success', 'Data jabatan berhasil diperbarui.');
    }

    public function setup(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbJabatan::query()->findOrFail($id);

        DB::transaction(function () use ($request, $record): void {
            TbJabatan::query()->where('id_peg', $record->id_peg)->update([
                'status_jab' => '',
                'updated_at' => now(),
                'updated_by' => $this->operator($request),
            ]);
            $record->update(['status_jab' => 'Aktif', 'updated_at' => now(), 'updated_by' => $this->operator($request)]);
        });

        return to_route('employees.show', $record->id_peg)->with('success', 'Jabatan aktif pegawai berhasil diperbarui.');
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || (string) $value === '0000-00-00') {
            return null;
        }

        return date('d-m-Y', strtotime((string) $value));
    }

    private function ensureStaffAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin', 'kepala'], true), 403);
    }

    private function canManage(Request $request): bool
    {
        return strtolower((string) $request->session()->get('hak_akses')) === 'admin';
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function ensureCanImport(Request $request): void
    {
        abort_unless(app(RbacService::class)->can($request, 'positions.import'), 403);
    }

    private function availableEmployees()
    {
        return TbPegawai::query()
            ->where('status_aktif', 1)
            ->orderBy('nama')
            ->get(['id_peg', 'nik', 'nama'])
            ->map(fn (TbPegawai $employee): array => [
                'id_peg' => $employee->id_peg,
                'nik' => $employee->nik,
                'nama' => $employee->nama,
            ])
            ->values();
    }

    private function masterPositions()
    {
        return TbRefJabatan::query()
            ->orderBy('jabatan')
            ->get(['kode_jabatan', 'jabatan'])
            ->map(fn (TbRefJabatan $position): array => [
                'kode_jabatan' => (string) $position->kode_jabatan,
                'nama_jabatan' => $position->jabatan,
            ])
            ->values();
    }

    private function units()
    {
        return TbKantor::query()
            ->orderBy('kode_kantor_detail')
            ->get(['kode_kantor_detail', 'nama_kantor'])
            ->map(fn (TbKantor $unit): array => [
                'kode_kantor_detail' => $unit->kode_kantor_detail,
                'nama_kantor' => $unit->nama_kantor,
            ])
            ->values();
    }

    private function resolvePosition(string $code): array
    {
        $master = TbRefJabatan::query()->where('kode_jabatan', (int) $code)->firstOrFail();

        return [
            'kode' => (string) $master->kode_jabatan,
            'nama' => $master->jabatan,
        ];
    }

    private function presentJob(TbJabatan $job): array
    {
        return [
            'id_jab' => $job->id_jab,
            'jabatan' => $job->jabatan,
            'unit_kerja' => $job->unit_kerja,
            'tmt_jabatan' => $job->tmt_jabatan?->format('d-m-Y'),
        ];
    }

    private function operator(Request $request): string
    {
        return substr((string) $request->session()->get('id_user', 'system'), 0, 20);
    }
}
