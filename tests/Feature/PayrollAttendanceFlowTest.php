<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Shuchkin\SimpleXLSX;
use Tests\TestCase;
use ZipArchive;

class PayrollAttendanceFlowTest extends TestCase
{
    private string $lastPayrollWorkbookContents = '';

    private const EARNINGS = [
        'gaji_pokok', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_pajak',
        'tunj_kinerja', 'tunj_fungsional', 'tunj_masa_kerja', 'honor_kontrak', 'thl', 'uang_makan_kontrak',
        'golongan_pegawai',
    ];

    private const DEDUCTIONS = [
        'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit', 'keris',
    ];

    private const TOTALS = ['jumlah_gaji', 'jumlah_potongan', 'gaji_bersih', 'total'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_attendance_is_deducted_from_status_specific_allowance_in_import_and_export_flow(): void
    {
        Storage::fake('local');
        $this->seedDirectory();
        $session = [
            'id_user' => 'payroll-test-admin',
            'hak_akses' => 'admin',
        ];

        $previewResponse = $this->withSession($session)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => hash('xxh128', config('app.asset_url')),
            ])
            ->post('/payroll/import/preview', [
                'tahun' => 2026,
                'bulan' => 10,
                'file_xlsx' => $this->payrollWorkbook([
                    [
                        'employee_id' => 'PERM-001',
                        'name' => 'PEGAWAI UJI TETAP',
                        'job' => 'Staf Tetap',
                        'status' => 'Tetap',
                        'account' => '0012345678901',
                        'amounts' => [
                            'gaji_pokok' => 5000000,
                            'golongan_pegawai' => 250000,
                            'tunj_ops' => 1000000,
                            'tunj_kinerja' => 500000,
                            'bpjs_kesehatan' => 100000,
                            'bpjs_tenaga_kerja' => 100000,
                            'absensi' => 200000,
                            'angsuran_kredit' => 50000,
                            'keris' => 20000,
                        ],
                    ],
                    [
                        'employee_id' => 'CONT-001',
                        'name' => 'PEGAWAI UJI KONTRAK',
                        'job' => 'Petugas Kontrak',
                        'status' => 'Kontrak',
                        'account' => '0012345678902',
                        'amounts' => [
                            'honor_kontrak' => 3000000,
                            'uang_makan_kontrak' => 500000,
                            'absensi' => 100000,
                            'keris' => 50000,
                        ],
                    ],
                    [
                        'employee_id' => 'THL-0001',
                        'name' => 'PEGAWAI UJI THL',
                        'job' => 'Petugas THL',
                        'status' => 'Tenaga Harian Lepas',
                        'account' => '0012345678903',
                        'amounts' => [
                            'thl' => 2000000,
                            'absensi' => 100000,
                            'keris' => 50000,
                        ],
                    ],
                ]),
            ]);

        $previewResponse->assertOk();
        $preview = $previewResponse->json('props.preview');
        $this->assertSame(3, $preview['row_count']);
        $this->assertSame(0, $preview['error_count']);
        $this->assertNotEmpty($preview['token']);

        $this->withSession($session)
            ->post('/payroll/import', [
                'preview_token' => $preview['token'],
                'file_xlsx' => UploadedFile::fake()->createWithContent('payroll-e2e.xlsx', $this->lastPayrollWorkbookContents),
            ])
            ->assertRedirect('/payroll?tahun=2026&bulan=10')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payroll_entries', [
            'employee_id' => 'PERM-001',
            'golongan_pegawai' => '250000.00',
            'tunj_ops' => '1000000.00',
            'absensi' => '200000.00',
            'jumlah_gaji' => '6750000.00',
            'jumlah_potongan' => '470000.00',
            'gaji_bersih' => '6280000.00',
        ]);
        $this->assertDatabaseHas('payroll_entries', [
            'employee_id' => 'CONT-001',
            'uang_makan_kontrak' => '500000.00',
            'absensi' => '100000.00',
            'jumlah_gaji' => '3500000.00',
            'jumlah_potongan' => '150000.00',
            'gaji_bersih' => '3350000.00',
        ]);
        $this->assertDatabaseHas('payroll_entries', [
            'employee_id' => 'THL-0001',
            'thl' => '2000000.00',
            'absensi' => '100000.00',
            'keris' => '50000.00',
            'jumlah_gaji' => '2000000.00',
            'jumlah_potongan' => '150000.00',
            'gaji_bersih' => '1850000.00',
        ]);
        $this->assertDatabaseHas('payroll_salary_masters', [
            'employee_id' => 'PERM-001',
            'golongan_pegawai' => '250000.00',
            'tunj_ops' => '1000000.00',
            'absensi' => '200000.00',
            'gaji_bersih' => '6280000.00',
        ]);
        $this->assertDatabaseHas('payroll_salary_masters', [
            'employee_id' => 'CONT-001',
            'uang_makan_kontrak' => '500000.00',
            'absensi' => '100000.00',
            'gaji_bersih' => '3350000.00',
        ]);
        $this->assertDatabaseHas('payroll_salary_masters', [
            'employee_id' => 'THL-0001',
            'thl' => '2000000.00',
            'absensi' => '100000.00',
            'gaji_bersih' => '1850000.00',
        ]);

        $export = $this->flushHeaders()->withSession($session)
            ->get('/payroll/export?tahun=2026&bulan=10&office=000000');
        $export->assertOk();
        $path = $export->baseResponse->getFile()->getPathname();
        try {
            $workbook = SimpleXLSX::parse($path);
            $this->assertNotFalse($workbook, 'Ekspor payroll harus menghasilkan XLSX yang dapat dibaca.');
            $this->assertSame(['hasil ketika export'], $workbook->sheetNames());
            $exportRows = array_slice($workbook->rows(0), 17, 3);
            $rowsByEmployee = [];
            foreach ($exportRows as $row) {
                $rowsByEmployee[$row[2]] = $row;
            }

            $this->assertEquals(800000, (float) $rowsByEmployee['PEGAWAI UJI TETAP'][7], 'Tunjangan operasional (H) harus dikurangi potongan absensi Tetap.');
            $this->assertEquals(5250000, (float) $rowsByEmployee['PEGAWAI UJI TETAP'][3], 'Nominal golongan harus ditambahkan ke gaji pokok ekspor satu kali.');
            $this->assertEquals(6280000, (float) $rowsByEmployee['PEGAWAI UJI TETAP'][18], 'Gaji bersih Tetap harus mengurangi seluruh potongan satu kali setelah nominal golongan ditambahkan.');
            $this->assertEquals(400000, (float) $rowsByEmployee['PEGAWAI UJI KONTRAK'][14], 'Uang makan kontrak (O) harus dikurangi potongan absensi Kontrak.');
            $this->assertEquals(3350000, (float) $rowsByEmployee['PEGAWAI UJI KONTRAK'][18], 'Gaji bersih Kontrak harus mengurangi seluruh potongan satu kali.');
            $this->assertEquals(1900000, (float) $rowsByEmployee['PEGAWAI UJI THL'][13], 'Tenaga harian lepas (N) harus dikurangi potongan absensi THL.');
            $this->assertEquals(1850000, (float) $rowsByEmployee['PEGAWAI UJI THL'][18], 'Gaji bersih THL harus mengurangi seluruh potongan satu kali.');
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseHas('payroll_salary_masters', [
            'employee_id' => 'THL-0001',
            'thl' => '2000000.00',
        ]);
    }

    private function payrollWorkbook(array $employees): UploadedFile
    {
        $source = resource_path('templates/payroll/Template_Import_Payroll.xlsx');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'payroll-e2e-');
        $this->assertIsString($temporaryPath);
        $this->assertTrue(copy($source, $temporaryPath));

        $archive = new ZipArchive;
        $this->assertSame(true, $archive->open($temporaryPath) === true);
        $worksheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($worksheetXml);

        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($worksheetXml, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach (array_values($employees) as $index => $employee) {
            $rowNumber = $index + 3;
            $amounts = array_fill_keys([...self::EARNINGS, ...self::DEDUCTIONS], 0);
            $amounts = array_replace($amounts, $employee['amounts']);
            $gross = array_sum(array_intersect_key($amounts, array_flip(self::EARNINGS)));
            $deductions = array_sum(array_intersect_key($amounts, array_flip(self::DEDUCTIONS)));
            $net = $gross - $deductions;
            $values = [
                'B' => '000000', 'C' => $employee['employee_id'], 'D' => '001', 'E' => $employee['account'],
                'F' => $employee['name'], 'G' => $employee['job'], 'H' => '000000', 'I' => $employee['status'],
                'J' => (string) $amounts['golongan_pegawai'], 'K' => '0', 'L' => '0', 'M' => '0',
                'N' => (string) $amounts['gaji_pokok'], 'O' => (string) $amounts['tunj_suami_istri'],
                'P' => (string) $amounts['tunj_anak'], 'Q' => (string) $amounts['tunj_jabatan'],
                'R' => (string) $amounts['tunj_ops'], 'S' => (string) $amounts['tunj_kinerja'],
                'T' => (string) $amounts['tunj_masa_kerja'], 'U' => (string) $amounts['tunj_fungsional'],
                'V' => (string) $amounts['honor_kontrak'], 'W' => (string) $amounts['thl'],
                'X' => (string) $amounts['tunj_pajak'], 'Y' => (string) $amounts['uang_makan_kontrak'],
                'Z' => (string) $gross, 'AA' => (string) $amounts['bpjs_kesehatan'],
                'AB' => (string) $amounts['bpjs_tenaga_kerja'], 'AC' => (string) $amounts['absensi'],
                'AD' => (string) $amounts['angsuran_kredit'], 'AE' => (string) $amounts['keris'],
                'AF' => (string) $deductions, 'AG' => (string) $net, 'AH' => '', 'AI' => '', 'AJ' => '25-10-2026',
            ];

            foreach ($values as $column => $value) {
                $reference = $column.$rowNumber;
                $cell = $xpath->query('//x:c[@r="'.$reference.'"]')->item(0);
                $this->assertNotNull($cell, "Template harus memiliki sel {$reference}.");
                while ($cell->firstChild !== null) {
                    $cell->removeChild($cell->firstChild);
                }

                if (in_array($column, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'AH', 'AI', 'AJ'], true)) {
                    $cell->setAttribute('t', 'inlineStr');
                    $inlineString = $document->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'x:is');
                    $text = $document->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'x:t');
                    $text->appendChild($document->createTextNode($value));
                    $inlineString->appendChild($text);
                    $cell->appendChild($inlineString);
                } else {
                    $cell->removeAttribute('t');
                    $cell->appendChild($document->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'x:v', $value));
                }
            }
        }

        $updatedXml = $document->saveXML();
        $this->assertIsString($updatedXml);
        $this->assertTrue($archive->addFromString('xl/worksheets/sheet1.xml', $updatedXml, ZipArchive::FL_OVERWRITE));
        $this->assertTrue($archive->close());
        $contents = file_get_contents($temporaryPath);
        @unlink($temporaryPath);
        $this->assertIsString($contents);
        $this->lastPayrollWorkbookContents = $contents;

        return UploadedFile::fake()->createWithContent('payroll-e2e.xlsx', $contents);
    }

    private function seedDirectory(): void
    {
        DB::table('tb_kantor')->insert([
            'kode_kantor_detail' => '000000',
            'kode_cabang' => '000',
            'nama_kantor' => 'Kantor Uji',
            'level' => 'KP',
        ]);
        DB::table('tb_user')->insert([
            'id_user' => 'payroll-test-admin',
            'id_pegawai' => null,
            'hak_akses' => 'admin',
            'status_aktif' => 'Y',
        ]);
        $employees = [
            ['id' => 'PERM-001', 'name' => 'PEGAWAI UJI TETAP', 'status' => 'Tetap', 'job' => 'Staf Tetap'],
            ['id' => 'CONT-001', 'name' => 'PEGAWAI UJI KONTRAK', 'status' => 'Kontrak', 'job' => 'Petugas Kontrak'],
            ['id' => 'THL-0001', 'name' => 'PEGAWAI UJI THL', 'status' => 'Tenaga Harian Lepas', 'job' => 'Petugas THL'],
        ];
        DB::table('tb_pegawai')->insert(array_map(fn (array $employee): array => [
            'id_peg' => $employee['id'],
            'nama' => $employee['name'],
            'status_aktif' => '1',
            'status_kepeg' => $employee['status'],
        ], $employees));
        DB::table('tb_jabatan')->insert(array_map(fn (array $employee): array => [
            'id_peg' => $employee['id'],
            'jabatan' => $employee['job'],
            'unit_kerja' => '000000',
            'tmt_jabatan' => '2026-01-01',
            'status_jab' => 'Aktif',
        ], $employees));
    }

    private function createSchema(): void
    {
        Schema::create('tb_config', function (Blueprint $table): void {
            $table->string('id_app')->primary();
            $table->string('nama_app')->nullable();
            $table->string('alias_app')->nullable();
            $table->text('desc_app')->nullable();
            $table->string('logo')->nullable();
        });
        Schema::create('tb_kantor', function (Blueprint $table): void {
            $table->string('kode_kantor_detail')->primary();
            $table->string('kode_cabang')->nullable();
            $table->string('nama_kantor')->nullable();
            $table->string('level')->nullable();
        });
        Schema::create('tb_user', function (Blueprint $table): void {
            $table->string('id_user')->primary();
            $table->string('id_pegawai')->nullable();
            $table->string('hak_akses')->nullable();
            $table->string('status_aktif')->nullable();
        });
        Schema::create('tb_pegawai', function (Blueprint $table): void {
            $table->string('id_peg')->primary();
            $table->string('nama')->nullable();
            $table->string('status_aktif')->nullable();
            $table->string('status_kepeg')->nullable();
        });
        Schema::create('tb_jabatan', function (Blueprint $table): void {
            $table->id('id_jab');
            $table->string('id_peg');
            $table->string('jabatan')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->date('tmt_jabatan')->nullable();
            $table->string('status_jab')->nullable();
        });
        Schema::create('payroll_imports', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->string('source_filename');
            $table->string('source_sha256', 64);
            $table->unsignedInteger('source_row_count')->default(0);
            $table->unsignedInteger('source_summary_count')->default(0);
            $table->boolean('is_current')->default(false);
            $table->string('imported_by')->nullable();
            $table->timestamps();
        });

        foreach (['payroll_entries', 'payroll_office_summaries', 'payroll_salary_masters'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                if ($name !== 'payroll_salary_masters') {
                    $table->unsignedBigInteger('payroll_import_id')->nullable();
                }
                if ($name !== 'payroll_office_summaries') {
                    $table->string('employee_id', 12)->nullable();
                }
                $table->string('office_code', 20)->nullable();
                if ($name === 'payroll_entries') {
                    $table->unsignedInteger('source_row_number')->nullable();
                }
                if ($name !== 'payroll_office_summaries') {
                    $table->string('bank_office_code', 20)->nullable();
                    $table->string('no_rekening', 64)->nullable();
                    $table->string('nama_nasabah', 150)->nullable();
                    $table->date('tgl_gajian')->nullable();
                    $table->string('rekening_tabungan', 64)->nullable();
                    $table->string('no_hp_keris', 32)->nullable();
                } else {
                    $table->string('source_label', 100)->nullable();
                    $table->unsignedInteger('employee_count')->default(0);
                }
                foreach ([...self::EARNINGS, ...self::DEDUCTIONS, ...self::TOTALS] as $field) {
                    $table->decimal($field, 18, 2)->default(0);
                }
                if ($name === 'payroll_salary_masters') {
                    $table->unsignedBigInteger('source_import_id')->nullable();
                    $table->string('created_by', 64)->nullable();
                    $table->string('updated_by', 64)->nullable();
                    $table->unique('employee_id');
                }
                $table->timestamps();
            });
        }
    }
}
