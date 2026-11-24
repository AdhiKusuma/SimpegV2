<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbAngkat;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use App\Services\ImportPreviewService;
use App\Services\XlsxExportService;
use App\Services\XlsxImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PengangkatanController extends Controller
{
    private const TYPES = ['Kontrak', 'Calon Pegawai', 'Tetap'];

    private const IMPORT_FIELDS = ['id_peg', 'jns_mutasi', 'tgl_mutasi', 'no_mutasi', 'tmt', 'tmt_selesai'];

    private const IMPORT_COLUMNS = [
        'ID Pegawai', 'Jenis Pengangkatan', 'Tanggal SK (dd-mm-yyyy)', 'Nomor SK',
        'TMT Mulai (dd-mm-yyyy)', 'TMT Selesai (dd-mm-yyyy, wajib untuk Kontrak/Calon Pegawai)',
    ];

    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $search = $request->string('search')->trim()->value();
        $year = $request->string('tahun')->trim()->value();

        $query = TbAngkat::query()
            ->from('tb_angkat as angkat')
            ->leftJoin('tb_pegawai as pegawai', 'pegawai.id_peg', '=', 'angkat.id_peg_baru')
            ->when($unit !== '', fn (Builder $builder) => $builder->whereExists(function (QueryBuilder $scope) use ($unit): void {
                $scope->selectRaw('1')
                    ->from('tb_jabatan as jabatan_scope')
                    ->whereColumn('jabatan_scope.id_peg', 'angkat.id_peg_baru')
                    ->where('jabatan_scope.status_jab', 'Aktif')
                    ->where('jabatan_scope.unit_kerja', $unit);
            }))
            ->when($year !== '', fn (Builder $builder) => $builder->whereYear('angkat.tgl_mutasi', $year))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (QueryBuilder $scope) use ($like): void {
                    $scope->where('pegawai.nama', 'like', $like)
                        ->orWhere('angkat.id_peg', 'like', $like)
                        ->orWhere('angkat.id_peg_baru', 'like', $like);
                });
            });

        $items = $query
            ->select('angkat.*', 'pegawai.nama as nama_pegawai')
            ->orderByDesc('angkat.tgl_mutasi')
            ->orderByDesc('angkat.id_angkat')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbAngkat $item): array => $this->present($item));

        return Inertia::render('Pengangkatan/Index', [
            'items' => $items,
            'years' => TbAngkat::query()->whereNotNull('tgl_mutasi')->selectRaw('YEAR(tgl_mutasi) as tahun')->distinct()->orderByDesc('tahun')->pluck('tahun')->values(),
            'filters' => ['search' => $search, 'tahun' => $year],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Pengangkatan/Form', [
            'mode' => 'create',
            'record' => null,
            'employees' => $this->employeesForCreate(),
            'types' => self::TYPES,
        ]);
    }

    public function importForm(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Imports/XlsxImport', [
            'title' => 'Import Pengangkatan Pegawai',
            'backUrl' => '/pengangkatan',
            'previewUrl' => '/pengangkatan/import/preview',
            'storeUrl' => '/pengangkatan/import',
            'templateUrl' => '/pengangkatan/import/template',
            'columns' => self::IMPORT_COLUMNS,
            'preview' => null,
        ]);
    }

    public function importTemplate(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureCanManage($request);

        $employeeIds = TbPegawai::query()
            ->orderBy('id_peg')
            ->pluck('id_peg')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();

        $content = $xlsxExport->makeTemplate(
            self::IMPORT_COLUMNS,
            'Pengangkatan',
            [['102-001', 'Kontrak', '01-01-2026', 'SK/CONTOH/2026', '01-01-2026', '31-12-2026']],
            [
                'id_pegawai' => ['title' => 'ID Pegawai', 'values' => $employeeIds],
                'jenis_pengangkatan' => ['title' => 'Jenis Pengangkatan', 'values' => self::TYPES],
            ],
            [
                ['column' => 'A', 'list' => 'id_pegawai', 'prompt' => 'Pilih ID pegawai yang tersedia.'],
                ['column' => 'B', 'list' => 'jenis_pengangkatan', 'prompt' => 'Pilih jenis pengangkatan dari daftar.'],
            ],
        );

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'Template_Import_Pengangkatan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function previewImport(Request $request, XlsxImportService $xlsxImport, ImportPreviewService $previewStore): JsonResponse
    {
        $this->ensureCanManage($request);
        $request->validate([
            'file_excel' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ], [
            'file_excel.required' => 'Pilih file XLSX terlebih dahulu.',
            'file_excel.mimes' => 'File import harus berformat XLSX.',
            'file_excel.max' => 'Ukuran file import maksimal 5 MB.',
        ]);

        $file = $request->file('file_excel');
        $preparedRows = $this->parseAppointmentImport($file->getRealPath(), $xlsxImport);
        if ($preparedRows === []) {
            throw ValidationException::withMessages(['file_excel' => 'Tidak ada baris data pengangkatan yang dapat diimport.']);
        }

        $invalidCount = count(array_filter($preparedRows, fn (array $row): bool => $row['errors'] !== []));
        $result = [
            'rows' => $preparedRows,
            'preview' => array_slice($preparedRows, 0, 25),
            'total' => count($preparedRows),
            'invalid_count' => $invalidCount,
            'columns' => self::IMPORT_COLUMNS,
        ];

        if ($invalidCount === 0) {
            $result['token'] = $previewStore->create($request, 'pengangkatan_import_previews', 'pengangkatan-import-previews', $file);
        }

        return response()->json($result);
    }

    public function storeImport(Request $request, XlsxImportService $xlsxImport, ImportPreviewService $previewStore): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['preview_token' => ['required', 'uuid']]);
        $preview = $previewStore->resolve($request, 'pengangkatan_import_previews', $validated['preview_token']);
        abort_unless($preview !== null, 422, 'Preview import tidak berlaku. Unggah kembali file pengangkatan untuk melanjutkan.');

        $preparedRows = $this->parseAppointmentImport($preview['full_path'], $xlsxImport);
        $validRows = [];
        $errors = [];

        foreach ($preparedRows as $prepared) {
            if ($prepared['errors'] !== []) {
                $errors[] = 'Baris '.$prepared['row_number'].': '.implode('; ', $prepared['errors']);

                continue;
            }

            $validRows[] = $prepared['data'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['preview_token' => 'Import dibatalkan karena ada data yang berubah atau tidak valid. '.implode(' ', $errors)]);
        }

        DB::transaction(function () use ($validRows): void {
            $ids = array_values(array_unique(array_map(static fn (array $row): string => (string) $row['id_peg'], $validRows)));
            TbPegawai::query()->whereIn('id_peg', $ids)->lockForUpdate()->get(['id_peg']);

            foreach ($validRows as $data) {
                if ($this->existingAppointment($data)) {
                    throw ValidationException::withMessages(['preview_token' => 'Import dibatalkan karena ada data pengangkatan yang sudah tersimpan.']);
                }

                TbAngkat::query()->create([
                    'id_peg' => $data['id_peg'],
                    'jns_mutasi' => $data['jns_mutasi'],
                    'id_peg_baru' => $data['id_peg'],
                    'tgl_mutasi' => $data['tgl_mutasi'],
                    'tmt' => $data['tmt'],
                    'tmt_selesai' => $data['tmt_selesai'],
                    'no_mutasi' => $data['no_mutasi'],
                    'sk_mutasi' => null,
                    'date_reg' => now(),
                ]);
            }
        });

        $previewStore->consume($request, 'pengangkatan_import_previews', $validated['preview_token']);

        return to_route('pengangkatan.index')->with('success', count($validRows).' data pengangkatan berhasil diimport.');
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbAngkat::query()->findOrFail($id);

        return Inertia::render('Pengangkatan/Form', [
            'mode' => 'edit',
            'record' => [
                'id_angkat' => $record->id_angkat,
                'id_peg' => $record->id_peg,
                'id_peg_baru' => $record->id_peg_baru,
                'jns_mutasi' => $record->jns_mutasi,
                'tgl_mutasi' => $record->tgl_mutasi?->format('Y-m-d'),
                'no_mutasi' => $record->no_mutasi,
                'tmt' => $record->tmt?->format('Y-m-d'),
                'tmt_selesai' => $record->tmt_selesai?->format('Y-m-d'),
                'sk_mutasi' => $record->sk_mutasi,
            ],
            'employees' => [],
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request);
        $storedFile = null;

        try {
            DB::transaction(function () use ($request, &$data, &$storedFile): void {
                $employeeId = $data['id_peg'];
                $storedFile = $this->storeDocument($request, $employeeId);

                TbAngkat::query()->create([
                    'id_peg' => $employeeId,
                    'jns_mutasi' => $data['jns_mutasi'],
                    'id_peg_baru' => $employeeId,
                    'tgl_mutasi' => $data['tgl_mutasi'],
                    'tmt' => $data['tmt'],
                    'tmt_selesai' => $data['tmt_selesai'] ?? null,
                    'no_mutasi' => $data['no_mutasi'],
                    'sk_mutasi' => $storedFile,
                    'date_reg' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            $this->deleteDocument($storedFile);
            throw $exception;
        }

        return to_route('pengangkatan.index')->with('success', 'Pengangkatan pegawai berhasil disimpan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbAngkat::query()->findOrFail($id);
        $rules = ['tmt' => ['required', 'date']];

        if (in_array($record->jns_mutasi, ['Kontrak', 'Calon Pegawai'], true)) {
            $rules['tmt_selesai'] = ['required', 'date', 'after_or_equal:tmt'];
        } else {
            $rules['tmt_selesai'] = ['nullable', 'date', 'after_or_equal:tmt'];
        }

        $data = $request->validate($rules, [
            'tmt_selesai.required' => 'TMT selesai wajib diisi untuk pengangkatan Kontrak atau Calon Pegawai.',
            'tmt_selesai.after_or_equal' => 'TMT selesai harus sama atau setelah TMT mulai.',
        ]);
        $record->update([
            'tmt' => $data['tmt'],
            'tmt_selesai' => $data['tmt_selesai'] ?? null,
        ]);

        return to_route('pengangkatan.index')->with('success', 'Masa berlaku pengangkatan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $document = null;

        DB::transaction(function () use ($request, $id, $validated, &$document): void {
            $record = TbAngkat::query()->lockForUpdate()->findOrFail($id);
            $document = $record->sk_mutasi;

            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_angkat',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);

            $record->delete();
        });

        $this->deleteDocument($document);

        return to_route('pengangkatan.index')->with('success', 'Data pengangkatan dipindahkan ke Recycle Bin.');
    }

    public function document(Request $request, int $id): BinaryFileResponse
    {
        $this->ensureStaffAccess($request);
        $record = TbAngkat::query()->findOrFail($id);
        $filename = basename((string) $record->sk_mutasi);
        abort_if($filename === '' || $filename === '.', 404);

        $paths = [
            Storage::disk('public')->path('sk_angkat/'.$filename),
            Storage::disk('public')->path('sk_mutasi/'.$filename),
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return response()->file($path);
            }
        }

        abort(404);
    }

    private function validatedData(Request $request): array
    {
        // Keep the current form contract (`id_peg`) while accepting the
        // legacy payload name when an older client still sends it.
        if (! $request->has('id_peg') && $request->has('id_peg_baru')) {
            $request->merge(['id_peg' => $request->input('id_peg_baru')]);
        }

        $rules = [
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')],
            'jns_mutasi' => ['required', Rule::in(self::TYPES)],
            'tgl_mutasi' => ['required', 'date'],
            'tmt' => ['required', 'date'],
            'tmt_selesai' => [
                'nullable',
                'date',
                'after_or_equal:tmt',
                Rule::requiredIf(fn (): bool => in_array($request->input('jns_mutasi'), ['Kontrak', 'Calon Pegawai'], true)),
            ],
            'no_mutasi' => ['required', 'string', 'max:50'],
            'sk_mutasi' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];

        // If the legacy field is present, validate it too and require it to
        // identify the same employee. Requests from the current UI do not
        // include this field, so their validation flow remains unchanged.
        if ($request->has('id_peg_baru')) {
            $rules['id_peg_baru'] = ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg'), 'same:id_peg'];
        }

        $data = $request->validate($rules, [
            'tmt_selesai.required' => 'TMT selesai wajib diisi untuk pengangkatan Kontrak atau Calon Pegawai.',
            'tmt_selesai.after_or_equal' => 'TMT selesai harus sama atau setelah TMT mulai.',
        ]);

        if (! in_array($data['jns_mutasi'], ['Kontrak', 'Calon Pegawai'], true)) {
            $data['tmt_selesai'] = null;
        }

        return $data;
    }

    private function employeesForCreate(): array
    {
        return TbPegawai::query()
            ->orderBy('nama')
            ->toBase()
            ->get(['id_peg', 'nama', 'status_kepeg'])
            ->map(fn ($employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama, 'status_kepeg' => $employee->status_kepeg])
            ->all();
    }

    private function parseAppointmentImport(string $path, XlsxImportService $xlsxImport): array
    {
        $rows = $xlsxImport->rows($path);
        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file_excel' => 'File import harus memiliki header dan minimal satu baris data.']);
        }

        $header = $this->trimImportRow(array_shift($rows) ?: []);
        $expectedHeader = array_map(fn (string $column): string => $xlsxImport->normalizeHeader($column), self::IMPORT_COLUMNS);
        $actualHeader = array_map(fn (mixed $column): string => $xlsxImport->normalizeHeader($column), $header);
        if ($actualHeader !== $expectedHeader) {
            throw ValidationException::withMessages(['file_excel' => 'Kolom template tidak sesuai. Unduh dan gunakan template import pengangkatan terbaru.']);
        }

        $employeeIds = TbPegawai::query()->pluck('id_peg')->mapWithKeys(fn ($id): array => [(string) $id => true])->all();
        $preparedRows = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $values = array_map(static fn (mixed $value): string => trim((string) $value), array_pad(array_slice($row, 0, count(self::IMPORT_FIELDS)), count(self::IMPORT_FIELDS), ''));
            if ($this->isBlankImportRow($values)) {
                continue;
            }

            $data = array_combine(self::IMPORT_FIELDS, $values) ?: [];
            $errors = [];
            foreach (['tgl_mutasi' => 'Tanggal SK', 'tmt' => 'TMT mulai', 'tmt_selesai' => 'TMT selesai'] as $field => $label) {
                if (($data[$field] ?? '') === '' || $data[$field] === '-') {
                    $data[$field] = null;

                    continue;
                }

                $parsedDate = $xlsxImport->date($data[$field]);
                if ($parsedDate === null) {
                    $errors[] = $label.' harus berformat dd-mm-yyyy.';
                    $data[$field] = null;
                } else {
                    $data[$field] = $parsedDate;
                }
            }

            $idPeg = (string) ($data['id_peg'] ?? '');
            if ($idPeg === '') {
                $errors[] = 'ID Pegawai wajib diisi.';
            } elseif (mb_strlen($idPeg) > 12) {
                $errors[] = 'ID Pegawai maksimal 12 karakter.';
            } elseif (! isset($employeeIds[$idPeg])) {
                $errors[] = 'ID Pegawai tidak ditemukan.';
            }

            if (($data['jns_mutasi'] ?? '') === '') {
                $errors[] = 'Jenis pengangkatan wajib diisi.';
            } elseif (! in_array($data['jns_mutasi'], self::TYPES, true)) {
                $errors[] = 'Jenis pengangkatan tidak tersedia pada daftar pilihan.';
            }

            foreach (['no_mutasi' => ['label' => 'Nomor SK', 'max' => 50]] as $field => $rule) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = $value === '' ? null : $value;
                if ($value === '') {
                    $errors[] = $rule['label'].' wajib diisi.';
                } elseif (mb_strlen($value) > $rule['max']) {
                    $errors[] = $rule['label'].' maksimal '.$rule['max'].' karakter.';
                }
            }

            if (($data['tgl_mutasi'] ?? null) === null) {
                $errors[] = 'Tanggal SK wajib diisi.';
            }
            if (($data['tmt'] ?? null) === null) {
                $errors[] = 'TMT mulai wajib diisi.';
            }

            if (in_array($data['jns_mutasi'] ?? '', ['Kontrak', 'Calon Pegawai'], true) && ($data['tmt_selesai'] ?? null) === null) {
                $errors[] = 'TMT selesai wajib diisi untuk Kontrak atau Calon Pegawai.';
            }
            if (($data['jns_mutasi'] ?? '') === 'Tetap') {
                $data['tmt_selesai'] = null;
            }
            if (($data['tmt'] ?? null) !== null && ($data['tmt_selesai'] ?? null) !== null && $data['tmt_selesai'] < $data['tmt']) {
                $errors[] = 'TMT selesai harus sama atau setelah TMT mulai.';
            }

            $key = implode('::', [(string) ($data['id_peg'] ?? ''), (string) ($data['jns_mutasi'] ?? ''), (string) ($data['no_mutasi'] ?? ''), (string) ($data['tgl_mutasi'] ?? '')]);
            if ($key !== ':::' && isset($seen[$key])) {
                $errors[] = 'Data duplikat dengan baris '.$seen[$key].' pada file import.';
            } elseif ($key !== ':::') {
                $seen[$key] = $index + 2;
            }
            if ($errors === [] && $this->existingAppointment($data)) {
                $errors[] = 'Data pengangkatan dengan ID pegawai, nomor SK, dan tanggal SK tersebut sudah ada.';
            }

            $preparedRows[] = [
                'row_number' => $index + 2,
                'status' => $errors === [] ? 'Data baru' : 'Perlu diperiksa',
                'errors' => array_values(array_unique($errors)),
                'values' => $values,
                'data' => $data,
            ];
        }

        return $preparedRows;
    }

    private function existingAppointment(array $data): bool
    {
        if (($data['id_peg'] ?? '') === '' || ($data['no_mutasi'] ?? '') === '' || ($data['tgl_mutasi'] ?? null) === null) {
            return false;
        }

        return TbAngkat::query()
            ->where('id_peg_baru', $data['id_peg'])
            ->where('jns_mutasi', $data['jns_mutasi'])
            ->where('no_mutasi', $data['no_mutasi'])
            ->whereDate('tgl_mutasi', $data['tgl_mutasi'])
            ->exists();
    }

    private function trimImportRow(array $row): array
    {
        while ($row !== [] && trim((string) end($row)) === '') {
            array_pop($row);
        }

        if (isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string) $row[0]) ?: (string) $row[0];
        }

        return array_values($row);
    }

    private function isBlankImportRow(array $row): bool
    {
        return count(array_filter($row, static fn (mixed $value): bool => trim((string) $value) !== '')) === 0;
    }

    private function present(TbAngkat $item): array
    {
        $hasDocument = $item->sk_mutasi && $this->documentExists($item->sk_mutasi);

        return [
            'id_angkat' => $item->id_angkat,
            'id_peg' => $item->id_peg,
            'id_peg_baru' => $item->id_peg_baru,
            'nama_pegawai' => $item->nama_pegawai ?: 'Tanpa Nama',
            'jns_mutasi' => $item->jns_mutasi,
            'tgl_mutasi' => $item->tgl_mutasi?->format('d-m-Y'),
            'no_mutasi' => $item->no_mutasi,
            'tmt' => $item->tmt?->format('d-m-Y'),
            'tmt_selesai' => $item->tmt_selesai?->format('d-m-Y'),
            'sk_mutasi' => $item->sk_mutasi,
            'document_url' => $hasDocument ? route('pengangkatan.document', $item->id_angkat) : null,
        ];
    }

    private function storeDocument(Request $request, string $newId): ?string
    {
        if (! $request->hasFile('sk_mutasi')) {
            return null;
        }

        $filename = 'SK_'.$newId.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6)).'.pdf';
        Storage::disk('public')->putFileAs('sk_angkat', $request->file('sk_mutasi'), $filename);

        return $filename;
    }

    private function deleteDocument(?string $filename): void
    {
        if (! $filename) {
            return;
        }

        $disk = Storage::disk('public');
        $safeFilename = basename($filename);
        $disk->delete(['sk_angkat/'.$safeFilename, 'sk_mutasi/'.$safeFilename]);
    }

    private function documentExists(string $filename): bool
    {
        $safeFilename = basename($filename);

        return is_file(Storage::disk('public')->path('sk_angkat/'.$safeFilename))
            || is_file(Storage::disk('public')->path('sk_mutasi/'.$safeFilename));
    }

    private function canManage(Request $request): bool
    {
        return in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'it_admin'], true);
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function ensureStaffAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin', 'kepala', 'it_admin'], true), 403);
    }
}
