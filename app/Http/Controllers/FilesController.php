<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbKantor;
use App\Services\PayslipPdfService;
use App\Services\RbacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class FilesController extends Controller
{
    private const EARNING_FIELDS = [
        'gaji_pokok' => 'Gaji Pokok',
        'tunj_suami_istri' => 'Tunjangan Suami/Istri',
        'tunj_anak' => 'Tunjangan Anak',
        'tunj_jabatan' => 'Tunjangan Jabatan',
        'tunj_ops' => 'Tunjangan Operasional',
        'tunj_kinerja' => 'Tunjangan Kinerja',
        'tunj_masa_kerja' => 'Tunjangan Masa Kerja',
        'tunj_fungsional' => 'Tunjangan Fungsional',
        'honor_kontrak' => 'Honor Kontrak',
        'uang_makan_kontrak' => 'Uang Makan Kontrak',
        'thl' => 'Honor THL',
    ];

    private const DEDUCTION_FIELDS = [
        'angsuran_kredit' => 'Pinjaman Pegawai',
        'absensi' => 'Keterlambatan/Absen',
        'bpjs_kesehatan' => 'Iuran Bpjs Kesehatan',
        'bpjs_tenaga_kerja' => 'Iuran Bpjs Tenaga Kerja',
        'keris' => 'K-ERIS',
    ];

    public function index(Request $request, RbacService $rbac): Response
    {
        $isAdministrative = $rbac->can($request, 'payroll.view');
        $employeeId = trim((string) ($request->session()->get('id_pegawai') ?? ''));
        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'employee_id' => ['nullable', 'string', 'max:150'],
        ]);

        $selectedEmployee = $isAdministrative
            ? trim((string) ($validated['employee_id'] ?? ''))
            : $employeeId;
        abort_if(! $isAdministrative && $employeeId === '', 403, 'Akun Anda belum terhubung ke data pegawai.');

        $scope = $isAdministrative ? $this->officeScope($request) : ['restricted' => false, 'codes' => [], 'label' => null];
        $query = $this->slipQuery($scope, $selectedEmployee, $isAdministrative)
            ->when(isset($validated['tahun']), fn ($builder) => $builder->where('i.tahun', $validated['tahun']))
            ->when(isset($validated['bulan']), fn ($builder) => $builder->where('i.bulan', $validated['bulan']))
            ->orderByDesc('i.tahun')
            ->orderByDesc('i.bulan')
            ->orderBy('p.nama')
            ->orderBy('e.employee_id');

        $slips = $query->paginate(20)->withQueryString()->through(fn (object $slip): array => [
            'payroll_import_id' => (int) $slip->payroll_import_id,
            'employee_id' => (string) $slip->employee_id,
            'employee_name' => (string) ($slip->employee_name ?: 'Nama pegawai tidak ditemukan'),
            'office_name' => (string) ($slip->office_name ?: $slip->office_code),
            'office_code' => (string) $slip->office_code,
            'period' => sprintf('%02d-%04d', $slip->bulan, $slip->tahun),
            'payment_date' => $slip->tgl_gajian ? (string) $slip->tgl_gajian : null,
            'net_salary' => (string) $slip->gaji_bersih,
        ]);

        $periods = $this->periodQuery($scope, $selectedEmployee, $isAdministrative)
            ->select(['i.tahun', 'i.bulan'])
            ->distinct()
            ->orderByDesc('i.tahun')
            ->orderByDesc('i.bulan')
            ->get()
            ->map(fn (object $period): array => [
                'value' => sprintf('%04d-%02d', $period->tahun, $period->bulan),
                'label' => sprintf('%02d-%04d', $period->bulan, $period->tahun),
                'tahun' => (int) $period->tahun,
                'bulan' => (int) $period->bulan,
            ])
            ->values()
            ->all();

        return Inertia::render('Files/Index', [
            'slips' => $slips,
            'periods' => $periods,
            'filters' => [
                'tahun' => isset($validated['tahun']) ? (int) $validated['tahun'] : null,
                'bulan' => isset($validated['bulan']) ? (int) $validated['bulan'] : null,
                'employee_id' => $selectedEmployee,
            ],
            'isAdministrative' => $isAdministrative,
            'scopeLabel' => $scope['label'],
            'templateAvailable' => $this->templateAvailable(),
        ]);
    }

    public function show(Request $request, int $payrollImport, string $employeeId, RbacService $rbac): Response
    {
        $slip = $this->resolveSlip($request, $payrollImport, $employeeId, $rbac);

        return Inertia::render('Files/Show', [
            'slip' => $this->presentSlip($slip),
            'templateAvailable' => $this->templateAvailable(),
            'isAdministrative' => $rbac->can($request, 'payroll.view'),
        ]);
    }

    /**
     * List published slips for the employee linked to the session.
     *
     * The profile shortcut uses the same payroll data source as FILES while
     * keeping the list scoped to the current account.
     */
    public function mine(Request $request): Response|RedirectResponse
    {
        if (strtolower((string) $request->session()->get('hak_akses', '')) === 'user') {
            return redirect()->route('files.index');
        }

        $employeeId = trim((string) ($request->session()->get('id_pegawai') ?? ''));
        if ($employeeId === '') {
            return redirect()->route('profile')->with('error', 'Akun Anda belum terhubung ke data pegawai.');
        }

        $validated = $request->validate([
            'tahun' => ['nullable', 'integer', 'between:2000,2100'],
            'bulan' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $scope = ['restricted' => false, 'codes' => [], 'label' => null];
        $query = $this->slipQuery($scope, $employeeId)
            ->when(isset($validated['tahun']), fn ($builder) => $builder->where('i.tahun', $validated['tahun']))
            ->when(isset($validated['bulan']), fn ($builder) => $builder->where('i.bulan', $validated['bulan']))
            ->orderByDesc('i.tahun')
            ->orderByDesc('i.bulan')
            ->orderBy('e.employee_id');

        $slips = $query->paginate(20)->withQueryString()->through(fn (object $slip): array => [
            'payroll_import_id' => (int) $slip->payroll_import_id,
            'employee_id' => (string) $slip->employee_id,
            'employee_name' => (string) ($slip->employee_name ?: 'Nama pegawai tidak ditemukan'),
            'office_name' => (string) ($slip->office_name ?: $slip->office_code),
            'office_code' => (string) $slip->office_code,
            'period' => sprintf('%02d-%04d', $slip->bulan, $slip->tahun),
            'payment_date' => $slip->tgl_gajian ? (string) $slip->tgl_gajian : null,
            'net_salary' => (string) $slip->gaji_bersih,
        ]);

        $periods = $this->periodQuery($scope, $employeeId)
            ->select(['i.tahun', 'i.bulan'])
            ->distinct()
            ->orderByDesc('i.tahun')
            ->orderByDesc('i.bulan')
            ->get()
            ->map(fn (object $period): array => [
                'value' => sprintf('%04d-%02d', $period->tahun, $period->bulan),
                'label' => sprintf('%02d-%04d', $period->bulan, $period->tahun),
                'tahun' => (int) $period->tahun,
                'bulan' => (int) $period->bulan,
            ])
            ->values()
            ->all();

        return Inertia::render('Files/Index', [
            'slips' => $slips,
            'periods' => $periods,
            'filters' => [
                'tahun' => isset($validated['tahun']) ? (int) $validated['tahun'] : null,
                'bulan' => isset($validated['bulan']) ? (int) $validated['bulan'] : null,
                'employee_id' => $employeeId,
            ],
            'isAdministrative' => false,
            'scopeLabel' => 'Hanya slip gaji yang terkait dengan akun Anda.',
            'templateAvailable' => $this->templateAvailable(),
            'pageTitle' => 'Slip Gaji Saya',
            'sectionLabel' => 'PROFIL SAYA · SLIP GAJI',
            'pageDescription' => 'Lihat dan filter slip gaji milik Anda tanpa membuka daftar pegawai lain.',
            'indexUrl' => route('profile.payslip'),
            'detailUrlPrefix' => route('profile.payslip'),
        ]);
    }

    public function mineShow(Request $request, int $payrollImport, string $employeeId): Response|RedirectResponse
    {
        if (strtolower((string) $request->session()->get('hak_akses', '')) === 'user') {
            return redirect()->route('files.show', ['payrollImport' => $payrollImport, 'employeeId' => $employeeId]);
        }

        $sessionEmployeeId = trim((string) ($request->session()->get('id_pegawai') ?? ''));
        $employeeId = trim($employeeId);
        abort_if($sessionEmployeeId === '' || $employeeId !== $sessionEmployeeId, 403);

        $slip = $this->slipQuery(['restricted' => false, 'codes' => [], 'label' => null], $employeeId)
            ->where('i.id', $payrollImport)
            ->first();

        if ($slip === null) {
            abort(404);
        }

        return Inertia::render('Files/Show', [
            'slip' => $this->presentSlip($slip),
            'templateAvailable' => $this->templateAvailable(),
            'isAdministrative' => false,
            'backHref' => route('profile.payslip'),
            'backLabel' => 'Kembali ke Slip Gaji Saya',
            'sectionLabel' => 'PROFIL SAYA · SLIP GAJI',
        ]);
    }

    public function download(Request $request, int $payrollImport, string $employeeId, RbacService $rbac, PayslipPdfService $pdf): mixed
    {
        $slip = $this->resolveSlip($request, $payrollImport, $employeeId, $rbac);
        abort_unless($this->templateAvailable(), 409, 'Referensi template PDF slip gaji belum tersedia. Sediakan kembali template tersebut sebelum export slip.');

        return $pdf->download($this->presentSlip($slip));
    }

    private function resolveSlip(Request $request, int $payrollImport, string $employeeId, RbacService $rbac): object
    {
        $isAdministrative = $rbac->can($request, 'payroll.view');
        $sessionEmployeeId = trim((string) ($request->session()->get('id_pegawai') ?? ''));
        $employeeId = trim($employeeId);
        abort_if(! $isAdministrative && ($sessionEmployeeId === '' || $employeeId !== $sessionEmployeeId), 403);

        $scope = $isAdministrative ? $this->officeScope($request) : ['restricted' => false, 'codes' => [], 'label' => null];
        $slip = $this->slipQuery($scope, $employeeId)
            ->where('i.id', $payrollImport)
            ->first();

        abort_if($slip === null, 404);

        return $slip;
    }

    private function slipQuery(array $scope, string $employeeSearch = '', bool $searchByName = false)
    {
        $latestPosition = DB::table('tb_jabatan')
            ->where('status_jab', 'Aktif')
            ->select('id_peg', DB::raw('MAX(id_jab) as latest_id'))
            ->groupBy('id_peg');
        $amountColumns = [
            'gaji_pokok', 'golongan_pegawai', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops',
            'tunj_kinerja', 'tunj_fungsional', 'tunj_masa_kerja', 'honor_kontrak', 'thl', 'tunj_pajak',
            'uang_makan_kontrak', 'jumlah_gaji', 'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi',
            'angsuran_kredit', 'pengembalian_tunj_keluarga', 'keris', 'jumlah_potongan', 'gaji_bersih', 'total',
        ];
        $availableAmountColumns = array_values(array_filter(
            $amountColumns,
            fn (string $column): bool => Schema::hasColumn('payroll_entries', $column),
        ));

        $query = DB::table('payroll_entries as e')
            ->join('payroll_imports as i', function ($join): void {
                $join->on('i.id', '=', 'e.payroll_import_id')->where('i.is_current', true);
            })
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'e.employee_id')
            ->leftJoinSub($latestPosition, 'latest_position', function ($join): void {
                $join->on('latest_position.id_peg', '=', 'e.employee_id');
            })
            ->leftJoin('tb_jabatan as j', 'j.id_jab', '=', 'latest_position.latest_id')
            ->leftJoin('tb_kantor as k', 'k.kode_kantor_detail', '=', 'e.office_code')
            ->leftJoin('tb_kantor as unit', 'unit.kode_kantor_detail', '=', 'j.unit_kerja');
        if ($this->publicationTableAvailable()) {
            $query->leftJoin('payroll_publications as publication', 'publication.payroll_import_id', '=', 'i.id')
                ->where(fn ($builder) => $builder->whereNull('publication.id')->orWhere('publication.status', 'sent'));
        }

        return $query
            ->when($employeeSearch !== '', function ($builder) use ($employeeSearch, $searchByName): void {
                if (! $searchByName) {
                    $builder->where('e.employee_id', $employeeSearch);

                    return;
                }

                $like = '%'.$employeeSearch.'%';
                $builder->where(function ($query) use ($like): void {
                    $query->where('e.employee_id', 'like', $like)
                        ->orWhere('p.nama', 'like', $like);
                });
            })
            ->when($scope['restricted'], fn ($builder) => $builder->whereIn('e.office_code', $scope['codes']))
            ->select([
                'e.id', 'e.payroll_import_id', 'e.employee_id', 'e.office_code', 'e.tgl_gajian',
                'e.nama_nasabah', 'e.no_rekening',
                ...array_map(fn (string $column): string => 'e.'.$column, $availableAmountColumns),
                'i.tahun', 'i.bulan',
                'p.nama as employee_name', 'p.status_kepeg', 'j.jabatan as position_name',
                'j.unit_kerja as unit_code', 'unit.nama_kantor as unit_name', 'k.nama_kantor as office_name',
            ]);
    }

    private function periodQuery(array $scope, string $employeeSearch = '', bool $searchByName = false)
    {
        $query = DB::table('payroll_entries as e')
            ->join('payroll_imports as i', function ($join): void {
                $join->on('i.id', '=', 'e.payroll_import_id')->where('i.is_current', true);
            })
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'e.employee_id');
        if ($this->publicationTableAvailable()) {
            $query->leftJoin('payroll_publications as publication', 'publication.payroll_import_id', '=', 'i.id')
                ->where(fn ($builder) => $builder->whereNull('publication.id')->orWhere('publication.status', 'sent'));
        }

        return $query
            ->when($employeeSearch !== '', function ($builder) use ($employeeSearch, $searchByName): void {
                if (! $searchByName) {
                    $builder->where('e.employee_id', $employeeSearch);

                    return;
                }

                $like = '%'.$employeeSearch.'%';
                $builder->where(function ($query) use ($like): void {
                    $query->where('e.employee_id', 'like', $like)
                        ->orWhere('p.nama', 'like', $like);
                });
            })
            ->when($scope['restricted'], fn ($builder) => $builder->whereIn('e.office_code', $scope['codes']));
    }

    private function publicationTableAvailable(): bool
    {
        return Schema::hasTable('payroll_publications');
    }

    private function presentSlip(object $slip): array
    {
        $amount = static fn (string $field): string => (string) ($slip->{$field} ?? '0.00');
        $fields = static function (array $labels) use ($amount): array {
            return collect($labels)->map(fn (string $label, string $field): array => [
                'field' => $field,
                'label' => $label,
                'amount' => $amount($field),
            ])->values()->all();
        };

        return [
            'payroll_import_id' => (int) $slip->payroll_import_id,
            'employee_id' => (string) $slip->employee_id,
            'employee_name' => (string) ($slip->employee_name ?: 'Nama pegawai tidak ditemukan'),
            'employment_status' => (string) ($slip->status_kepeg ?? ''),
            'position_name' => (string) ($slip->position_name ?? ''),
            'unit_name' => (string) ($slip->unit_name ?? ''),
            'unit_code' => (string) ($slip->unit_code ?? ''),
            'office_name' => (string) ($slip->office_name ?: $slip->office_code),
            'office_code' => (string) $slip->office_code,
            'period' => sprintf('%02d-%04d', $slip->bulan, $slip->tahun),
            'tahun' => (int) $slip->tahun,
            'bulan' => (int) $slip->bulan,
            'payment_date' => $slip->tgl_gajian ? (string) $slip->tgl_gajian : null,
            'nama_nasabah' => (string) ($slip->nama_nasabah ?? ''),
            'no_rekening' => (string) ($slip->no_rekening ?? ''),
            'earnings' => $fields(self::EARNING_FIELDS),
            'deductions' => $fields(self::DEDUCTION_FIELDS),
            'gross_salary' => $amount('jumlah_gaji'),
            'total_deductions' => $amount('jumlah_potongan'),
            'net_salary' => $amount('gaji_bersih'),
            'total' => $amount('total'),
        ];
    }

    private function templateAvailable(): bool
    {
        return is_file(resource_path('views/files/payslip.blade.php'));
    }

    private function officeScope(Request $request): array
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return ['restricted' => false, 'codes' => [], 'label' => null];
        }

        $officeCode = $role === 'kepala'
            ? (string) $request->session()->get('kode_kantor', '')
            : (string) TbJabatan::query()
                ->where('id_peg', (string) $request->session()->get('id_pegawai', ''))
                ->where('status_jab', 'Aktif')
                ->orderByDesc('id_jab')
                ->value('unit_kerja');
        $office = TbKantor::query()->where('kode_kantor_detail', $officeCode)->first(['kode_kantor_detail', 'kode_cabang', 'nama_kantor', 'level']);
        if (! $office) {
            return ['restricted' => true, 'codes' => [], 'label' => 'Kantor akun belum tercatat'];
        }

        $codes = strtoupper((string) $office->level) === 'KC' && $office->kode_cabang
            ? TbKantor::query()->where('kode_cabang', $office->kode_cabang)->pluck('kode_kantor_detail')->map(fn ($code): string => (string) $code)->all()
            : [(string) $office->kode_kantor_detail];

        return ['restricted' => true, 'codes' => $codes, 'label' => 'Wilayah: '.trim((string) $office->nama_kantor)];
    }
}
