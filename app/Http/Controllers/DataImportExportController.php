<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbAnak;
use App\Models\Legacy\TbBiayaPendidikan;
use App\Models\Legacy\TbDiklat;
use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbOrtu;
use App\Models\Legacy\TbPangkat;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbPendidikan;
use App\Models\Legacy\TbSertifikasi;
use App\Models\Legacy\TbSuamiIstri;
use App\Services\XlsxExportService;
use App\Services\XlsxImportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportExportController extends Controller
{
    private const FAMILY_MODELS = [
        'children' => [
            'label' => 'Data Anak',
            'model' => TbAnak::class,
            'table' => 'tb_anak',
            'key' => 'id_anak',
            'columns' => [
                'id_peg' => 'ID Pegawai', 'nik' => 'NIK Anak', 'nama' => 'Nama Anak', 'tmp_lhr' => 'Tempat Lahir',
                'tgl_lhr' => 'Tgl Lahir (dd-mm-yyyy)', 'pendidikan' => 'Pendidikan', 'id_pekerjaan' => 'ID Pekerjaan',
                'pekerjaan' => 'Pekerjaan', 'status_hub' => 'Status Hub (Kandung/Tiri)', 'anak_ke' => 'Anak Ke-', 'bpjs_anak' => 'No BPJS Anak',
            ],
        ],
        'parents' => [
            'label' => 'Data Orang Tua',
            'model' => TbOrtu::class,
            'table' => 'tb_ortu',
            'key' => 'id_ortu',
            'columns' => [
                'id_peg' => 'ID Pegawai', 'nama' => 'Nama Orang Tua', 'status_hub' => 'Status Hubungan',
            ],
        ],
        'spouses' => [
            'label' => 'Data Suami/Istri',
            'model' => TbSuamiIstri::class,
            'table' => 'tb_suamiistri',
            'key' => 'id_si',
            'columns' => [
                'id_peg' => 'ID Pegawai', 'nik' => 'NIK', 'nama' => 'Nama Pasangan', 'tmp_lhr' => 'Tempat Lahir',
                'tgl_lhr' => 'Tgl Lahir (dd-mm-yyyy)', 'pendidikan' => 'Pendidikan', 'id_pekerjaan' => 'ID Pekerjaan',
                'pekerjaan' => 'Pekerjaan', 'status_hub' => 'Status Hub (Suami/Istri)', 'hp' => 'No HP', 'bpjs_pasangan' => 'BPJS Pasangan',
            ],
        ],
    ];

    private const EDUCATION_COLUMNS = [
        'id_peg' => 'ID Pegawai (Wajib)', 'id_sekolah' => 'ID Sekolah (Opsional)', 'jenjang' => 'Jenjang',
        'nama_sekolah' => 'Nama Sekolah', 'lokasi' => 'Lokasi', 'jurusan' => 'Jurusan', 'no_ijazah' => 'No Ijazah',
        'tgl_ijazah' => 'Tgl Ijazah (dd-mm-yyyy)', 'kepala' => 'Kepala Sekolah', 'status' => 'Status', 'th_masuk' => 'Th Masuk', 'th_lulus' => 'Th Lulus',
    ];

    private const CERTIFICATION_COLUMNS = [
        'id_peg' => 'ID Pegawai', 'sertifikasi' => 'Nama Sertifikasi', 'penyelenggara' => 'Penyelenggara',
        'tgl_sertifikat' => 'Tgl Sertifikat (dd-mm-yyyy)', 'tgl_expired' => 'Tgl Expired (dd-mm-yyyy)', 'sertifikat' => 'No Sertifikat',
    ];

    private const FAMILY_TYPE_ALIASES = [
        'anak' => 'children',
        'orang-tua' => 'parents',
        'suami-istri' => 'spouses',
    ];

    public function __construct(private readonly XlsxImportService $xlsxImport) {}

    public function form(Request $request, string $resource, ?string $type = null): Response
    {
        [$resource, $type] = $this->routeResourceAndType($request, $resource, $type);
        $this->ensureAdmin($request);
        $type = $this->canonicalFamilyType($resource, $type);
        $config = $this->resourceConfig($resource, $type);

        return Inertia::render('Imports/XlsxImport', [
            'title' => $config['title'],
            'resource' => $resource,
            'type' => $type,
            'backUrl' => $config['backUrl'],
            'previewUrl' => $config['previewUrl'],
            'storeUrl' => $config['storeUrl'],
            'templateUrl' => $config['templateUrl'],
            'columns' => array_values($config['columns']),
            'preview' => null,
        ]);
    }

    public function preview(Request $request, string $resource, ?string $type = null): JsonResponse
    {
        [$resource, $type] = $this->routeResourceAndType($request, $resource, $type);
        $this->ensureAdmin($request);
        $type = $this->canonicalFamilyType($resource, $type);
        $config = $this->resourceConfig($resource, $type);
        $request->validate(['file_excel' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);
        $rows = $this->xlsxImport->rows($request->file('file_excel')->getRealPath());

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file_excel' => 'File import harus memiliki header dan minimal satu baris data.']);
        }

        $header = array_shift($rows);
        $indexes = $this->headerIndexes($header, $config['columns'], $resource, $type);
        $employees = $this->activeEmployeeIds();
        $prepared = [];

        foreach ($rows as $index => $row) {
            if (count(array_filter($row, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach (array_keys($config['columns']) as $field) {
                $data[$field] = trim((string) ($row[$indexes[$field]] ?? ''));
            }

            $data['id_peg'] = $this->resolveEmployeeId($data['id_peg'], $employees);
            $errors = $this->validateImportRow($resource, $type, $data, $employees);
            $existing = $this->findExisting($resource, $type, $data);
            $prepared[] = [
                'row_number' => $index + 2,
                'status' => $errors === [] ? ($existing ? 'Update data' : 'Data baru') : 'Perlu diperiksa',
                'errors' => $errors,
                'values' => array_values($data),
                'data' => $data,
            ];
        }

        $invalid = collect($prepared)->filter(fn (array $row): bool => $row['errors'] !== [])->count();

        return response()->json([
            'columns' => array_values($config['columns']),
            'rows' => $prepared,
            'preview' => array_slice($prepared, 0, 25),
            'total' => count($prepared),
            'invalid_count' => $invalid,
        ]);
    }

    public function template(Request $request, string $resource, XlsxExportService $xlsxExport, ?string $type = null): StreamedResponse
    {
        [$resource, $type] = $this->routeResourceAndType($request, $resource, $type);
        $this->ensureAdmin($request);
        $type = $this->canonicalFamilyType($resource, $type);
        $config = $this->resourceConfig($resource, $type);
        $template = $this->templateOptions($resource, $type);
        $content = $xlsxExport->makeTemplate(
            array_values($config['columns']),
            $config['title'],
            $template['examples'],
            $template['lists'],
            $template['validations'],
        );

        return $this->download($content, 'Template_Import_'.$this->safeFileName($config['title']).'.xlsx');
    }

    private function templateOptions(string $resource, ?string $type): array
    {
        $employeeIds = TbPegawai::query()
            ->where('status_aktif', 1)
            ->orderBy('id_peg')
            ->pluck('id_peg')
            ->map(fn ($id): string => (string) $id)
            ->all();

        if ($resource === 'family') {
            $statusOptions = match ($type) {
                'children' => ['Anak Kandung', 'Anak Tiri', 'Anak Angkat'],
                'parents' => ['Ayah Kandung', 'Ibu Kandung', 'Ayah Tiri', 'Ibu Tiri', 'Mertua L', 'Mertua P', 'Wali'],
                default => ['Suami', 'Istri'],
            };

            $examples = match ($type) {
                'children' => [['CONTOH-001', '1234567890123456', 'Contoh Anak', 'Semarang', '10-02-2015', 'SD', '', 'Pelajar', 'Anak Kandung', '1', '123456789012']],
                'parents' => [['CONTOH-001', 'Contoh Nama Orang Tua', 'Ayah Kandung']],
                default => [['CONTOH-001', '1234567890123456', 'Contoh Pasangan', 'Semarang', '20-05-1990', 'S1', '', 'Karyawan', 'Istri', '081234567890', '123456789012']],
            };

            $statusColumn = $type === 'parents' ? 'C' : ($type === 'children' ? 'I' : 'I');

            return [
                'examples' => $examples,
                'lists' => [
                    'id_peg' => ['title' => 'ID Pegawai aktif', 'values' => $employeeIds],
                    'status_hub' => ['title' => 'Status hubungan', 'values' => $statusOptions],
                ],
                'validations' => [
                    ['column' => 'A', 'list' => 'id_peg'],
                    ['column' => $statusColumn, 'list' => 'status_hub'],
                ],
            ];
        }

        if ($resource === 'education') {
            return [
                'examples' => [['CONTOH-001', '', 'S1', 'Universitas Contoh', 'Semarang', 'Manajemen', '123/CONTOH/2026', '10-02-2026', 'Contoh Rektor', 'Aktif', '2022', '2026']],
                'lists' => [
                    'id_peg' => ['title' => 'ID Pegawai aktif', 'values' => $employeeIds],
                    'jenjang' => ['title' => 'Jenjang', 'values' => ['SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3']],
                    'status' => ['title' => 'Status pendidikan', 'values' => ['Aktif', 'Non', 'Lulus', 'Belum Lulus']],
                ],
                'validations' => [
                    ['column' => 'A', 'list' => 'id_peg'],
                    ['column' => 'C', 'list' => 'jenjang'],
                    ['column' => 'J', 'list' => 'status'],
                ],
            ];
        }

        return [
            'examples' => [['CONTOH-001', 'Sertifikasi Contoh', 'Lembaga Contoh', '10-02-2026', '10-02-2029', 'CERT/CONTOH/2026']],
            'lists' => [
                'id_peg' => ['title' => 'ID Pegawai aktif', 'values' => $employeeIds],
            ],
            'validations' => [
                ['column' => 'A', 'list' => 'id_peg'],
            ],
        ];
    }

    public function store(Request $request, string $resource, ?string $type = null): RedirectResponse
    {
        [$resource, $type] = $this->routeResourceAndType($request, $resource, $type);
        $this->ensureAdmin($request);
        $type = $this->canonicalFamilyType($resource, $type);
        $config = $this->resourceConfig($resource, $type);
        $validated = $request->validate(['data_rows' => ['required', 'string', 'max:5000000']]);
        $rows = json_decode($validated['data_rows'], true);

        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages(['data_rows' => 'Data preview import tidak valid. Silakan unggah ulang file.']);
        }

        $employees = $this->activeEmployeeIds();
        $errors = [];
        foreach ($rows as $row) {
            $data = (array) ($row['data'] ?? []);
            $data['id_peg'] = $this->resolveEmployeeId($data['id_peg'] ?? '', $employees);
            $rowErrors = $this->validateImportRow($resource, $type, $data, $employees);
            if ($rowErrors !== []) {
                $errors[] = 'Baris '.((int) ($row['row_number'] ?? 0)).': '.implode('; ', $rowErrors);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['data_rows' => 'Import dibatalkan karena ada data tidak valid. '.implode(' ', array_slice($errors, 0, 5))]);
        }

        $summary = ['inserted' => 0, 'updated' => 0];
        $actor = $request->session()->get('id_user', $request->session()->get('nama_user', 'Admin'));

        DB::transaction(function () use ($resource, $type, $rows, $actor, $employees, &$summary): void {
            foreach ($rows as $row) {
                $data = (array) ($row['data'] ?? []);
                $data['id_peg'] = $this->resolveEmployeeId($data['id_peg'] ?? '', $employees);
                $record = $this->findExisting($resource, $type, $data);
                $payload = $this->payloadForImport($resource, $type, $data, $actor);

                if ($record) {
                    $record->fill($payload)->save();
                    $summary['updated']++;
                } else {
                    if ($resource === 'family' && $type === 'spouses') {
                        $payload['id_si'] = $this->nextSpouseId();
                    }

                    $this->modelFor($resource, $type)::query()->create($payload);
                    $summary['inserted']++;
                }
            }
        });

        if ($resource === 'family') {
            return to_route('family.index', ['type' => $this->localizedFamilyType($type)])->with('success', "Import {$config['label']} selesai. Data baru: {$summary['inserted']}, diperbarui: {$summary['updated']}.");
        }

        return to_route($config['backRoute'])->with('success', "Import {$config['label']} selesai. Data baru: {$summary['inserted']}, diperbarui: {$summary['updated']}.");
    }

    public function exportFamily(Request $request, string $type, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaff($request);
        $type = $this->canonicalFamilyType('family', $type);
        $urlType = $this->localizedFamilyType($type);
        $definition = self::FAMILY_MODELS[$type] ?? abort(404);
        $query = $definition['model']::query()->from($definition['table'].' as f')->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'f.id_peg');
        $this->applyEmployeeScope($query, $request, 'f.id_peg');

        if ($request->filled('uid')) {
            $query->where('f.id_peg', $request->string('uid')->trim());
        }

        if ($request->filled('search')) {
            $like = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('f.nama', 'like', $like)->orWhere('f.nik', 'like', $like)->orWhere('p.nama', 'like', $like);
            });
        }

        $columns = array_values($definition['columns']);
        $rows = [$columns];
        foreach ($query->select('f.*', 'p.nama as nama_pegawai')->orderBy('p.nama')->get() as $item) {
            $rows[] = $this->exportFamilyRow($item, $type);
        }

        return $this->download($xlsxExport->make($rows, $definition['label'], true), 'Export_'.$urlType.'_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportEducation(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaff($request);
        $query = TbPendidikan::query()->from('tb_pendidikan as edu')->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'edu.id_peg')->leftJoin('tb_jabatan as j', function ($join): void {
            $join->on('p.id_peg', '=', 'j.id_peg')->where('j.status_jab', 'Aktif');
        })->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail');
        $this->applyEmployeeScope($query, $request, 'edu.id_peg');
        $this->applyEducationFilters($query, $request);

        $rows = [array_merge(array_values(self::EDUCATION_COLUMNS), ['Kantor'])];
        foreach ($query->select('edu.*', 'p.nama as nama_pegawai', 'k.nama_kantor')->orderBy('p.nama')->orderByDesc('edu.th_lulus')->get() as $item) {
            $rows[] = [$item->id_peg, $item->nama_pegawai, $item->id_sekolah, $item->jenjang, $item->nama_sekolah, $item->lokasi, $item->jurusan, $item->no_ijazah, $this->dateText($item->tgl_ijazah), $item->kepala, $item->status, $item->th_masuk, $item->th_lulus, $item->nama_kantor];
        }

        return $this->download($xlsxExport->make($rows, 'Pendidikan', true), 'Export_Pendidikan_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportCertification(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaff($request);
        $query = TbSertifikasi::query()->from('tb_sertifikasi as s')->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 's.id_peg')->leftJoin('tb_jabatan as j', function ($join): void {
            $join->on('p.id_peg', '=', 'j.id_peg')->where('j.status_jab', 'Aktif');
        })->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail');
        $this->applyEmployeeScope($query, $request, 's.id_peg');
        $this->applyCertificationFilters($query, $request);

        $rows = [array_merge(array_values(self::CERTIFICATION_COLUMNS), ['Nama Pegawai', 'Kantor', 'Status'])];
        foreach ($query->select('s.*', 'p.nama as nama_pegawai', 'k.nama_kantor')->orderBy('p.nama')->orderByDesc('s.tgl_sertifikat')->get() as $item) {
            $status = $item->tgl_expired === null ? 'Seumur Hidup' : (Carbon::parse($item->tgl_expired)->isBefore(today()) ? 'Expired' : 'Aktif');
            $rows[] = [$item->id_peg, $item->sertifikasi, $item->penyelenggara, $this->dateText($item->tgl_sertifikat), $this->dateText($item->tgl_expired), $item->sertifikat, $item->nama_pegawai, $item->nama_kantor, $status];
        }

        return $this->download($xlsxExport->make($rows, 'Sertifikasi', true), 'Export_Sertifikasi_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportDiklat(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaff($request);
        $query = TbDiklat::query()->from('tb_diklat as d')->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'd.id_peg')->leftJoin('tb_biaya_pendidikan as m', 'm.biaya_id', '=', 'd.diklat_id')->leftJoin('tb_jabatan as j', function ($join): void {
            $join->on('p.id_peg', '=', 'j.id_peg')->where('j.status_jab', 'Aktif');
        })->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail');
        $this->applyEmployeeScope($query, $request, 'd.id_peg');

        if ($request->filled('tahun')) {
            $query->where('d.tahun', $request->string('tahun')->trim());
        }

        if ($request->filled('kantor')) {
            $query->where('j.unit_kerja', $request->string('kantor')->trim());
        }

        if ($request->filled('search')) {
            $like = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('p.nama', 'like', $like)->orWhere('d.id_peg', 'like', $like)->orWhere('d.diklat', 'like', $like)->orWhere('m.pengembangan_sdm', 'like', $like);
            });
        }

        $rows = [['ID Pegawai', 'Nama Pegawai', 'Nama Diklat', 'Penyelenggara', 'Tempat', 'Angkatan', 'Tahun', 'Tanggal Pelatihan', 'Biaya per Peserta', 'Master Kegiatan', 'Kantor']];
        foreach ($query->select('d.*', 'p.nama as nama_pegawai', 'm.pengembangan_sdm as master_name', 'm.pihak_pelaksana as master_party', 'k.nama_kantor')->orderBy('p.nama')->orderByDesc('d.tahun')->get() as $item) {
            $rows[] = [$item->id_peg, $item->nama_pegawai, $item->master_name ?: $item->diklat, $item->master_party ?: $item->penyelenggara, $item->tempat, $item->angkatan, $item->tahun, $this->dateText($item->date_reg), (int) ($item->biaya_per_peserta ?? $item->biaya ?? 0), $item->master_name ?: '-', $item->nama_kantor];
        }

        return $this->download($xlsxExport->make($rows, 'Diklat', true), 'Export_Diklat_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportBiaya(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaff($request);
        $stats = DB::table('tb_diklat')->whereNotNull('diklat_id')->select('diklat_id')->selectRaw('COUNT(*) as peserta')->selectRaw('COALESCE(SUM(COALESCE(biaya_per_peserta, biaya, 0)), 0) as realisasi')->groupBy('diklat_id');
        $query = TbBiayaPendidikan::query()->from('tb_biaya_pendidikan as b')->leftJoin('tb_ref_pengembangan as rp', 'b.kode_pengembangan', '=', 'rp.kode_sandi')->leftJoin('tb_ref_pelaksana as rpl', 'b.kode_pihak', '=', 'rpl.kode_pihak')->leftJoinSub($stats, 's', 's.diklat_id', '=', 'b.biaya_id');

        if ($request->filled('tahun')) {
            $query->whereYear('b.tgl_pengembangan_sdm', (int) $request->string('tahun')->trim());
        }
        if ($request->filled('kuartal') && in_array($request->string('kuartal')->trim()->value(), ['1', '2', '3', '4'], true)) {
            [$startMonth, $endMonth] = match ($request->string('kuartal')->trim()->value()) {
                '1' => [1, 3],
                '2' => [4, 6],
                '3' => [7, 9],
                default => [10, 12],
            };
            $query->whereBetween(DB::raw('MONTH(b.tgl_pengembangan_sdm)'), [$startMonth, $endMonth]);
        }
        if ($request->filled('search')) {
            $like = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('b.pengembangan_sdm', 'like', $like)->orWhere('b.pihak_pelaksana', 'like', $like)->orWhere('rp.kategori', 'like', $like)->orWhere('rpl.nama_pihak', 'like', $like);
            });
        }

        $rows = [['ID Kegiatan', 'Kategori', 'Nama Kegiatan', 'Penyelenggara', 'Tipe Pelaksana', 'Waktu / Durasi', 'Target Peserta', 'Total Biaya', 'Tanggal Mulai', 'Terdaftar', 'Realisasi Biaya']];
        foreach ($query->select('b.*', 'rp.kategori', 'rpl.nama_pihak as tipe_pelaksana', 's.peserta', 's.realisasi')->orderByDesc('b.tgl_pengembangan_sdm')->get() as $item) {
            $rows[] = [$item->biaya_id, $item->kategori, $item->pengembangan_sdm, $item->pihak_pelaksana, $item->tipe_pelaksana, $item->waktu_pelaksanaan, (int) $item->jumlah_sdm, (int) $item->total_biaya, $this->dateText($item->tgl_pengembangan_sdm), (int) ($item->peserta ?? 0), (int) ($item->realisasi ?? 0)];
        }

        return $this->download($xlsxExport->make($rows, 'Master Diklat', true), 'Export_Master_Diklat_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportEmployees(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaff($request);
        $rows = [['ID Pegawai', 'NIK', 'Nama Lengkap', 'Tempat Lahir', 'Tanggal Lahir', 'Jenis Kelamin', 'Status Kepegawaian', 'TMT Kerja', 'No Telp', 'Email', 'Jabatan', 'Unit Kerja', 'Kantor']];
        foreach ($this->employeeExportQuery($request)->get() as $item) {
            $rows[] = [$item->id_peg, $item->nik, $item->nama, $item->tempat_lhr, $this->dateText($item->tgl_lhr), $item->jk, $item->status_kepeg, $this->dateText($item->tmt_kerja), $item->telp, $item->email, $item->jabatan, $item->nama_unit_kerja ?: $item->unit_kerja, $item->nama_kantor];
        }

        return $this->download($xlsxExport->make($rows, 'Pegawai', true), 'Export_Pegawai_'.now()->format('Ymd_His').'.xlsx');
    }

    /**
     * Export the complete employee detail layout used by the HR report.
     *
     * This is deliberately a separate endpoint so the existing employee
     * export remains unchanged for current users and integrations.
     */
    public function exportEmployeesDetail(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaff($request);

        $employees = $this->employeeExportQuery($request)
            ->addSelect(
                'k.kode_cabang as kode_cabang',
                'mj.level as level_jabatan',
                'mj.group_jabatan as group_jabatan',
                'mj.nama_jabatan as nama_jabatan',
            )
            ->get();
        $employeeIds = $employees->pluck('id_peg')->filter()->values();

        $childrenCounts = $employeeIds->isEmpty()
            ? collect()
            : TbAnak::query()
                ->whereIn('id_peg', $employeeIds->all())
                ->select('id_peg')
                ->selectRaw('COUNT(*) as jumlah_anak')
                ->groupBy('id_peg')
                ->pluck('jumlah_anak', 'id_peg');

        $educations = $employeeIds->isEmpty()
            ? collect()
            : TbPendidikan::query()
                ->whereIn('id_peg', $employeeIds->all())
                ->select(['id_peg', 'jenjang', 'status', 'tgl_ijazah'])
                ->orderByDesc('tgl_ijazah')
                ->get()
                ->groupBy('id_peg')
                ->map(fn ($records) => $records->firstWhere('status', 'Akhir') ?? $records->first());

        $ranks = $employeeIds->isEmpty()
            ? collect()
            : TbPangkat::query()
                ->whereIn('id_peg', $employeeIds->all())
                ->select(['id_peg', 'tmt_pangkat', 'status_pan'])
                ->orderByDesc('tmt_pangkat')
                ->get()
                ->groupBy('id_peg')
                ->map(fn ($records) => $records->firstWhere('status_pan', 'Aktif') ?? $records->first());

        $rows = [[
            'Kode Cabang', 'ID Pegawai', 'Nama', 'Kantor Cabang', 'Unit Kerja', 'Jabatan',
            'Level Jabatan', 'Tanggal Masuk', 'Tanggal Capeg', 'Tanggal Pensiun', 'Status Pegawai',
            'Email', 'Tanggal Lahir', 'Usia', 'Tempat Lahir', 'Alamat', 'BPJS Ketenagakerjaan',
            'BPJS Kesehatan', 'NIK', 'No.Hp', 'Agama', 'Jenis Kelamin', 'Status pernikahan',
            'Golongan Darah', 'Masa Kerja', 'Jumlah Anak', 'Masa Kerja Golongan', 'Pendidikan Terakhir',
        ]];

        foreach ($employees as $employee) {
            $education = $educations->get($employee->id_peg);
            $rank = $ranks->get($employee->id_peg);
            $educationText = $education ? trim((string) $education->jenjang) : '';

            $rows[] = [
                $employee->kode_cabang,
                $employee->id_peg,
                $employee->nama,
                $employee->nama_kantor,
                $employee->nama_unit_kerja ?: $employee->unit_kerja,
                $employee->jabatan ?: $employee->nama_jabatan,
                $employee->level_jabatan ?: $employee->group_jabatan,
                $this->dateText($employee->tmt_kerja),
                '',
                $this->dateText($employee->tgl_pensiun),
                $employee->status_kepeg,
                $employee->email,
                $this->dateText($employee->tgl_lhr),
                $this->ageText($employee->tgl_lhr),
                $employee->tempat_lhr,
                $employee->alamat,
                $employee->bpjstk,
                $employee->bpjskes,
                $employee->nik,
                $employee->telp,
                $employee->agama,
                $employee->jk,
                $employee->status_nikah,
                $employee->gol_darah,
                $this->durationText($employee->tmt_kerja),
                (int) ($childrenCounts->get($employee->id_peg) ?? 0),
                $this->durationText($rank?->tmt_pangkat),
                $educationText,
            ];
        }

        return $this->download($xlsxExport->make($rows, 'Detail Pegawai', true), 'Export_Detail_Pegawai_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportEmployeesPdf(Request $request)
    {
        $this->ensureStaff($request);
        $employeeRows = $this->employeeExportQuery($request)->get()->map(function ($item): array {
            return [
                'id' => $item->id_peg,
                'name' => $item->nama,
                'ttl' => trim(implode(', ', array_filter([$item->tempat_lhr, $this->dateText($item->tgl_lhr)]))),
                'position' => $item->jabatan,
                'division' => $item->nama_unit_kerja ?: $item->unit_kerja,
                'office' => $item->nama_kantor ?: $item->unit_kerja,
                'status' => $item->status_kepeg,
                'start_date' => $this->dateText($item->tmt_kerja),
                'phone' => $item->telp,
            ];
        })->values()->all();

        $tabValue = $request->string('tab')->trim()->value();
        $tabLabel = match ($tabValue) {
            'nonjob' => 'Belum Ada Jabatan',
            'purna', 'retired' => 'Purna',
            default => 'Aktif',
        };

        return response()->view('reports.employees-print', [
            'employees' => $employeeRows,
            'total' => count($employeeRows),
            'filters' => [
                'tab' => $tabLabel,
                'search' => $request->string('search')->trim()->value(),
                'kantor' => $request->string('kantor')->trim()->value(),
                'divisi' => $request->string('divisi')->trim()->value(),
                'jabatan' => $request->string('jabatan')->trim()->value(),
                'year' => $request->string('year')->trim()->value(),
            ],
            'generatedAt' => now('Asia/Jakarta')->format('d-m-Y H:i'),
        ]);
    }

    private function employeeExportQuery(Request $request): Builder
    {
        $query = TbPegawai::query()->from('tb_pegawai as p')->leftJoin('tb_jabatan as j', function ($join): void {
            $join->on('p.id_peg', '=', 'j.id_peg')->where('j.status_jab', 'Aktif');
        })->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')->leftJoin('tb_master_jabatan as mj', 'j.kode_jabatan', '=', 'mj.kode_jabatan');
        $this->applyEmployeeScope($query, $request, 'p.id_peg');

        $tab = $request->string('tab')->trim()->value();
        if ($tab === 'retired' || $tab === 'purna') {
            $query->where('p.status_aktif', 0);
        } elseif ($tab === 'nonjob') {
            $query->whereNull('j.id_jab')->where('p.status_aktif', 1);
        } elseif ($tab === 'active' || $tab === '') {
            $query->where('p.status_aktif', 1);
        }
        if ($request->filled('search')) {
            $like = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('p.id_peg', 'like', $like)->orWhere('p.nama', 'like', $like);
            });
        }
        if ($request->filled('kantor')) {
            $query->where('j.unit_kerja', $request->string('kantor')->trim());
        }
        if ($request->filled('divisi') && $request->string('divisi')->trim()->value() !== '__all__') {
            $division = $request->string('divisi')->trim()->value();
            if (str_starts_with($division, 'unit:')) {
                $query->where('j.unit_kerja', trim(substr($division, strlen('unit:'))));
            } elseif (str_starts_with($division, 'division:')) {
                $query->whereRaw('TRIM(mj.nama_unit_kerja) = ?', [substr($division, strlen('division:'))]);
            } else {
                $query->whereRaw('TRIM(mj.nama_unit_kerja) = ?', [$division]);
            }
        }
        if ($request->filled('jabatan')) {
            $position = $request->string('jabatan')->trim()->value();
            $query->where(function (Builder $builder) use ($position): void {
                $builder->whereRaw('TRIM(mj.nama_jabatan) = ?', [$position])->orWhereRaw('TRIM(j.jabatan) = ?', [$position]);
            });
        }
        if ($request->filled('year')) {
            $query->whereYear('p.tmt_kerja', (int) $request->string('year')->trim());
        }

        return $query->select('p.*', 'j.jabatan', 'j.unit_kerja', 'k.nama_kantor', 'mj.nama_unit_kerja')->distinct()->orderBy('p.nama');
    }

    public function exportPositions(Request $request, XlsxExportService $xlsxExport): StreamedResponse
    {
        $this->ensureStaff($request);
        $query = TbJabatan::query()->from('tb_jabatan as j')->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'j.id_peg')->leftJoin('tb_kantor as k', 'k.kode_kantor_detail', '=', 'j.unit_kerja');
        $this->applyEmployeeScope($query, $request, 'j.id_peg');
        if ($request->filled('search')) {
            $like = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('p.nama', 'like', $like)->orWhere('j.id_peg', 'like', $like);
            });
        }
        if ($request->filled('unit')) {
            $query->where('j.unit_kerja', $request->string('unit')->trim());
        }
        if ($request->filled('jabatan')) {
            $query->where('j.jabatan', $request->string('jabatan')->trim());
        }

        $rows = [['ID Pegawai', 'Kode Jabatan', 'Nama Jabatan', 'Kode Unit Kerja', 'Nama Kantor', 'TMT Jabatan', 'Sampai Tanggal', 'No SK', 'Tgl SK', 'Status']];
        foreach ($query->select('j.*', 'p.nama', 'k.nama_kantor')->orderBy('p.nama')->orderBy('j.tmt_jabatan')->get() as $item) {
            $rows[] = [$item->id_peg, $item->kode_jabatan, $item->jabatan, $item->unit_kerja, $item->nama_kantor, $this->dateText($item->tmt_jabatan), $this->dateText($item->sampai_tgl), $item->no_sk, $this->dateText($item->tgl_sk), $item->status_jab];
        }

        return $this->download($xlsxExport->make($rows, 'Jabatan', true), 'Export_Jabatan_'.now()->format('Ymd_His').'.xlsx');
    }

    private function resourceConfig(string $resource, ?string $type): array
    {
        if ($resource === 'family') {
            $type = $this->canonicalFamilyType($resource, $type);
            abort_unless(isset(self::FAMILY_MODELS[$type]), 404);
            $label = self::FAMILY_MODELS[$type]['label'];
            $urlType = $this->localizedFamilyType($type);

            return ['title' => 'Import '.$label, 'label' => strtolower($label), 'columns' => self::FAMILY_MODELS[$type]['columns'], 'backUrl' => '/keluarga/'.$urlType, 'backRoute' => 'family.index', 'previewUrl' => '/keluarga/'.$urlType.'/import/preview', 'storeUrl' => '/keluarga/'.$urlType.'/import', 'templateUrl' => '/keluarga/'.$urlType.'/import/template'];
        }

        if ($resource === 'education') {
            return ['title' => 'Import Data Pendidikan', 'label' => 'data pendidikan', 'columns' => self::EDUCATION_COLUMNS, 'backUrl' => '/pendidikan', 'backRoute' => 'pendidikan.index', 'previewUrl' => '/pendidikan/import/preview', 'storeUrl' => '/pendidikan/import', 'templateUrl' => '/pendidikan/import/template'];
        }

        if ($resource === 'certification') {
            return ['title' => 'Import Data Sertifikasi', 'label' => 'data sertifikasi', 'columns' => self::CERTIFICATION_COLUMNS, 'backUrl' => '/sertifikasi', 'backRoute' => 'sertifikasi.index', 'previewUrl' => '/sertifikasi/import/preview', 'storeUrl' => '/sertifikasi/import', 'templateUrl' => '/sertifikasi/import/template'];
        }

        abort(404);
    }

    private function canonicalFamilyType(string $resource, ?string $type): ?string
    {
        if ($resource !== 'family' || $type === null) {
            return $type;
        }

        $type = strtolower(trim($type));

        return self::FAMILY_TYPE_ALIASES[$type] ?? $type;
    }

    /**
     * Route keluarga memiliki parameter {type}, sedangkan resource import
     * memakai default parameter "family". Ambil keduanya dari route agar
     * urutan argumen method tidak mengubah tujuan import.
     */
    private function routeResourceAndType(Request $request, string $resource, ?string $type): array
    {
        $route = $request->route();
        $routeResource = $route?->parameter('resource', $resource);
        $routeType = $route?->parameter('type', $type);

        return [
            (string) $routeResource,
            $routeType !== null ? (string) $routeType : null,
        ];
    }

    private function localizedFamilyType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $type = $this->canonicalFamilyType('family', $type);

        return array_search($type, self::FAMILY_TYPE_ALIASES, true) ?: $type;
    }

    private function headerIndexes(array $header, array $columns, string $resource, ?string $type): array
    {
        $normalized = [];
        foreach ($header as $index => $value) {
            $normalized[$this->xlsxImport->normalizeHeader($value)] = $index;
        }

        $aliases = $this->aliases($resource, $type);
        $indexes = [];
        $missing = [];
        foreach (array_keys($columns) as $field) {
            $found = null;
            foreach ($aliases[$field] ?? [$field] as $alias) {
                $key = $this->xlsxImport->normalizeHeader($alias);
                if (array_key_exists($key, $normalized)) {
                    $found = $normalized[$key];
                    break;
                }
            }
            if ($found === null) {
                $missing[] = $columns[$field];
            } else {
                $indexes[$field] = $found;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages(['file_excel' => 'Header XLSX tidak lengkap. Kolom yang belum ditemukan: '.implode(', ', $missing).'. Unduh template resmi untuk format yang benar.']);
        }

        return $indexes;
    }

    private function aliases(string $resource, ?string $type): array
    {
        $common = ['id_peg' => ['ID Pegawai', 'ID Pegawai (Wajib)', 'NIK', 'NIK Pegawai'], 'nama' => ['Nama', 'Nama Anak', 'Nama Orang Tua', 'Nama Pasangan'], 'tmp_lhr' => ['Tempat Lahir'], 'tgl_lhr' => ['Tgl Lahir (dd-mm-yyyy)', 'Tgl Lahir (DD-MM-YYYY)', 'Tgl Lahir'], 'pendidikan' => ['Pendidikan'], 'id_pekerjaan' => ['ID Pekerjaan'], 'pekerjaan' => ['Pekerjaan'], 'status_hub' => ['Status Hubungan', 'Status Hub (Suami/Istri)', 'Status Hub (Kandung/Tiri)'], 'nik' => ['NIK', 'NIK Anak'], 'hp' => ['No HP', 'Nomor HP']];

        if ($resource === 'family') {
            $common['bpjs_anak'] = ['No BPJS Anak', 'BPJS Anak'];
            $common['bpjs_pasangan'] = ['BPJS Pasangan', 'No BPJS Pasangan'];
            $common['anak_ke'] = ['Anak Ke-', 'Anak Ke'];

            return $common;
        }

        if ($resource === 'education') {
            return ['id_peg' => ['ID Pegawai (Wajib)', 'ID Pegawai'], 'id_sekolah' => ['ID Sekolah (Opsional)', 'ID Sekolah'], 'jenjang' => ['Jenjang'], 'nama_sekolah' => ['Nama Sekolah'], 'lokasi' => ['Lokasi'], 'jurusan' => ['Jurusan'], 'no_ijazah' => ['No Ijazah'], 'tgl_ijazah' => ['Tgl Ijazah (dd-mm-yyyy)', 'Tgl Ijazah'], 'kepala' => ['Kepala Sekolah'], 'status' => ['Status'], 'th_masuk' => ['Th Masuk', 'Tahun Masuk'], 'th_lulus' => ['Th Lulus', 'Tahun Lulus']];
        }

        return ['id_peg' => ['ID Pegawai (Wajib)', 'ID Pegawai'], 'sertifikasi' => ['Nama Sertifikasi', 'Sertifikasi'], 'penyelenggara' => ['Penyelenggara'], 'tgl_sertifikat' => ['Tgl Sertifikat (dd-mm-yyyy)', 'Tgl Sertifikat'], 'tgl_expired' => ['Tgl Expired (dd-mm-yyyy)', 'Tgl Expired'], 'sertifikat' => ['No Sertifikat', 'Nomor Sertifikat']];
    }

    private function validateImportRow(string $resource, ?string $type, array $data, array $employees): array
    {
        $errors = [];
        $id = trim((string) ($data['id_peg'] ?? ''));
        if ($id === '' || ! isset($employees[$id])) {
            $errors[] = 'ID pegawai tidak ditemukan atau tidak aktif.';
        }

        if ($resource === 'family') {
            $this->validateFamilyRow($type, $data, $errors);
        } elseif ($resource === 'education') {
            if (! in_array(strtoupper(trim((string) ($data['jenjang'] ?? ''))), ['SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'], true)) {
                $errors[] = 'Jenjang pendidikan tidak sesuai pilihan sistem.';
            }
            if (trim((string) ($data['nama_sekolah'] ?? '')) === '') {
                $errors[] = 'Nama sekolah wajib diisi.';
            }
            $this->lengthErrors($data, ['nama_sekolah' => 64, 'lokasi' => 32, 'jurusan' => 32, 'no_ijazah' => 32, 'kepala' => 64, 'status' => 5], $errors);
            $this->validateDateField($data['tgl_ijazah'] ?? '', 'Tanggal ijazah', $errors);
            $this->validateYearField($data['th_masuk'] ?? '', 'Tahun masuk', $errors);
            $this->validateYearField($data['th_lulus'] ?? '', 'Tahun lulus', $errors);
        } else {
            if (trim((string) ($data['sertifikasi'] ?? '')) === '') {
                $errors[] = 'Nama sertifikasi wajib diisi.';
            }
            $this->lengthErrors($data, ['sertifikasi' => 255, 'penyelenggara' => 255, 'sertifikat' => 255], $errors);
            $this->validateDateField($data['tgl_sertifikat'] ?? '', 'Tanggal sertifikat', $errors);
            $this->validateDateField($data['tgl_expired'] ?? '', 'Tanggal expired', $errors);
            $issued = $this->xlsxImport->date($data['tgl_sertifikat'] ?? '');
            $expired = $this->xlsxImport->date($data['tgl_expired'] ?? '');
            if ($issued && $expired && $expired < $issued) {
                $errors[] = 'Tanggal expired tidak boleh sebelum tanggal sertifikat.';
            }
        }

        return $errors;
    }

    private function validateFamilyRow(?string $type, array $data, array &$errors): void
    {
        if (trim((string) ($data['nama'] ?? '')) === '') {
            $errors[] = 'Nama wajib diisi.';
        }
        $this->lengthErrors($data, ['nama' => 255, 'tmp_lhr' => 64, 'pendidikan' => 26, 'pekerjaan' => 64, 'status_hub' => 16], $errors);
        $this->digitsField($data['nik'] ?? '', 'NIK', 16, $errors);
        $this->validateDateField($data['tgl_lhr'] ?? '', 'Tanggal lahir', $errors);

        if ($type === 'children') {
            $this->digitsField($data['bpjs_anak'] ?? '', 'BPJS Anak', 32, $errors);
            $this->lengthErrors($data, ['anak_ke' => 3], $errors);
        }
        if ($type === 'spouses') {
            if (trim((string) ($data['tmp_lhr'] ?? '')) === '') {
                $errors[] = 'Tempat lahir pasangan wajib diisi.';
            }
            if ($this->xlsxImport->date($data['tgl_lhr'] ?? '') === null) {
                $errors[] = 'Tanggal lahir pasangan wajib diisi dan valid.';
            }
            if (trim((string) ($data['status_hub'] ?? '')) === '') {
                $errors[] = 'Status hubungan wajib diisi.';
            }
            $this->digitsField($data['hp'] ?? '', 'Nomor HP', 13, $errors);
            $this->digitsField($data['bpjs_pasangan'] ?? '', 'BPJS Pasangan', 20, $errors);
        } elseif (trim((string) ($data['status_hub'] ?? '')) === '') {
            $errors[] = 'Status hubungan wajib diisi.';
        }
    }

    private function payloadForImport(string $resource, ?string $type, array $data, mixed $actor): array
    {
        if ($resource === 'family') {
            $fields = array_keys(self::FAMILY_MODELS[$type]['columns']);
            $payload = [];
            foreach ($fields as $field) {
                if ($field === 'id_pekerjaan' && ! Schema::hasColumn(self::FAMILY_MODELS[$type]['table'], $field)) {
                    continue;
                }
                $payload[$field] = $data[$field] ?? null;
            }
            $payload['tgl_lhr'] = $this->xlsxImport->date($data['tgl_lhr'] ?? '');
            $payload['date_reg'] = now()->toDateString();
            $payload['created_by'] = $actor;
            if ($type === 'spouses' && Schema::hasColumn(self::FAMILY_MODELS[$type]['table'], 'id_pekerjaan')) {
                $payload['id_pekerjaan'] = '';
            }

            return $payload;
        }

        if ($resource === 'education') {
            return [...$data, 'jenjang' => strtoupper($data['jenjang']), 'tgl_ijazah' => $this->xlsxImport->date($data['tgl_ijazah']), 'date_reg' => now()->toDateString(), 'created_by' => $actor];
        }

        return [...$data, 'tgl_sertifikat' => $this->xlsxImport->date($data['tgl_sertifikat']), 'tgl_expired' => $this->xlsxImport->date($data['tgl_expired']), 'date_reg' => now(), 'created_by' => $actor];
    }

    private function modelFor(string $resource, ?string $type): string
    {
        return $resource === 'family' ? self::FAMILY_MODELS[$type]['model'] : ($resource === 'education' ? TbPendidikan::class : TbSertifikasi::class);
    }

    private function findExisting(string $resource, ?string $type, array $data): ?Model
    {
        $model = $this->modelFor($resource, $type);
        $query = $model::query()->where('id_peg', $data['id_peg']);

        if ($resource === 'family') {
            $query->when(trim((string) ($data['nik'] ?? '')) !== '', fn (Builder $builder) => $builder->where('nik', trim((string) $data['nik'])), fn (Builder $builder) => $builder->where('nama', trim((string) $data['nama']))->where('status_hub', trim((string) ($data['status_hub'] ?? ''))));
        } elseif ($resource === 'education') {
            $query->where('jenjang', strtoupper(trim((string) $data['jenjang'])))->where('nama_sekolah', trim((string) $data['nama_sekolah']))->where('th_lulus', trim((string) ($data['th_lulus'] ?? '')));
        } else {
            $query->where('sertifikasi', trim((string) $data['sertifikasi']))->when($this->xlsxImport->date($data['tgl_sertifikat'] ?? '') !== null, fn (Builder $builder) => $builder->whereDate('tgl_sertifikat', $this->xlsxImport->date($data['tgl_sertifikat'] ?? '')), fn (Builder $builder) => $builder->whereNull('tgl_sertifikat'));
        }

        return $query->first();
    }

    private function activeEmployeeIds(): array
    {
        $employees = [];

        TbPegawai::query()
            ->where('status_aktif', 1)
            ->get(['id_peg', 'nik'])
            ->each(function (TbPegawai $employee) use (&$employees): void {
                $id = (string) $employee->id_peg;
                $employees[$id] = $id;

                if (trim((string) $employee->nik) !== '') {
                    $employees[trim((string) $employee->nik)] = $id;
                }
            });

        return $employees;
    }

    private function resolveEmployeeId(mixed $value, array $employees): string
    {
        $value = trim((string) $value);

        return (string) ($employees[$value] ?? $value);
    }

    private function nextSpouseId(): string
    {
        $max = TbSuamiIstri::query()->selectRaw('MAX(CAST(SUBSTRING(id_si, 3) AS UNSIGNED)) AS max_id')->lockForUpdate()->value('max_id');

        return 'SI'.str_pad((string) (((int) $max) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function exportFamilyRow(Model $item, string $type): array
    {
        if ($type === 'parents') {
            return [$item->id_peg, $item->nama, $item->status_hub];
        }

        $row = [$item->id_peg, $item->nik, $item->nama, $item->tmp_lhr, $this->dateText($item->tgl_lhr), $item->pendidikan, $item->id_pekerjaan, $item->pekerjaan, $item->status_hub];
        if ($type === 'children') {
            $row[] = $item->anak_ke;
        }
        if ($type === 'children') {
            $row[] = $item->bpjs_anak;
        }
        if ($type === 'spouses') {
            $row[] = $item->hp;
            $row[] = $item->bpjs_pasangan;
        }

        return $row;
    }

    private function applyEmployeeScope(Builder $query, Request $request, string $column): void
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        if ($role === 'user') {
            $query->where($column, $request->session()->get('id_pegawai'));
        }
        if ($role === 'kepala') {
            $query->whereExists(function ($scope) use ($column, $request): void {
                $scope->selectRaw('1')->from('tb_jabatan as scope_j')->whereColumn('scope_j.id_peg', $column)->where('scope_j.status_jab', 'Aktif')->where('scope_j.unit_kerja', $request->session()->get('kode_kantor'));
            });
        }
    }

    private function applyEducationFilters(Builder $query, Request $request): void
    {
        if ($request->filled('tahun')) {
            $query->where('edu.th_lulus', $request->string('tahun')->trim());
        }
        if ($request->filled('jenjang')) {
            $query->where('edu.jenjang', $request->string('jenjang')->trim());
        }
        if ($request->filled('kantor')) {
            $query->where('j.unit_kerja', $request->string('kantor')->trim());
        }
        if ($request->filled('search')) {
            $like = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('p.nama', 'like', $like)->orWhere('edu.id_peg', 'like', $like)->orWhere('edu.nama_sekolah', 'like', $like)->orWhere('edu.jurusan', 'like', $like);
            });
        }
    }

    private function applyCertificationFilters(Builder $query, Request $request): void
    {
        if ($request->filled('tahun')) {
            $query->whereYear('s.tgl_sertifikat', (int) $request->string('tahun')->trim());
        }
        if ($request->filled('sertifikasi')) {
            $query->where('s.sertifikasi', $request->string('sertifikasi')->trim());
        }
        if ($request->filled('kantor')) {
            $query->where('j.unit_kerja', $request->string('kantor')->trim());
        }
        if ($request->filled('search')) {
            $like = '%'.$request->string('search')->trim().'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('p.nama', 'like', $like)->orWhere('s.sertifikasi', 'like', $like)->orWhere('s.penyelenggara', 'like', $like)->orWhere('s.sertifikat', 'like', $like);
            });
        }
    }

    private function lengthErrors(array $data, array $limits, array &$errors): void
    {
        foreach ($limits as $field => $limit) {
            if (mb_strlen(trim((string) ($data[$field] ?? ''))) > $limit) {
                $errors[] = ucfirst(str_replace('_', ' ', $field))." maksimal {$limit} karakter.";
            }
        }
    }

    private function digitsField(mixed $value, string $label, int $max, array &$errors): void
    {
        $value = trim((string) $value);
        if ($value !== '' && (! preg_match('/^\d+$/', $value) || strlen($value) > $max)) {
            $errors[] = "{$label} hanya boleh berisi angka dan maksimal {$max} digit.";
        }
    }

    private function validateDateField(mixed $value, string $label, array &$errors): void
    {
        if (trim((string) $value) !== '' && $this->xlsxImport->date($value) === null) {
            $errors[] = "{$label} tidak valid.";
        }
    }

    private function validateYearField(mixed $value, string $label, array &$errors): void
    {
        $value = trim((string) $value);
        if ($value !== '' && ! preg_match('/^\d{4}$/', $value)) {
            $errors[] = "{$label} harus 4 digit.";
        }
    }

    private function dateText(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d-m-Y');
        }
        if (trim((string) $value) === '') {
            return '';
        }
        try {
            return Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function ageText(mixed $value): string
    {
        if (trim((string) $value) === '') {
            return '';
        }

        try {
            return (string) Carbon::parse($value)->age;
        } catch (\Throwable) {
            return '';
        }
    }

    private function durationText(mixed $value): string
    {
        if (trim((string) $value) === '') {
            return '';
        }

        try {
            $from = Carbon::parse($value);
            $today = now('Asia/Jakarta');
            if ($from->greaterThan($today)) {
                return '';
            }

            $difference = $from->diff($today);
            $parts = [];

            if ($difference->y > 0) {
                $parts[] = $difference->y.' tahun';
            }
            if ($difference->m > 0) {
                $parts[] = $difference->m.' bulan';
            }
            if ($difference->d > 0) {
                $parts[] = $difference->d.' hari';
            }

            return $parts !== [] ? implode(' ', $parts) : '0 hari';
        } catch (\Throwable) {
            return '';
        }
    }

    private function download(string $content, string $name): StreamedResponse
    {
        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function safeFileName(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value)) ?? 'Template';

        return trim($value, '._-') ?: 'Template';
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(strtolower((string) $request->session()->get('hak_akses')) === 'admin', 403);
    }

    private function ensureStaff(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin', 'kepala', 'user'], true), 403);
    }
}
