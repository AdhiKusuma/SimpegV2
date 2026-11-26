<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * Reference-only checks derived from "Kepdir 090 VI 2021 - Grade Gaji Kelas Cabang".
 * These rules never calculate or overwrite payroll values.
 */
class PayrollMasterValidationService
{
    private const BRANCH_CLASSES = [
        'REMBANG' => 'KELAS 3', 'PATI' => 'KELAS 3', 'DEMAK' => 'KELAS 1', 'KENDAL' => 'KELAS 3',
        'SALATIGA' => 'KELAS 3', 'SEMARANG' => 'KELAS 2', 'WONOGIRI' => 'KELAS 1', 'KOTA SURAKARTA' => 'KELAS 3',
        'KARANGANYAR' => 'KELAS 2', 'SUKOHARJO' => 'KELAS 3', 'SRAGEN' => 'KELAS 1', 'BOYOLALI' => 'KELAS 3',
        'MAGELANG' => 'KELAS 3', 'WONOSOBO' => 'KELAS 3', 'PURWOREJO' => 'KELAS 3', 'KEBUMEN' => 'KELAS 3',
        'BANJARNEGARA' => 'KELAS 3', 'PURBALINGGA' => 'KELAS 3', 'BANYUMAS' => 'KELAS 1', 'CILACAP' => 'KELAS 3',
        'TEGAL' => 'KELAS 1', 'BREBES' => 'KELAS 1', 'KOTA TEGAL' => 'KELAS 3', 'PEMALANG' => 'KELAS 1',
        'KOTA PEKALONGAN' => 'KELAS 3', 'PEKALONGAN' => 'KELAS 3', 'BATANG' => 'KELAS 3',
    ];

    private const POSITION_LABELS = [
        'division' => 'Kepala Divisi/Satuan Kerja', 'branch_coordinator' => 'Kepala Cabang Koordinator',
        'main_branch' => 'Kepala Cabang Utama', 'deputy_branch_coordinator' => 'Wakil Kepala Cabang Koordinator',
        'branch' => 'Kepala Cabang', 'field_head' => 'Kepala Bidang', 'subfield_head' => 'Kepala Sub Bidang',
        'cash_office_head' => 'Kepala Kantor Kas', 'staff' => 'Staf', 'non_staff' => 'Non Staf',
    ];

    private const POSITION_ALLOWANCES = [
        'division' => ['tunj_jabatan' => 10300000, 'tunj_ops' => 8500000, 'tunj_kinerja' => 7500000],
        'branch_coordinator' => ['tunj_jabatan' => 5150000, 'tunj_ops' => 7650000, 'tunj_kinerja' => 7500000],
        'main_branch' => ['tunj_jabatan' => 5150000, 'tunj_ops' => 7225000, 'tunj_kinerja' => 7500000],
        'deputy_branch_coordinator' => ['tunj_jabatan' => 4120000, 'tunj_ops' => 7225000, 'tunj_kinerja' => 7500000],
        'branch' => ['tunj_jabatan' => 4120000], 'field_head' => ['tunj_jabatan' => 2575000],
        'subfield_head' => ['tunj_jabatan' => 1545000], 'cash_office_head' => ['tunj_jabatan' => 1236000],
    ];

    private const CLASS_ALLOWANCES = [
        'branch' => [
            'tunj_ops' => ['KELAS 1' => 7225000, 'KELAS 2' => 6375000, 'KELAS 3' => 5100000],
            'tunj_kinerja' => ['KELAS 1' => 7500000, 'KELAS 2' => 6375000, 'KELAS 3' => 4500000],
        ],
        'field_head' => [
            'tunj_ops' => ['KANTOR PUSAT' => 5525000, 'KANTOR WILAYAH' => 4335000, 'KC UTAMA' => 4335000, 'KELAS 1' => 4335000, 'KELAS 2' => 3825000, 'KELAS 3' => 3060000],
            'tunj_kinerja' => ['KANTOR PUSAT' => 4875000, 'KANTOR WILAYAH' => 4500000, 'KC UTAMA' => 4500000, 'KELAS 1' => 4500000, 'KELAS 2' => 3825000, 'KELAS 3' => 2700000],
        ],
        'subfield_head' => [
            'tunj_ops' => ['KANTOR PUSAT' => 3400000, 'KANTOR WILAYAH' => 2528750, 'KC UTAMA' => 2528750, 'KELAS 1' => 2528750, 'KELAS 2' => 1912500, 'KELAS 3' => 1530000],
            'tunj_kinerja' => ['KANTOR PUSAT' => 3000000, 'KANTOR WILAYAH' => 2625000, 'KC UTAMA' => 2625000, 'KELAS 1' => 2625000, 'KELAS 2' => 2231250, 'KELAS 3' => 2025000],
        ],
        'cash_office_head' => [
            'tunj_ops' => ['KANTOR WILAYAH' => 2023000, 'KC UTAMA' => 2023000, 'KELAS 1' => 2023000, 'KELAS 2' => 1753125, 'KELAS 3' => 1657500],
            'tunj_kinerja' => ['KANTOR WILAYAH' => 2625000, 'KC UTAMA' => 2625000, 'KELAS 1' => 2625000, 'KELAS 2' => 2231250, 'KELAS 3' => 2025000],
        ],
        'staff' => [
            'tunj_ops' => ['KANTOR PUSAT' => 1530000, 'KANTOR WILAYAH' => 1372750, 'KC UTAMA' => 1372750, 'KELAS 1' => 1372750, 'KELAS 2' => 1275000, 'KELAS 3' => 1071000],
            'tunj_kinerja' => ['KANTOR PUSAT' => 1687500, 'KANTOR WILAYAH' => 1687500, 'KC UTAMA' => 1687500, 'KELAS 1' => 1687500, 'KELAS 2' => 1434375, 'KELAS 3' => 1012500],
        ],
        'non_staff' => ['tunj_kinerja' => ['KANTOR PUSAT' => 1250000, 'KANTOR WILAYAH' => 1000000, 'KC UTAMA' => 1000000, 'KELAS 1' => 1000000, 'KELAS 2' => 900000, 'KELAS 3' => 900000]],
    ];

    public function warnings(array $input, object $employee, ?object $office, int $year, int $month): array
    {
        return array_map(
            fn (array $warning): string => $warning['message'],
            $this->warningDetails($input, $employee, $office, $year, $month),
        );
    }

    public function warningDetails(array $input, object $employee, ?object $office, int $year, int $month): array
    {
        if (! in_array($this->normalise((string) ($employee->status_kepeg ?? '')), ['TETAP', 'CALON PEGAWAI', 'CAPEG'], true)) {
            return [];
        }

        $position = $this->positionGroup(
            (string) ($employee->active_jabatan ?? ''),
            (string) ($employee->active_jabatan_level ?? ''),
        );
        if ($position === null) {
            return [[
                'field' => null,
                'editable' => false,
                'message' => 'Jabatan aktif belum memiliki padanan pada master Kepdir 090/VI/2021; nominal tetap mengikuti file import.',
            ]];
        }

        $officeGroup = $this->officeGroup($office);
        if ($officeGroup === null) {
            return [[
                'field' => null,
                'editable' => false,
                'message' => 'Kantor aktif belum memiliki kelas cabang pada master Kepdir 090/VI/2021; nominal tetap mengikuti file import.',
            ]];
        }

        $warnings = [];
        $positionLabel = self::POSITION_LABELS[$position];
        foreach (['tunj_jabatan', 'tunj_ops', 'tunj_kinerja'] as $field) {
            $expected = $this->expectedAllowance($position, $officeGroup, $field);
            if ($expected !== null) {
                $warning = $this->compareAmountDetail($input, $field, $expected, $field === 'tunj_jabatan' ? $positionLabel : $positionLabel.' di '.$officeGroup);
                if ($warning !== null) {
                    $warnings[] = $warning;
                }
            }
        }

        $startDate = trim((string) ($employee->tmt_kerja ?? ''));
        if ($startDate !== '') {
            try {
                $asOf = (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->modify('last day of this month');
                $started = new DateTimeImmutable($startDate);
                $years = $started > $asOf ? 0 : $started->diff($asOf)->y;
                $expected = 500000 + (min(40, $years) >> 1) * 25000;
                $warning = $this->compareAmountDetail($input, 'tunj_masa_kerja', $expected, 'masa kerja '.$years.' tahun per '.$asOf->format('d-m-Y'));
                if ($warning !== null) {
                    $warnings[] = $warning;
                }
            } catch (\Throwable) {
                $warnings[] = [
                    'field' => null,
                    'editable' => false,
                    'message' => 'TMT kerja pegawai tidak dapat dibaca untuk memeriksa tunjangan masa kerja.',
                ];
            }
        }

        return $warnings;
    }

    /**
     * Returns editable initial values for the manual Master Gaji form.
     * Empty means the employee is outside the Tetap/Calon Pegawai reference.
     */
    public function defaults(object $employee, ?object $office, int $year, int $month): array
    {
        if (! in_array($this->normalise((string) ($employee->status_kepeg ?? '')), ['TETAP', 'CALON PEGAWAI', 'CAPEG'], true)) {
            return [];
        }

        $position = $this->positionGroup(
            (string) ($employee->active_jabatan ?? ''),
            (string) ($employee->active_jabatan_level ?? ''),
        );
        $officeGroup = $this->officeGroup($office);
        if ($position === null || $officeGroup === null) {
            return [];
        }

        $defaults = [];
        foreach (['tunj_jabatan', 'tunj_ops', 'tunj_kinerja'] as $field) {
            $expected = $this->expectedAllowance($position, $officeGroup, $field);
            if ($expected !== null) {
                $defaults[$field] = $this->formatAmount($expected);
            }
        }

        $startDate = trim((string) ($employee->tmt_kerja ?? ''));
        if ($startDate !== '') {
            try {
                $asOf = (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->modify('last day of this month');
                $started = new DateTimeImmutable($startDate);
                $years = $started > $asOf ? 0 : $started->diff($asOf)->y;
                $defaults['tunj_masa_kerja'] = $this->formatAmount(500000 + (min(40, $years) >> 1) * 25000);
            } catch (\Throwable) {
                // Leave the field at its normal zero value when the date is unavailable.
            }
        }

        return $defaults;
    }

    private function positionGroup(string $job, string $level = ''): ?string
    {
        $fromMaster = match ($this->normalise($level)) {
            'KEPALA DIVISI' => 'division',
            'KEPALA BIDANG' => 'field_head',
            'KEPALA CABANG' => 'branch',
            'KEPALA SUB BIDANG' => 'subfield_head',
            'KEPALA KANTOR KAS' => 'cash_office_head',
            'STAF' => 'staff',
            'NON STAF' => 'non_staff',
            default => null,
        };
        if ($fromMaster !== null) {
            return $fromMaster;
        }

        $job = $this->normalise($job);

        return match (true) {
            str_contains($job, 'KEPALA DIVISI'), str_contains($job, 'KEPALA SATUAN KERJA') => 'division',
            str_contains($job, 'KEPALA CABANG KOORDINATOR') => 'branch_coordinator',
            str_contains($job, 'KEPALA CABANG UTAMA') => 'main_branch',
            str_contains($job, 'WAKIL KEPALA CABANG') => 'deputy_branch_coordinator',
            str_contains($job, 'KEPALA KANTOR KAS') => 'cash_office_head',
            str_contains($job, 'KEPALA SUB BIDANG') => 'subfield_head',
            str_contains($job, 'KEPALA BIDANG') => 'field_head',
            str_contains($job, 'KEPALA CABANG') => 'branch',
            str_contains($job, 'ANGGOTA SKAI') => 'staff',
            str_contains($job, 'NON STAF'), str_contains($job, 'NON STAFF') => 'non_staff',
            str_contains($job, 'STAF'), str_contains($job, 'STAFF') => 'staff',
            default => null,
        };
    }

    private function officeGroup(?object $office): ?string
    {
        $name = $this->normalise((string) ($office?->nama_kantor ?? ''));
        if ($name === '') {
            return null;
        }
        if (str_contains($name, 'KANTOR PUSAT')) {
            return 'KANTOR PUSAT';
        }
        if (str_contains($name, 'WILAYAH')) {
            return 'KANTOR WILAYAH';
        }
        if (str_contains($name, 'UTAMA')) {
            return 'KC UTAMA';
        }
        foreach (self::BRANCH_CLASSES as $branch => $class) {
            if (str_contains($name, $branch)) {
                return $class;
            }
        }

        return null;
    }

    private function expectedAllowance(string $position, string $officeGroup, string $field): ?int
    {
        return self::POSITION_ALLOWANCES[$position][$field] ?? self::CLASS_ALLOWANCES[$position][$field][$officeGroup] ?? null;
    }

    private function compareAmountDetail(array $input, string $field, int $expected, string $context): ?array
    {
        $labels = ['tunj_jabatan' => 'Tunjangan jabatan', 'tunj_ops' => 'Tunjangan operasional', 'tunj_kinerja' => 'Tunjangan kinerja', 'tunj_masa_kerja' => 'Tunjangan masa kerja'];
        $raw = trim((string) ($input[$field] ?? ''));
        if ($raw === '') {
            return [
                'field' => $field,
                'label' => $labels[$field] ?? $field,
                'current_value' => '',
                'suggested_value' => $this->formatAmount($expected),
                'editable' => true,
                'message' => sprintf('Referensi master: %s untuk %s seharusnya Rp %s, tetapi kolom pada file masih kosong.', $labels[$field] ?? $field, $context, number_format($expected, 0, ',', '.')),
            ];
        }
        if (str_starts_with($raw, '=')) {
            return null;
        }
        $actual = $this->parseAmountCents($raw);
        if ($actual === null || $actual === $expected * 100) {
            return null;
        }

        $actualText = number_format(intdiv($actual, 100), 0, ',', '.').($actual % 100 !== 0 ? ','.str_pad((string) ($actual % 100), 2, '0', STR_PAD_LEFT) : '');

        return [
            'field' => $field,
            'label' => $labels[$field] ?? $field,
            'current_value' => $raw,
            'suggested_value' => $this->formatAmount($expected),
            'editable' => true,
            'message' => sprintf('Referensi master: %s untuk %s seharusnya Rp %s, sedangkan file berisi Rp %s.', $labels[$field] ?? $field, $context, number_format($expected, 0, ',', '.'), $actualText),
        ];
    }

    private function formatAmount(int $amount): string
    {
        return $amount.'.00';
    }

    private function parseAmountCents(string $value): ?int
    {
        $amount = trim(str_ireplace('rp', '', $value));
        if ($amount === '' || preg_match('/\A\d[\d\s.,]*\z/u', $amount) !== 1) {
            return null;
        }
        $amount = preg_replace('/\s+/u', '', $amount) ?? '';
        $comma = strrpos($amount, ',');
        $dot = strrpos($amount, '.');
        $separator = null;
        if ($comma !== false && $dot !== false) {
            $separator = $comma > $dot ? ',' : '.';
        } elseif ($comma !== false || $dot !== false) {
            $candidate = $comma !== false ? ',' : '.';
            $last = strrpos($amount, $candidate);
            if (strlen($amount) - $last - 1 > 0 && strlen($amount) - $last - 1 <= 2) {
                $separator = $candidate;
            }
        }
        if ($separator === null) {
            return (int) str_replace([',', '.'], '', $amount) * 100;
        }
        $position = strrpos($amount, $separator);
        $whole = str_replace([',', '.'], '', substr($amount, 0, $position));
        $fraction = substr($amount, $position + 1);
        if ($whole === '' || ! ctype_digit($whole) || ! ctype_digit($fraction)) {
            return null;
        }

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function normalise(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
