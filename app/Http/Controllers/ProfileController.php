<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbAnak;
use App\Models\Legacy\TbAngkat;
use App\Models\Legacy\TbBahasa;
use App\Models\Legacy\TbDiklat;
use App\Models\Legacy\TbDp3;
use App\Models\Legacy\TbEditPending;
use App\Models\Legacy\TbHukuman;
use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbLatJabatan;
use App\Models\Legacy\TbMutasi;
use App\Models\Legacy\TbOrtu;
use App\Models\Legacy\TbPangkat;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbPendidikan;
use App\Models\Legacy\TbSertifikasi;
use App\Models\Legacy\TbSuamiIstri;
use App\Services\RbacService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    private const FAMILY_REQUEST_TYPES = [
        'keluarga_anak_create' => 'Tambah data anak',
        'keluarga_anak_update' => 'Perubahan data anak',
        'keluarga_anak_delete' => 'Penghapusan data anak',
        'keluarga_ortu_create' => 'Tambah data orang tua',
        'keluarga_ortu_update' => 'Perubahan data orang tua',
        'keluarga_ortu_delete' => 'Penghapusan data orang tua',
        'keluarga_pasangan_create' => 'Tambah data pasangan',
        'keluarga_pasangan_update' => 'Perubahan data pasangan',
        'keluarga_pasangan_delete' => 'Penghapusan data pasangan',
    ];

    public function show(Request $request): Response
    {
        $idPegawai = trim((string) $request->session()->get('id_pegawai'));
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $employee = $idPegawai !== '' ? TbPegawai::query()->where('id_peg', $idPegawai)->first() : null;

        return Inertia::render('Profile/Index', [
            'employee' => $employee ? $this->employeeData($employee) : null,
            'family' => $employee ? [
                'spouses' => $this->records(TbSuamiIstri::class, 'id_peg', $employee->id_peg, ['id_si', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'hp']),
                'children' => $this->records(TbAnak::class, 'id_peg', $employee->id_peg, ['id_anak', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'anak_ke']),
                'parents' => $this->records(TbOrtu::class, 'id_peg', $employee->id_peg, ['id_ortu', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub']),
            ] : ['spouses' => [], 'children' => [], 'parents' => []],
            'familyRequests' => $employee ? $this->familyRequests($request, $employee->id_peg) : [],
            'canRequestFamily' => $role === 'user' && app(RbacService::class)->can($request, 'family.request'),
            'canManageFamily' => $role === 'admin' && app(RbacService::class)->can($request, 'family.manage'),
            'history' => $employee ? [
                'appointment' => $this->records(TbAngkat::class, 'id_peg_baru', $employee->id_peg, ['id_angkat', 'jns_mutasi', 'tgl_mutasi', 'no_mutasi', 'tmt', 'sk_mutasi'], 'tgl_mutasi'),
                'mutation' => $this->records(TbMutasi::class, 'id_peg', $employee->id_peg, ['id_mutasi', 'jns_mutasi', 'tgl_mutasi', 'no_mutasi', 'jabatan'], 'tgl_mutasi'),
                'training' => $this->records(TbDiklat::class, 'id_peg', $employee->id_peg, ['id_diklat', 'diklat', 'penyelenggara', 'tempat', 'tahun', 'biaya'], 'tahun'),
                'certification' => $this->records(TbSertifikasi::class, 'id_peg', $employee->id_peg, ['id_sertif', 'sertifikasi', 'penyelenggara', 'tgl_sertifikat', 'tgl_expired', 'sertifikat'], 'tgl_sertifikat'),
                'discipline' => $this->records(TbHukuman::class, 'id_peg', $employee->id_peg, ['id_hukum', 'hukuman', 'tgl_sk', 'no_sk', 'keterangan'], 'tgl_sk'),
                'position' => $this->records(TbJabatan::class, 'id_peg', $employee->id_peg, ['id_jab', 'jabatan', 'unit_kerja', 'tmt_jabatan', 'sampai_tgl', 'status_jab', 'no_sk'], 'tmt_jabatan'),
                'rank' => $this->records(TbPangkat::class, 'id_peg', $employee->id_peg, ['id_pangkat', 'pangkat', 'gol', 'jns_pangkat', 'tgl_sk', 'tmt_pangkat', 'no_sk'], 'tgl_sk'),
                'language' => $this->records(TbBahasa::class, 'id_peg', $employee->id_peg, ['id_bhs', 'bahasa', 'jns_bhs', 'kemampuan']),
                'education' => $this->records(TbPendidikan::class, 'id_peg', $employee->id_peg, ['id_pendidikan', 'jenjang', 'nama_sekolah', 'jurusan', 'tgl_ijazah', 'th_lulus'], 'tgl_ijazah'),
                'performance' => $this->records(TbDp3::class, 'id_peg', $employee->id_peg, ['id_dp3', 'periode_awal', 'periode_akhir', 'hasil_penilaian', 'nilai_kesetiaan', 'nilai_prestasi', 'nilai_tgjwb', 'nilai_ketaatan', 'nilai_kejujuran', 'nilai_kerjasama', 'nilai_prakarsa', 'nilai_kepemimpinan'], 'periode_akhir'),
                'jobTraining' => $this->records(TbLatJabatan::class, 'id_peg', $employee->id_peg, ['id_lat_jabatan', 'nama_pelatih', 'tahun_lat', 'jml_jam'], 'tahun_lat'),
            ] : [
                'appointment' => [], 'mutation' => [], 'training' => [], 'certification' => [], 'discipline' => [],
                'position' => [], 'rank' => [], 'language' => [], 'education' => [], 'performance' => [], 'jobTraining' => [],
            ],
        ]);
    }

    private function familyRequests(Request $request, string $idPegawai): array
    {
        $types = array_keys(self::FAMILY_REQUEST_TYPES);

        return TbEditPending::query()
            ->where('id_peg', $idPegawai)
            ->where('id_user', $request->session()->get('id_user'))
            ->whereIn('jenis_data', $types)
            ->orderByDesc('tanggal_pengajuan')
            ->orderByDesc('id_edit')
            ->get()
            ->map(function (TbEditPending $pending): array {
                $data = json_decode((string) $pending->data_baru, true) ?: [];
                $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

                return [
                    'id_edit' => $pending->id_edit,
                    'label' => self::FAMILY_REQUEST_TYPES[$pending->jenis_data] ?? 'Perubahan data keluarga',
                    'action' => match ($data['action'] ?? null) {
                        'create' => 'Tambah',
                        'update' => 'Perubahan',
                        'delete' => 'Penghapusan',
                        default => 'Perubahan',
                    },
                    'nama' => $payload['nama'] ?? '-',
                    'status_hub' => $payload['status_hub'] ?? '-',
                    'status' => $pending->status_otorisasi,
                    'submitted_at' => $pending->tanggal_pengajuan?->format('d-m-Y H:i'),
                    'comment' => $data['request_comment'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function employeeData(TbPegawai $employee): array
    {
        return [
            'id_peg' => $employee->id_peg,
            'nik' => $employee->nik,
            'nama' => $employee->nama,
            'tempat_lhr' => $employee->tempat_lhr,
            'tgl_lhr' => $this->formatDate($employee->tgl_lhr),
            'jk' => $employee->jk,
            'agama' => $employee->agama,
            'gol_darah' => $employee->gol_darah,
            'status_nikah' => $employee->status_nikah,
            'alamat' => $employee->alamat,
            'telp' => $employee->telp,
            'email' => $employee->email,
            'status_kepeg' => $employee->status_kepeg,
            'tmt_kerja' => $this->formatDate($employee->tmt_kerja),
            'tgl_pensiun' => $this->formatDate($employee->tgl_pensiun),
            'foto_url' => $employee->foto ? route('employees.photo', ['filename' => $employee->foto]) : null,
        ];
    }

    private function records(string $model, string $column, string $id, array $fields, ?string $orderBy = null): array
    {
        $query = $model::query()->where($column, $id);

        if ($orderBy !== null) {
            $query->orderByDesc($orderBy);
        }

        return $query->get()->map(fn (Model $record): array => $this->recordData($record, $fields))->values()->all();
    }

    private function recordData(Model $record, array $fields): array
    {
        $data = [];

        foreach ($fields as $field) {
            $value = $record->{$field};
            $data[$field] = $value instanceof \DateTimeInterface ? $this->formatDate($value) : $value;
        }

        return $data;
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || (string) $value === '0000-00-00') {
            return null;
        }

        return date('d-m-Y', strtotime((string) $value));
    }
}
