<?php

namespace App\Services;

use App\Models\Legacy\TbKantor;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollEntryValidationService
{
    public const EARNING_FIELDS = [
        'gaji_pokok', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_pajak',
        'tunj_kinerja', 'tunj_fungsional', 'tunj_masa_kerja', 'honor_kontrak', 'thl', 'uang_makan_kontrak',
    ];

    public const DEDUCTION_FIELDS = [
        'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit', 'keris',
    ];

    public const AMOUNT_FIELDS = [
        ...self::EARNING_FIELDS,
        'golongan_pegawai',
        ...self::DEDUCTION_FIELDS,
        'jumlah_gaji', 'jumlah_potongan', 'gaji_bersih', 'total',
    ];

    private const MAX_AMOUNT_CENTS = 999999999999999999;

    private const PAYROLL_FIELD_LABELS = [
        'gaji_pokok' => 'Gaji pokok',
        'golongan_pegawai' => 'Nominal Golongan Pegawai',
        'tunj_ops' => 'Tunjangan operasional',
        'tunj_kinerja' => 'Tunjangan kinerja',
        'honor_kontrak' => 'Honor kontrak',
        'thl' => 'Tenaga harian lepas',
        'bpjs_kesehatan' => 'BPJS kesehatan',
        'bpjs_tenaga_kerja' => 'BPJS ketenagakerjaan',
        'tunj_suami_istri' => 'Tunjangan suami/istri',
        'tunj_anak' => 'Tunjangan anak',
        'tunj_jabatan' => 'Tunjangan jabatan',
        'tunj_masa_kerja' => 'Tunjangan masa kerja',
        'tunj_fungsional' => 'Tunjangan fungsional',
        'tunj_pajak' => 'Tunjangan pajak',
        'uang_makan_kontrak' => 'Tunjangan uang makan',
        'keris' => 'Potongan Keris',
    ];

    public function officeDirectory(): array
    {
        $offices = TbKantor::query()->get(['kode_kantor_detail', 'kode_cabang', 'nama_kantor', 'level']);

        return [
            'offices' => $offices,
            'by_detail' => $offices->keyBy(fn (TbKantor $office): string => (string) $office->kode_kantor_detail),
            'branch_office' => $offices
                ->filter(fn (TbKantor $office): bool => strtoupper((string) $office->level) === 'KC')
                ->keyBy(fn (TbKantor $office): string => (string) $office->kode_cabang),
        ];
    }

    public function validateRows(array $rows, array $scope, int $year, int $month): array
    {
        $directory = $this->officeDirectory();
        $entries = [];
        $errors = [];
        $normalisedRows = [];
        $employeeIds = [];
        $seenEmployees = [];

        foreach ($rows as $input) {
            $rowNumber = (int) ($input['row_number'] ?? 1);
            $employeeId = trim((string) ($input['employee_id'] ?? ''));
            $officeCode = $this->normaliseOfficeCode($input['office_code'] ?? '', $directory);
            $account = $this->nullableText($input['no_rekening'] ?? null, 64);
            $customerName = $this->nullableText($input['nama_nasabah'] ?? null, 150);
            $bankDigits = preg_replace('/\D+/', '', (string) $account) ?? '';
            $payrollDate = $this->parsePayrollDate($input['tgl_gajian'] ?? null);
            $rowErrors = [];

            if ($employeeId === '' || mb_strlen($employeeId) > 12) {
                $rowErrors[] = 'ID Pegawai kosong atau lebih dari 12 karakter';
            } else {
                $employeeIds[] = $employeeId;
                if (isset($seenEmployees[$employeeId])) {
                    $rowErrors[] = 'ID Pegawai muncul lebih dari sekali pada data payroll';
                } else {
                    $seenEmployees[$employeeId] = true;
                }
            }

            if ($officeCode === null) {
                $rowErrors[] = 'Kode Kantor tidak cocok dengan data kantor';
            } elseif (($scope['restricted'] ?? false) && ! in_array($officeCode, $scope['codes'] ?? [], true)) {
                $rowErrors[] = 'Kode Kantor berada di luar wilayah akses akun';
            }

            $bankOfficeCode = strlen($bankDigits) >= 3 ? substr($bankDigits, 0, 3) : null;
            $rawBankOfficeCode = trim((string) ($input['bank_office_code'] ?? ''));
            if ($rawBankOfficeCode === '') {
                $rowErrors[] = 'Kode Kantor rekening wajib diisi';
            } elseif (preg_match('/\A\d{1,3}\z/', $rawBankOfficeCode) !== 1) {
                $rowErrors[] = 'Kode Kantor rekening harus berisi satu sampai tiga digit angka';
            } elseif ($bankOfficeCode === null || str_pad($rawBankOfficeCode, 3, '0', STR_PAD_LEFT) !== $bankOfficeCode) {
                $rowErrors[] = 'Kode Kantor rekening harus sama dengan tiga digit awal No Rekening';
            }
            if ($account === null || strlen($bankDigits) < 3 || preg_match('/\A[\d\s.\-]+\z/u', $account) !== 1) {
                $rowErrors[] = 'No Rekening wajib diisi dengan angka dan harus memuat minimal tiga digit';
            }
            if ($customerName === null) {
                $rowErrors[] = 'Nama Nasabah wajib diisi';
            }
            if ($payrollDate === null) {
                $rowErrors[] = 'Tanggal gajian tidak valid';
            } elseif ((int) substr($payrollDate, 0, 4) !== $year || (int) substr($payrollDate, 5, 2) !== $month) {
                $rowErrors[] = 'Tanggal gajian tidak sesuai dengan periode yang dipilih';
            }

            $phone = $this->nullableText($input['no_hp_keris'] ?? null, 32);
            $normalisedPhone = $phone === null ? null : preg_replace('/[\s+().-]/', '', $phone);
            if ($normalisedPhone !== null && preg_match('/\A\d{7,32}\z/', $normalisedPhone) !== 1) {
                $rowErrors[] = 'No HP Keris harus berisi nomor telepon yang valid';
            }

            foreach (['source_jabatan' => 'Jabatan', 'source_unit_kerja' => 'Unit Kerja', 'source_status' => 'Status'] as $field => $label) {
                if (array_key_exists($field, $input) && trim((string) $input[$field]) === '') {
                    $rowErrors[] = $label.' wajib diisi sesuai data pegawai aktif';
                }
            }

            $normalisedRows[$rowNumber] = [
                'input' => $input,
                'errors' => $rowErrors,
                'employee_id' => $employeeId,
                'office_code' => $officeCode,
                'account' => $account,
                'customer_name' => $customerName,
                'payroll_date' => $payrollDate,
                'bank_office_code' => $bankOfficeCode,
                'rekening_tabungan' => $this->nullableText($input['rekening_tabungan'] ?? null, 64),
                'no_hp_keris' => $normalisedPhone,
            ];
        }

        $employees = $this->loadEmployeeRecords(array_values(array_unique($employeeIds)));
        foreach ($normalisedRows as $rowNumber => $row) {
            $input = $row['input'];
            $rowErrors = $row['errors'];
            $employee = $employees['id:'.$row['employee_id']] ?? null;
            $employeeOfficeCode = null;

            if ($row['employee_id'] !== '' && $employee === null) {
                $rowErrors[] = 'ID Pegawai tidak ditemukan pada data pegawai';
            } elseif ($employee !== null && ! $this->isActiveEmployee($employee->status_aktif)) {
                $rowErrors[] = 'Pegawai dengan ID tersebut tidak berstatus aktif';
            } elseif ($employee !== null && ($employee->active_unit_kerja === null || trim((string) $employee->active_jabatan) === '')) {
                $rowErrors[] = 'Pegawai tidak memiliki jabatan aktif yang terdaftar';
            } elseif ($employee !== null) {
                $employeeOfficeCode = $this->normaliseOfficeCode($employee->active_unit_kerja, $directory);
                if ($employeeOfficeCode === null) {
                    $rowErrors[] = 'Pegawai tidak memiliki kantor pada jabatan aktif yang terdaftar';
                } elseif ($row['office_code'] !== null && ! $this->payrollOfficeMatchesEmployee($row['office_code'], $employeeOfficeCode, $directory)) {
                    $office = $directory['by_detail']->get($employeeOfficeCode);
                    $officeLabel = $office?->nama_kantor
                        ? $employeeOfficeCode.' - '.$office->nama_kantor
                        : $employeeOfficeCode;
                    $rowErrors[] = 'Kode Kantor tidak sesuai dengan kantor aktif pegawai ('.$officeLabel.')';
                }

                if ($row['customer_name'] !== null && ! $this->payrollNamesMatch((string) $employee->nama, $row['customer_name'])) {
                    $rowErrors[] = 'Nama Nasabah tidak sesuai dengan nama pegawai pada ID tersebut';
                }
                if (array_key_exists('source_jabatan', $input)
                    && $this->normaliseComparableText((string) $input['source_jabatan']) !== $this->normaliseComparableText((string) $employee->active_jabatan)) {
                    $rowErrors[] = 'Jabatan pada file tidak sesuai dengan jabatan aktif pegawai';
                }
                if (array_key_exists('source_unit_kerja', $input)
                    && ! $this->payrollUnitMatchesEmployee((string) $input['source_unit_kerja'], (string) $employee->active_unit_kerja, $employeeOfficeCode, $directory)) {
                    $rowErrors[] = 'Unit Kerja pada file tidak sesuai dengan kantor aktif pegawai';
                }
                if (array_key_exists('source_status', $input)
                    && $this->normaliseEmploymentStatus((string) $input['source_status']) !== $this->normaliseEmploymentStatus((string) $employee->status_kepeg)) {
                    $rowErrors[] = 'Status pada file tidak sesuai dengan status kepegawaian pegawai';
                }
            }

            if ($employee !== null) {
                $calculated = $this->calculateAmounts($input, (string) $employee->status_kepeg);
                $rowErrors = [...$rowErrors, ...$calculated['errors']];
            } else {
                $calculated = ['amounts' => [], 'errors' => []];
            }

            if ($rowErrors !== []) {
                $errors[] = ['row' => $rowNumber, 'messages' => array_values(array_unique($rowErrors))];

                continue;
            }

            $entries[] = [
                'office_code' => $row['office_code'] ?? '',
                'employee_id' => $row['employee_id'],
                'source_row_number' => $rowNumber,
                'bank_office_code' => $row['bank_office_code'],
                'no_rekening' => $row['account'],
                'nama_nasabah' => $row['customer_name'],
                'tgl_gajian' => $row['payroll_date'],
                'rekening_tabungan' => $row['rekening_tabungan'],
                'no_hp_keris' => $row['no_hp_keris'],
                ...$calculated['amounts'],
            ];
        }

        return [
            'entries' => $entries,
            'errors' => $this->groupErrors($errors),
            'directory' => $directory,
        ];
    }

    public function calculateAmounts(array $input, string $employmentStatus): array
    {
        $amounts = [];
        $cents = [];
        $errors = [];
        $fieldRules = $this->fieldRulesForStatus($employmentStatus);

        if (! $fieldRules['supported']) {
            $errors[] = 'Status kepegawaian '.$employmentStatus.' belum memiliki aturan payroll yang didukung';
        }

        foreach ([...self::EARNING_FIELDS, ...self::DEDUCTION_FIELDS] as $field) {
            $rawValue = $input[$field] ?? '';
            if (in_array($field, $fieldRules['required'], true) && trim((string) $rawValue) === '') {
                $label = self::PAYROLL_FIELD_LABELS[$field] ?? str_replace('_', ' ', $field);
                $errors[] = $label.' wajib diisi untuk status '.$fieldRules['status_label'];
            }

            $value = $this->parseAmountCents($rawValue);
            if ($value === null) {
                $errors[] = 'Nilai '.str_replace('_', ' ', $field).' harus berupa angka nonnegatif dengan maksimal dua angka desimal';
                $value = 0;
            }

            $cents[$field] = $value;
            $amounts[$field] = $this->formatCents($value);
        }

        $gradeAmount = $this->parseAmountCents($input['golongan_pegawai'] ?? '');
        if ($gradeAmount === null) {
            $errors[] = 'Nilai Golongan Pegawai harus berupa angka nonnegatif dengan maksimal dua angka desimal';
            $gradeAmount = 0;
        }
        $amounts['golongan_pegawai'] = $this->formatCents($gradeAmount);

        $earnings = $this->sumCents(array_intersect_key($cents, array_flip(self::EARNING_FIELDS)));
        $gross = $earnings === null ? null : $this->sumCents([$earnings, $gradeAmount]);
        $deductions = $this->sumCents(array_intersect_key($cents, array_flip(self::DEDUCTION_FIELDS)));
        if ($gross === null || $deductions === null) {
            $errors[] = 'Jumlah komponen payroll melebihi batas nominal yang dapat disimpan';
            $gross ??= 0;
            $deductions ??= 0;
        }

        $attendance = $cents['absensi'] ?? 0;
        if ($attendance > 0) {
            $allowanceField = $this->attendanceAllowanceField($amounts, $employmentStatus);
            if ($allowanceField === null) {
                $errors[] = 'Potongan absensi memerlukan status pegawai Tetap, Calon Pegawai, Kontrak, atau Tenaga Harian Lepas';
            } elseif ($attendance > ($cents[$allowanceField] ?? 0)) {
                $allowanceLabel = match ($allowanceField) {
                    'tunj_ops' => 'Tunj. Operasional',
                    'uang_makan_kontrak' => 'Uang Makan Kontrak',
                    default => 'Tenaga Harian Lepas',
                };
                $errors[] = 'Potongan absensi melebihi '.$allowanceLabel.' pegawai';
            }
        }

        $net = $gross - $deductions;
        if ($net < 0) {
            $errors[] = 'Jumlah potongan melebihi jumlah gaji';
        }

        foreach ([
            'jumlah_gaji' => $gross,
            'jumlah_potongan' => $deductions,
            'gaji_bersih' => max(0, $net),
        ] as $field => $expected) {
            $provided = trim((string) ($input[$field] ?? ''));
            if ($provided === '' || str_starts_with($provided, '=')) {
                continue;
            }
            $providedCents = $this->parseAmountCents($provided);
            if ($providedCents === null) {
                $errors[] = 'Nilai '.str_replace('_', ' ', $field).' bukan angka yang valid';
            } elseif ($providedCents !== $expected) {
                $errors[] = 'Nilai '.str_replace('_', ' ', $field).' tidak sesuai hasil perhitungan komponen';
            }
        }

        $legacyTotal = trim((string) ($input['total'] ?? ''));
        if ($legacyTotal !== '' && ! str_starts_with($legacyTotal, '=')) {
            $legacyCents = $this->parseAmountCents($legacyTotal);
            if ($legacyCents === null) {
                $errors[] = 'Nilai Total bukan angka yang valid';
            } elseif ($legacyCents !== max(0, $net)) {
                $errors[] = 'Nilai Total harus sama dengan gaji bersih setelah seluruh potongan';
            }
        }

        $amounts['jumlah_gaji'] = $this->formatCents($gross);
        $amounts['jumlah_potongan'] = $this->formatCents($deductions);
        $amounts['gaji_bersih'] = $this->formatCents(max(0, $net));
        $amounts['total'] = $amounts['gaji_bersih'];

        return ['amounts' => $amounts, 'errors' => array_values(array_unique($errors))];
    }

    public function fieldRulesForStatus(string $employmentStatus): array
    {
        $category = $this->employmentCategory($employmentStatus);
        $rules = match ($category) {
            'permanent' => [
                'required' => ['gaji_pokok', 'tunj_ops', 'tunj_kinerja', 'bpjs_kesehatan', 'bpjs_tenaga_kerja'],
            ],
            'contract' => [
                'required' => [],
            ],
            'thl' => [
                'required' => ['thl'],
            ],
            default => ['required' => []],
        };

        $optionalFields = array_values(array_diff([...self::EARNING_FIELDS, ...self::DEDUCTION_FIELDS], $rules['required']));

        return [
            'supported' => $category !== null,
            'category' => $category,
            'status_label' => match ($category) {
                'permanent' => 'Tetap/Calon Pegawai',
                'contract' => 'Kontrak',
                'thl' => 'Tenaga Harian Lepas',
                default => $employmentStatus !== '' ? $employmentStatus : 'tidak diketahui',
            },
            'required' => $rules['required'],
            'optional' => $optionalFields,
        ];
    }

    public function applyAttendanceDeductionToExportAllowance(array $amounts, string $employmentStatus): array
    {
        $attendance = $this->parseAmountCents($amounts['absensi'] ?? 0);
        $allowanceField = $this->attendanceAllowanceField($amounts, $employmentStatus);

        if ($attendance === null || $attendance === 0 || $allowanceField === null) {
            return $amounts;
        }

        $allowance = $this->parseAmountCents($amounts[$allowanceField] ?? 0);
        if ($allowance === null) {
            return $amounts;
        }

        $amounts[$allowanceField] = $this->formatCents(max(0, $allowance - $attendance));

        return $amounts;
    }

    public function applyGradeToBaseSalaryForExport(array $amounts): array
    {
        $baseSalary = $this->parseAmountCents($amounts['gaji_pokok'] ?? 0);
        $gradeAmount = $this->parseAmountCents($amounts['golongan_pegawai'] ?? 0);
        if ($baseSalary === null || $gradeAmount === null) {
            throw new RuntimeException('Nilai gaji pokok atau golongan pegawai tidak valid untuk ekspor payroll.');
        }

        $salaryWithGrade = $this->sumCents([$baseSalary, $gradeAmount]);
        if ($salaryWithGrade === null) {
            throw new RuntimeException('Jumlah gaji pokok dan golongan pegawai melebihi batas nominal ekspor.');
        }

        $amounts['gaji_pokok'] = $this->formatCents($salaryWithGrade);

        return $amounts;
    }

    public function normaliseOfficeCode(mixed $rawCode, array $directory): ?string
    {
        $raw = trim((string) $rawCode);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw) && ! preg_match('/^\d+$/', $raw)) {
            $raw = sprintf('%.0f', (float) $raw);
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') {
            return null;
        }
        $byDetail = $directory['by_detail'];
        if ($byDetail->has($digits)) {
            return $digits;
        }
        $branchCode = strlen($digits) <= 3 ? str_pad($digits, 3, '0', STR_PAD_LEFT) : $digits;
        if (strlen($digits) <= 3 && $directory['branch_office']->has($branchCode)) {
            return (string) $directory['branch_office']->get($branchCode)->kode_kantor_detail;
        }
        if (strlen($digits) < 6) {
            $padded = str_pad($digits, 6, '0', STR_PAD_LEFT);
            if ($byDetail->has($padded)) {
                return $padded;
            }
        }
        if ($directory['branch_office']->has($branchCode)) {
            return (string) $directory['branch_office']->get($branchCode)->kode_kantor_detail;
        }

        return null;
    }

    private function loadEmployeeRecords(array $employeeIds): array
    {
        $employeeRecords = [];
        foreach (array_chunk(array_values(array_unique($employeeIds)), 500) as $ids) {
            if ($ids === []) {
                continue;
            }
            $requestedIds = array_fill_keys(array_map(fn (string $id): string => 'id:'.$id, $ids), true);
            foreach (DB::table('tb_pegawai')->whereIn('id_peg', $ids)->get(['id_peg', 'nama', 'status_aktif', 'status_kepeg']) as $record) {
                $key = 'id:'.(string) $record->id_peg;
                if (isset($requestedIds[$key])) {
                    $employeeRecords[$key] = (object) [
                        'id_peg' => (string) $record->id_peg,
                        'nama' => (string) $record->nama,
                        'status_aktif' => $record->status_aktif,
                        'status_kepeg' => (string) ($record->status_kepeg ?? ''),
                        'active_unit_kerja' => null,
                        'active_jabatan' => null,
                    ];
                }
            }

            $resolved = [];
            foreach (DB::table('tb_jabatan')->where('status_jab', 'Aktif')->whereIn('id_peg', $ids)
                ->orderByDesc('tmt_jabatan')->orderByDesc('id_jab')->get(['id_peg', 'unit_kerja', 'jabatan']) as $job) {
                $key = 'id:'.(string) $job->id_peg;
                if (isset($requestedIds[$key], $employeeRecords[$key]) && ! isset($resolved[$key])) {
                    $employeeRecords[$key]->active_unit_kerja = $job->unit_kerja;
                    $employeeRecords[$key]->active_jabatan = (string) $job->jabatan;
                    $resolved[$key] = true;
                }
            }
        }

        return $employeeRecords;
    }

    private function isActiveEmployee(mixed $status): bool
    {
        return in_array(mb_strtolower(trim((string) $status), 'UTF-8'), ['1', 'aktif', 'active', 'ya'], true);
    }

    private function employmentCategory(string $status): ?string
    {
        return match ($this->normaliseEmploymentStatus($status)) {
            'TETAP', 'CAPEG', 'PEGAWAI TETAP', 'KARYAWAN TETAP' => 'permanent',
            'KONTRAK', 'PEGAWAI KONTRAK', 'KARYAWAN KONTRAK', 'PKWT' => 'contract',
            'THL', 'TENAGA HARIAN LEPAS', 'PEGAWAI HARIAN LEPAS', 'HARIAN LEPAS' => 'thl',
            default => null,
        };
    }

    private function attendanceAllowanceField(array $amounts, string $employmentStatus): ?string
    {
        $thlAmount = $this->parseAmountCents($amounts['thl'] ?? 0) ?? 0;
        if ($thlAmount > 0) {
            $mealAmount = $this->parseAmountCents($amounts['uang_makan_kontrak'] ?? 0) ?? 0;

            return $mealAmount > 0 ? 'uang_makan_kontrak' : 'thl';
        }

        return match ($this->employmentCategory($employmentStatus)) {
            'permanent' => 'tunj_ops',
            'contract' => 'uang_makan_kontrak',
            'thl' => 'thl',
            default => null,
        };
    }

    private function normaliseEmploymentStatus(string $status): string
    {
        $status = $this->normaliseComparableText($status);
        if (in_array($status, ['CALON PEGAWAI', 'CAPEG'], true)) {
            return 'CAPEG';
        }

        return $status;
    }

    private function payrollUnitMatchesEmployee(string $sourceUnit, string $activeUnit, ?string $officeCode, array $directory): bool
    {
        $source = $this->normaliseComparableText($sourceUnit);
        $candidates = [$this->normaliseComparableText($activeUnit)];
        if ($officeCode !== null) {
            $office = $directory['by_detail']->get($officeCode);
            if ($office !== null) {
                $name = $this->normaliseComparableText((string) $office->nama_kantor);
                $candidates[] = $name;
                $candidates[] = $this->normaliseComparableText('KC '.$office->nama_kantor);
                $candidates[] = $this->normaliseComparableText('KC. '.$office->nama_kantor);
                $candidates[] = $this->normaliseComparableText((string) $office->kode_cabang);
            }
            $candidates[] = $this->normaliseComparableText($officeCode);
        }

        return $source !== '' && in_array($source, array_filter($candidates), true);
    }

    private function payrollOfficeMatchesEmployee(string $payrollOfficeCode, string $employeeOfficeCode, array $directory): bool
    {
        if ($payrollOfficeCode === $employeeOfficeCode) {
            return true;
        }
        $payrollOffice = $directory['by_detail']->get($payrollOfficeCode);
        $employeeOffice = $directory['by_detail']->get($employeeOfficeCode);

        return $payrollOffice !== null
            && strtoupper((string) $payrollOffice->level) === 'KC'
            && $payrollOffice->kode_cabang !== null
            && (string) $payrollOffice->kode_cabang === (string) $employeeOffice?->kode_cabang;
    }

    private function payrollNamesMatch(string $employeeName, string $accountName): bool
    {
        return $this->normalisePayrollName($employeeName) !== ''
            && $this->normalisePayrollName($employeeName) === $this->normalisePayrollName($accountName);
    }

    private function normalisePayrollName(string $name): string
    {
        $name = mb_strtoupper($name, 'UTF-8');
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        $degreeSuffixes = [
            'S E SY', 'S KOM', 'S SOS', 'S PD', 'S SI', 'S AK', 'S PSI', 'S FARM', 'S KED', 'S GZ',
            'M KOM', 'M SOS', 'M PD', 'M SI', 'M AK', 'M HUM', 'M KES', 'M FARM', 'M T', 'M E', 'M M',
            'S E', 'S H', 'S T', 'DRS', 'DRA', 'DR',
        ];
        do {
            $previous = $name;
            foreach ($degreeSuffixes as $suffix) {
                $name = preg_replace('/\s+'.preg_quote($suffix, '/').'$/u', '', $name) ?? $name;
            }
            $name = trim($name);
        } while ($name !== $previous);

        return $name;
    }

    private function normaliseComparableText(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function parsePayrollDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || str_starts_with($text, '=')) {
            return null;
        }
        if (is_numeric($text) && (float) $text > 0 && (float) $text < 100000) {
            try {
                return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) floor((float) $text).' days')->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        foreach (['!d-m-Y', '!d/m/Y', '!Y-m-d', '!d.m.Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $text);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        try {
            return (new DateTimeImmutable($text))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmountCents(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value < 0 || $value > intdiv(self::MAX_AMOUNT_CENTS, 100) ? null : $value * 100;
        }
        if (is_float($value)) {
            if (! is_finite($value) || $value < 0 || $value > self::MAX_AMOUNT_CENTS / 100) {
                return null;
            }

            return (int) round($value * 100);
        }

        $amount = trim(str_ireplace('rp', '', (string) $value));
        if ($amount === '' || str_starts_with($amount, '=') || preg_match('/\A\d[\d\s.,]*\z/u', $amount) !== 1) {
            return null;
        }
        $amount = preg_replace('/\s+/u', '', $amount) ?? '';
        $comma = strrpos($amount, ',');
        $dot = strrpos($amount, '.');
        $decimalSeparator = null;
        if ($comma !== false && $dot !== false) {
            $decimalSeparator = $comma > $dot ? ',' : '.';
        } elseif ($comma !== false || $dot !== false) {
            $separator = $comma !== false ? ',' : '.';
            $last = strrpos($amount, $separator);
            $fractionLength = strlen($amount) - $last - 1;
            if ($fractionLength > 0 && $fractionLength <= 2) {
                $decimalSeparator = $separator;
            }
        }

        if ($decimalSeparator !== null) {
            $decimalPosition = strrpos($amount, $decimalSeparator);
            $whole = str_replace([',', '.'], '', substr($amount, 0, $decimalPosition));
            $fraction = substr($amount, $decimalPosition + 1);
        } else {
            $whole = str_replace([',', '.'], '', $amount);
            $fraction = '';
        }
        if ($whole === '' || ! ctype_digit($whole) || ($fraction !== '' && ! ctype_digit($fraction))) {
            return null;
        }
        $whole = ltrim($whole, '0') ?: '0';
        if (strlen($whole) > 16) {
            return null;
        }
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $centsText = $whole.$fraction;
        $centsText = ltrim($centsText, '0') ?: '0';
        if (strlen($centsText) > 18 || (strlen($centsText) === 18 && strcmp($centsText, (string) self::MAX_AMOUNT_CENTS) > 0)) {
            return null;
        }

        return (int) $centsText;
    }

    private function sumCents(array $values): ?int
    {
        $sum = 0;
        foreach ($values as $value) {
            if ($value > self::MAX_AMOUNT_CENTS - $sum) {
                return null;
            }
            $sum += $value;
        }

        return $sum;
    }

    private function formatCents(int $value): string
    {
        return intdiv($value, 100).'.'.str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }

    private function groupErrors(array $errors): array
    {
        $byRow = [];
        $unscoped = [];
        foreach ($errors as $error) {
            if ($error['row'] === null) {
                $unscoped[] = $error;

                continue;
            }
            $row = $error['row'];
            $byRow[$row] ??= ['row' => $row, 'messages' => []];
            $byRow[$row]['messages'] = array_values(array_unique([...$byRow[$row]['messages'], ...$error['messages']]));
        }
        ksort($byRow);

        return [...array_values($byRow), ...$unscoped];
    }
}
