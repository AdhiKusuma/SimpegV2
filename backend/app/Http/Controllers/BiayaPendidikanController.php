<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbBiayaPendidikan;
use App\Models\Legacy\TbRecycleBin;
use App\Services\XlsxExportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BiayaPendidikanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $year = $request->string('tahun')->trim()->value();
        $quarter = $request->string('kuartal')->trim()->value();
        $search = $request->string('search')->trim()->value();

        $query = TbBiayaPendidikan::query()
            ->from('tb_biaya_pendidikan as b')
            ->leftJoin('tb_ref_pengembangan as rp', 'b.kode_pengembangan', '=', 'rp.kode_sandi')
            ->leftJoin('tb_ref_pelaksana as rpl', 'b.kode_pihak', '=', 'rpl.kode_pihak')
            ->when(preg_match('/^\d{4}$/', $year) === 1, fn (Builder $builder) => $builder->whereRaw('YEAR(b.tgl_pengembangan_sdm) = ?', [$year]))
            ->when(in_array($quarter, ['1', '2', '3', '4'], true), function (Builder $builder) use ($quarter): void {
                [$startMonth, $endMonth] = match ($quarter) {
                    '1' => [1, 3],
                    '2' => [4, 6],
                    '3' => [7, 9],
                    default => [10, 12],
                };

                $builder->whereBetween(DB::raw('MONTH(b.tgl_pengembangan_sdm)'), [$startMonth, $endMonth]);
            })
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('b.pengembangan_sdm', 'like', $like)
                        ->orWhere('b.pihak_pelaksana', 'like', $like)
                        ->orWhere('rp.kategori', 'like', $like)
                        ->orWhere('rpl.nama_pihak', 'like', $like);
                });
            });

        $items = $query
            ->select('b.*', 'rp.kategori as nama_kategori', 'rpl.nama_pihak as jenis_pihak')
            ->orderByDesc('b.tgl_pengembangan_sdm')
            ->orderByDesc('b.created_at')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbBiayaPendidikan $item): array => $this->presentRecord($item));

        return Inertia::render('BiayaPendidikan/Index', [
            'items' => $items,
            'years' => $this->years(),
            'filters' => [
                'tahun' => $year,
                'kuartal' => in_array($quarter, ['1', '2', '3', '4'], true) ? $quarter : '',
                'search' => $search,
            ],
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('BiayaPendidikan/Form', [
            'mode' => 'create',
            'record' => null,
            ...$this->referenceData(),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->ensureCanManage($request);
        $record = TbBiayaPendidikan::query()->findOrFail($id);

        return Inertia::render('BiayaPendidikan/Form', [
            'mode' => 'edit',
            'record' => $this->formRecord($record),
            ...$this->referenceData(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $data = $this->validatedData($request);
        $actor = $this->actor($request);

        TbBiayaPendidikan::query()->create([
            ...$data,
            'created' => now()->toDateString(),
            'created_by' => $actor,
        ]);

        return to_route('biaya-pendidikan.index')->with('success', 'Data kegiatan diklat berhasil disimpan.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $record = TbBiayaPendidikan::query()->findOrFail($id);
        $record->update([
            ...$this->validatedData($request),
            'updated_at' => now(),
            'updated_by' => $this->actor($request),
        ]);

        return to_route('biaya-pendidikan.index')->with('success', 'Data kegiatan diklat berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbBiayaPendidikan::query()->lockForUpdate()->findOrFail($id);

            TbRecycleBin::query()->create([
                'tabel_by' => 'tb_biaya_pendidikan',
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'Admin'),
                'created' => now(),
            ]);

            $record->delete();
        });

        return to_route('biaya-pendidikan.index')->with('success', 'Data dipindahkan ke Recycle Bin.');
    }

    public function importForm(Request $request): Response
    {
        $this->ensureCanManage($request);

        return Inertia::render('BiayaPendidikan/Import', [
            'preview' => null,
            'rawRows' => null,
        ]);
    }

    public function previewImport(Request $request): Response
    {
        $this->ensureCanManage($request);
        $file = $this->validatedImportFile($request);
        $rows = $this->readImportRows($file->getRealPath(), strtolower($file->getClientOriginalExtension()));

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file_excel' => 'File import harus memiliki header dan minimal satu baris data.']);
        }

        array_shift($rows);

        return Inertia::render('BiayaPendidikan/Import', [
            'preview' => array_map(fn (array $row): array => $this->previewRow($row), array_slice($rows, 0, 15)),
            'rawRows' => $rows,
        ]);
    }

    public function importRows(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $validated = $request->validate(['data_rows' => ['required', 'string', 'max:5000000']]);
        $rows = json_decode($validated['data_rows'], true);

        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages(['data_rows' => 'Data preview import tidak valid. Silakan unggah ulang file.']);
        }

        $summary = ['inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $actor = $this->actor($request);

        DB::transaction(function () use (&$summary, $rows, $actor): void {
            foreach ($rows as $row) {
                $this->saveImportRow($row, $actor, $summary);
            }
        });

        return to_route('biaya-pendidikan.index')->with(
            'success',
            "Import kegiatan diklat selesai. Data baru: {$summary['inserted']}, diperbarui: {$summary['updated']}, dilewati: {$summary['skipped']}."
        );
    }

    public function downloadTemplate(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureCanManage($request);

        $content = $xlsxExport->make([
            [
                'Kode Kategori',
                'Kode Pihak',
                'Judul Pengembangan SDM',
                'Waktu Pelaksanaan',
                'Nama Pihak',
                'Jumlah SDM',
                'Total Biaya',
                'Tgl Mulai (YYYY-MM-DD)',
            ],
        ], 'Template Biaya Pendidikan');

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'Template_Import_Biaya_Pendidikan.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function participants(Request $request, int $id): JsonResponse
    {
        $this->ensureStaffAccess($request);
        $record = TbBiayaPendidikan::query()->findOrFail($id);
        $page = max($request->integer('page', 1), 1);
        $perPage = 3;

        $query = DB::table('tb_diklat as d')
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'd.id_peg')
            ->where('d.diklat', $record->pengembangan_sdm)
            ->where('d.penyelenggara', $record->pihak_pelaksana)
            ->where('d.date_reg', $record->tgl_pengembangan_sdm);

        $total = (clone $query)->count();
        $participants = $query
            ->select('p.nama', 'p.id_peg', 'd.biaya')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn ($item, $index): array => [
                'no' => (($page - 1) * $perPage) + $index + 1,
                'nama' => $item->nama ?: '-',
                'id_peg' => $item->id_peg ?: '-',
                'biaya' => (int) ($item->biaya ?? 0),
            ])
            ->all();

        return response()->json([
            'data' => $participants,
            'summary' => [
                'total_orang' => $total,
                'total_biaya' => (int) ((clone $query)->sum('d.biaya')),
            ],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => max((int) ceil($total / $perPage), 1),
                'total_data' => $total,
            ],
        ]);
    }

    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'kode_pengembangan' => ['required', 'string', 'max:10', Rule::exists('tb_ref_pengembangan', 'kode_sandi')],
            'kode_pihak' => ['nullable', 'string', 'max:5', Rule::exists('tb_ref_pelaksana', 'kode_pihak')],
            'pengembangan_sdm' => ['required', 'string', 'max:255'],
            'pihak_pelaksana' => ['nullable', 'string', 'max:255'],
            'waktu_pelaksanaan' => ['nullable', 'string', 'max:100'],
            'jumlah_sdm' => ['required', 'integer', 'min:1'],
            'total_biaya' => ['nullable', 'string', 'max:32'],
            'tgl_pengembangan_sdm' => ['required', 'date'],
        ]);

        $validated['total_biaya'] = ($validated['total_biaya'] ?? '') === ''
            ? 0
            : (int) preg_replace('/[^0-9]/', '', $validated['total_biaya']);

        return $validated;
    }

    private function validatedImportFile(Request $request): mixed
    {
        $request->validate(['file_excel' => ['required', 'file', 'max:5120']]);
        $file = $request->file('file_excel');
        $extension = strtolower((string) $file?->getClientOriginalExtension());

        if ($extension !== 'xlsx') {
            throw ValidationException::withMessages(['file_excel' => 'Gunakan file XLSX dari template resmi.']);
        }

        return $file;
    }

    private function readImportRows(string $path, string $extension): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw ValidationException::withMessages(['file_excel' => 'Server belum mengaktifkan pembaca XLSX.']);
        }

        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file_excel' => 'File XLSX tidak dapat dibuka.']);
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedXml !== false) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared instanceof \SimpleXMLElement) {
                $shared->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            }

            foreach ($shared instanceof \SimpleXMLElement ? ($shared->xpath('//x:si') ?: []) : [] as $item) {
                $item->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $value = '';
                foreach ($item->xpath('.//x:t') ?: [] as $text) {
                    $value .= (string) $text;
                }
                $sharedStrings[] = $value;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw ValidationException::withMessages(['file_excel' => 'Sheet pertama XLSX tidak ditemukan.']);
        }

        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet instanceof \SimpleXMLElement) {
            throw ValidationException::withMessages(['file_excel' => 'Isi XLSX tidak dapat dibaca.']);
        }

        $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];

        foreach ($sheet->xpath('//x:sheetData/x:row') ?: [] as $rowNode) {
            $rowNode->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $row = [];

            foreach ($rowNode->xpath('./x:c') ?: [] as $cell) {
                $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $reference = (string) $cell['r'];
                preg_match('/^([A-Z]+)/', $reference, $matches);
                $column = $this->columnIndex($matches[1] ?? 'A');
                $type = (string) $cell['t'];
                $value = '';

                if ($type === 'inlineStr') {
                    foreach ($cell->xpath('.//x:t') ?: [] as $text) {
                        $value .= (string) $text;
                    }
                } else {
                    $value = (string) ($cell->v ?? '');
                    if ($type === 's' && $value !== '') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    }
                }

                $row[$column] = trim($value);
            }

            if ($row !== []) {
                ksort($row);
                $lastColumn = max(array_keys($row));
                $rows[] = array_map(fn (int $index): string => (string) ($row[$index] ?? ''), range(0, $lastColumn));
            }
        }

        return $rows;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return max($index - 1, 0);
    }

    private function previewRow(array $row): array
    {
        $date = $this->parseImportDate(trim((string) ($row[7] ?? '')));
        $title = trim((string) ($row[2] ?? ''));
        $party = trim((string) ($row[4] ?? ''));
        $duplicate = $title !== '' && $date !== '' && TbBiayaPendidikan::query()
            ->where('pengembangan_sdm', $title)
            ->where('pihak_pelaksana', $party)
            ->where('tgl_pengembangan_sdm', $date)
            ->exists();

        return [
            'status' => $duplicate ? 'Update data' : 'Data baru',
            'kode_kategori' => trim((string) ($row[0] ?? '')),
            'kode_pihak' => trim((string) ($row[1] ?? '')),
            'judul' => $title,
            'waktu' => trim((string) ($row[3] ?? '')),
            'pihak' => $party,
            'jumlah_sdm' => trim((string) ($row[5] ?? '')),
            'total_biaya' => (int) preg_replace('/[^0-9]/', '', (string) ($row[6] ?? '0')),
            'tanggal' => $date,
        ];
    }

    private function saveImportRow(array $row, string $actor, array &$summary): void
    {
        $title = trim((string) ($row[2] ?? ''));
        $date = $this->parseImportDate(trim((string) ($row[7] ?? '')));

        if ($title === '' || $date === '') {
            $summary['skipped']++;

            return;
        }

        $party = trim((string) ($row[4] ?? ''));
        $payload = [
            'kode_pengembangan' => $this->lookupCode('tb_ref_pengembangan', 'kode_sandi', 'kategori', $row[0] ?? null),
            'kode_pihak' => $this->lookupCode('tb_ref_pelaksana', 'kode_pihak', 'nama_pihak', $row[1] ?? null),
            'waktu_pelaksanaan' => $this->limitImportValue($row[3] ?? null, 100),
            'pengembangan_sdm' => mb_substr($title, 0, 255),
            'pihak_pelaksana' => $party === '' ? null : mb_substr($party, 0, 255),
            'jumlah_sdm' => max((int) preg_replace('/[^0-9]/', '', (string) ($row[5] ?? '0')), 0),
            'total_biaya' => (int) preg_replace('/[^0-9]/', '', (string) ($row[6] ?? '0')),
            'tgl_pengembangan_sdm' => $date,
        ];

        if ($payload['jumlah_sdm'] < 1) {
            $summary['skipped']++;

            return;
        }

        $record = TbBiayaPendidikan::query()
            ->where('pengembangan_sdm', $title)
            ->where('pihak_pelaksana', $party)
            ->where('tgl_pengembangan_sdm', $date)
            ->first();

        if ($record) {
            $record->update([...$payload, 'updated_at' => now(), 'updated_by' => $actor]);
            $summary['updated']++;

            return;
        }

        TbBiayaPendidikan::query()->create([...$payload, 'created' => now()->toDateString(), 'created_by' => $actor]);
        $summary['inserted']++;
    }

    private function lookupCode(string $table, string $codeColumn, string $nameColumn, mixed $input): ?string
    {
        $value = trim((string) $input);

        if ($value === '') {
            return null;
        }

        $code = DB::table($table)->where($codeColumn, $value)->value($codeColumn);

        if ($code !== null) {
            return (string) $code;
        }

        $code = DB::table($table)->where($nameColumn, 'like', "%{$value}%")->value($codeColumn);

        return $code === null ? null : (string) $code;
    }

    private function limitImportValue(mixed $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }

    private function parseImportDate(string $value): string
    {
        if ($value === '' || $value === '-') {
            return '';
        }

        if (is_numeric($value) && (int) $value > 1000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    private function presentRecord(TbBiayaPendidikan $item): array
    {
        $date = $item->tgl_pengembangan_sdm;

        return [
            'biaya_id' => $item->biaya_id,
            'kategori' => $item->nama_kategori ?: ($item->kode_pengembangan ?: '-'),
            'jenis_pihak' => $item->jenis_pihak ?: '-',
            'pengembangan_sdm' => $item->pengembangan_sdm ?: '-',
            'pihak_pelaksana' => $item->pihak_pelaksana ?: '-',
            'waktu_pelaksanaan' => $item->waktu_pelaksanaan ?: '-',
            'tanggal' => $date ? $date->format('d-m-Y') : '-',
            'tanggal_raw' => $date?->format('Y-m-d'),
            'jumlah_sdm' => (int) ($item->jumlah_sdm ?? 0),
            'total_biaya' => (int) ($item->total_biaya ?? 0),
        ];
    }

    private function formRecord(TbBiayaPendidikan $record): array
    {
        return [
            'biaya_id' => $record->biaya_id,
            'kode_pengembangan' => $record->kode_pengembangan,
            'kode_pihak' => $record->kode_pihak,
            'pengembangan_sdm' => $record->pengembangan_sdm,
            'pihak_pelaksana' => $record->pihak_pelaksana,
            'waktu_pelaksanaan' => $record->waktu_pelaksanaan,
            'jumlah_sdm' => $record->jumlah_sdm,
            'total_biaya' => $record->total_biaya,
            'tgl_pengembangan_sdm' => $record->tgl_pengembangan_sdm?->format('Y-m-d'),
        ];
    }

    private function referenceData(): array
    {
        return [
            'categories' => DB::table('tb_ref_pengembangan')
                ->orderBy('kode_sandi')
                ->get(['kode_sandi', 'kategori'])
                ->map(fn ($row): array => ['code' => $row->kode_sandi, 'name' => $row->kategori])
                ->all(),
            'parties' => DB::table('tb_ref_pelaksana')
                ->orderBy('kode_pihak')
                ->get(['kode_pihak', 'nama_pihak'])
                ->map(fn ($row): array => ['code' => $row->kode_pihak, 'name' => $row->nama_pihak])
                ->all(),
        ];
    }

    private function years(): array
    {
        return TbBiayaPendidikan::query()
            ->whereNotNull('tgl_pengembangan_sdm')
            ->selectRaw('DISTINCT YEAR(tgl_pengembangan_sdm) as tahun')
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->map(fn ($value): string => (string) $value)
            ->values()
            ->all();
    }

    private function actor(Request $request): string
    {
        return (string) $request->session()->get('id_user', $request->session()->get('nama_user', 'Admin'));
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
