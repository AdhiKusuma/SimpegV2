<?php

namespace App\Services;

use App\Models\PayrollImport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only projection for the Tukin export.
 *
 * The regular payroll values remain the source of truth. This service only
 * derives the two Tukin presentation modes when an export or payslip is
 * requested; it never writes to payroll tables.
 */
class PayrollTukinExportService
{
    public const DEFAULT_KPI = 0.95;

    public const HEADERS = [
        'ID Peg', 'Nama', 'Jabatan', 'Unit Kerja', 'Status', 'Gol', 'MK', 'S/I', 'Anak',
        'Gaji Pokok', 'Tunj. Suami/Istri', 'Tunj. Anak', 'Jumlah Gaji', 'Tunj. Jabatan',
        'Tunj. Ops', 'Tunj. Kinerja 100%', 'Tunj. Kinerja 75%', 'Tunj. Kinerja',
        'Tunj. Masa Kerja', 'Tunj. Fungsional', 'Honor Kontrak', 'Jumlah Gaji',
        'Potongan Angsuran', 'Potongan Absen', 'Potongan BPJS', 'Pengembalian Tunj.',
        'Pengalihan Keris', 'Uang Makan Kontrak', 'Gaji Diterima',
    ];

    public function __construct(private readonly PayrollEntryValidationService $entryValidator) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rowsForImport(
        PayrollImport $import,
        array $scope,
        string $officeCode = '',
        string $mode = 'real',
        float $kpi = self::DEFAULT_KPI,
    ): array {
        $rowsByMode = $this->rowsForImportBothModes($import, $scope, $officeCode, $kpi);

        return $rowsByMode[$this->normaliseMode($mode)] ?? [];
    }

    /**
     * Fetch the payroll source once and project it into both reference sheets.
     * This keeps the two-sheet export cheap even for a large payroll batch.
     *
     * @return array{real: array<int, array<string, mixed>>, 75: array<int, array<string, mixed>>}
     */
    public function rowsForImportBothModes(
        PayrollImport $import,
        array $scope,
        string $officeCode = '',
        float $kpi = self::DEFAULT_KPI,
    ): array {
        $real = [];
        $seventyFive = [];

        foreach ($this->sourceQuery($import, $scope, $officeCode)->get() as $entry) {
            $real[] = $this->project($entry, 'real', $kpi);
            $seventyFive[] = $this->project($entry, '75', $kpi);
        }

        return ['real' => $real, '75' => $seventyFive];
    }

    private function sourceQuery(PayrollImport $import, array $scope, string $officeCode)
    {
        $hasPayrollGradeReference = Schema::hasTable('employee_payroll_assignments')
            && Schema::hasTable('payroll_grade_masters')
            && Schema::hasTable('payroll_unit_masters');
        $hasMasaKerjaColumn = $hasPayrollGradeReference
            && Schema::hasColumn('employee_payroll_assignments', 'masa_kerja');
        $hasUnitKerjaColumn = $hasPayrollGradeReference
            && Schema::hasColumn('payroll_unit_masters', 'name');
        $latestPosition = DB::table('tb_jabatan')
            ->where('status_jab', 'Aktif')
            ->select('id_peg', DB::raw('MAX(id_jab) as latest_id'))
            ->groupBy('id_peg');
        $latestRank = DB::table('tb_pangkat')
            ->select('id_peg', DB::raw('MAX(id_pangkat) as latest_id'))
            ->groupBy('id_peg');
        $spouseCounts = DB::table('tb_suamiistri')
            ->select('id_peg', DB::raw('COUNT(*) as spouse_count'))
            ->groupBy('id_peg');
        $childCounts = DB::table('tb_anak')
            ->select('id_peg', DB::raw('COUNT(*) as child_count'))
            ->groupBy('id_peg');
        $currentPayrollAssignment = $hasPayrollGradeReference
            ? DB::table('employee_payroll_assignments')
                ->whereNull('effective_to')
                ->select('employee_id', DB::raw('MAX(id) as latest_id'))
                ->groupBy('employee_id')
            : null;

        $query = DB::table('payroll_entries as e')
            ->join('payroll_imports as i', 'i.id', '=', 'e.payroll_import_id')
            ->leftJoin('tb_pegawai as p', 'p.id_peg', '=', 'e.employee_id')
            ->leftJoinSub($latestPosition, 'latest_position', function ($join): void {
                $join->on('latest_position.id_peg', '=', 'e.employee_id');
            })
            ->leftJoin('tb_jabatan as j', 'j.id_jab', '=', 'latest_position.latest_id')
            ->leftJoinSub($latestRank, 'latest_rank', function ($join): void {
                $join->on('latest_rank.id_peg', '=', 'e.employee_id');
            })
            ->leftJoin('tb_pangkat as r', 'r.id_pangkat', '=', 'latest_rank.latest_id')
            ->leftJoinSub($spouseCounts, 'spouses', function ($join): void {
                $join->on('spouses.id_peg', '=', 'e.employee_id');
            })
            ->leftJoinSub($childCounts, 'children', function ($join): void {
                $join->on('children.id_peg', '=', 'e.employee_id');
            })
            ->leftJoin('tb_kantor as office', 'office.kode_kantor_detail', '=', 'e.office_code')
            ->leftJoin('tb_kantor as unit', 'unit.kode_kantor_detail', '=', 'j.unit_kerja');

        if ($hasPayrollGradeReference && $currentPayrollAssignment !== null) {
            $query
                ->leftJoinSub($currentPayrollAssignment, 'current_payroll_assignment', function ($join): void {
                    $join->on('current_payroll_assignment.employee_id', '=', 'e.employee_id');
                })
                ->leftJoin('employee_payroll_assignments as epa', 'epa.id', '=', 'current_payroll_assignment.latest_id')
                ->leftJoin('payroll_grade_masters as pgm', 'pgm.id', '=', 'epa.payroll_grade_master_id')
                ->leftJoin('payroll_unit_masters as pum', 'pum.id', '=', 'epa.payroll_unit_master_id');
        }

        $query
            ->where('e.payroll_import_id', $import->id)
            ->when($scope['restricted'] ?? false, fn ($builder) => $builder->whereIn('e.office_code', $scope['codes'] ?? []))
            ->when($officeCode !== '', fn ($builder) => $builder->where('e.office_code', $officeCode))
            ->select($this->sourceColumns($hasPayrollGradeReference, $hasMasaKerjaColumn, $hasUnitKerjaColumn))
            ->orderBy('e.office_code')
            ->orderBy('p.nama')
            ->orderBy('e.employee_id');

        return $query;
    }

    /**
     * Build one Tukin slip from the already-authorized payroll slip query.
     */
    public function projectExistingSlip(object $slip, string $mode = 'real', float $kpi = self::DEFAULT_KPI): array
    {
        $employeeId = (string) ($slip->employee_id ?? '');
        $spouseCount = (int) DB::table('tb_suamiistri')->where('id_peg', $employeeId)->count();
        $childCount = (int) DB::table('tb_anak')->where('id_peg', $employeeId)->count();

        if (Schema::hasTable('employee_payroll_assignments')
            && Schema::hasTable('payroll_grade_masters')
            && Schema::hasTable('payroll_unit_masters')) {
            $slip->payroll_grade_reference_available = 1;
            $assignment = DB::table('employee_payroll_assignments as assignment')
                ->join('payroll_grade_masters as grade', 'grade.id', '=', 'assignment.payroll_grade_master_id')
                ->leftJoin('payroll_unit_masters as unit', 'unit.id', '=', 'assignment.payroll_unit_master_id')
                ->where('assignment.employee_id', $employeeId)
                ->whereNull('assignment.effective_to')
                ->orderByDesc('assignment.effective_from')
                ->orderByDesc('assignment.id')
                ->first([
                    'grade.code as payroll_grade_code',
                    Schema::hasColumn('employee_payroll_assignments', 'masa_kerja')
                        ? 'assignment.masa_kerja as payroll_masa_kerja'
                        : DB::raw('NULL as payroll_masa_kerja'),
                    Schema::hasColumn('payroll_unit_masters', 'name')
                        ? 'unit.name as payroll_unit_name'
                        : DB::raw('NULL as payroll_unit_name'),
                ]);
            if ($assignment !== null) {
                $slip->payroll_grade_code = $assignment->payroll_grade_code;
                $slip->payroll_masa_kerja = $assignment->payroll_masa_kerja;
                $slip->payroll_unit_name = $assignment->payroll_unit_name;
            }
        } else {
            $slip->payroll_grade_reference_available = 0;
        }

        $slip->spouse_count = $spouseCount;
        $slip->child_count = $childCount;

        return $this->project($slip, $mode, $kpi);
    }

    public function normaliseMode(string $mode): string
    {
        return $mode === '75' ? '75' : 'real';
    }

    private function sourceColumns(bool $hasPayrollGradeReference, bool $hasMasaKerjaColumn, bool $hasUnitKerjaColumn): array
    {
        return [
            'e.employee_id', 'e.office_code', 'e.tgl_gajian', 'i.tahun', 'i.bulan',
            'e.gaji_pokok', 'e.golongan_pegawai', 'e.tunj_suami_istri', 'e.tunj_anak',
            'e.tunj_jabatan', 'e.tunj_ops', 'e.tunj_kinerja', 'e.tunj_masa_kerja',
            'e.tunj_fungsional', 'e.honor_kontrak', 'e.thl', 'e.uang_makan_kontrak',
            'e.bpjs_kesehatan', 'e.bpjs_tenaga_kerja', 'e.absensi', 'e.angsuran_kredit',
            'e.pengembalian_tunj_keluarga', 'e.keris',
            'p.nama as employee_name', 'p.status_kepeg', 'p.tmt_kerja',
            'j.jabatan as position_name', 'j.unit_kerja as unit_code',
            'unit.nama_kantor as unit_name', 'office.nama_kantor as office_name',
            'r.gol as grade',
            $hasPayrollGradeReference
                ? DB::raw('pgm.code as payroll_grade_code')
                : DB::raw('NULL as payroll_grade_code'),
            $hasMasaKerjaColumn
                ? DB::raw('epa.masa_kerja as payroll_masa_kerja')
                : DB::raw('NULL as payroll_masa_kerja'),
            $hasUnitKerjaColumn
                ? DB::raw('pum.name as payroll_unit_name')
                : DB::raw('NULL as payroll_unit_name'),
            DB::raw($hasPayrollGradeReference
                ? '1 as payroll_grade_reference_available'
                : '0 as payroll_grade_reference_available'),
            DB::raw('COALESCE(spouses.spouse_count, 0) as spouse_count'),
            DB::raw('COALESCE(children.child_count, 0) as child_count'),
        ];
    }

    private function project(object $entry, string $mode, float $kpi): array
    {
        $mode = $this->normaliseMode($mode);
        $kpiBasisPoints = $this->kpiBasisPoints($kpi);
        $amounts = $this->entryValidator->applyGradeToBaseSalaryForExport([
            'gaji_pokok' => (string) ($entry->gaji_pokok ?? 0),
            'golongan_pegawai' => (string) ($entry->golongan_pegawai ?? 0),
        ]);

        $baseSalary = $this->cents($amounts['gaji_pokok']);
        $spouseAllowance = $this->cents($entry->tunj_suami_istri ?? 0);
        $childAllowance = $this->cents($entry->tunj_anak ?? 0);
        $positionAllowance = $this->cents($entry->tunj_jabatan ?? 0);
        $operationalAllowance = $this->cents($entry->tunj_ops ?? 0);
        $performance100 = $this->cents($entry->tunj_kinerja ?? 0);
        $performance75 = intdiv($performance100 * 75 + 50, 100);
        $performanceReal = $performance75 + intdiv((($performance100 - $performance75) * $kpiBasisPoints) + 5000, 10000);
        $performance = $mode === '75' ? $performance75 : $performanceReal;
        $serviceAllowance = $this->cents($entry->tunj_masa_kerja ?? 0);
        $functionalAllowance = $this->cents($entry->tunj_fungsional ?? 0);
        $contractHonor = $this->cents($entry->honor_kontrak ?? 0) + $this->cents($entry->thl ?? 0);
        $mealAllowance = $this->cents($entry->uang_makan_kontrak ?? 0);
        $baseTotal = $baseSalary + $spouseAllowance + $childAllowance;
        $gross = $baseTotal + $positionAllowance + $operationalAllowance + $performance
            + $serviceAllowance + $functionalAllowance + $contractHonor;
        $installment = $this->cents($entry->angsuran_kredit ?? 0);
        $attendance = $this->cents($entry->absensi ?? 0);
        $bpjsKesehatan = $this->cents($entry->bpjs_kesehatan ?? 0);
        $bpjsTenagaKerja = $this->cents($entry->bpjs_tenaga_kerja ?? 0);
        $bpjs = $bpjsKesehatan + $bpjsTenagaKerja;
        $familyReturn = $this->cents($entry->pengembalian_tunj_keluarga ?? 0);
        $keris = $this->cents($entry->keris ?? 0);
        $totalDeductions = $installment + $attendance + $bpjs + $familyReturn + $keris;
        $net = $gross - $totalDeductions + $mealAllowance;
        $grade = trim((string) ($entry->payroll_grade_code ?? ''));
        $gradeReferenceAvailable = (int) ($entry->payroll_grade_reference_available ?? 0) === 1;
        if ($grade === '' && ! $gradeReferenceAvailable) {
            $grade = trim((string) ($entry->grade ?? ''));
        }
        if ($grade === '') {
            $grade = '0';
        }
        $unitName = trim((string) ($entry->payroll_unit_name ?? ''));
        if ($unitName === '') {
            $unitName = trim((string) ($entry->unit_name ?? $entry->office_name ?? $entry->office_code ?? ''));
        }

        return [
            'employee_id' => (string) ($entry->employee_id ?? ''),
            'employee_name' => (string) ($entry->employee_name ?? 'Nama pegawai tidak ditemukan'),
            'position_name' => (string) ($entry->position_name ?? ''),
            'unit_name' => $unitName,
            'unit_code' => (string) ($entry->unit_code ?? ''),
            'office_name' => (string) ($entry->office_name ?? $entry->office_code ?? ''),
            'office_code' => (string) ($entry->office_code ?? ''),
            'employment_status' => (string) ($entry->status_kepeg ?? ''),
            'grade' => $grade,
            'service_years' => $this->serviceYears($entry),
            'spouse_count' => (int) ($entry->spouse_count ?? 0),
            'child_count' => (int) ($entry->child_count ?? 0),
            'gaji_pokok' => $this->money($baseSalary),
            'tunj_suami_istri' => $this->money($spouseAllowance),
            'tunj_anak' => $this->money($childAllowance),
            'jumlah_gaji_dasar' => $this->money($baseTotal),
            'tunj_jabatan' => $this->money($positionAllowance),
            'tunj_ops' => $this->money($operationalAllowance),
            'tunj_kinerja_100' => $this->money($performance100),
            'tunj_kinerja_75' => $this->money($performance75),
            'tunj_kinerja_real' => $this->money($performanceReal),
            'tunj_kinerja' => $this->money($performance),
            'tunj_masa_kerja' => $this->money($serviceAllowance),
            'tunj_fungsional' => $this->money($functionalAllowance),
            'honor_kontrak' => $this->money($contractHonor),
            'jumlah_pendapatan' => $this->money($gross),
            'potongan_angsuran' => $this->money($installment),
            'potongan_absen' => $this->money($attendance),
            'potongan_bpjs_kesehatan' => $this->money($bpjsKesehatan),
            'potongan_bpjs_tenaga_kerja' => $this->money($bpjsTenagaKerja),
            'potongan_bpjs' => $this->money($bpjs),
            'pengembalian_tunjangan' => $this->money($familyReturn),
            'pengalihan_keris' => $this->money($keris),
            'uang_makan_kontrak' => $this->money($mealAllowance),
            'potongan_total' => $this->money($totalDeductions),
            'gaji_diterima' => $this->money($net),
            'mode' => $mode,
            'kpi' => $kpiBasisPoints / 10000,
            'period' => sprintf('%02d-%04d', (int) ($entry->bulan ?? 0), (int) ($entry->tahun ?? 0)),
            'tahun' => (int) ($entry->tahun ?? 0),
            'bulan' => (int) ($entry->bulan ?? 0),
            'payment_date' => $entry->tgl_gajian ? (string) $entry->tgl_gajian : null,
        ];
    }

    private function serviceYears(object $entry): int
    {
        if (($entry->payroll_masa_kerja ?? null) !== null && trim((string) $entry->payroll_masa_kerja) !== '') {
            return max(0, (int) $entry->payroll_masa_kerja);
        }

        if (empty($entry->tmt_kerja)) {
            return 0;
        }

        try {
            $asOf = ! empty($entry->tgl_gajian) ? Carbon::parse((string) $entry->tgl_gajian) : now();

            return max(0, (int) Carbon::parse((string) $entry->tmt_kerja)->diffInYears($asOf));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function kpiBasisPoints(float $kpi): int
    {
        return max(0, min(10000, (int) round($kpi * 10000)));
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
