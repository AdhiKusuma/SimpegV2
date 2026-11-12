<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbBiayaPendidikan;
use App\Models\Legacy\TbDiklat;
use App\Models\Legacy\TbHukuman;
use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbKantor;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbPendidikan;
use App\Models\Legacy\TbRefJabatan;
use App\Services\XlsxExportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    private const AGE_RANGES = ['0-25', '26-35', '36-45', '46-55', '>55'];

    public function nominative(Request $request): InertiaResponse
    {
        $this->ensureReportAccess($request);

        $filters = $this->nominativeFilters($request);
        $items = $this->nominativeQuery($request, $filters)
            ->select([
                'p.id_peg',
                'p.nama',
                'p.status_kepeg',
                'j.jabatan',
                'j.tmt_jabatan',
                'k.nama_kantor',
                'education.jenjang',
                'education.nama_sekolah',
            ])
            ->distinct()
            ->orderBy('p.nama')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100))
            ->withQueryString()
            ->through(fn ($item, int $index): array => $this->presentNominative($item, $index));

        return Inertia::render('Reports/Nominative', [
            'items' => $items,
            'offices' => $this->offices($request),
            'positions' => $this->positions($request),
            'statuses' => $this->statuses($request),
            'filters' => $filters,
            'role' => strtolower((string) $request->session()->get('hak_akses')),
        ]);
    }

    public function exportNominative(Request $request, XlsxExportService $xlsxExport): SymfonyResponse
    {
        $this->ensureReportAccess($request);

        $filters = $this->nominativeFilters($request);
        $query = $this->nominativeQuery($request, $filters)
            ->select([
                'p.id_peg',
                'p.nama',
                'p.status_kepeg',
                'j.jabatan',
                'j.tmt_jabatan',
                'k.nama_kantor',
                'education.jenjang',
                'education.nama_sekolah',
            ])
            ->distinct()
            ->orderBy('p.nama');

        if ($request->string('type')->lower()->value() === 'pdf') {
            return response()->view('reports.nominative-print', [
                'employees' => $query->get(),
                'filters' => $filters,
            ]);
        }

        $rows = [['No', 'Nama Pegawai', 'ID Pegawai', 'Jabatan', 'TMT Jabatan', 'Unit Kerja', 'Status Kepegawaian', 'Jenjang Pendidikan', 'Sekolah/Universitas']];
        $number = 1;

        foreach ($query->cursor() as $item) {
            $rows[] = [
                $number++, $item->nama, $item->id_peg, $item->jabatan ?: '-',
                $this->formatDate($item->tmt_jabatan), $item->nama_kantor ?: '-',
                $item->status_kepeg ?: '-', $item->jenjang ?: '-', $item->nama_sekolah ?: '-',
            ];
        }

        $content = $xlsxExport->make($rows, 'Laporan Nominatif', true);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'laporan-nominatif-'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function formation(Request $request): InertiaResponse
    {
        $this->ensureReportAccess($request);

        return Inertia::render('Reports/Formation', $this->formationData($request));
    }

    public function exportFormation(Request $request, XlsxExportService $xlsxExport): SymfonyResponse
    {
        $this->ensureReportAccess($request);

        $data = $this->formationData($request);

        if ($request->string('type')->lower()->value() === 'pdf') {
            return response()->view('reports.formation-print', $data);
        }

        $rows = [['No', 'Jabatan', 'Kuota', 'Terisi', 'Sisa', 'Status']];

        foreach ($data['rows'] as $index => $row) {
            $rows[] = [
                $index + 1,
                $row['nama_jabatan'].($row['sumber'] === 'NonMaster' ? ' *' : ''),
                $row['kuota'],
                $row['terisi'],
                $row['sisa'],
                $row['status'],
            ];
        }

        $rows[] = [
            '',
            'GRAND TOTAL',
            $data['totals']['kuota'],
            $data['totals']['terisi'],
            $data['totals']['sisa'],
            '',
        ];
        $content = $xlsxExport->make($rows, 'Laporan Formasi', true);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'laporan-formasi-'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function trainingCosts(Request $request): InertiaResponse
    {
        $this->ensureReportAccess($request);

        $data = $this->trainingCostData($request);

        return Inertia::render('Reports/TrainingCosts', $data);
    }

    public function exportTrainingCosts(Request $request, XlsxExportService $xlsxExport): SymfonyResponse
    {
        $this->ensureReportAccess($request);

        $data = $this->trainingCostData($request);

        if ($request->string('type')->lower()->value() === 'pdf') {
            return response()->view('reports.training-costs-print', $data);
        }

        $rows = [['No', 'Nama Kegiatan', 'Penyelenggara', 'Tahun', 'Peserta', 'Total Biaya']];

        foreach ($data['rows'] as $index => $row) {
            $rows[] = [
                $index + 1,
                $row['diklat'],
                $row['penyelenggara'],
                $row['tahun'],
                $row['jumlah_peserta'],
                $row['total_biaya_kegiatan'],
            ];
        }

        $rows[] = ['', 'GRAND TOTAL', '', '', $data['totals']['peserta'], $data['totals']['biaya']];
        $content = $xlsxExport->make($rows, 'Rekap Biaya Diklat', true);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'rekap-biaya-diklat-'.($data['filters']['tahun'] === 'Semua' ? 'semua' : $data['filters']['tahun']).'-'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function trainingCostDetail(Request $request): JsonResponse
    {
        $this->ensureReportAccess($request);

        $validated = $request->validate([
            'diklat' => ['required', 'string', 'max:255'],
            'tahun' => ['required', 'string', 'max:4'],
            'penyelenggara' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = (int) ($validated['page'] ?? 1);

        $query = TbDiklat::query()
            ->from('tb_diklat as d')
            ->join('tb_pegawai as p', 'd.id_peg', '=', 'p.id_peg')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')
                    ->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->where('d.diklat', $validated['diklat'])
            ->where('d.tahun', $validated['tahun']);
        if (($validated['penyelenggara'] ?? '') === '') {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('d.penyelenggara')->orWhere('d.penyelenggara', '');
            });
        } else {
            $query->where('d.penyelenggara', $validated['penyelenggara']);
        }
        $this->applyTrainingAccessFilter($query, $request);

        $items = $query
            ->select(['d.biaya', 'p.nama', 'p.id_peg', 'k.nama_kantor'])
            ->orderBy('p.nama')
            ->paginate(4, ['*'], 'page', $page);

        return response()->json([
            'data' => $items->getCollection()->map(fn ($item): array => [
                'nama' => $item->nama,
                'id_peg' => $item->id_peg,
                'nama_kantor' => $item->nama_kantor,
                'biaya' => (int) ($item->biaya ?? 0),
            ])->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function educationCosts(Request $request): InertiaResponse
    {
        $this->ensureReportAccess($request);

        return Inertia::render('Reports/EducationCosts', $this->educationCostData($request));
    }

    public function exportEducationCosts(Request $request, XlsxExportService $xlsxExport): SymfonyResponse
    {
        $this->ensureReportAccess($request);

        $data = $this->educationCostData($request);

        if ($request->string('type')->lower()->value() === 'pdf') {
            return response()->view('reports.education-costs-print', $data);
        }

        $rows = [['No', 'Kategori Pengembangan', 'Detail Kegiatan', 'Penyelenggara', 'Tanggal', 'Tahun', 'Peserta', 'Total Biaya']];

        foreach ($data['rows'] as $index => $row) {
            $rows[] = [
                $index + 1,
                $row['kategori'],
                $row['pengembangan_sdm'],
                $row['pihak_pelaksana'],
                $row['tanggal_export'],
                $row['tahun'],
                $row['jumlah_sdm'],
                $row['total_biaya'],
            ];
        }

        $rows[] = ['', 'GRAND TOTAL', '', '', '', '', $data['totals']['peserta'], $data['totals']['biaya']];
        $content = $xlsxExport->make($rows, 'Rekap Biaya Pendidikan', true);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'rekap-biaya-pendidikan-'.($data['filters']['tahun'] === 'Semua' ? 'semua' : $data['filters']['tahun']).'-'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function condition(Request $request): InertiaResponse
    {
        $this->ensureReportAccess($request);

        $data = $this->conditionData($request);

        return Inertia::render('Reports/Condition', $data);
    }

    public function exportCondition(Request $request, XlsxExportService $xlsxExport): SymfonyResponse
    {
        $this->ensureReportAccess($request);

        $data = $this->conditionData($request);

        if ($request->string('type')->lower()->value() === 'pdf') {
            return response()->view('reports.condition-print', $data);
        }

        $outputRows = [['Jenis Laporan', ...$data['ranges'], 'Total']];

        foreach ($data['categories'] as $category) {
            $outputRows[] = [$category['label'], '', '', '', '', '', ''];

            foreach ($category['sub'] as $sub) {
                $row = [$sub];
                $total = 0;

                foreach ($data['ranges'] as $range) {
                    $value = (int) ($data['counts'][$range][$category['key']][$sub] ?? 0);
                    $row[] = $value;
                    $total += $value;
                }

                $row[] = $total;
                $outputRows[] = $row;
            }

            $subtotal = $data['subtotals'][$category['key']];
            $outputRows[] = [
                'SUBTOTAL '.$category['label'],
                ...array_map(fn ($range) => $subtotal[$range], $data['ranges']),
                $subtotal['total'],
            ];
        }

        $content = $xlsxExport->make($outputRows, 'Laporan Keadaan Pegawai', true);

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, 'laporan-keadaan-pegawai-'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function nominativeFilters(Request $request): array
    {
        return [
            'kantor' => $this->effectiveOffice($request),
            'jabatan' => $request->string('jabatan')->trim()->value(),
            'status_kepeg' => $request->string('status_kepeg')->trim()->value(),
            'search' => $request->string('search')->trim()->value(),
        ];
    }

    private function nominativeQuery(Request $request, array $filters): Builder
    {
        $query = TbPegawai::query()
            ->from('tb_pegawai as p')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')
                    ->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->leftJoin('tb_pendidikan as education', function ($join): void {
                $join->on('p.id_peg', '=', 'education.id_peg')
                    ->where('education.status', 'Akhir');
            })
            ->where('p.status_aktif', 1)
            ->when($filters['jabatan'] !== '', fn (Builder $builder) => $builder->where('j.jabatan', $filters['jabatan']))
            ->when($filters['status_kepeg'] !== '', fn (Builder $builder) => $builder->where('p.status_kepeg', $filters['status_kepeg']))
            ->when($filters['search'] !== '', function (Builder $builder) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('p.nama', 'like', $like)
                        ->orWhere('p.id_peg', 'like', $like);
                });
            });

        $this->applyOfficeFilter($query, $filters['kantor']);

        return $query;
    }

    private function conditionData(Request $request): array
    {
        $office = $this->effectiveOffice($request);
        $year = max(2000, min(2100, $request->integer('tahun', now()->year)));
        $counts = [];

        foreach (self::AGE_RANGES as $range) {
            $counts[$range] = [
                'jk' => ['Laki-laki' => 0, 'Perempuan' => 0],
                'pend' => array_fill_keys(['SD', 'SMP', 'SMA', 'SMK', 'D1', 'D2/D3', 'S1', 'S2/S3', 'LAIN-LAIN'], 0),
                'status' => [],
                'jabatan' => [],
                'hukuman' => [],
            ];
        }

        $employeeQuery = TbPegawai::query()
            ->from('tb_pegawai as p')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')
                    ->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->where('p.status_aktif', 1);

        $this->applyOfficeFilter($employeeQuery, $office);

        $employees = $employeeQuery
            ->select(['p.id_peg', 'p.tgl_lhr', 'p.jk', 'p.status_kepeg', 'j.jabatan'])
            ->distinct()
            ->get();

        $education = $employees->isEmpty()
            ? collect()
            : TbPendidikan::query()
                ->whereIn('id_peg', $employees->pluck('id_peg')->all())
                ->orderByDesc('tgl_ijazah')
                ->get(['id_peg', 'jenjang'])
                ->groupBy('id_peg')
                ->map(fn ($records) => $records->first());

        $statuses = [];
        $positions = [];

        foreach ($employees as $employee) {
            $range = $this->ageRange($employee->tgl_lhr);
            $gender = strtolower((string) $employee->jk) === 'perempuan' ? 'Perempuan' : 'Laki-laki';
            $status = strtoupper(trim((string) $employee->status_kepeg));
            $status = $status !== '' ? $status : '-';
            $position = trim((string) $employee->jabatan);
            $position = $position !== '' ? $position : 'Non-Job';
            $level = strtoupper(trim((string) optional($education->get($employee->id_peg))->jenjang));

            if ($level === '') {
                $level = 'LAIN-LAIN';
            } elseif (in_array($level, ['D2', 'D3'], true)) {
                $level = 'D2/D3';
            } elseif (in_array($level, ['S2', 'S3'], true)) {
                $level = 'S2/S3';
            }

            if ($range !== null) {
                $counts[$range]['jk'][$gender]++;
                $counts[$range]['pend'][$level] = ($counts[$range]['pend'][$level] ?? 0) + 1;
                $counts[$range]['status'][$status] = ($counts[$range]['status'][$status] ?? 0) + 1;
                $counts[$range]['jabatan'][$position] = ($counts[$range]['jabatan'][$position] ?? 0) + 1;
            }
            $statuses[$status] = true;
            $positions[$position] = true;
        }

        $punishmentQuery = TbHukuman::query()
            ->from('tb_hukuman as h')
            ->join('tb_pegawai as p', 'h.id_peg', '=', 'p.id_peg')
            ->leftJoin('tb_jabatan as j', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')
                    ->where('j.status_jab', 'Aktif');
            })
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->whereYear('h.tgl_sk', $year);

        $this->applyOfficeFilter($punishmentQuery, $office);

        $punishments = [];
        foreach ($punishmentQuery->select(['p.tgl_lhr', 'h.hukuman'])->get() as $punishment) {
            $range = $this->ageRange($punishment->tgl_lhr);
            $type = strtoupper(trim((string) $punishment->hukuman));
            $type = $type !== '' ? $type : '-';
            if ($range !== null) {
                $counts[$range]['hukuman'][$type] = ($counts[$range]['hukuman'][$type] ?? 0) + 1;
            }
            $punishments[$type] = true;
        }

        $statusList = array_keys($statuses);
        $positionList = array_keys($positions);
        $punishmentList = array_keys($punishments);
        sort($statusList);
        sort($positionList);
        sort($punishmentList);

        $categories = [
            ['label' => 'JENIS KELAMIN', 'key' => 'jk', 'sub' => ['Laki-laki', 'Perempuan']],
            ['label' => 'JENIS PENDIDIKAN', 'key' => 'pend', 'sub' => ['SD', 'SMP', 'SMA', 'SMK', 'D1', 'D2/D3', 'S1', 'S2/S3', 'LAIN-LAIN']],
            ['label' => 'STATUS KEPEGAWAIAN', 'key' => 'status', 'sub' => $statusList],
            ['label' => 'JABATAN', 'key' => 'jabatan', 'sub' => $positionList],
            ['label' => 'HUKUMAN DISIPLIN (Tahun '.$year.')', 'key' => 'hukuman', 'sub' => $punishmentList],
        ];

        // Satu perhitungan dipakai bersama oleh halaman, cetak/PDF, dan Excel.
        $subtotals = [];

        foreach ($categories as $category) {
            $subtotal = array_fill_keys(self::AGE_RANGES, 0);

            foreach ($category['sub'] as $sub) {
                foreach (self::AGE_RANGES as $range) {
                    $subtotal[$range] += (int) ($counts[$range][$category['key']][$sub] ?? 0);
                }
            }

            $subtotal['total'] = array_sum($subtotal);
            $subtotals[$category['key']] = $subtotal;
        }

        return [
            'ranges' => self::AGE_RANGES,
            'counts' => $counts,
            'categories' => $categories,
            'subtotals' => $subtotals,
            'offices' => $this->offices($request),
            'filters' => ['kantor' => $office, 'tahun' => $year],
            'year' => $year,
        ];
    }

    private function formationData(Request $request): array
    {
        $office = $this->effectiveFormationOffice($request);
        $rows = $this->formationRows($office);

        return [
            'rows' => $rows,
            'totals' => [
                'kuota' => array_sum(array_column($rows, 'kuota')),
                'terisi' => array_sum(array_column($rows, 'terisi')),
                'sisa' => array_sum(array_column($rows, 'sisa')),
            ],
            'offices' => $this->formationOffices($request),
            'filters' => ['kode_cabang' => $office],
        ];
    }

    private function trainingCostData(Request $request): array
    {
        $year = $request->has('tahun')
            ? $request->string('tahun')->trim()->value()
            : (string) now()->year;
        $year = $year !== '' ? $year : (string) now()->year;

        $rows = $this->trainingCostQuery($request, $year)
            ->get()
            ->map(fn ($row): array => [
                'diklat' => $row->diklat,
                'penyelenggara' => $row->penyelenggara ?: '-',
                'tahun' => $row->tahun,
                'jumlah_peserta' => (int) $row->jumlah_peserta,
                'total_biaya_kegiatan' => (int) ($row->total_biaya_kegiatan ?? 0),
            ])
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'years' => TbDiklat::query()
                ->whereNotNull('tahun')
                ->where('tahun', '<>', '')
                ->distinct()
                ->orderByDesc('tahun')
                ->pluck('tahun')
                ->values()
                ->all(),
            'filters' => ['tahun' => $year],
            'totals' => [
                'peserta' => array_sum(array_column($rows, 'jumlah_peserta')),
                'biaya' => array_sum(array_column($rows, 'total_biaya_kegiatan')),
            ],
        ];
    }

    private function trainingCostQuery(Request $request, string $year): Builder
    {
        $query = TbDiklat::query()
            ->from('tb_diklat as d')
            ->when($year !== 'Semua', fn (Builder $builder) => $builder->where('d.tahun', $year));
        $this->applyTrainingAccessFilter($query, $request);

        return $query
            ->select(['d.diklat', 'd.penyelenggara', 'd.tahun'])
            ->selectRaw('COUNT(d.id_peg) as jumlah_peserta')
            ->selectRaw('SUM(d.biaya) as total_biaya_kegiatan')
            ->groupBy('d.diklat', 'd.penyelenggara', 'd.tahun')
            ->orderByDesc('total_biaya_kegiatan');
    }

    private function educationCostData(Request $request): array
    {
        $year = $request->string('tahun')->trim()->value();
        $year = preg_match('/^\d{4}$/', $year) === 1 ? $year : 'Semua';
        $quarter = $request->string('kuartal')->trim()->value();
        $quarter = in_array($quarter, ['1', '2', '3', '4'], true) ? $quarter : 'Semua';

        $rows = $this->educationCostQuery($year, $quarter)
            ->get()
            ->map(fn ($row): array => $this->presentEducationCostRow($row))
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'years' => TbBiayaPendidikan::query()
                ->whereNotNull('tgl_pengembangan_sdm')
                ->where('tgl_pengembangan_sdm', '<>', '0000-00-00')
                ->selectRaw('DISTINCT YEAR(tgl_pengembangan_sdm) as tahun')
                ->orderByDesc('tahun')
                ->pluck('tahun')
                ->map(fn ($value): string => (string) $value)
                ->values()
                ->all(),
            'filters' => [
                'tahun' => $year,
                'kuartal' => $quarter,
            ],
            'totals' => [
                'peserta' => array_sum(array_column($rows, 'jumlah_sdm')),
                'biaya' => array_sum(array_column($rows, 'total_biaya')),
            ],
        ];
    }

    private function educationCostQuery(string $year, string $quarter): Builder
    {
        $query = TbBiayaPendidikan::query()
            ->from('tb_biaya_pendidikan as b')
            ->leftJoin('tb_ref_pengembangan as r', 'b.kode_pengembangan', '=', 'r.kode_sandi')
            ->whereNotNull('b.tgl_pengembangan_sdm')
            ->where('b.tgl_pengembangan_sdm', '<>', '0000-00-00')
            ->when($year !== 'Semua', fn (Builder $builder) => $builder->whereRaw('YEAR(b.tgl_pengembangan_sdm) = ?', [$year]));

        if ($quarter !== 'Semua') {
            [$startMonth, $endMonth] = match ($quarter) {
                '1' => [1, 3],
                '2' => [4, 6],
                '3' => [7, 9],
                default => [10, 12],
            };
            $query->whereBetween(DB::raw('MONTH(b.tgl_pengembangan_sdm)'), [$startMonth, $endMonth]);
        }

        return $query
            ->select(['b.*', 'r.kategori as nama_kategori'])
            ->orderByDesc('b.tgl_pengembangan_sdm');
    }

    private function presentEducationCostRow(object $row): array
    {
        $date = $row->tgl_pengembangan_sdm;

        return [
            'kategori' => $row->nama_kategori ?: 'Kode: '.($row->kode_pengembangan ?: '-'),
            'pengembangan_sdm' => $row->pengembangan_sdm ?: '-',
            'pihak_pelaksana' => $row->pihak_pelaksana ?: '-',
            'tanggal' => $date ? date('d M Y', strtotime((string) $date)) : '-',
            'tanggal_export' => $date ? date('d/m/Y', strtotime((string) $date)) : '-',
            'tahun' => $date ? date('Y', strtotime((string) $date)) : '-',
            'jumlah_sdm' => (int) ($row->jumlah_sdm ?? 0),
            'total_biaya' => (int) ($row->total_biaya ?? 0),
        ];
    }

    private function applyTrainingAccessFilter(Builder $query, Request $request): void
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));

        if (in_array($role, ['admin', 'superadmin'], true)) {
            return;
        }

        $unit = (string) $request->session()->get('kode_kantor', '');

        if ($unit === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function ($subQuery) use ($unit): void {
            $subQuery->selectRaw('1')
                ->from('tb_jabatan as access_j')
                ->whereColumn('access_j.id_peg', 'd.id_peg')
                ->where('access_j.unit_kerja', $unit)
                ->where('access_j.status_jab', 'Aktif');
        });
    }

    private function formationRows(string $office): array
    {
        $masterQuery = TbRefJabatan::query()
            ->from('tb_ref_jabatan as m')
            ->leftJoin('tb_jabatan as j', function ($join) use ($office): void {
                $join->on('j.kode_jabatan', '=', 'm.kode_jabatan')
                    ->where('j.status_jab', 'Aktif');

                if ($office !== '' && $office !== 'PUSAT') {
                    $join->where('j.unit_kerja', $office);
                }
            })
            ->leftJoin('tb_pegawai as p', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')
                    ->where('p.status_aktif', 1);
            });

        if ($office === 'PUSAT') {
            $masterQuery->whereIn('m.lingkup', ['KP', 'KANWIL']);
        } elseif ($office !== '') {
            $masterQuery->where('m.lingkup', 'KC');
        }

        $masterRows = $masterQuery
            ->select(['m.jabatan as nama_jabatan', 'm.kuota'])
            ->selectRaw('COUNT(DISTINCT p.id_peg) as terisi')
            ->groupBy('m.kode_jabatan', 'm.jabatan', 'm.kuota')
            ->get()
            ->map(fn ($row): array => $this->presentFormationRow($row->nama_jabatan, (int) $row->kuota, (int) $row->terisi, 'Master'));

        $nonMasterQuery = TbJabatan::query()
            ->from('tb_jabatan as j')
            ->join('tb_pegawai as p', function ($join): void {
                $join->on('p.id_peg', '=', 'j.id_peg')
                    ->where('p.status_aktif', 1);
            })
            ->leftJoin('tb_ref_jabatan as m', 'm.kode_jabatan', '=', 'j.kode_jabatan')
            ->whereNull('m.kode_jabatan')
            ->where('j.status_jab', 'Aktif')
            ->when($office !== '' && $office !== 'PUSAT', fn (Builder $builder) => $builder->where('j.unit_kerja', $office));

        $nonMasterRows = $nonMasterQuery
            ->select('j.jabatan')
            ->selectRaw('COUNT(DISTINCT p.id_peg) as terisi')
            ->groupBy('j.jabatan')
            ->get()
            ->map(fn ($row): array => $this->presentFormationRow($row->jabatan, 0, (int) $row->terisi, 'NonMaster'));

        return $masterRows
            ->concat($nonMasterRows)
            ->sortBy([
                ['priority', 'asc'],
                ['nama_jabatan', 'asc'],
            ])
            ->values()
            ->all();
    }

    private function presentFormationRow(?string $name, int $quota, int $filled, string $source): array
    {
        $remaining = $quota - $filled;
        $percentage = $quota > 0 ? min(100, (int) round(($filled / $quota) * 100)) : 0;

        return [
            'nama_jabatan' => $name ?: '-',
            'kuota' => $quota,
            'terisi' => $filled,
            'sisa' => $remaining,
            'persentase' => $percentage,
            'sumber' => $source,
            'priority' => $source === 'Master' ? 1 : 2,
            'status' => $remaining > 0
                ? "Kurang {$remaining}"
                : ($remaining < 0 ? 'Over '.abs($remaining) : 'Terpenuhi'),
        ];
    }

    private function formationOffices(Request $request): array
    {
        $office = $this->effectiveFormationOffice($request);
        $role = strtolower((string) $request->session()->get('hak_akses'));

        return TbKantor::query()
            ->where('level', 'KC')
            ->when($role === 'kepala' && $office !== '', fn (Builder $builder) => $builder->where('kode_kantor_detail', $office))
            ->orderBy('nama_kantor')
            ->get(['kode_kantor_detail', 'nama_kantor'])
            ->map(fn (TbKantor $office): array => [
                'kode' => $office->kode_kantor_detail,
                'nama' => $office->nama_kantor,
            ])
            ->all();
    }

    private function effectiveFormationOffice(Request $request): string
    {
        if (strtolower((string) $request->session()->get('hak_akses')) === 'kepala') {
            return (string) $request->session()->get('kode_kantor', '');
        }

        return $request->string('kode_cabang')->trim()->value();
    }

    private function offices(Request $request): array
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $unit = $role === 'kepala' ? (string) $request->session()->get('kode_kantor') : '';

        return TbKantor::query()
            ->whereIn('level', ['KP', 'KANWIL', 'KC'])
            ->when($unit !== '', fn (Builder $builder) => $builder->where('kode_kantor_detail', $unit))
            ->orderBy('kode_kantor_detail')
            ->get(['kode_kantor_detail', 'nama_kantor', 'level'])
            ->map(fn (TbKantor $office): array => [
                'kode' => $office->kode_kantor_detail,
                'nama' => $office->nama_kantor,
                'level' => $office->level,
            ])
            ->all();
    }

    private function positions(Request $request): array
    {
        $query = TbJabatan::query()
            ->from('tb_jabatan as j')
            ->leftJoin('tb_kantor as k', 'j.unit_kerja', '=', 'k.kode_kantor_detail')
            ->where('j.status_jab', 'Aktif');

        $this->applyOfficeFilter($query, $this->effectiveOffice($request));

        return $query
            ->whereNotNull('j.jabatan')
            ->where('j.jabatan', '<>', '')
            ->select('j.jabatan')
            ->distinct()
            ->orderBy('j.jabatan')
            ->pluck('j.jabatan')
            ->values()
            ->all();
    }

    private function statuses(Request $request): array
    {
        return TbPegawai::query()
            ->where('status_aktif', 1)
            ->whereNotNull('status_kepeg')
            ->where('status_kepeg', '<>', '')
            ->distinct()
            ->orderBy('status_kepeg')
            ->pluck('status_kepeg')
            ->values()
            ->all();
    }

    private function presentNominative(object $item, int $index): array
    {
        return [
            'no' => $index + 1,
            'id_peg' => $item->id_peg,
            'nama' => $item->nama,
            'status_kepeg' => $item->status_kepeg,
            'jabatan' => $item->jabatan,
            'tmt_jabatan' => $this->formatDate($item->tmt_jabatan),
            'nama_kantor' => $item->nama_kantor,
            'jenjang' => $item->jenjang,
            'nama_sekolah' => $item->nama_sekolah,
        ];
    }

    private function effectiveOffice(Request $request): string
    {
        if (strtolower((string) $request->session()->get('hak_akses')) === 'kepala') {
            return (string) $request->session()->get('kode_kantor');
        }

        return $request->string('kantor')->trim()->value();
    }

    private function applyOfficeFilter(Builder $query, string $office): void
    {
        if ($office === '') {
            return;
        }

        $selectedOffice = TbKantor::query()->find($office);

        if ($selectedOffice?->level === 'KC' && $selectedOffice->kode_cabang) {
            $query->where('k.kode_cabang', $selectedOffice->kode_cabang);

            return;
        }

        $query->where('j.unit_kerja', $office);
    }

    private function ageRange(mixed $birthDate): ?string
    {
        if (! $birthDate) {
            return null;
        }

        try {
            $dateOfBirth = Carbon::parse($birthDate)->startOfDay();
            $today = now()->startOfDay();

            if ($dateOfBirth->gt($today)) {
                return null;
            }

            $age = $dateOfBirth->diff($today)->y;
        } catch (\Throwable) {
            return null;
        }

        return match (true) {
            $age <= 25 => '0-25',
            $age <= 35 => '26-35',
            $age <= 45 => '36-45',
            $age <= 55 => '46-55',
            default => '>55',
        };
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || (string) $value === '0000-00-00') {
            return null;
        }

        return date('d-m-Y', strtotime((string) $value));
    }

    private function ensureReportAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin', 'kepala'], true), 403);
    }
}
