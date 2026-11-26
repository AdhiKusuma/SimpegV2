<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PayrollGradeSyncService
{
    public function __construct(private readonly XlsxImportService $xlsx) {}

    /**
     * Sync payroll reference assignments from an XLSX snapshot.
     *
     * Only employee IDs already present in tb_pegawai are considered. The
     * method never creates employees and never removes assignment history.
     */
    public function sync(string $path, bool $dryRun = false, ?string $effectiveFrom = null): array
    {
        $this->ensureSchema();
        $effectiveFrom = $this->normaliseDate($effectiveFrom);
        $rows = $this->xlsx->rows($path);
        [$headerRow, $columns] = $this->findColumns($rows);

        $sourceRows = [];
        $duplicates = [];
        $defaultedWithoutGrade = 0;

        foreach (array_slice($rows, $headerRow + 1) as $offset => $row) {
            $employeeId = trim((string) ($row[$columns['IDPEG']] ?? ''));
            $grade = strtoupper(trim((string) ($row[$columns['GOL']] ?? '')));
            $officeCode = array_key_exists('KODEKANTOR', $columns)
                ? $this->nullableText($row[$columns['KODEKANTOR']] ?? null)
                : null;
            $masaKerja = array_key_exists('MK', $columns)
                ? $this->parseMasaKerja($row[$columns['MK']] ?? null)
                : null;
            $unitKerja = array_key_exists('UNITKERJA', $columns)
                ? $this->nullableText($row[$columns['UNITKERJA']] ?? null)
                : null;

            if ($employeeId === '') {
                continue;
            }

            if ($this->isEmptyGrade($grade)) {
                $grade = '0';
                $defaultedWithoutGrade++;
            }

            if (! preg_match('/\A[A-Z0-9][A-Z0-9_-]{0,5}\z/', $grade)) {
                throw new InvalidArgumentException(sprintf('Kode golongan "%s" pada baris sumber %d tidak valid.', $grade, $offset + $headerRow + 2));
            }

            $sourceRow = $offset + $headerRow + 2;
            if (isset($sourceRows[$employeeId])) {
                $existing = $sourceRows[$employeeId];
                $conflicting = $existing['grade'] !== $grade
                    || $existing['office_code'] !== $officeCode
                    || $existing['masa_kerja'] !== $masaKerja
                    || $existing['unit_kerja'] !== $unitKerja;
                if ($conflicting) {
                    $duplicates[] = $employeeId;
                }

                continue;
            }

            $sourceRows[$employeeId] = [
                'employee_id' => $employeeId,
                'office_code' => $officeCode,
                'grade' => $grade,
                'masa_kerja' => $masaKerja,
                'unit_kerja' => $unitKerja,
                'unit_lookup_key' => $unitKerja === null ? null : $this->unitLookupKey($officeCode, $unitKerja),
                'source_row_number' => $sourceRow,
            ];
        }

        if ($duplicates !== []) {
            throw new InvalidArgumentException('ID Pegawai memiliki referensi Golongan/MK/Unit Kerja berbeda di beberapa baris: '.implode(', ', array_values(array_unique($duplicates))));
        }

        $sourceFilename = basename($path);
        $employeeIds = array_keys($sourceRows);
        $existingIds = DB::table('tb_pegawai')
            ->whereIn('id_peg', $employeeIds)
            ->pluck('id_peg')
            ->map(fn ($id): string => (string) $id)
            ->all();
        $existingSet = array_fill_keys($existingIds, true);
        $missingIds = array_values(array_diff($employeeIds, $existingIds));
        $matchedRows = array_values(array_filter($sourceRows, fn (array $row): bool => isset($existingSet[$row['employee_id']])));
        $gradeCodes = array_values(array_unique(array_map(fn (array $row): string => $row['grade'], $matchedRows)));
        sort($gradeCodes);

        $currentAssignments = $this->currentAssignments($existingIds);
        $assignmentsToCreate = [];
        $unchanged = 0;
        foreach ($matchedRows as $row) {
            $current = $currentAssignments[$row['employee_id']] ?? null;
            $sameGrade = $current !== null && strtoupper((string) $current->grade_code) === $row['grade'];
            $sameMasaKerja = $row['masa_kerja'] === null
                || ($current !== null && (int) ($current->masa_kerja ?? -1) === $row['masa_kerja']);
            $sameUnitKerja = $row['unit_lookup_key'] === null
                || ($current !== null && (string) ($current->unit_lookup_key ?? '') === $row['unit_lookup_key']);
            if ($sameGrade && $sameMasaKerja && $sameUnitKerja) {
                $unchanged++;

                continue;
            }

            $assignmentsToCreate[] = $row + [
                'previous_grade' => $current?->grade_code,
            ];
        }

        $result = [
            'source_filename' => $sourceFilename,
            'source_rows' => count($sourceRows),
            'matched_employees' => count($matchedRows),
            'missing_employees' => count($missingIds),
            'missing_employee_ids' => $missingIds,
            'grade_codes' => $gradeCodes,
            'rows_defaulted_to_zero' => $defaultedWithoutGrade,
            'assignments_to_create' => count($assignmentsToCreate),
            'assignments_unchanged' => $unchanged,
            'effective_from' => $effectiveFrom,
            'dry_run' => $dryRun,
        ];

        if ($dryRun || $assignmentsToCreate === []) {
            return $result;
        }

        $unitRows = [];
        foreach ($matchedRows as $row) {
            if ($row['unit_lookup_key'] === null) {
                continue;
            }

            $unitRows[$row['unit_lookup_key']] = [
                'lookup_key' => $row['unit_lookup_key'],
                'name' => $row['unit_kerja'],
                'office_code' => $row['office_code'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($gradeCodes, $unitRows, $assignmentsToCreate, $sourceFilename, $effectiveFrom): void {
            $now = now();
            $masterRows = array_map(fn (string $code): array => [
                'code' => $code,
                'label' => $code,
                'is_active' => true,
                'source' => 'Import Excel Gol',
                'created_at' => $now,
                'updated_at' => $now,
            ], $gradeCodes);

            if ($masterRows !== []) {
                DB::table('payroll_grade_masters')->upsert(
                    $masterRows,
                    ['code'],
                    ['label', 'is_active', 'source', 'updated_at'],
                );
            }

            $masterIds = DB::table('payroll_grade_masters')
                ->whereIn('code', $gradeCodes)
                ->pluck('id', 'code');
            if ($unitRows !== []) {
                DB::table('payroll_unit_masters')->upsert(
                    array_values($unitRows),
                    ['lookup_key'],
                    ['name', 'office_code', 'is_active', 'updated_at'],
                );
            }
            $unitIds = $unitRows === []
                ? collect()
                : DB::table('payroll_unit_masters')
                    ->whereIn('lookup_key', array_keys($unitRows))
                    ->pluck('id', 'lookup_key');
            $closeDate = $effectiveFrom ?: $now->toDateString();

            foreach ($assignmentsToCreate as $assignment) {
                $employeeId = $assignment['employee_id'];
                $masterId = $masterIds[$assignment['grade']] ?? null;
                if ($masterId === null) {
                    continue;
                }

                DB::table('employee_payroll_assignments')
                    ->where('employee_id', $employeeId)
                    ->whereNull('effective_to')
                    ->update(['effective_to' => $closeDate, 'updated_at' => $now]);

                DB::table('employee_payroll_assignments')->insert([
                    'employee_id' => $employeeId,
                    'payroll_grade_master_id' => $masterId,
                    'payroll_unit_master_id' => $assignment['unit_lookup_key'] === null
                        ? null
                        : ($unitIds[$assignment['unit_lookup_key']] ?? null),
                    'masa_kerja' => $assignment['masa_kerja'],
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                    'source_filename' => $sourceFilename,
                    'source_row_number' => $assignment['source_row_number'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return $result;
    }

    private function ensureSchema(): void
    {
        if (! Schema::hasTable('payroll_grade_masters')
            || ! Schema::hasTable('payroll_unit_masters')
            || ! Schema::hasTable('employee_payroll_assignments')) {
            throw new InvalidArgumentException('Tabel master golongan payroll belum tersedia. Jalankan php artisan migrate terlebih dahulu.');
        }
    }

    private function findColumns(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $columns = [];
            foreach ($row as $column => $value) {
                $columns[$this->xlsx->normalizeHeader($value)] = (int) $column;
            }
            if (isset($columns['IDPEG'], $columns['GOL'])) {
                return [$index, $columns];
            }
        }

        throw new InvalidArgumentException('Header ID Peg dan Gol tidak ditemukan pada sheet pertama XLSX.');
    }

    private function currentAssignments(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $current = DB::table('employee_payroll_assignments as assignment')
            ->join('payroll_grade_masters as grade', 'grade.id', '=', 'assignment.payroll_grade_master_id')
            ->leftJoin('payroll_unit_masters as unit', 'unit.id', '=', 'assignment.payroll_unit_master_id')
            ->whereIn('assignment.employee_id', $employeeIds)
            ->whereNull('assignment.effective_to')
            ->orderByDesc('assignment.effective_from')
            ->orderByDesc('assignment.id')
            ->get([
                'assignment.id',
                'assignment.employee_id',
                'grade.code as grade_code',
                'assignment.masa_kerja',
                'unit.lookup_key as unit_lookup_key',
            ]);

        $result = [];
        foreach ($current as $row) {
            $result[(string) $row->employee_id] ??= $row;
        }

        return $result;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
    }

    private function unitLookupKey(?string $officeCode, string $name): string
    {
        $officeCode = strtolower(trim((string) $officeCode));
        $name = strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));

        return hash('sha256', $officeCode.'|'.$name);
    }

    private function parseMasaKerja(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        if (preg_match('/\A\d+(?:[.,]\d+)?\z/', $value) === 1) {
            $years = (int) round((float) str_replace(',', '.', $value));
        } elseif (preg_match('/\d+/', $value, $match) === 1) {
            $years = (int) $match[0];
        } else {
            throw new InvalidArgumentException(sprintf('Nilai MK "%s" tidak valid.', $value));
        }

        if ($years < 0 || $years > 100) {
            throw new InvalidArgumentException(sprintf('Nilai MK "%s" berada di luar rentang 0-100 tahun.', $value));
        }

        return $years;
    }

    private function isEmptyGrade(string $grade): bool
    {
        return $grade === ''
            || $grade === '-'
            || preg_match('/\A0(?:[.,]0+)?\z/', $grade) === 1;
    }

    private function normaliseDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
