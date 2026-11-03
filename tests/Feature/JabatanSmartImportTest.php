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

class JabatanSmartImportTest extends TestCase
{
    private const HEADERS = [
        'ID Pegawai (Wajib)',
        'Kode Jabatan (Opsional)',
        'Nama Jabatan (Wajib)',
        'Kode Unit Kerja',
        'No SK',
        'Tgl SK (DD-MM-YYYY)',
        'Status (Aktif/Non)',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.asset_url' => 'https://simpeg.test/assets',
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        $this->seedDirectory();
    }

    public function test_smart_import_resolves_position_by_code_or_name_and_closes_previous_active_job(): void
    {
        Storage::fake('local');

        $previewResponse = $this->withSession($this->adminSession())
            ->post('/jabatan/import/preview', ['file_xlsx' => $this->jabatanFixture()]);

        $previewResponse->assertOk();
        $preview = $previewResponse->json();
        $this->assertSame(2, $preview['total']);
        $this->assertSame(2, $preview['valid_count']);
        $this->assertSame(0, $preview['invalid_count']);
        $this->assertSame('10', $preview['preview'][0]['kode_jabatan']);
        $this->assertSame('Analis Kredit', $preview['preview'][0]['nama_jabatan']);
        $this->assertSame('20', $preview['preview'][1]['kode_jabatan']);
        $this->assertSame('Kepala Cabang', $preview['preview'][1]['nama_jabatan']);
        $this->assertNotEmpty($preview['token']);

        $importResponse = $this->withSession($this->adminSession())
            ->post('/jabatan/import', ['preview_token' => $preview['token']]);

        $importResponse->assertRedirect('/jabatan');
        $importResponse->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tb_jabatan', [
            'id_peg' => 'TEST-0001',
            'kode_jabatan' => 10,
            'jabatan' => 'Analis Kredit',
            'unit_kerja' => '000000',
            'status_jab' => 'Aktif',
            'created_by' => 'test-admin',
        ]);
        $newJob = DB::table('tb_jabatan')->where('id_peg', 'TEST-0001')->where('kode_jabatan', 10)->first();
        $this->assertNotNull($newJob);
        $this->assertSame('2026-10-01', substr((string) $newJob->tmt_jabatan, 0, 10));
        $this->assertSame('2026-10-01', substr((string) $newJob->tgl_sk, 0, 10));
        $this->assertDatabaseHas('tb_jabatan', [
            'id_peg' => 'TEST-0002',
            'kode_jabatan' => 20,
            'jabatan' => 'Kepala Cabang',
            'status_jab' => 'Aktif',
        ]);
        $this->assertDatabaseHas('tb_jabatan', [
            'id_peg' => 'TEST-0001',
            'jabatan' => 'Jabatan Sebelumnya TEST-0001',
            'status_jab' => 'Non',
            'updated_by' => 'test-admin',
        ]);
        $closedJob = DB::table('tb_jabatan')->where('id_peg', 'TEST-0001')->where('kode_jabatan', 99)->first();
        $this->assertNotNull($closedJob);
        $this->assertSame('2026-10-01', substr((string) $closedJob->sampai_tgl, 0, 10));
        $this->assertDatabaseHas('tb_jabatan', [
            'id_peg' => 'TEST-0002',
            'jabatan' => 'Jabatan Sebelumnya TEST-0002',
            'status_jab' => 'Non',
        ]);
        $this->assertSame(4, DB::table('tb_jabatan')->count());
    }

    public function test_preview_rejects_unknown_employee_and_does_not_issue_import_token(): void
    {
        Storage::fake('local');

        $response = $this->withSession($this->adminSession())
            ->post('/jabatan/import/preview', [
                'file_xlsx' => $this->jabatanFixture([
                    ['NO-SUCH-EMPLOYEE', '10', '', '000000', 'SK/UJI/003', '03-10-2026', 'Aktif'],
                ]),
            ]);

        $response->assertOk();
        $preview = $response->json();
        $this->assertSame(1, $preview['invalid_count']);
        $this->assertStringContainsString('ID pegawai tidak ditemukan', implode(' ', $preview['invalid_rows'][0]['errors']));
        $this->assertArrayNotHasKey('token', $preview);
        $this->assertSame(2, DB::table('tb_jabatan')->count());
        $this->assertSame(2, DB::table('tb_jabatan')->where('status_jab', 'Aktif')->count());
    }

    private function createSchema(): void
    {
        Schema::create('tb_pegawai', function (Blueprint $table): void {
            $table->string('id_peg')->primary();
            $table->string('nama');
            $table->integer('status_aktif');
        });

        Schema::create('tb_ref_jabatan', function (Blueprint $table): void {
            $table->integer('kode_jabatan')->primary();
            $table->string('jabatan');
            $table->string('level')->nullable();
            $table->string('group')->nullable();
            $table->integer('kuota')->nullable();
            $table->integer('status')->nullable();
            $table->string('lingkup')->nullable();
        });

        Schema::create('tb_kantor', function (Blueprint $table): void {
            $table->string('kode_kantor_detail')->primary();
            $table->string('nama_kantor');
        });

        Schema::create('tb_jabatan', function (Blueprint $table): void {
            $table->id('id_jab');
            $table->string('id_peg');
            $table->integer('kode_jabatan')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->date('tmt_jabatan')->nullable();
            $table->date('sampai_tgl')->nullable();
            $table->string('status_jab')->nullable();
            $table->string('no_sk')->nullable();
            $table->date('tgl_sk')->nullable();
            $table->date('date_reg')->nullable();
            $table->string('created_by')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('updated_by')->nullable();
        });

        Schema::create('tb_user', function (Blueprint $table): void {
            $table->string('id_user')->primary();
            $table->string('id_pegawai')->nullable();
            $table->string('hak_akses');
            $table->string('status_aktif');
        });
    }

    private function seedDirectory(): void
    {
        DB::table('tb_user')->insert([
            'id_user' => 'test-admin',
            'id_pegawai' => null,
            'hak_akses' => 'admin',
            'status_aktif' => 'Y',
        ]);
        DB::table('tb_kantor')->insert([
            'kode_kantor_detail' => '000000',
            'nama_kantor' => 'Kantor Uji',
        ]);
        DB::table('tb_ref_jabatan')->insert([
            ['kode_jabatan' => 10, 'jabatan' => 'Analis Kredit', 'group' => 'PS', 'lingkup' => 'KC'],
            ['kode_jabatan' => 20, 'jabatan' => 'Kepala Cabang', 'group' => 'PS', 'lingkup' => 'KC'],
        ]);

        foreach (['TEST-0001', 'TEST-0002'] as $id) {
            DB::table('tb_pegawai')->insert([
                'id_peg' => $id,
                'nama' => 'Pegawai '.$id,
                'status_aktif' => 1,
            ]);
            DB::table('tb_jabatan')->insert([
                'id_peg' => $id,
                'kode_jabatan' => 99,
                'jabatan' => 'Jabatan Sebelumnya '.$id,
                'unit_kerja' => '000000',
                'tmt_jabatan' => '2020-01-01',
                'status_jab' => 'Aktif',
            ]);
        }
    }

    private function adminSession(): array
    {
        return ['id_user' => 'test-admin', 'hak_akses' => 'admin'];
    }

    private function jabatanFixture(?array $dataRows = null): UploadedFile
    {
        $dataRows ??= [
            ['TEST-0001', '10', '', '000000', 'SK/UJI/001', '01-10-2026', 'Aktif'],
            ['TEST-0002', '', 'Kepala Cabang', '000000', 'SK/UJI/002', '02-10-2026', 'Aktif'],
        ];

        $temporaryPath = tempnam(sys_get_temp_dir(), 'jabatan-import-');
        $this->assertIsString($temporaryPath);
        $this->assertTrue(copy(public_path('templates/Template_Import_Jabatan_Smart.xlsx'), $temporaryPath));

        $archive = new ZipArchive;
        $this->assertSame(true, $archive->open($temporaryPath) === true);
        $worksheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($worksheetXml);

        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($worksheetXml, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        for ($rowOffset = 0; $rowOffset < 2; $rowOffset++) {
            for ($columnOffset = 0; $columnOffset < count(self::HEADERS); $columnOffset++) {
                $value = $dataRows[$rowOffset][$columnOffset] ?? '';
                $cellReference = chr(ord('A') + $columnOffset).($rowOffset + 2);
                $cell = $xpath->query('//x:c[@r="'.$cellReference.'"]')->item(0);
                $this->assertNotNull($cell, "Template harus memiliki sel {$cellReference}.");
                while ($cell->firstChild !== null) {
                    $cell->removeChild($cell->firstChild);
                }

                if ($value === '') {
                    $cell->removeAttribute('t');

                    continue;
                }

                $cell->setAttribute('t', 'inlineStr');
                $inlineString = $document->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'x:is');
                $text = $document->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'x:t');
                $text->appendChild($document->createTextNode((string) $value));
                $inlineString->appendChild($text);
                $cell->appendChild($inlineString);
            }
        }

        $updatedXml = $document->saveXML();
        $this->assertIsString($updatedXml);
        $this->assertTrue($archive->addFromString('xl/worksheets/sheet1.xml', $updatedXml, ZipArchive::FL_OVERWRITE));
        $this->assertTrue($archive->close());

        $workbook = SimpleXLSX::parse($temporaryPath);
        $this->assertNotFalse($workbook, 'Fixture harus tetap dapat dibaca sebagai workbook XLSX.');
        $this->assertSame(self::HEADERS, array_map('strval', $workbook->rows(0)[0]));

        return UploadedFile::fake()->createWithContent('jabatan.xlsx', (string) file_get_contents($temporaryPath));
    }
}
