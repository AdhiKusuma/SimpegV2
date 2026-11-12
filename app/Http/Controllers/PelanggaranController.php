<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbHukuman;
use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use App\Services\ImportPreviewService;
use App\Services\XlsxExportService;
use App\Services\XlsxImportService;
use Illuminate\Database\Eloquent\Builder;
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

class PelanggaranController extends Controller
{
    private const TYPES = [
        'Surat Peringatan I',
        'Surat Peringatan II',
        'Surat Peringatan III',
        'Skorsing',
        'PTDH',
        'Teguran Lisan',
        'Teguran Tertulis',
    ];

    private const OFFICIAL_POSITIONS = [
        'Direktur Operasional',
        'Kepala Divisi SDM dan Umum',
    ];

    private const IMPORT_FIELDS = [
        'id_peg', 'hukuman', 'keterangan', 'tgl_sk', 'no_sk', 'pejabat_sk',
        'jabatan_sk', 'pejabat_pulih', 'jabatan_pulih', 'no_pulih', 'tgl_pulih',
    ];

    private const IMPORT_COLUMNS = [
        'ID Pegawai', 'Jenis Sanksi', 'Keterangan', 'Tanggal SK (dd-mm-yyyy)',
        'Nomor SK', 'Pejabat SK', 'Jabatan SK', 'Pejabat Pemulihan (opsional)',
        'Jabatan Pemulihan (opsional)', 'Nomor Surat Pemulihan (opsional)',
        'Tanggal Pemulihan (dd-mm-yyyy, opsional)',
    ];

    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $search = $request->string('search')->trim()->value();
        $items = $this->filteredPenaltyQuery($request, $search)
            ->select('h.*', 'p.nama as nama_pegawai', 'k.nama_kantor')
            ->orderByDesc('h.tgl_sk')
            ->orderByDesc('h.id_hukum')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbHukuman $item): array => $this->presentPenalty($item));

        return Inertia::render('Pelanggaran/Index', [
            'items' => $items,
            'filters' => ['search' => $search],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function export(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaffAccess($request);

        $search = $request->string('search')->trim()->value();
        $records = $this->filteredPenaltyQuery($request, $search)
            ->select('h.*', 'p.nama as nama_pegawai', 'k.nama_kantor')
            ->orderByDesc('h.tgl_sk')
            ->orderByDesc('h.id_hukum')
            ->get();

        $rows = [[
            'Nama pegawai', 'ID pegawai', 'Jenis sanksi', 'Keterangan', 'Tanggal SK',
            'Nomor SK', 'Kantor', 'Pejabat SK', 'Jabatan SK', 'Dokumen SK',
            'Pejabat pemulihan', 'Jabatan pemulihan', 'Nomor surat pemulihan', 'Tanggal pemulihan',
        ]];

        foreach ($records as $record) {
            $rows[] = [
                $record->nama_pegawai ?: 'Tanpa Nama',
                $record->id_peg,
                $record->hukuman,
                $record->keterangan,
                $record->tgl_sk?->format('d-m-Y'),
                $record->no_sk,
                $record->nama_kantor,
                $record->pejabat_sk,
                $record->jabatan_sk,
                $record->dokumen ? basename((string) $record->dokumen) : '-',
                $record->pejabat_pulih,
                $record->jabatan_pulih,
                $record->no_pulih,
                $record->tgl_pulih?->format('d-m-Y'),
            ];
        }

        $content = $xlsxExport->make($rows, 'Pelanggaran', true);
        $filename = 'data-pelanggaran-'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importForm(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Imports/XlsxImport', [
            'title' => 'Import Data Pelanggaran',
            'backUrl' => '/pelanggaran',
            'previewUrl' => '/pelanggaran/import/preview',
            'storeUrl' => '/pelanggaran/import',
            'templateUrl' => '/pelanggaran/import/template',
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
            'Pelanggaran',
            [[
                '102-001', 'Surat Peringatan I', 'Contoh pelanggaran untuk dihapus sebelum import',
                '10-02-2026', 'SK/CONTOH/2026', 'Nama pejabat', 'Direktur Operasional',
                '', '', '', '',
            ]],
            [
                'id_pegawai' => ['title' => 'ID Pegawai', 'values' => $employeeIds],
                'jenis_sanksi' => ['title' => 'Jenis Sanksi', 'values' => self::TYPES],
            ],
            [
                ['column' => 'A', 'list' => 'id_pegawai', 'prompt' => 'Pilih ID pegawai yang tersedia.'],
                ['column' => 'B', 'list' => 'jenis_sanksi', 'prompt' => 'Pilih jenis sanksi dari daftar.'],
            ],
        );

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'Template_Import_Pelanggaran.xlsx', [
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
        $preparedRows = $this->parsePelanggaranImport($file->getRealPath(), $xlsxImport);

        if ($preparedRows === []) {
            throw ValidationException::withMessages(['file_excel' => 'Tidak ada baris data yang dapat diimport.']);
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
            $result['token'] = $previewStore->create(
                $request,
                'pelanggaran_import_previews',
                'pelanggaran-import-previews',
                $file,
            );
        }

        return response()->json($result);
    }

    public function storeImport(Request $request, XlsxImportService $xlsxImport, ImportPreviewService $previewStore): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['preview_token' => ['required', 'uuid']]);
        $preview = $previewStore->resolve($request, 'pelanggaran_import_previews', $validated['preview_token']);
        abort_unless($preview !== null, 422, 'Preview import tidak berlaku. Unggah kembali file pelanggaran untuk melanjutkan.');

        $preparedRows = $this->parsePelanggaranImport($preview['full_path'], $xlsxImport);
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
            throw ValidationException::withMessages([
                'preview_token' => 'Import dibatalkan karena ada data yang berubah atau tidak valid. '.implode(' ', $errors),
            ]);
        }

        DB::transaction(function () use ($validRows): void {
            $ids = array_values(array_unique(array_filter(array_map(
                static fn (array $row): string => (string) $row['id_peg'],
                $validRows,
            ))));
            TbPegawai::query()->whereIn('id_peg', $ids)->lockForUpdate()->get(['id_peg']);

            foreach ($validRows as $data) {
                if ($this->existingPenalty($data)) {
                    throw ValidationException::withMessages([
                        'preview_token' => 'Import dibatalkan karena ada data pelanggaran yang sudah tersimpan.',
                    ]);
                }

                TbHukuman::query()->create([
                    ...$data,
                    'dokumen' => '',
                    'date_reg' => now()->toDateString(),
                ]);
            }
        });

        $previewStore->consume($request, 'pelanggaran_import_previews', $validated['preview_token']);

        return to_route('pelanggaran.index')->with('success', count($validRows).' data pelanggaran berhasil diimport.');
    }

    private function parsePelanggaranImport(string $path, XlsxImportService $xlsxImport): array
    {
        $rows = $xlsxImport->rows($path);
        $header = $this->trimImportRow(array_shift($rows) ?: []);
        $expectedHeader = array_map(
            fn (string $column): string => $xlsxImport->normalizeHeader($column),
            self::IMPORT_COLUMNS,
        );
        $actualHeader = array_map(
            fn (mixed $column): string => $xlsxImport->normalizeHeader($column),
            $header,
        );

        if ($actualHeader !== $expectedHeader) {
            throw ValidationException::withMessages([
                'file_excel' => 'Kolom template tidak sesuai. Unduh dan gunakan template import pelanggaran terbaru.',
            ]);
        }

        $employeeIds = $this->importEmployeeIds();
        $preparedRows = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $row = array_pad(array_slice($row, 0, count(self::IMPORT_FIELDS)), count(self::IMPORT_FIELDS), '');
            if ($this->isBlankImportRow($row)) {
                continue;
            }

            $prepared = $this->prepareImportRow($row, $index + 2, $employeeIds, $xlsxImport);
            $key = $this->importDuplicateKey($prepared['data']);

            if ($key !== '::' && isset($seen[$key])) {
                $prepared['errors'][] = 'Data duplikat dengan baris '.$seen[$key].' pada file import.';
            } elseif ($key !== '::') {
                $seen[$key] = $prepared['row_number'];
            }

            if ($prepared['errors'] === [] && $this->existingPenalty($prepared['data'])) {
                $prepared['errors'][] = 'Data pelanggaran dengan ID pegawai, nomor SK, dan tanggal SK tersebut sudah ada.';
            }

            $prepared['status'] = $prepared['errors'] === [] ? 'Data baru' : 'Perlu diperiksa';
            $preparedRows[] = $prepared;
        }

        return $preparedRows;
    }

    private function importEmployeeIds(): array
    {
        return TbPegawai::query()
            ->pluck('id_peg')
            ->mapWithKeys(fn ($id): array => [(string) $id => true])
            ->all();
    }

    private function prepareImportRow(
        array $values,
        int $rowNumber,
        array $employeeIds,
        XlsxImportService $xlsxImport,
    ): array {
        $values = array_map(static fn (mixed $value): string => trim((string) $value), $values);
        $data = array_combine(self::IMPORT_FIELDS, array_pad($values, count(self::IMPORT_FIELDS), '')) ?: [];
        $errors = [];
        $rawTanggalSk = trim((string) ($data['tgl_sk'] ?? ''));

        foreach (['tgl_sk', 'tgl_pulih'] as $dateField) {
            $rawDate = (string) ($data[$dateField] ?? '');

            if ($rawDate === '' || $rawDate === '-') {
                $data[$dateField] = null;
                continue;
            }

            $parsedDate = $xlsxImport->date($rawDate);
            if ($parsedDate === null) {
                $label = $dateField === 'tgl_sk' ? 'Tanggal SK' : 'Tanggal pemulihan';
                $errors[] = $label.' harus berformat dd-mm-yyyy.';
                $data[$dateField] = null;
            } else {
                $data[$dateField] = $parsedDate;
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

        $hukuman = (string) ($data['hukuman'] ?? '');
        if ($hukuman === '') {
            $errors[] = 'Jenis sanksi wajib diisi.';
        } elseif (! in_array($hukuman, self::TYPES, true)) {
            $errors[] = 'Jenis sanksi tidak tersedia pada daftar pilihan.';
        }

        foreach ([
            'no_sk' => ['label' => 'Nomor SK', 'required' => true, 'max' => 32],
            'pejabat_sk' => ['label' => 'Pejabat SK', 'required' => true, 'max' => 64],
            'jabatan_sk' => ['label' => 'Jabatan SK', 'required' => true, 'max' => 64],
            'pejabat_pulih' => ['label' => 'Pejabat pemulihan', 'required' => false, 'max' => 64],
            'jabatan_pulih' => ['label' => 'Jabatan pemulihan', 'required' => false, 'max' => 64],
            'no_pulih' => ['label' => 'Nomor surat pemulihan', 'required' => false, 'max' => 32],
        ] as $field => $rule) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;

            if ($rule['required'] && $value === '') {
                $errors[] = $rule['label'].' wajib diisi.';
            } elseif ($value !== '' && mb_strlen($value) > $rule['max']) {
                $errors[] = $rule['label'].' maksimal '.$rule['max'].' karakter.';
            }
        }

        if (($data['tgl_sk'] ?? null) === null && $rawTanggalSk === '') {
            $errors[] = 'Tanggal SK wajib diisi.';
        }

        return [
            'row_number' => $rowNumber,
            'status' => $errors === [] ? 'Data baru' : 'Perlu diperiksa',
            'errors' => array_values(array_unique($errors)),
            'values' => $values,
            'data' => $data,
        ];
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

    private function importDuplicateKey(array $data): string
    {
        $idPeg = trim((string) ($data['id_peg'] ?? ''));
        $noSk = trim((string) ($data['no_sk'] ?? ''));
        $tglSk = trim((string) ($data['tgl_sk'] ?? ''));

        return $idPeg.'::'.$noSk.'::'.$tglSk;
    }

    private function existingPenalty(array $data): bool
    {
        if (($data['id_peg'] ?? '') === '' || ($data['no_sk'] ?? '') === '' || ($data['tgl_sk'] ?? '') === null) {
            return false;
        }

        return TbHukuman::query()
            ->where('id_peg', $data['id_peg'])
            ->where('no_sk', $data['no_sk'])
            ->whereDate('tgl_sk', $data['tgl_sk'])
            ->exists();
    }

    private function basePenaltyQuery(Request $request): Builder
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));

        return TbHukuman::query()
            ->from('tb_hukuman as h')
            ->join('tb_pegawai as p', 'p.id_peg', '=', 'h.id_peg')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->when($role === 'kepala', function (Builder $builder) use ($request): void {
                $builder->where('j.unit_kerja', $request->session()->get('kode_kantor'));
            });
    }

    private function filteredPenaltyQuery(Request $request, string $search): Builder
    {
        return $this->basePenaltyQuery($request)
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('p.nama', 'like', $like)
                        ->orWhere('h.id_peg', 'like', $like)
                        ->orWhere('h.hukuman', 'like', $like)
                        ->orWhere('h.keterangan', 'like', $like)
                        ->orWhere('h.no_sk', 'like', $like)
                        ->orWhere('k.nama_kantor', 'like', $like);
                });
            });
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('Pelanggaran/Form', [
            'mode' => 'create',
            'record' => null,
            'employees' => $this->employees(),
            'officials' => $this->officials(),
            'types' => self::TYPES,
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbHukuman::query()->findOrFail($id);
        $officials = $this->officials();

        if ($record->pejabat_sk && ! collect($officials)->contains('nama', $record->pejabat_sk)) {
            $officials[] = ['nama' => $record->pejabat_sk, 'jabatan' => $record->jabatan_sk];
        }

        return Inertia::render('Pelanggaran/Form', [
            'mode' => 'edit',
            'record' => $this->formRecord($record),
            'employees' => $this->employees(),
            'officials' => $officials,
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
                $data['dokumen'] = $storedFile = $this->storeDocument($request, $data['id_peg']) ?: '';
                $data['date_reg'] = now()->toDateString();
                TbHukuman::query()->create($data);
            });
        } catch (\Throwable $exception) {
            $this->deleteDocument($storedFile);
            throw $exception;
        }

        return to_route('pelanggaran.index')->with('success', 'Data pelanggaran berhasil disimpan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbHukuman::query()->findOrFail($id);
        $data = $this->validatedData($request);
        $oldFile = $record->dokumen;
        $storedFile = null;

        try {
            DB::transaction(function () use ($request, $record, $data, &$storedFile): void {
                $update = collect($data)->except('dokumen')->all();
                $update['date_update'] = now()->toDateString();

                if ($request->hasFile('dokumen')) {
                    $storedFile = $this->storeDocument($request, $data['id_peg']);
                    $update['dokumen'] = $storedFile;
                }

                $record->update($update);
            });
        } catch (\Throwable $exception) {
            $this->deleteDocument($storedFile);
            throw $exception;
        }

        if ($storedFile !== null && $oldFile !== $storedFile) {
            $this->deleteDocument($oldFile);
        }

        return to_route('pelanggaran.index')->with('success', 'Data pelanggaran berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbHukuman::query()->lockForUpdate()->findOrFail($id);

            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_hukuman',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);

            $record->delete();
        });

        return to_route('pelanggaran.index')->with('success', 'Data pelanggaran dipindahkan ke Recycle Bin.');
    }

    public function document(Request $request, int $id): BinaryFileResponse
    {
        $this->ensureStaffAccess($request);
        $record = TbHukuman::query()->findOrFail($id);
        $this->ensurePenaltyVisible($request, $record);

        $filename = basename((string) $record->dokumen);
        abort_if($filename === '' || $filename === '.', 404);

        $paths = [
            Storage::disk('public')->path('dokumen_hukuman/'.$filename),
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
        return $request->validate([
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')],
            'hukuman' => ['required', Rule::in(self::TYPES)],
            'keterangan' => ['nullable', 'string'],
            'pejabat_sk' => ['required', 'string', 'max:64'],
            'jabatan_sk' => ['required', 'string', 'max:64'],
            'no_sk' => ['required', 'string', 'max:32'],
            'tgl_sk' => ['required', 'date'],
            'dokumen' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'pejabat_pulih' => ['nullable', 'string', 'max:64'],
            'jabatan_pulih' => ['nullable', 'string', 'max:64'],
            'no_pulih' => ['nullable', 'string', 'max:32'],
            'tgl_pulih' => ['nullable', 'date'],
        ]);
    }

    private function employees(): array
    {
        return TbPegawai::query()
            ->orderBy('nama')
            ->get(['id_peg', 'nama'])
            ->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama])
            ->all();
    }

    private function officials(): array
    {
        return TbPegawai::query()
            ->from('tb_pegawai as p')
            ->join('tb_jabatan as j', 'j.id_peg', '=', 'p.id_peg')
            ->where('j.status_jab', 'Aktif')
            ->whereIn('j.jabatan', self::OFFICIAL_POSITIONS)
            ->select(['p.nama', 'j.jabatan'])
            ->distinct()
            ->orderBy('j.jabatan')
            ->orderBy('p.nama')
            ->get()
            ->map(fn ($official): array => ['nama' => $official->nama, 'jabatan' => $official->jabatan])
            ->all();
    }

    private function storeDocument(Request $request, string $idPeg): ?string
    {
        if (! $request->hasFile('dokumen')) {
            return null;
        }

        $extension = strtolower($request->file('dokumen')->getClientOriginalExtension() ?: 'bin');
        $filename = 'SK_HUKUM_'.$idPeg.'_'.now()->format('YmdHis').'_'.Str::lower(Str::random(6)).'.'.$extension;
        Storage::disk('public')->putFileAs('dokumen_hukuman', $request->file('dokumen'), $filename);

        return $filename;
    }

    private function deleteDocument(?string $filename): void
    {
        if ($filename) {
            Storage::disk('public')->delete('dokumen_hukuman/'.basename($filename));
        }
    }

    private function presentPenalty(TbHukuman $item): array
    {
        $hasDocument = $item->dokumen && $this->documentExists($item->dokumen);

        return [
            'id_hukum' => $item->id_hukum,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai,
            'hukuman' => $item->hukuman,
            'keterangan' => $item->keterangan,
            'tgl_sk' => $item->tgl_sk?->format('d-m-Y'),
            'nama_kantor' => $item->nama_kantor,
            'dokumen_url' => $hasDocument ? route('pelanggaran.document', $item->id_hukum) : null,
        ];
    }

    private function formRecord(TbHukuman $record): array
    {
        return collect($record->getAttributes())->only([
            'id_hukum', 'id_peg', 'hukuman', 'keterangan', 'pejabat_sk', 'jabatan_sk', 'no_sk', 'tgl_sk', 'dokumen',
            'pejabat_pulih', 'jabatan_pulih', 'no_pulih', 'tgl_pulih',
        ])->map(function ($value, string $key) {
            return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        })->all();
    }

    private function documentExists(string $filename): bool
    {
        $safeFilename = basename($filename);

        return is_file(Storage::disk('public')->path('dokumen_hukuman/'.$safeFilename));
    }

    private function ensurePenaltyVisible(Request $request, TbHukuman $record): void
    {
        if (strtolower((string) $request->session()->get('hak_akses')) !== 'kepala') {
            return;
        }

        abort_unless(TbJabatan::query()
            ->where('id_peg', $record->id_peg)
            ->where('status_jab', 'Aktif')
            ->where('unit_kerja', $request->session()->get('kode_kantor'))
            ->exists(), 403);
    }

    private function canManage(Request $request): bool
    {
        return strtolower((string) $request->session()->get('hak_akses')) === 'admin';
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    private function ensureStaffAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin', 'kepala'], true), 403);
    }
}
