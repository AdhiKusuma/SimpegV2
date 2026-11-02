<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbDiklat;
use App\Models\Legacy\TbHukuman;
use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbMasterJabatan;
use App\Models\Legacy\TbPegawai;
use App\Models\QuotaJabatan;
use App\Services\RbacService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly RbacService $rbac) {}

    public function index(Request $request): Response
    {
        abort_unless($this->rbac->can($request, 'dashboard.view'), 403);
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $data = $this->dashboardData($request);

        return Inertia::render('Dashboard/Index', [
            ...$data,
            ...$this->dashboardDetails($data['unit'], $data['year']),
            'offices' => $this->dashboardOffices($request),
            'filters' => ['unit_kerja' => $data['unit']],
            'dashboardLocked' => $role === 'kepala',
            'canSelectOffice' => $role !== 'kepala',
        ]);
    }

    public function branch(Request $request)
    {
        abort_unless($this->rbac->can($request, 'dashboard.view'), 403);

        return to_route('dashboard');
    }

    public function tenureEmployees(Request $request, string $range): JsonResponse
    {
        abort_unless($this->rbac->can($request, 'dashboard.view'), 403);

        $ranges = $this->tenureRanges();
        abort_unless(isset($ranges[$range]), 404);

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala'
            ? $request->session()->get('kode_kantor')
            : $request->string('unit_kerja')->trim()->value();
        $unit = $unit ?: null;
        $bounds = $ranges[$range];

        $employeeIds = TbPegawai::query()
            ->from('tb_pegawai as p')
            ->join('tb_jabatan as j', 'p.id_peg', '=', 'j.id_peg')
            ->where('p.status_aktif', 1)
            ->where('j.status_jab', 'Aktif')
            ->whereNotNull('p.tmt_kerja')
            ->whereRaw(
                $bounds['max'] === null
                    ? 'TIMESTAMPDIFF(YEAR, p.tmt_kerja, CURDATE()) >= ?'
                    : 'TIMESTAMPDIFF(YEAR, p.tmt_kerja, CURDATE()) BETWEEN ? AND ?',
                $bounds['max'] === null ? [$bounds['min']] : [$bounds['min'], $bounds['max']],
            )
            ->select('p.id_peg')
            ->distinct();
        $this->applyUnitFilter($employeeIds, $unit);

        $employees = TbPegawai::query()
            ->whereIn('id_peg', $employeeIds)
            ->select(['id_peg', 'nama', 'tmt_kerja'])
            ->selectRaw('TIMESTAMPDIFF(YEAR, tmt_kerja, CURDATE()) as masa_kerja')
            ->orderBy('nama')
            ->orderBy('id_peg')
            ->paginate(20)
            ->through(fn (TbPegawai $employee): array => [
                'id_peg' => (string) $employee->id_peg,
                'nama' => trim((string) $employee->nama),
                'tmt_kerja' => $employee->tmt_kerja?->format('d-m-Y'),
                'masa_kerja' => (int) $employee->masa_kerja,
            ]);

        return response()->json($employees);
    }

    private function dashboardData(Request $request, ?string $forcedUnit = null): array
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $forcedUnit ?? ($role === 'kepala'
            ? $request->session()->get('kode_kantor')
            : $request->string('unit_kerja')->trim()->value());
        $unit = $unit ?: null;
        $year = now()->year;

        $activeEmployees = TbPegawai::query()
            ->from('tb_pegawai as p')
            ->join('tb_jabatan as j', 'p.id_peg', '=', 'j.id_peg')
            ->where('p.status_aktif', 1)
            ->where('j.status_jab', 'Aktif');
        $this->applyUnitFilter($activeEmployees, $unit);

        $inactiveEmployees = TbPegawai::query()
            ->from('tb_pegawai as p')
            ->join('tb_mutasi as m', 'p.id_peg', '=', 'm.id_peg')
            ->join('tb_jabatan as j', 'p.id_peg', '=', 'j.id_peg')
            ->where('p.status_aktif', 3)
            ->whereYear('m.tgl_mutasi', $year);
        $this->applyUnitFilter($inactiveEmployees, $unit);

        $punishments = TbHukuman::query()
            ->from('tb_hukuman as h')
            ->join('tb_jabatan as j', 'h.id_peg', '=', 'j.id_peg')
            ->whereYear('h.tgl_sk', $year)
            ->where('j.status_jab', 'Aktif');
        $this->applyUnitFilter($punishments, $unit);

        $training = TbDiklat::query()
            ->from('tb_diklat as d')
            ->join('tb_jabatan as j', 'd.id_peg', '=', 'j.id_peg')
            ->where('d.tahun', (string) $year)
            ->where('j.status_jab', 'Aktif');
        $this->applyUnitFilter($training, $unit);

        return [
            'unit' => $unit,
            'year' => $year,
            'stats' => [
                [
                    'key' => 'employees',
                    'label' => 'Total Pegawai',
                    'value' => $activeEmployees->distinct()->count('p.id_peg'),
                    'tone' => 'blue',
                    'href' => '/pegawai',
                ],
                [
                    'key' => 'inactive',
                    'label' => "Non Aktif ($year)",
                    'value' => $inactiveEmployees->distinct()->count('p.id_peg'),
                    'tone' => 'red',
                    'href' => '/mutasi',
                ],
                [
                    'key' => 'punishments',
                    'label' => "Pelanggaran ($year)",
                    'value' => $punishments->distinct()->count('h.id_peg'),
                    'tone' => 'amber',
                    'href' => '/pelanggaran',
                ],
                [
                    'key' => 'training',
                    'label' => "Diklat ($year)",
                    'value' => $training->distinct()->count('d.id_peg'),
                    'tone' => 'green',
                    'href' => '/diklat',
                ],
            ],
            'tenure' => $this->tenureStats($unit),
        ];
    }

    private function tenureStats(?string $unit): array
    {
        return collect($this->tenureRanges())->map(function (array $range, string $key) use ($unit): array {
            $query = TbPegawai::query()
                ->from('tb_pegawai as p')
                ->join('tb_jabatan as j', 'p.id_peg', '=', 'j.id_peg')
                ->where('p.status_aktif', 1)
                ->where('j.status_jab', 'Aktif')
                ->whereNotNull('p.tmt_kerja');

            $query->whereRaw(
                $range['max'] === null
                    ? 'TIMESTAMPDIFF(YEAR, p.tmt_kerja, CURDATE()) >= ?'
                    : 'TIMESTAMPDIFF(YEAR, p.tmt_kerja, CURDATE()) BETWEEN ? AND ?',
                $range['max'] === null ? [$range['min']] : [$range['min'], $range['max']],
            );
            $this->applyUnitFilter($query, $unit);

            return [
                'key' => $key,
                'label' => $range['label'],
                'value' => $query->distinct()->count('p.id_peg'),
                'tone' => $range['tone'],
            ];
        })->values()->all();
    }

    private function tenureRanges(): array
    {
        return [
            'under-10' => ['label' => '< 10 Tahun', 'min' => 0, 'max' => 10, 'tone' => 'cyan'],
            '11-20' => ['label' => '11 - 20 Tahun', 'min' => 11, 'max' => 20, 'tone' => 'green'],
            '21-30' => ['label' => '21 - 30 Tahun', 'min' => 21, 'max' => 30, 'tone' => 'amber'],
            'over-30' => ['label' => '> 30 Tahun', 'min' => 31, 'max' => null, 'tone' => 'red'],
        ];
    }

    private function dashboardDetails(?string $unit, int $year): array
    {
        $group = 'dashboard-details';

        return [
            'charts' => Inertia::defer(fn (): array => [
                'gender' => $this->genderStats($unit),
                'education' => $this->educationStats($unit),
                'employment' => $this->employmentStats($unit),
                'violations' => $this->violationTrend($unit, $year),
                'positions' => $this->positionStats($unit),
            ], $group),
            'retirement' => Inertia::defer(fn (): array => $this->retirementList($unit, $year), $group),
            'executivePositions' => Inertia::defer(fn (): array => $this->positionAvailability('PE', $unit), $group),
            'structuralPositions' => Inertia::defer(fn (): array => $this->positionAvailability('PS', $unit), $group),
        ];
    }

    private function activeEmployeeQuery(?string $unit): Builder
    {
        $query = TbPegawai::query()
            ->from('tb_pegawai as p')
            ->join('tb_jabatan as j', 'p.id_peg', '=', 'j.id_peg')
            ->where('p.status_aktif', 1)
            ->where('j.status_jab', 'Aktif');

        $this->applyUnitFilter($query, $unit);

        return $query;
    }

    private function genderStats(?string $unit): array
    {
        $rows = $this->activeEmployeeQuery($unit)
            ->select('p.jk')
            ->selectRaw('COUNT(DISTINCT p.id_peg) as total')
            ->groupBy('p.jk')
            ->get();

        $counts = ['Laki-laki' => 0, 'Perempuan' => 0];

        foreach ($rows as $row) {
            $gender = strtoupper(trim((string) $row->jk));
            $label = in_array($gender, ['L', 'LAKI-LAKI', 'PRIA'], true)
                ? 'Laki-laki'
                : (in_array($gender, ['P', 'PEREMPUAN', 'WANITA'], true) ? 'Perempuan' : null);

            if ($label !== null) {
                $counts[$label] += (int) $row->total;
            }
        }

        return [
            'labels' => array_keys($counts),
            'values' => array_values($counts),
            'total' => array_sum($counts),
        ];
    }

    private function employmentStats(?string $unit): array
    {
        $rows = $this->activeEmployeeQuery($unit)
            ->select('p.status_kepeg')
            ->selectRaw('COUNT(DISTINCT p.id_peg) as total')
            ->groupBy('p.status_kepeg')
            ->get();

        $counts = [
            'Pegawai Tetap' => 0,
            'Calon Pegawai' => 0,
            'Kontrak' => 0,
            'Outsourcing' => 0,
            'Lainnya' => 0,
        ];

        foreach ($rows as $row) {
            $status = strtoupper(trim((string) $row->status_kepeg));
            $label = match (true) {
                in_array($status, ['TETAP', 'PEGAWAI TETAP'], true) => 'Pegawai Tetap',
                in_array($status, ['CAPEG', 'CALON PEGAWAI'], true) => 'Calon Pegawai',
                str_contains($status, 'KONTRAK') || $status === 'PKWT' => 'Kontrak',
                str_contains($status, 'SOURCE') || $status === 'THL' => 'Outsourcing',
                default => 'Lainnya',
            };

            $counts[$label] += (int) $row->total;
        }

        $counts = array_filter($counts, static fn (int $value): bool => $value > 0);

        return [
            'labels' => array_keys($counts),
            'values' => array_values($counts),
        ];
    }

    private function educationStats(?string $unit): array
    {
        $levels = ['S3', 'S2', 'S1', 'D4', 'D3', 'D2', 'D1', 'SMA', 'SMK', 'SMP', 'SD'];
        $rank = array_flip($levels);
        $highest = [];

        $rows = $this->activeEmployeeQuery($unit)
            ->leftJoin('tb_pendidikan as edu', 'edu.id_peg', '=', 'p.id_peg')
            ->select(['p.id_peg', 'edu.jenjang'])
            ->get();

        foreach ($rows as $row) {
            $id = (string) $row->id_peg;
            $highest[$id] ??= null;
            $level = strtoupper(trim((string) $row->jenjang));

            if (isset($rank[$level]) && ($highest[$id] === null || $rank[$level] < $rank[$highest[$id]])) {
                $highest[$id] = $level;
            }
        }

        $counts = array_fill_keys([...$levels, 'Belum Input'], 0);
        foreach ($highest as $level) {
            $counts[$level ?? 'Belum Input']++;
        }

        $counts = array_filter($counts, static fn (int $value): bool => $value > 0);

        return [
            'labels' => array_keys($counts),
            'values' => array_values($counts),
        ];
    }

    private function violationTrend(?string $unit, int $year): array
    {
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $values = array_fill(0, 12, 0);

        $query = TbHukuman::query()
            ->from('tb_hukuman as h')
            ->join('tb_jabatan as j', 'h.id_peg', '=', 'j.id_peg')
            ->whereYear('h.tgl_sk', $year)
            ->where('j.status_jab', 'Aktif');
        $this->applyUnitFilter($query, $unit);

        $rows = $query
            ->selectRaw('MONTH(h.tgl_sk) as month, COUNT(h.id_hukum) as total')
            ->groupByRaw('MONTH(h.tgl_sk)')
            ->orderBy('month')
            ->get();

        foreach ($rows as $row) {
            $month = (int) $row->month;
            if ($month >= 1 && $month <= 12) {
                $values[$month - 1] = (int) $row->total;
            }
        }

        return ['labels' => $labels, 'values' => $values, 'year' => $year];
    }

    private function dashboardOffices(Request $request): array
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';

        $offices = TbKantor::query()
            ->where('level', 'KC')
            ->when($unit !== '', fn (Builder $builder) => $builder->where('kode_kantor_detail', $unit))
            ->orderBy('kode_cabang')
            ->get(['kode_kantor_detail', 'kode_cabang', 'nama_kantor'])
            ->map(fn (TbKantor $office): array => [
                'kode' => (string) $office->kode_kantor_detail,
                'nama' => sprintf('%s - %s', $office->kode_cabang, trim((string) $office->nama_kantor)),
            ]);

        if ($unit !== '') {
            return $offices->values()->all();
        }

        return collect(config('dashboard.region_filters', []))
            ->map(fn (array $region): array => [
                'kode' => $region['code'],
                'nama' => $region['label'],
            ])
            ->concat($offices)
            ->values()
            ->all();
    }

    private function positionStats(?string $unit): array
    {
        return $this->activeEmployeeQuery($unit)
            ->whereNotNull('j.jabatan')
            ->where('j.jabatan', '<>', '')
            ->selectRaw('j.jabatan as label, COUNT(DISTINCT p.id_peg) as total')
            ->groupBy('j.jabatan')
            ->orderBy('j.jabatan')
            ->get()
            ->map(fn ($row): array => ['label' => $row->label, 'value' => (int) $row->total])
            ->all();
    }

    private function retirementList(?string $unit, int $year): array
    {
        $excludedIds = ['101-001', '101-002', '101-003', '101-004', '101-005', '101-007', '101-008'];

        return TbPegawai::query()
            ->from('tb_pegawai as p')
            ->whereNotIn('p.id_peg', $excludedIds)
            ->whereYear('p.tgl_pensiun', $year)
            ->when($unit !== null, function (Builder $builder) use ($unit): void {
                $builder->whereExists(function ($subquery) use ($unit): void {
                    $subquery->select(DB::raw('1'))
                        ->from('tb_jabatan as unit_job')
                        ->whereColumn('unit_job.id_peg', 'p.id_peg')
                        ->where('unit_job.status_jab', 'Aktif');
                    $this->applyUnitFilter($subquery, $unit, 'unit_job');
                });
            })
            ->select(['p.id_peg', 'p.nama', 'p.jk', 'p.tempat_lhr', 'p.tgl_pensiun'])
            ->selectSub(
                DB::table('tb_jabatan')
                    ->select('jabatan')
                    ->whereColumn('tb_jabatan.id_peg', 'p.id_peg')
                    ->where('status_jab', 'Aktif')
                    ->orderByDesc('tmt_jabatan')
                    ->limit(1),
                'jabatan',
            )
            ->selectRaw('DATEDIFF(p.tgl_pensiun, CURDATE()) as hari_tersisa')
            ->orderByRaw('CASE WHEN DATEDIFF(p.tgl_pensiun, CURDATE()) BETWEEN 0 AND 30 THEN 1 ELSE 2 END')
            ->orderBy('p.tgl_pensiun')
            ->get()
            ->map(fn (TbPegawai $employee): array => [
                'id_peg' => $employee->id_peg,
                'nama' => $employee->nama,
                'jk' => $employee->jk,
                'jabatan' => $employee->jabatan ?: '-',
                'tempat_lhr' => $employee->tempat_lhr,
                'tgl_pensiun' => $employee->tgl_pensiun?->format('d-m-Y'),
                'hari_tersisa' => (int) $employee->hari_tersisa,
                'status' => (int) $employee->hari_tersisa > 0 ? 'Segera Pensiun' : 'Sudah Lewat',
            ])
            ->all();
    }

    private function positionAvailability(string $group, ?string $unit): array
    {
        $rows = TbMasterJabatan::query()
            ->from('tb_master_jabatan as m')
            ->leftJoin('tb_jabatan as j', function ($join) use ($unit): void {
                $join->on('m.nama_jabatan', '=', 'j.jabatan')->where('j.status_jab', 'Aktif');
                if ($unit !== null) {
                    $scope = $this->dashboardUnitScope($unit);
                    if ($scope['type'] === 'unit') {
                        $join->where('j.unit_kerja', $scope['code']);
                    } else {
                        $join->whereExists(function ($subquery) use ($scope): void {
                            $subquery->select(DB::raw('1'))
                                ->from('tb_kantor as scope_office')
                                ->whereColumn('scope_office.kode_kantor_detail', 'j.unit_kerja')
                                ->whereBetween('scope_office.kode_cabang', [$scope['from'], $scope['to']]);
                        });
                    }
                }
            })
            ->leftJoin('tb_pegawai as p', function ($join): void {
                $join->on('j.id_peg', '=', 'p.id_peg')->where('p.status_aktif', 1);
            })
            ->where('m.group_jabatan', $group)
            ->select(['m.kode_jabatan', 'm.nama_jabatan', 'm.kuota'])
            ->selectRaw('COUNT(DISTINCT p.id_peg) as terisi')
            ->groupBy('m.kode_jabatan', 'm.nama_jabatan', 'm.kuota')
            ->orderBy('m.kode_jabatan')
            ->get();

        $configuredQuotas = $this->configuredPositionQuotas($group, $unit);

        if ($unit !== null) {
            $rows = $rows->filter(fn ($row): bool => (int) $row->terisi > 0 || $configuredQuotas->has((string) $row->kode_jabatan)
            )->values();
        }

        return $rows->map(function ($row) use ($configuredQuotas): array {
            $positionId = (string) $row->kode_jabatan;
            $quota = $configuredQuotas->has($positionId)
                ? (int) $configuredQuotas->get($positionId)
                : max((int) $row->kuota, 1);
            $filled = (int) $row->terisi;
            $remaining = max($quota - $filled, 0);
            $status = $filled === 0
                ? 'Kosong'
                : ($filled < $quota ? 'Tersedia' : ($filled > $quota ? 'Melebihi kuota' : 'Penuh'));

            return [
                'kode_jabatan' => $row->kode_jabatan,
                'nama_jabatan' => $row->nama_jabatan,
                'terisi' => $filled,
                'kuota' => $quota,
                'sisa' => $remaining,
                'status' => $status,
            ];
        })
            ->filter(fn (array $row): bool => $row['kuota'] > 0 || $row['terisi'] > 0)
            ->all();
    }

    private function configuredPositionQuotas(string $group, ?string $unit)
    {
        if (! Schema::hasTable('kuota_jabatan')) {
            return collect();
        }

        $query = QuotaJabatan::query()
            ->from('kuota_jabatan as q')
            ->join('tb_master_jabatan as m', 'm.kode_jabatan', '=', 'q.jabatan_id')
            ->where('m.group_jabatan', $group)
            ->select('q.jabatan_id')
            ->selectRaw('SUM(q.kuota_pegawai) as total_kuota')
            ->groupBy('q.jabatan_id');

        if ($unit !== null) {
            $scope = $this->dashboardUnitScope($unit);
            if ($scope['type'] === 'unit') {
                $query->whereExists(function ($subquery) use ($scope): void {
                    $subquery->select(DB::raw('1'))
                        ->from('tb_kantor as quota_office')
                        ->whereColumn('quota_office.kode_cabang', 'q.kode_cabang')
                        ->where('quota_office.kode_kantor_detail', $scope['code']);
                });
            } else {
                $query->whereBetween('q.kode_cabang', [$scope['from'], $scope['to']]);
            }
        }

        return $query->get()->mapWithKeys(fn ($row): array => [
            (string) $row->jabatan_id => (int) $row->total_kuota,
        ]);
    }

    private function applyUnitFilter(Builder|QueryBuilder $query, ?string $unit, string $jobAlias = 'j'): void
    {
        if ($unit === null) {
            return;
        }

        $scope = $this->dashboardUnitScope($unit);
        if ($scope['type'] === 'unit') {
            $query->where($jobAlias.'.unit_kerja', $scope['code']);

            return;
        }

        $query->whereExists(function ($subquery) use ($scope, $jobAlias): void {
            $subquery->select(DB::raw('1'))
                ->from('tb_kantor as scope_office')
                ->whereColumn('scope_office.kode_kantor_detail', $jobAlias.'.unit_kerja')
                ->whereBetween('scope_office.kode_cabang', [$scope['from'], $scope['to']]);
        });
    }

    private function dashboardUnitScope(string $unit): array
    {
        foreach (config('dashboard.region_filters', []) as $region) {
            if ($region['code'] === $unit) {
                return [
                    'type' => 'region',
                    'from' => $region['from'],
                    'to' => $region['to'],
                ];
            }
        }

        return [
            'type' => 'unit',
            'code' => $unit,
        ];
    }
}
