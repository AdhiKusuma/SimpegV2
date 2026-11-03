<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbPegawai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $search = $request->string('search')->trim()->value();

        $offices = TbKantor::query()
            ->from('tb_kantor as o')
            ->whereRaw('RIGHT(o.kode_kantor_detail, 3) = ?', ['000'])
            ->when($unit !== '', fn (Builder $builder) => $builder->where('o.kode_kantor_detail', $unit))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('o.kode_kantor_detail', 'like', $like)
                        ->orWhere('o.kode_cabang', 'like', $like)
                        ->orWhere('o.nama_kantor', 'like', $like)
                        ->orWhere('o.alamat', 'like', $like)
                        ->orWhere('o.status_kantor', 'like', $like);
                });
            })
            ->select('o.*')
            ->selectSub(
                TbJabatan::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('unit_kerja', 'o.kode_kantor_detail')
                    ->where('status_jab', 'Aktif'),
                'employee_count'
            )
            ->orderBy('o.kode_kantor_detail')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100))
            ->withQueryString()
            ->through(fn (TbKantor $office): array => $this->presentOffice($office));

        return Inertia::render('Offices/Index', [
            'offices' => $offices,
            'filters' => ['search' => $search],
            'role' => $role,
        ]);
    }

    public function show(Request $request, string $code): Response
    {
        $this->ensureStaffAccess($request);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';
        $office = TbKantor::query()->findOrFail($code);

        abort_if($unit !== '' && $office->kode_kantor_detail !== $unit, 403);

        $employees = TbPegawai::query()
            ->from('tb_pegawai as p')
            ->join('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')
                    ->where('j.status_jab', 'Aktif');
            })
            ->where('p.status_aktif', 1)
            ->where('j.unit_kerja', $office->kode_kantor_detail)
            ->select(['p.id_peg', 'p.nama', 'p.status_kepeg', 'j.jabatan', 'j.tmt_jabatan'])
            ->orderBy('p.nama')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100))
            ->withQueryString()
            ->through(fn ($employee): array => [
                'id_peg' => $employee->id_peg,
                'nama' => $employee->nama,
                'status_kepeg' => $employee->status_kepeg,
                'jabatan' => $employee->jabatan,
                'tmt_jabatan' => $this->formatDate($employee->tmt_jabatan),
            ]);

        $officeData = $this->presentOffice($office);
        $officeData['employee_count'] = $employees->total();

        return Inertia::render('Offices/Show', [
            'office' => $officeData,
            'employees' => $employees,
        ]);
    }

    private function presentOffice(TbKantor $office): array
    {
        return [
            'kode_kantor_detail' => $office->kode_kantor_detail,
            'kode_cabang' => $office->kode_cabang,
            'nama_kantor' => $office->nama_kantor,
            'alamat' => $office->alamat,
            'level' => $office->level,
            'jarak_kc' => $office->jarak_kc,
            'waktu_kc' => $office->waktu_kc,
            'status_kantor' => $office->status_kantor,
            'bentuk_dokumen' => $office->bentuk_dokumen,
            'no_dokumen' => $office->no_dokumen,
            'tgl_mulai' => $this->formatDate($office->tgl_mulai),
            'tgl_selesai' => $this->formatDate($office->tgl_selesai),
            'keterangan' => $office->keterangan,
            'employee_count' => (int) ($office->employee_count ?? 0),
        ];
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
}
