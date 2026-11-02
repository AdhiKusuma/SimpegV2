<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbBahasa;
use App\Models\Legacy\TbCuti;
use App\Models\Legacy\TbDp3;
use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbLatJabatan;
use App\Models\Legacy\TbMutasi;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbPenghargaan;
use App\Models\Legacy\TbPenugasan;
use App\Models\Legacy\TbSeminar;
use App\Models\Legacy\TbUser;
use App\Support\SimpegFieldRules;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Shuchkin\SimpleXLSX;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeController extends Controller
{
    private const IMPORT_HEADERS = [
        'ID Pegawai (Wajib)',
        'NIP',
        'Nama Lengkap',
        'Tempat Lahir',
        'Tgl Lahir (DD-MM-YY)',
        'Agama',
        'Jenis Kelamin',
        'Gol Darah',
        'Status Nikah',
        'Status Kepegawaian',
        'Alamat',
        'No Telp',
        'Email',
        'Nama File Foto',
        'TMT Kerja (DD-MM-YY)',
        'Tgl Pensiun (DD-MM-YY - Opsional)',
        'No BPJS TK',
        'No BPJS Kes',
    ];

    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $tab = $request->string('tab')->trim()->value() ?: 'active';
        $search = $request->string('search')->trim()->value();
        $office = $request->string('kantor')->trim()->value();
        $requestedYear = $request->string('year')->trim()->value();
        $year = preg_match('/^\d{4}$/', $requestedYear) === 1 ? $requestedYear : '';
        $division = $request->string('divisi')->trim()->value();
        $position = $request->string('jabatan')->trim()->value();
        $unit = $role === 'kepala' ? $request->session()->get('kode_kantor') : null;

        $query = $this->baseQuery($tab);

        if ($unit) {
            $query->where('j.unit_kerja', $unit);
        }

        if ($office) {
            $selectedOffice = TbKantor::query()->find($office);

            if ($selectedOffice?->level === 'KC') {
                $query->where('k.kode_cabang', $selectedOffice->kode_cabang);
            } else {
                $query->where('j.unit_kerja', $office);
            }
        }

        if ($division) {
            $query->where(is_numeric($division) ? 'j.unit_kerja' : 'm.nama_unit_kerja', $division);
        }

        if ($position) {
            $query->where('j.jabatan', $position);
        }

        $yearsQuery = clone $query;
        $years = $yearsQuery
            ->whereNotNull('p.tmt_kerja')
            ->selectRaw('YEAR(p.tmt_kerja) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value): string => (string) $value)
            ->values();

        if ($year !== '') {
            $query->whereYear('p.tmt_kerja', $year);
        }

        if ($search) {
            $query->where(function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where('p.id_peg', 'like', $like)
                    ->orWhere('p.nik', 'like', $like)
                    ->orWhere('p.nama', 'like', $like)
                    ->orWhere('j.jabatan', 'like', $like)
                    ->orWhere('k.nama_kantor', 'like', $like);
            });
        }

        $employees = $query
            ->select([
                'p.id_peg',
                'p.nama',
                'p.tempat_lhr',
                'p.tgl_lhr',
                'p.tmt_kerja',
                'p.telp',
                'p.foto',
                'p.jk',
                'p.status_kepeg',
                'j.jabatan',
                'j.unit_kerja',
                'k.nama_kantor',
                'm.nama_unit_kerja as divisi',
                'mut.jns_mutasi',
                'mut.tgl_mutasi',
            ])
            ->distinct()
            ->orderBy('p.nama')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbPegawai $employee): array => $this->presentEmployee($employee));

        $offices = TbKantor::query()
            ->whereIn('level', ['KP', 'KANWIL', 'KC'])
            ->when($unit, fn (Builder $builder) => $builder->where('kode_kantor_detail', $unit))
            ->orderBy('kode_kantor_detail')
            ->get(['kode_kantor_detail', 'nama_kantor', 'level', 'kode_cabang']);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'offices' => $offices,
            'years' => $years,
            'filters' => [
                'tab' => $tab,
                'search' => $search,
                'kantor' => $office,
                'year' => $year,
                'divisi' => $division,
                'jabatan' => $position,
            ],
            'canManage' => $role === 'admin',
            'role' => $role,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->ensureStaffAccess($request);

        $query = TbPegawai::query()->where('id_peg', $id)->with([
            'jabatan' => fn ($relation) => $relation->orderBy('tmt_jabatan'),
            'pangkat' => fn ($relation) => $relation->orderBy('tgl_sk'),
            'penghargaan' => fn ($relation) => $relation->orderByDesc('tahun'),
            'penugasan' => fn ($relation) => $relation->orderByDesc('tahun'),
            'seminar' => fn ($relation) => $relation->orderByDesc('tgl_mulai'),
            'cuti' => fn ($relation) => $relation->orderByDesc('tgl_mulai'),
            'bahasa' => fn ($relation) => $relation->orderBy('bahasa'),
            'dp3' => fn ($relation) => $relation->orderByDesc('periode_akhir'),
            'latihanJabatan' => fn ($relation) => $relation->orderByDesc('tahun_lat'),
        ]);

        if (strtolower((string) $request->session()->get('hak_akses')) === 'kepala') {
            $query->whereHas('jabatan', function (Builder $builder) use ($request): void {
                $builder->where('status_jab', 'Aktif')
                    ->where('unit_kerja', $request->session()->get('kode_kantor'));
            });
        }

        $employee = $query->firstOrFail();

        return Inertia::render('Employees/Show', [
            'employee' => [
                'id_peg' => $employee->id_peg,
                'nama' => $employee->nama,
                'nik' => $employee->nik,
                'tempat_lhr' => $employee->tempat_lhr,
                'tgl_lhr' => $employee->tgl_lhr?->format('d-m-Y'),
                'jk' => $employee->jk,
                'status_kepeg' => $employee->status_kepeg,
                'alamat' => $employee->alamat,
                'telp' => $employee->telp,
                'email' => $employee->email,
                'tmt_kerja' => $employee->tmt_kerja?->format('d-m-Y'),
                'jabatan' => $employee->jabatan->map(fn ($job): array => [
                    'jabatan' => $job->jabatan,
                    'unit_kerja' => $job->unit_kerja,
                    'tmt_jabatan' => $job->tmt_jabatan?->format('d-m-Y'),
                    'sampai_tgl' => $job->sampai_tgl?->format('d-m-Y'),
                    'status_jab' => $job->status_jab,
                    'no_sk' => $job->no_sk,
                    'tgl_sk' => $job->tgl_sk?->format('d-m-Y'),
                ])->values(),
                'pangkat' => $employee->pangkat->map(fn ($rank): array => [
                    'id_pangkat' => $rank->id_pangkat,
                    'pangkat' => $rank->pangkat,
                    'gol' => $rank->gol,
                    'jns_pangkat' => $rank->jns_pangkat,
                    'pejabat_sk' => $rank->pejabat_sk,
                    'no_sk' => $rank->no_sk,
                    'tgl_sk' => $rank->tgl_sk?->format('d-m-Y'),
                    'tmt_pangkat' => $rank->tmt_pangkat?->format('d-m-Y'),
                    'status_pan' => $rank->status_pan,
                ])->values(),
                'penghargaan' => $employee->penghargaan->map(fn (TbPenghargaan $award): array => [
                    'id_penghargaan' => $award->id_penghargaan,
                    'penghargaan' => $award->penghargaan,
                    'tahun' => $award->tahun,
                    'pemberi' => $award->pemberi,
                ])->values(),
                'penugasan' => $employee->penugasan->map(fn (TbPenugasan $assignment): array => [
                    'id_penugasan' => $assignment->id_penugasan,
                    'tujuan' => $assignment->tujuan,
                    'tahun' => $assignment->tahun,
                    'lama' => $assignment->lama,
                    'alasan' => $assignment->alasan,
                ])->values(),
                'seminar' => $employee->seminar->map(fn (TbSeminar $seminar): array => [
                    'id_seminar' => $seminar->id_seminar,
                    'seminar' => $seminar->seminar,
                    'tempat' => $seminar->tempat,
                    'penyelenggara' => $seminar->penyelenggara,
                    'tgl_mulai' => $seminar->tgl_mulai?->format('d-m-Y'),
                    'tgl_selesai' => $seminar->tgl_selesai?->format('d-m-Y'),
                    'no_piagam' => $seminar->no_piagam,
                ])->values(),
                'cuti' => $employee->cuti->map(fn (TbCuti $leave): array => [
                    'id_cuti' => $leave->id_cuti,
                    'jns_cuti' => $leave->jns_cuti,
                    'no_suratcuti' => $leave->no_suratcuti,
                    'tgl_suratcuti' => $leave->tgl_suratcuti?->format('d-m-Y'),
                    'tgl_mulai' => $leave->tgl_mulai?->format('d-m-Y'),
                    'tgl_selesai' => $leave->tgl_selesai?->format('d-m-Y'),
                    'ket' => $leave->ket,
                ])->values(),
                'bahasa' => $employee->bahasa->map(fn (TbBahasa $language): array => [
                    'id_bhs' => $language->id_bhs,
                    'jns_bhs' => $language->jns_bhs,
                    'bahasa' => $language->bahasa,
                    'kemampuan' => $language->kemampuan,
                ])->values(),
                'dp3' => $employee->dp3->map(fn (TbDp3 $record): array => [
                    'id_dp3' => $record->id_dp3,
                    'periode_akhir' => $record->periode_akhir?->format('d-m-Y'),
                    'hasil_penilaian' => $record->hasil_penilaian,
                    'nilai_total' => collect(['nilai_kesetiaan', 'nilai_prestasi', 'nilai_tgjwb', 'nilai_ketaatan', 'nilai_kejujuran', 'nilai_kerjasama', 'nilai_prakarsa', 'nilai_kepemimpinan'])->sum(fn (string $score): int => (int) $record->{$score}),
                ])->values(),
                'latihan_jabatan' => $employee->latihanJabatan->map(fn (TbLatJabatan $record): array => [
                    'id_lat_jabatan' => $record->id_lat_jabatan,
                    'nama_pelatih' => $record->nama_pelatih,
                    'tahun_lat' => $record->tahun_lat,
                    'jml_jam' => $record->jml_jam,
                ])->values(),
            ],
            'canManage' => strtolower((string) $request->session()->get('hak_akses')) === 'admin',
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('Employees/Form', [
            'mode' => 'create',
            'record' => $this->emptyFormRecord(),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureAdmin($request);
        $employee = TbPegawai::query()->findOrFail($id);

        return Inertia::render('Employees/Form', [
            'mode' => 'edit',
            'record' => $this->formRecord($employee),
        ]);
    }

    public function importForm(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('Employees/Import');
    }

    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        $this->ensureAdmin($request);
        $path = public_path('templates/Template_Import_Pegawai_V2.xlsx');

        abort_unless(is_file($path), 404, 'Template import pegawai tidak ditemukan.');

        return response()->download($path, 'Template_Import_Pegawai_V2.xlsx');
    }

    public function previewImport(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $request->validate([
            'file_xlsx' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        $file = $request->file('file_xlsx');
        $xlsx = SimpleXLSX::parse($file->getRealPath());
        if ($xlsx === false) {
            throw ValidationException::withMessages(['file_xlsx' => 'File XLSX tidak dapat dibaca. '.SimpleXLSX::parseError()]);
        }

        $rawRows = $xlsx->rows();
        $header = array_map(fn ($value): string => trim((string) $value), array_shift($rawRows) ?: []);
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        $normalisedHeader = array_map(fn (string $value): string => $this->normaliseImportHeader($value), array_slice($header, 0, count(self::IMPORT_HEADERS)));
        $expectedHeader = array_map(fn (string $value): string => $this->normaliseImportHeader($value), self::IMPORT_HEADERS);
        if ($normalisedHeader !== $expectedHeader) {
            throw ValidationException::withMessages(['file_xlsx' => 'Header XLSX tidak sesuai Template_Import_Pegawai_V2.xlsx. Unduh template resmi lalu isi tanpa mengubah nama kolom.']);
        }

        $records = TbPegawai::query()->get(['id_peg', 'nama', 'nik']);
        $byId = $records->keyBy('id_peg');
        $byNameNik = $records->keyBy(fn (TbPegawai $employee): string => $this->importMatchKey($employee->nama, $employee->nik));
        $rows = [];

        foreach ($rawRows as $rowIndex => $row) {
            $values = array_map(fn ($value): string => trim((string) $value), array_slice(array_pad($row, 18, ''), 0, 18));
            if (count(array_filter($values, fn (string $value): bool => $value !== '')) === 0) {
                continue;
            }

            $id = $values[0];
            $match = $byId->get($id);
            $status = $match ? 'update' : ($byNameNik->has($this->importMatchKey($values[2], $values[1])) ? 'rename' : 'new');
            $errors = $this->importFieldErrors($values);
            $rows[] = [
                'values' => $values,
                'status' => $errors === [] ? $status : 'invalid',
                'errors' => $errors,
                'row_number' => $rowIndex + 2,
                'existing_id' => $match?->id_peg ?: ($status === 'rename' ? $byNameNik->get($this->importMatchKey($values[2], $values[1]))?->id_peg : null),
            ];
        }

        $invalidRows = array_values(array_map(
            fn (array $row): array => ['row_number' => $row['row_number'], 'errors' => $row['errors']],
            array_filter($rows, fn (array $row): bool => $row['status'] === 'invalid'),
        ));

        return response()->json([
            'columns' => $header,
            'rows' => $rows,
            'preview' => array_slice($rows, 0, 15),
            'total' => count($rows),
            'invalid_count' => count($invalidRows),
            'invalid_rows' => $invalidRows,
        ]);
    }

    public function importXlsx(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $validated = $request->validate(['rows' => ['required', 'string', 'max:20480']]);
        try {
            $rows = json_decode($validated['rows'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['rows' => 'Data preview import tidak valid.']);
        }

        if (! is_array($rows)) {
            throw ValidationException::withMessages(['rows' => 'Data preview import tidak valid.']);
        }

        $invalidRows = [];
        foreach ($rows as $index => $item) {
            if (! is_array($item) || ! is_array($item['values'] ?? null)) {
                throw ValidationException::withMessages(['rows' => 'Data preview import tidak valid. Silakan unggah ulang file XLSX.']);
            }

            $values = array_map(fn ($value): string => trim((string) $value), array_slice(array_pad($item['values'], 18, ''), 0, 18));
            $errors = $this->importFieldErrors($values);
            if ($errors !== []) {
                $invalidRows[] = (($item['row_number'] ?? $index + 2).': '.implode(', ', $errors));
            }
        }

        if ($invalidRows !== []) {
            throw ValidationException::withMessages([
                'rows' => 'Perbaiki data angka pada baris XLSX berikut sebelum mengimpor: '.implode('; ', array_slice($invalidRows, 0, 8)).(count($invalidRows) > 8 ? '; dan '.(count($invalidRows) - 8).' baris lainnya.' : '.'),
            ]);
        }

        $summary = ['inserted' => 0, 'updated' => 0, 'renamed' => 0, 'skipped' => 0];
        $actor = $request->session()->get('id_user', $request->session()->get('nama_user', 'Admin'));

        DB::transaction(function () use (&$summary, $rows, $actor): void {
            foreach ($rows as $item) {
                $values = array_map(fn ($value): string => trim((string) $value), array_slice(array_pad($item['values'] ?? [], 18, ''), 0, 18));
                $id = $values[0] ?? '';
                if ($id === '' || ($values[2] ?? '') === '') {
                    $summary['skipped']++;

                    continue;
                }

                $payload = $this->importPayload($values);
                $record = TbPegawai::query()->where('id_peg', $id)->first();
                if ($record) {
                    $record->update([...$payload, 'updated_at' => now(), 'updated_by' => $actor]);
                    $summary['updated']++;

                    continue;
                }

                $matched = TbPegawai::query()
                    ->where('nama', $payload['nama'])
                    ->where('nik', $payload['nik'])
                    ->first();
                if ($matched) {
                    $oldId = $matched->id_peg;
                    $matched->id_peg = $id;
                    $matched->fill($payload);
                    $matched->updated_at = now();
                    $matched->updated_by = $actor;
                    $matched->save();
                    TbUser::query()->where('id_pegawai', $oldId)->update(['id_user' => $id, 'id_pegawai' => $id]);
                    $summary['renamed']++;

                    continue;
                }

                TbPegawai::query()->create(['id_peg' => $id, ...$payload, 'pegawai_uid' => (string) Str::uuid(), 'status_aktif' => '1', 'date_reg' => now()->toDateString(), 'created_by' => $actor]);
                $summary['inserted']++;
            }
        });

        return to_route('employees.index')->with('success', "Import pegawai selesai. Baru: {$summary['inserted']}, diperbarui: {$summary['updated']}, ganti ID: {$summary['renamed']}, dilewati: {$summary['skipped']}.");
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $data = $this->validatedData($request, false);
        $data['pegawai_uid'] = (string) Str::uuid();
        $data['status_aktif'] = '1';
        $data['date_reg'] = now()->toDateString();
        $data['created_by'] = $request->session()->get('id_user', 'System');
        $this->attachPhoto($request, $data);
        TbPegawai::query()->create($data);

        return to_route('employees.index')->with('success', 'Data pegawai berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        $employee = TbPegawai::query()->findOrFail($id);
        $data = $this->validatedData($request, true);
        $this->attachPhoto($request, $data, $employee->foto);
        $data['updated_at'] = now();
        $data['updated_by'] = $request->session()->get('id_user', 'System');
        $employee->update($data);

        return to_route('employees.show', $employee->id_peg)->with('success', 'Data pegawai berhasil diperbarui.');
    }

    public function photo(string $filename): BinaryFileResponse
    {
        abort_unless($filename === basename($filename), 404);

        $paths = [
            public_path('uploads/pegawai/'.$filename),
            base_path('../pages/assets/foto/'.$filename),
        ];

        foreach ($paths as $path) {
            if (File::isFile($path)) {
                return response()->file($path);
            }
        }

        abort(404);
    }

    private function baseQuery(string $tab): Builder
    {
        $query = TbPegawai::query()
            ->from('tb_pegawai as p')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')
                    ->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->leftJoin('tb_master_jabatan as m', 'j.jabatan', '=', 'm.nama_jabatan');

        if ($tab === 'purna') {
            $latestMutation = TbMutasi::query()
                ->selectRaw('id_peg, MAX(tgl_mutasi) as tgl_mutasi')
                ->whereIn('jns_mutasi', ['Pensiun', 'Pensiun Dini', 'Meninggal Dunia', 'Pengunduran Diri', 'PTDH'])
                ->groupBy('id_peg');

            $query->joinSub($latestMutation, 'latest_mut', 'p.id_peg', '=', 'latest_mut.id_peg')
                ->join('tb_mutasi as mut', function ($join): void {
                    $join->on('mut.id_peg', '=', 'latest_mut.id_peg')
                        ->on('mut.tgl_mutasi', '=', 'latest_mut.tgl_mutasi');
                });
        } else {
            $query->leftJoin('tb_mutasi as mut', function ($join): void {
                $join->on('mut.id_peg', '=', 'p.id_peg')
                    ->whereRaw('1 = 0');
            });
        }

        if ($tab === 'nonjob') {
            return $query->where('p.status_aktif', 1)->whereNull('j.id_jab');
        }

        if ($tab === 'purna') {
            return $query;
        }

        return $query->where('p.status_aktif', 1)->whereNotNull('j.id_jab');
    }

    private function presentEmployee(TbPegawai $employee): array
    {
        return [
            'id_peg' => $employee->id_peg,
            'nama' => $employee->nama,
            'ttl' => trim(implode(', ', array_filter([$employee->tempat_lhr, $this->formatDate($employee->tgl_lhr)]))),
            'jabatan' => $employee->jabatan,
            'kantor' => $employee->nama_kantor,
            'divisi' => $employee->divisi,
            'tmt_kerja' => $this->formatDate($employee->tmt_kerja),
            'telp' => $employee->telp,
            'status_kepeg' => $employee->status_kepeg,
            'status_mutasi' => $employee->jns_mutasi,
            'tgl_mutasi' => $this->formatDate($employee->tgl_mutasi),
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d-m-Y');
        }

        try {
            return Carbon::parse((string) $value)->format('d-m-Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private function emptyFormRecord(): array
    {
        return [
            'id_peg' => '',
            'nik' => '',
            'nama' => '',
            'tempat_lhr' => '',
            'tgl_lhr' => '',
            'agama' => '',
            'jk' => '',
            'gol_darah' => '-',
            'status_nikah' => '',
            'status_kepeg' => '',
            'alamat' => '',
            'telp' => '',
            'email' => '',
            'bpjstk' => '',
            'bpjskes' => '',
            'foto' => null,
            'foto_url' => null,
        ];
    }

    private function formRecord(TbPegawai $employee): array
    {
        return [
            'id_peg' => $employee->id_peg,
            'nik' => $employee->nik,
            'nama' => $employee->nama,
            'tempat_lhr' => $employee->tempat_lhr,
            'tgl_lhr' => $employee->tgl_lhr?->format('Y-m-d'),
            'agama' => $employee->agama,
            'jk' => $employee->jk,
            'gol_darah' => $employee->gol_darah,
            'status_nikah' => $employee->status_nikah,
            'status_kepeg' => $employee->status_kepeg,
            'alamat' => $employee->alamat,
            'telp' => $employee->telp,
            'email' => $employee->email,
            'bpjstk' => $employee->bpjstk,
            'bpjskes' => $employee->bpjskes,
            'foto' => $employee->foto,
            'foto_url' => $employee->foto ? route('employees.photo', ['filename' => $employee->foto]) : null,
        ];
    }

    private function importPayload(array $values): array
    {
        $birthDate = $this->parseImportDate($values[4] ?? '');
        $retirementDate = $this->parseImportDate($values[15] ?? '') ?: ($birthDate ? Carbon::parse($birthDate)->addYears(56)->toDateString() : null);

        return [
            'nik' => $this->importedDigits($values[1] ?? '', 16),
            'nama' => mb_substr($values[2] ?? '', 0, 64),
            'tempat_lhr' => mb_substr($values[3] ?? '', 0, 64),
            'tgl_lhr' => $birthDate,
            'agama' => mb_substr($values[5] ?? '', 0, 16),
            'jk' => mb_substr($values[6] ?? '', 0, 12),
            'gol_darah' => mb_substr($values[7] ?? '', 0, 2),
            'status_nikah' => mb_substr($values[8] ?? '', 0, 18),
            'status_kepeg' => mb_substr($values[9] ?? '', 0, 24),
            'alamat' => mb_substr($values[10] ?? '', 0, 255),
            'telp' => $this->importedDigits($values[11] ?? '', 13),
            'email' => mb_substr($values[12] ?? '', 0, 64),
            'foto' => mb_substr($values[13] ?? '', 0, 255),
            'tmt_kerja' => $this->parseImportDate($values[14] ?? ''),
            'tgl_pensiun' => $retirementDate,
            'bpjstk' => $this->importedDigits($values[16] ?? '', 12),
            'bpjskes' => $this->importedDigits($values[17] ?? '', 12),
        ];
    }

    private function importedDigits(string $value, int $maxLength): string
    {
        $value = trim($value);

        return $value === '-' ? '' : mb_substr($value, 0, $maxLength);
    }

    private function importMatchKey(?string $name, ?string $nik): string
    {
        return mb_strtolower(trim((string) $name)).'|'.preg_replace('/[^0-9]/', '', (string) $nik);
    }

    private function importFieldErrors(array $values): array
    {
        $errors = [];
        foreach ([
            1 => ['NIK', 16],
            11 => ['No. telepon / WA', 13],
            16 => ['No. BPJS Ketenagakerjaan', 12],
            17 => ['No. BPJS Kesehatan', 12],
        ] as $index => [$label, $maxLength]) {
            $value = trim((string) ($values[$index] ?? ''));
            if ($value === '' || $value === '-') {
                continue;
            }
            if (preg_match('/\A[0-9]+\z/', $value) !== 1) {
                $errors[] = "{$label} hanya boleh berisi angka";
            } elseif (mb_strlen($value) > $maxLength) {
                $errors[] = "{$label} maksimal {$maxLength} digit";
            }
        }

        return $errors;
    }

    private function normaliseImportHeader(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim($value)));
    }

    private function parseImportDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return null;
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
            return null;
        }
    }

    private function validatedData(Request $request, bool $isEdit): array
    {
        $rules = [
            'id_peg' => [$isEdit ? 'nullable' : 'required', 'string', 'max:12', $isEdit ? '' : 'unique:tb_pegawai,id_peg'],
            'nik' => ['required', 'string', 'max:16', SimpegFieldRules::DIGITS_ONLY],
            'nama' => ['required', 'string', 'max:64'],
            'tempat_lhr' => ['required', 'string', 'max:64'],
            'tgl_lhr' => ['required', 'date'],
            'agama' => ['required', 'string', 'max:16'],
            'jk' => ['required', 'string', 'max:12'],
            'gol_darah' => ['nullable', 'string', 'max:2'],
            'status_nikah' => ['nullable', 'string', 'max:18'],
            'status_kepeg' => ['required', 'string', 'max:24'],
            'alamat' => ['required', 'string', 'max:255'],
            'telp' => ['required', 'string', 'max:13', SimpegFieldRules::DIGITS_ONLY],
            'email' => ['required', 'email', 'max:64'],
            'bpjstk' => ['nullable', 'string', 'max:12', SimpegFieldRules::DIGITS_ONLY],
            'bpjskes' => ['nullable', 'string', 'max:12', SimpegFieldRules::DIGITS_ONLY],
            'foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
        ];
        $rules['id_peg'] = array_values(array_filter($rules['id_peg']));

        $data = $request->validate($rules, [
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'telp.regex' => 'Nomor telepon hanya boleh berisi angka.',
            'bpjstk.regex' => 'Nomor BPJS Ketenagakerjaan hanya boleh berisi angka.',
            'bpjskes.regex' => 'Nomor BPJS Kesehatan hanya boleh berisi angka.',
        ]);
        unset($data['foto']);
        if ($isEdit) {
            unset($data['id_peg']);
        }

        return $data;
    }

    private function attachPhoto(Request $request, array &$data, ?string $oldPhoto = null): void
    {
        if (! $request->hasFile('foto')) {
            return;
        }

        $directory = public_path('uploads/pegawai');
        File::ensureDirectoryExists($directory, 0755);
        $file = $request->file('foto');
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($data['id_peg'] ?? $request->route('id')));
        $name = 'foto_'.$id.'_'.now()->format('YmdHisv').'_'.Str::lower(Str::random(6)).'.'.$file->extension();
        $file->move($directory, $name);
        $data['foto'] = $name;

        if ($oldPhoto && basename($oldPhoto) === $oldPhoto) {
            $oldPath = $directory.'/'.$oldPhoto;
            if ($oldPhoto !== $name && File::isFile($oldPath)) {
                File::delete($oldPath);
            }
        }
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(strtolower((string) $request->session()->get('hak_akses')) === 'admin', 403);
    }

    private function ensureStaffAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin', 'kepala'], true), 403);
    }
}
