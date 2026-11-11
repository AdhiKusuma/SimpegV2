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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use ZipArchive;

class PayrollWorkflowTest extends TestCase
{
    private string $lastPayrollFixtureContents = '';

    private const ENTRY_FIELDS = [
        'gaji_pokok', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_pajak',
        'tunj_kinerja', 'tunj_fungsional', 'tunj_masa_kerja', 'honor_kontrak', 'thl', 'uang_makan_kontrak',
        'golongan_pegawai', 'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit', 'keris',
        'jumlah_gaji', 'jumlah_potongan', 'gaji_bersih', 'total',
    ];

    private const SUMMARY_FIELDS = [
        'gaji_pokok', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_pajak',
        'tunj_kinerja', 'tunj_fungsional', 'tunj_masa_kerja', 'honor_kontrak', 'thl', 'uang_makan_kontrak',
        'golongan_pegawai', 'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit', 'keris',
        'jumlah_gaji', 'jumlah_potongan', 'gaji_bersih', 'total',
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
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
    }

    public function test_admin_can_download_preview_import_filter_and_export_payroll(): void
    {
        Storage::fake('local');
        $this->seedPayrollDirectory();
        $session = $this->adminSession();

        $template = $this->withSession($session)->get('/payroll/template');
        $template->assertOk();
        $this->assertStringContainsString('Template_Import_Payroll.xlsx', (string) $template->headers->get('content-disposition'));
        $this->assertInstanceOf(BinaryFileResponse::class, $template->baseResponse);
        $templatePath = $template->baseResponse->getFile()->getPathname();
        try {
            $this->assertSame(['Template Import', 'Contoh Pengisian'], $this->workbookSheetNames($templatePath, 2));
            $templateWorkbook = SimpleXLSX::parse($templatePath);
            $this->assertNotFalse($templateWorkbook, 'Template import payroll harus dapat dibaca.');
            $exampleSheetIndex = array_search('Contoh Pengisian', $templateWorkbook->sheetNames(), true);
            $this->assertNotFalse($exampleSheetIndex);
            $exampleRows = $templateWorkbook->rows($exampleSheetIndex);
            $this->assertSame('CONTOH-001', (string) ($exampleRows[2][2] ?? ''));
        } finally {
            @unlink($templatePath);
        }

        $preview = $this->previewPayroll($session);
        $this->assertSame(1, $preview['row_count']);
        $this->assertSame(1, $preview['office_count']);
        $this->assertSame(0, $preview['error_count']);
        $this->assertArrayHasKey('token', $preview);

        $import = $this->withSession($session)->post('/payroll/import', [
            'preview_token' => $preview['token'],
            'file_xlsx' => $this->payrollConfirmationFile(),
        ]);
        $import->assertRedirect();
        $import->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_imports', [
            'tahun' => 2026,
            'bulan' => 10,
            'is_current' => 1,
            'imported_by' => 'test-admin',
            'source_row_count' => 1,
        ]);
        $this->assertDatabaseHas('payroll_entries', [
            'employee_id' => 'TEST-0001',
            'office_code' => '000000',
            'bank_office_code' => '001',
            'gaji_pokok' => '1200000.00',
            'total' => '1200000.00',
        ]);
        $this->assertDatabaseHas('payroll_office_summaries', [
            'office_code' => '000000',
            'employee_count' => 1,
            'gaji_pokok' => '1200000.00',
            'total' => '1200000.00',
        ]);

        $secondPreview = $this->previewPayroll($session, 1300000);
        $this->assertTrue($secondPreview['replaces_current']);
        $this->withSession($session)->post('/payroll/import', [
            'preview_token' => $secondPreview['token'],
            'file_xlsx' => $this->payrollConfirmationFile(),
        ])->assertRedirect();

        $this->assertSame(2, DB::table('payroll_imports')->where('tahun', 2026)->where('bulan', 10)->count());
        $this->assertSame(1, DB::table('payroll_imports')->where('tahun', 2026)->where('bulan', 10)->where('is_current', true)->count());
        $this->assertSame(1, DB::table('payroll_imports')->where('tahun', 2026)->where('bulan', 10)->where('is_current', false)->count());

        $this->flushHeaders();
        $export = $this->withSession($session)
            ->get('/payroll/export?tahun=2026&bulan=10&office=000000');
        $export->assertOk();
        $this->assertStringContainsString('Payroll_2026_10_000000.xlsx', (string) $export->headers->get('content-disposition'));
        $this->assertInstanceOf(BinaryFileResponse::class, $export->baseResponse);
        $exportPath = $export->baseResponse->getFile()->getPathname();
        try {
            $this->assertSame(['hasil ketika export'], $this->workbookSheetNames($exportPath));
            $workbook = SimpleXLSX::parse($exportPath);
        } finally {
            @unlink($exportPath);
        }
        $this->assertNotFalse($workbook, 'Ekspor harus menghasilkan workbook XLSX yang dapat dibaca.');
        $sheetIndex = array_search('hasil ketika export', $workbook->sheetNames(), true);
        $this->assertNotFalse($sheetIndex);
        $outputRows = $workbook->rows($sheetIndex);
        $this->assertSame('001', (string) ($outputRows[17][0] ?? ''));
        $this->assertSame('00123456789', (string) ($outputRows[17][1] ?? ''));
        $this->assertSame('SIMPEG TEST', (string) ($outputRows[17][2] ?? ''));

        $page = $this->withSession($session)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => hash('xxh128', config('app.asset_url')),
            ])
            ->get('/payroll?tahun=2026&bulan=10&office=000000&view=rincian');
        $page->assertOk();
        $this->assertSame('000000', $page->json('props.filters.office'));
        $this->assertSame(1, $page->json('props.summaryRows.0.employee_count'));
        $this->assertSame('SIMPEG TEST', $page->json('props.entries.data.0.employee_name'));
        $this->assertSame('SIMPEG ADMIN', $page->json('props.payrollImport.imported_by_name'));
    }

    public function test_employee_files_are_limited_to_own_current_payroll_entry(): void
    {
        $this->seedPayrollDirectory();
        DB::table('tb_user')->insert([
            'id_user' => 'test-user',
            'nama_user' => 'SIMPEG TEST',
            'id_pegawai' => 'TEST-0001',
            'hak_akses' => 'user',
            'status_aktif' => '1',
        ]);
        $importId = DB::table('payroll_imports')->insertGetId([
            'tahun' => 2026,
            'bulan' => 10,
            'source_filename' => 'payroll.xlsx',
            'source_sha256' => hash('sha256', 'files-test'),
            'source_row_count' => 1,
            'source_summary_count' => 1,
            'is_current' => true,
            'imported_by' => 'test-admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payroll_entries')->insert([
            'payroll_import_id' => $importId,
            'office_code' => '000000',
            'employee_id' => 'TEST-0001',
            'source_row_number' => 1,
            'tgl_gajian' => '2026-10-15',
            'nama_nasabah' => 'SIMPEG TEST',
            'gaji_bersih' => 1500000,
            'jumlah_gaji' => 1500000,
            'total' => 1500000,
        ]);

        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => hash('xxh128', config('app.asset_url')),
        ];
        $page = $this->withSession([
            'id_user' => 'test-user',
            'id_pegawai' => 'TEST-0001',
            'hak_akses' => 'user',
        ])->withHeaders($headers)->get('/files');
        $page->assertOk();
        $this->assertSame(1, $page->json('props.slips.total'));
        $this->assertSame('TEST-0001', $page->json('props.slips.data.0.employee_id'));

        $page = $this->withSession([
            'id_user' => 'test-user',
            'id_pegawai' => 'TEST-0001',
            'hak_akses' => 'user',
        ])->withHeaders($headers)->get("/files/{$importId}/slip/OTHER-0001");
        $page->assertForbidden();

        $this->flushHeaders();
        $download = $this->withSession([
            'id_user' => 'test-user',
            'id_pegawai' => 'TEST-0001',
            'hak_akses' => 'user',
        ])->get("/files/{$importId}/slip/TEST-0001/download");
        $download->assertOk();
        $this->assertSame('application/pdf', $download->headers->get('content-type'));
        $this->assertInstanceOf(StreamedResponse::class, $download->baseResponse);
    }

    public function test_admin_can_filter_files_by_employee_name_or_id(): void
    {
        $this->seedPayrollDirectory();
        $importId = DB::table('payroll_imports')->insertGetId([
            'tahun' => 2026,
            'bulan' => 10,
            'source_filename' => 'payroll.xlsx',
            'source_sha256' => hash('sha256', 'files-search-test'),
            'source_row_count' => 1,
            'source_summary_count' => 1,
            'is_current' => true,
            'imported_by' => 'test-admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payroll_entries')->insert([
            'payroll_import_id' => $importId,
            'office_code' => '000000',
            'employee_id' => 'TEST-0001',
            'source_row_number' => 1,
            'tgl_gajian' => '2026-10-15',
            'nama_nasabah' => 'SIMPEG TEST',
            'gaji_bersih' => 1500000,
            'jumlah_gaji' => 1500000,
            'total' => 1500000,
        ]);

        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => hash('xxh128', config('app.asset_url')),
        ];

        $byName = $this->withSession($this->adminSession())
            ->withHeaders($headers)
            ->get('/files?employee_id=SIMPEG%20TEST');
        $byName->assertOk();
        $this->assertSame(1, $byName->json('props.slips.total'));
        $this->assertSame('TEST-0001', $byName->json('props.slips.data.0.employee_id'));

        $byId = $this->withSession($this->adminSession())
            ->withHeaders($headers)
            ->get('/files?employee_id=TEST-0001');
        $byId->assertOk();
        $this->assertSame(1, $byId->json('props.slips.total'));

        DB::table('tb_user')->where('id_user', 'test-admin')->update(['id_pegawai' => 'TEST-0001']);
        $ownProfile = $this->withSession([
            'id_user' => 'test-admin',
            'id_pegawai' => 'TEST-0001',
            'hak_akses' => 'admin',
        ])->withHeaders($headers)->get('/profile/slip-gaji?tahun=2026');
        $ownProfile->assertOk();
        $this->assertSame('Slip Gaji Saya', $ownProfile->json('props.pageTitle'));
        $this->assertFalse($ownProfile->json('props.isAdministrative'));
        $this->assertSame(1, $ownProfile->json('props.slips.total'));

        $ownDetail = $this->withSession([
            'id_user' => 'test-admin',
            'id_pegawai' => 'TEST-0001',
            'hak_akses' => 'admin',
        ])->withHeaders($headers)->get("/profile/slip-gaji/{$importId}/slip/TEST-0001");
        $ownDetail->assertOk();

        $this->withSession([
            'id_user' => 'test-admin',
            'id_pegawai' => 'TEST-0001',
            'hak_akses' => 'admin',
        ])->get("/profile/slip-gaji/{$importId}/slip/OTHER-0001")->assertForbidden();
    }

    public function test_identical_current_payroll_import_does_not_create_a_duplicate_batch(): void
    {
        Storage::fake('local');
        $this->seedPayrollDirectory();
        $session = $this->adminSession();
        $fixture = $this->payrollFixture();

        $previewResponse = $this->withSession($session)
            ->withHeader('X-Inertia', 'true')
            ->post('/payroll/import/preview', [
                'tahun' => 2026,
                'bulan' => 10,
                'file_xlsx' => $fixture,
            ]);
        $previewResponse->assertOk();
        $preview = $previewResponse->json('props.preview');
        $this->withSession($session)->post('/payroll/import', [
            'preview_token' => $preview['token'],
            'file_xlsx' => $this->payrollConfirmationFile(),
        ])->assertRedirect();

        $duplicatePreviewResponse = $this->withSession($session)
            ->withHeader('X-Inertia', 'true')
            ->post('/payroll/import/preview', [
                'tahun' => 2026,
                'bulan' => 10,
                'file_xlsx' => $this->payrollConfirmationFile(),
            ]);
        $duplicatePreviewResponse->assertOk();
        $duplicatePreview = $duplicatePreviewResponse->json('props.preview');
        $this->assertTrue($duplicatePreview['duplicate_current']);
        $this->assertFalse($duplicatePreview['duplicate_pending']);
        $this->assertArrayNotHasKey('token', $duplicatePreview);
        $this->assertSame(1, DB::table('payroll_imports')->where('tahun', 2026)->where('bulan', 10)->count());
        $this->assertSame([], Storage::disk('local')->allFiles('payroll-previews'));
    }

    public function test_prune_revisions_keeps_current_and_latest_non_current_batch(): void
    {
        $this->seedPayrollDirectory();
        $ids = [];
        foreach ([false, false, true] as $isCurrent) {
            $ids[] = DB::table('payroll_imports')->insertGetId([
                'tahun' => 2026,
                'bulan' => 10,
                'source_filename' => 'payroll-'.$isCurrent.'-'.count($ids).'.xlsx',
                'source_sha256' => hash('sha256', (string) count($ids)),
                'source_row_count' => 1,
                'source_summary_count' => 1,
                'is_current' => $isCurrent,
                'imported_by' => 'test-admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->artisan('payroll:prune-revisions')
            ->assertExitCode(0)
            ->expectsOutputToContain('Dry-run');
        $this->assertSame(3, DB::table('payroll_imports')->count());

        $this->artisan('payroll:prune-revisions', ['--execute' => true])->assertExitCode(0);
        $this->assertDatabaseMissing('payroll_imports', ['id' => $ids[0]]);
        $this->assertDatabaseHas('payroll_imports', ['id' => $ids[1], 'is_current' => 0]);
        $this->assertDatabaseHas('payroll_imports', ['id' => $ids[2], 'is_current' => 1]);
    }

    public function test_admin_can_create_master_payroll_manually_and_export_it(): void
    {
        $this->seedPayrollDirectory();
        $session = $this->adminSession();

        $create = $this->withSession($session)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => hash('xxh128', config('app.asset_url')),
            ])
            ->get('/payroll/edit/create');
        $create->assertOk();
        $this->assertSame('TEST-0001', $create->json('props.employees.0.id_peg'));

        $response = $this->withSession($session)->post('/payroll/edit', [
            'employee_id' => 'TEST-0001',
            'office_code' => '000000',
            'bank_office_code' => '001',
            'no_rekening' => '00123456789',
            'nama_nasabah' => 'SIMPEG TEST',
            'tahun' => 2026,
            'bulan' => 10,
            'tgl_gajian' => '2026-10-15',
            'gaji_pokok' => '1200000',
            'golongan_pegawai' => '500000',
            'tunj_ops' => '100000',
            'tunj_kinerja' => '50000',
            'bpjs_kesehatan' => '10000',
            'bpjs_tenaga_kerja' => '15000',
            'keris' => '5000',
        ]);
        $response->assertRedirect('/payroll?tahun=2026&bulan=10');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_salary_masters', [
            'employee_id' => 'TEST-0001',
            'office_code' => '000000',
            'bank_office_code' => '001',
            'no_rekening' => '00123456789',
            'nama_nasabah' => 'SIMPEG TEST',
            'gaji_pokok' => '1200000.00',
            'golongan_pegawai' => '500000.00',
            'jumlah_gaji' => '1850000.00',
            'jumlah_potongan' => '30000.00',
            'gaji_bersih' => '1820000.00',
            'total' => '1820000.00',
            'created_by' => 'test-admin',
        ]);
        $this->assertDatabaseHas('payroll_change_requests', [
            'change_type' => 'manual_create',
            'status' => 'approved',
            'requested_by' => 'test-admin',
            'decided_by' => 'test-admin',
        ]);

        $this->flushHeaders();
        $export = $this->withSession($session)->get('/payroll/master/export?employee_id=TEST-0001');
        $export->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $export->baseResponse);
        $exportPath = $export->baseResponse->getFile()->getPathname();
        try {
            $this->assertSame(['hasil ketika export'], $this->workbookSheetNames($exportPath));
            $workbook = SimpleXLSX::parse($exportPath);
        } finally {
            @unlink($exportPath);
        }
        $this->assertNotFalse($workbook, 'Ekspor Master Gaji harus berupa XLSX yang valid.');
        $sheetIndex = array_search('hasil ketika export', $workbook->sheetNames(), true);
        $this->assertNotFalse($sheetIndex);
        $rows = $workbook->rows($sheetIndex);
        $this->assertSame('001', (string) ($rows[17][0] ?? ''));
        $this->assertSame('00123456789', (string) ($rows[17][1] ?? ''));
        $this->assertSame('SIMPEG TEST', (string) ($rows[17][2] ?? ''));
        $this->assertSame('1700000', (string) ($rows[17][3] ?? ''));
        $this->assertSame('1820000', (string) ($rows[17][18] ?? ''));
    }

    public function test_manual_master_payroll_uses_import_validation_and_rejects_mismatched_holder(): void
    {
        $this->seedPayrollDirectory();

        $response = $this->withSession($this->adminSession())->post('/payroll/edit', [
            'employee_id' => 'TEST-0001',
            'office_code' => '000000',
            'bank_office_code' => '001',
            'no_rekening' => '00123456789',
            'nama_nasabah' => 'NAMA YANG BERBEDA',
            'tahun' => 2026,
            'bulan' => 10,
            'tgl_gajian' => '2026-10-15',
            'gaji_pokok' => '1200000',
            'total' => '1200000',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('payroll');
        $this->assertSame(0, DB::table('payroll_salary_masters')->count());
        $this->assertSame(0, DB::table('payroll_change_requests')->count());
    }

    public function test_admin_can_transfer_master_payroll_to_a_scheduled_batch(): void
    {
        $this->seedPayrollDirectory();
        DB::table('payroll_salary_masters')->insert([
            'employee_id' => 'TEST-0001',
            'office_code' => '000000',
            'bank_office_code' => '001',
            'no_rekening' => '00123456789',
            'nama_nasabah' => 'SIMPEG TEST',
            'tgl_gajian' => '2026-09-15',
            'gaji_pokok' => '1200000.00',
            'tunj_ops' => '100000.00',
            'tunj_kinerja' => '50000.00',
            'bpjs_kesehatan' => '10000.00',
            'bpjs_tenaga_kerja' => '15000.00',
            'jumlah_gaji' => '1350000.00',
            'jumlah_potongan' => '25000.00',
            'gaji_bersih' => '1325000.00',
            'total' => '1325000.00',
            'created_by' => 'test-admin',
            'updated_by' => 'test-admin',
        ]);

        $response = $this->withSession($this->adminSession())->post('/payroll/edit/transfer', [
            'tahun' => 2026,
            'bulan' => 10,
            'tgl_gajian' => '2026-10-15',
        ]);

        $response->assertRedirect('/payroll/edit');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_imports', [
            'tahun' => 2026,
            'bulan' => 10,
            'source_filename' => 'Transfer Master Gaji 10-2026',
            'source_row_count' => 1,
            'is_current' => 1,
            'imported_by' => 'test-admin',
        ]);
        $this->assertDatabaseHas('payroll_entries', [
            'employee_id' => 'TEST-0001',
            'tgl_gajian' => '2026-10-15',
            'gaji_pokok' => '1200000.00',
            'total' => '1325000.00',
        ]);
        $this->assertDatabaseHas('payroll_salary_masters', [
            'employee_id' => 'TEST-0001',
            'source_import_id' => DB::table('payroll_imports')->where('source_filename', 'Transfer Master Gaji 10-2026')->value('id'),
        ]);
    }

    public function test_admin_can_delete_master_with_confirmation_and_preserves_payroll_history(): void
    {
        $this->seedPayrollDirectory();
        $this->seedSalaryMaster();
        $importId = DB::table('payroll_imports')->insertGetId([
            'tahun' => 2026,
            'bulan' => 10,
            'source_filename' => 'riwayat.xlsx',
            'source_sha256' => str_repeat('a', 64),
            'source_row_count' => 1,
            'imported_by' => 'test-admin',
        ]);
        DB::table('payroll_entries')->insert([
            'payroll_import_id' => $importId,
            'office_code' => '000000',
            'employee_id' => 'TEST-0001',
            'source_row_number' => 2,
        ]);

        $response = $this->withSession($this->adminSession())
            ->delete('/payroll/edit/TEST-0001', ['confirmation' => 'HAPUS']);

        $response->assertRedirect('/payroll/edit');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('payroll_salary_masters', ['employee_id' => 'TEST-0001']);
        $this->assertDatabaseHas('payroll_entries', [
            'employee_id' => 'TEST-0001',
            'payroll_import_id' => $importId,
        ]);
        $this->assertDatabaseHas('payroll_change_requests', [
            'change_type' => 'manual_delete',
            'status' => 'approved',
            'requested_by' => 'test-admin',
            'decided_by' => 'test-admin',
        ]);
    }

    public function test_master_delete_requires_exact_confirmation_and_permission(): void
    {
        $this->seedPayrollDirectory();
        $this->seedSalaryMaster();

        $invalidConfirmation = $this->withSession($this->adminSession())
            ->delete('/payroll/edit/TEST-0001', ['confirmation' => 'hapus']);
        $invalidConfirmation->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('payroll_salary_masters', ['employee_id' => 'TEST-0001']);
        $this->assertSame(0, DB::table('payroll_change_requests')->count());

        $this->flushHeaders();
        $forbidden = $this->withSession(['id_user' => 'test-user', 'hak_akses' => 'user'])
            ->delete('/payroll/edit/TEST-0001', ['confirmation' => 'HAPUS']);
        $forbidden->assertForbidden();
        $this->assertDatabaseHas('payroll_salary_masters', ['employee_id' => 'TEST-0001']);
    }

    public function test_master_delete_is_blocked_while_payroll_request_is_pending(): void
    {
        $this->seedPayrollDirectory();
        $this->seedSalaryMaster();
        $changeId = DB::table('payroll_change_requests')->insertGetId([
            'change_type' => 'edit',
            'requested_by' => 'test-supervisor',
            'office_codes' => json_encode(['000000']),
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
        DB::table('payroll_change_request_entries')->insert([
            'payroll_change_request_id' => $changeId,
            'employee_id' => 'TEST-0001',
            'office_code' => '000000',
            'old_data' => json_encode(['total' => '1200000.00']),
            'new_data' => json_encode(['total' => '1300000.00']),
        ]);

        $response = $this->withSession($this->adminSession())
            ->delete('/payroll/edit/TEST-0001', ['confirmation' => 'HAPUS']);

        $response->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('payroll_salary_masters', ['employee_id' => 'TEST-0001']);
        $this->assertDatabaseHas('payroll_change_requests', ['id' => $changeId, 'status' => 'pending']);
    }

    public function test_non_admin_master_delete_waits_for_office_approval(): void
    {
        $this->seedPayrollDirectory();
        $this->seedSalaryMaster();
        Schema::create('rbac_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('label', 160);
            $table->string('group_name', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('rbac_access_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_id')->constrained('rbac_permissions')->cascadeOnDelete();
            $table->string('subject_type', 24);
            $table->string('subject_value', 160);
            $table->boolean('allowed')->default(true);
            $table->timestamps();
            $table->unique(['permission_id', 'subject_type', 'subject_value']);
        });
        $deletePermissionId = DB::table('rbac_permissions')->insertGetId([
            'code' => 'payroll.master.delete',
            'label' => 'Hapus master gaji',
            'group_name' => 'Master gaji',
            'sort_order' => 355,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('rbac_access_rules')->insert([
            'permission_id' => $deletePermissionId,
            'subject_type' => 'role',
            'subject_value' => 'kepala',
            'allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = $this->withSession([
            'id_user' => 'test-kepala',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000000',
        ])->delete('/payroll/edit/TEST-0001', ['confirmation' => 'HAPUS']);

        $request->assertRedirect('/payroll/edit');
        $request->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_salary_masters', ['employee_id' => 'TEST-0001']);
        $changeId = DB::table('payroll_change_requests')->where('change_type', 'manual_delete')->value('id');
        $this->assertNotNull($changeId);
        $this->assertDatabaseHas('payroll_change_requests', ['id' => $changeId, 'status' => 'pending']);

        $decision = $this->withSession([
            'id_user' => 'test-supervisor',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000000',
        ])->post("/payroll/approvals/{$changeId}/decision", ['decision' => 'approve']);

        $decision->assertRedirect('/payroll/approvals');
        $decision->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('payroll_salary_masters', ['employee_id' => 'TEST-0001']);
        $this->assertDatabaseHas('payroll_change_requests', ['id' => $changeId, 'status' => 'approved']);
    }

    public function test_preview_rejects_unknown_employee_without_creating_an_import(): void
    {
        Storage::fake('local');
        $this->seedPayrollDirectory(includeEmployee: false);

        $response = $this->withSession($this->adminSession())
            ->withHeader('X-Inertia', 'true')
            ->post('/payroll/import/preview', [
                'tahun' => 2026,
                'bulan' => 10,
                'file_xlsx' => $this->payrollFixture(),
            ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('props.preview.row_count'));
        $this->assertSame(1, $response->json('props.preview.error_count'));
        $this->assertStringContainsString('ID Pegawai tidak ditemukan', json_encode($response->json('props.preview.errors')));
        $this->assertNull($response->json('props.preview.token'));
        $this->assertSame(0, DB::table('payroll_imports')->count());
        $this->assertSame(0, DB::table('payroll_entries')->count());
    }

    public function test_preview_rejects_employee_id_that_only_matches_database_collation(): void
    {
        Storage::fake('local');
        $this->seedPayrollDirectory(includeEmployee: false);
        DB::table('tb_pegawai')->insert([
            'id_peg' => 'test-0001',
            'nama' => 'SIMPEG TEST',
            'status_aktif' => '1',
        ]);
        DB::table('tb_jabatan')->insert([
            'id_peg' => 'test-0001',
            'jabatan' => 'Jabatan Uji',
            'unit_kerja' => '000000',
            'tmt_jabatan' => '2026-01-01',
            'status_jab' => 'Aktif',
        ]);

        $response = $this->withSession($this->adminSession())
            ->withHeader('X-Inertia', 'true')
            ->post('/payroll/import/preview', [
                'tahun' => 2026,
                'bulan' => 10,
                'file_xlsx' => $this->payrollFixture(),
            ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('props.preview.row_count'));
        $this->assertSame(1, $response->json('props.preview.error_count'));
        $this->assertStringContainsString('ID Pegawai tidak ditemukan', json_encode($response->json('props.preview.errors')));
        $this->assertNull($response->json('props.preview.token'));
        $this->assertSame(0, DB::table('payroll_imports')->count());
        $this->assertSame(0, DB::table('payroll_entries')->count());
    }

    public function test_preview_rejects_valid_employee_id_with_different_account_holder_name(): void
    {
        Storage::fake('local');
        $this->seedPayrollDirectory();
        DB::table('tb_pegawai')->where('id_peg', 'TEST-0001')->update(['nama' => 'PEGAWAI YANG BERBEDA']);

        $preview = $this->previewPayroll($this->adminSession());

        $this->assertSame(0, $preview['row_count']);
        $this->assertSame(1, $preview['error_count']);
        $this->assertStringContainsString('Nama Nasabah tidak sesuai', json_encode($preview['errors']));
        $this->assertArrayNotHasKey('token', $preview);
        $this->assertSame(0, DB::table('payroll_imports')->count());
        $this->assertSame(0, DB::table('payroll_entries')->count());
    }

    public function test_preview_matches_account_holder_name_ignoring_case_punctuation_and_degree_suffix(): void
    {
        Storage::fake('local');
        $this->seedPayrollDirectory();
        DB::table('tb_pegawai')->where('id_peg', 'TEST-0001')->update(['nama' => 'simpeg test, S.Kom.']);

        $preview = $this->previewPayroll($this->adminSession());

        $this->assertSame(1, $preview['row_count']);
        $this->assertSame(0, $preview['error_count']);
        $this->assertNotEmpty($preview['token']);
    }

    public function test_preview_rejects_payroll_office_different_from_employee_active_office(): void
    {
        Storage::fake('local');
        $this->seedPayrollDirectory();
        DB::table('tb_kantor')->insert([
            'kode_kantor_detail' => '001000',
            'kode_cabang' => '001',
            'nama_kantor' => 'Kantor Cabang Uji',
            'level' => 'KC',
        ]);
        DB::table('tb_jabatan')->where('id_peg', 'TEST-0001')->update(['unit_kerja' => '001000']);

        $response = $this->withSession($this->adminSession())
            ->withHeader('X-Inertia', 'true')
            ->post('/payroll/import/preview', [
                'tahun' => 2026,
                'bulan' => 10,
                'file_xlsx' => $this->payrollFixture(),
            ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('props.preview.row_count'));
        $this->assertSame(1, $response->json('props.preview.error_count'));
        $this->assertStringContainsString('Kode Kantor tidak sesuai', json_encode($response->json('props.preview.errors')));
        $this->assertNull($response->json('props.preview.token'));
        $this->assertSame(0, DB::table('payroll_imports')->count());
        $this->assertSame(0, DB::table('payroll_entries')->count());
    }

    public function test_preview_rejects_non_numeric_bank_office_code(): void
    {
        Storage::fake('local');
        $this->seedPayrollDirectory();

        $response = $this->withSession($this->adminSession())
            ->withHeader('X-Inertia', 'true')
            ->post('/payroll/import/preview', [
                'tahun' => 2026,
                'bulan' => 10,
                'file_xlsx' => $this->payrollFixtureWithBankOfficeCode('12A'),
            ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('props.preview.row_count'));
        $this->assertSame(1, $response->json('props.preview.error_count'));
        $this->assertStringContainsString('Kode Kantor rekening harus berisi satu sampai tiga digit angka', json_encode($response->json('props.preview.errors')));
        $this->assertNull($response->json('props.preview.token'));
        $this->assertSame(0, DB::table('payroll_imports')->count());
        $this->assertSame(0, DB::table('payroll_entries')->count());
    }

    public function test_preview_rejects_bank_office_code_that_differs_from_account_prefix(): void
    {
        Storage::fake('local');
        $this->seedPayrollDirectory();

        $response = $this->withSession($this->adminSession())
            ->withHeader('X-Inertia', 'true')
            ->post('/payroll/import/preview', [
                'tahun' => 2026,
                'bulan' => 10,
                'file_xlsx' => $this->payrollFixtureWithBankOfficeCode('123'),
            ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('props.preview.row_count'));
        $this->assertSame(1, $response->json('props.preview.error_count'));
        $this->assertStringContainsString('Kode Kantor rekening harus sama dengan tiga digit awal No Rekening', json_encode($response->json('props.preview.errors')));
        $this->assertNull($response->json('props.preview.token'));
        $this->assertSame(0, DB::table('payroll_imports')->count());
        $this->assertSame(0, DB::table('payroll_entries')->count());
    }

    public function test_non_admin_cannot_open_payroll_import(): void
    {
        $response = $this->withSession([
            'id_user' => 'test-user',
            'hak_akses' => 'user',
        ])->get('/payroll/import');

        $response->assertForbidden();
    }

    public function test_get_preview_url_redirects_to_import_form_with_guidance(): void
    {
        $response = $this->withSession($this->adminSession())->get('/payroll/import/preview');

        $response->assertRedirect('/payroll/import');
        $response->assertSessionHas('error', 'Preview dibuat setelah file XLSX diunggah. Pilih file, lalu tekan Periksa dan tampilkan preview.');
    }

    public function test_get_preview_url_redirects_view_only_users_to_payroll_page(): void
    {
        $response = $this->withSession([
            'id_user' => 'test-viewer',
            'hak_akses' => 'superadmin',
        ])->get('/payroll/import/preview');

        $response->assertRedirect('/payroll');
        $response->assertSessionHas('error', 'Halaman preview hanya tersedia setelah file XLSX dipilih. Hak akses Anda hanya mencakup melihat data payroll.');
    }

    private function previewPayroll(array $session, int $baseSalary = 1200000): array
    {
        $response = $this->withSession($session)
            ->withHeader('X-Inertia', 'true')
            ->post('/payroll/import/preview', [
                'tahun' => 2026,
                'bulan' => 10,
                'file_xlsx' => $this->payrollFixtureWithBankOfficeCode('001', $baseSalary),
            ]);

        $response->assertOk();

        return $response->json('props.preview');
    }

    private function payrollFixture(): UploadedFile
    {
        return $this->payrollFixtureWithBankOfficeCode('001');
    }

    private function payrollFixtureWithBankOfficeCode(string $bankOfficeCode, int $baseSalary = 1200000): UploadedFile
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'payroll-test-');
        $this->assertIsString($temporaryPath);
        $this->assertTrue(copy(resource_path('templates/payroll/Template_Import_Payroll.xlsx'), $temporaryPath));

        $archive = new ZipArchive;
        $this->assertSame(true, $archive->open($temporaryPath) === true);
        $worksheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($worksheetXml);

        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($worksheetXml, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $earnings = [
            'gaji_pokok' => $baseSalary,
            'tunj_suami_istri' => 0,
            'tunj_anak' => 0,
            'tunj_jabatan' => 0,
            'tunj_ops' => 0,
            'tunj_kinerja' => 0,
            'tunj_fungsional' => 0,
            'tunj_masa_kerja' => 0,
            'honor_kontrak' => 0,
            'thl' => 0,
            'tunj_pajak' => 0,
            'uang_makan_kontrak' => 0,
            'golongan_pegawai' => 0,
        ];
        $deductions = [
            'bpjs_kesehatan' => 0,
            'bpjs_tenaga_kerja' => 0,
            'absensi' => 0,
            'angsuran_kredit' => 0,
            'keris' => 0,
        ];
        $gross = array_sum($earnings);
        $totalDeductions = array_sum($deductions);
        $net = $gross - $totalDeductions;
        $values = [
            'A' => '1', 'B' => '000000', 'C' => 'TEST-0001', 'D' => $bankOfficeCode,
            'E' => '00123456789', 'F' => 'SIMPEG TEST', 'G' => 'Jabatan Uji', 'H' => '000000',
            'I' => 'Tetap', 'J' => '0', 'K' => '0', 'L' => '0', 'M' => '0',
            'N' => (string) $earnings['gaji_pokok'], 'O' => (string) $earnings['tunj_suami_istri'],
            'P' => (string) $earnings['tunj_anak'], 'Q' => (string) $earnings['tunj_jabatan'],
            'R' => (string) $earnings['tunj_ops'], 'S' => (string) $earnings['tunj_kinerja'],
            'T' => (string) $earnings['tunj_masa_kerja'], 'U' => (string) $earnings['tunj_fungsional'],
            'V' => (string) $earnings['honor_kontrak'], 'W' => (string) $earnings['thl'],
            'X' => (string) $earnings['tunj_pajak'], 'Y' => (string) $earnings['uang_makan_kontrak'],
            'Z' => (string) $gross, 'AA' => (string) $deductions['bpjs_kesehatan'],
            'AB' => (string) $deductions['bpjs_tenaga_kerja'], 'AC' => (string) $deductions['absensi'],
            'AD' => (string) $deductions['angsuran_kredit'], 'AE' => (string) $deductions['keris'],
            'AF' => (string) $totalDeductions, 'AG' => (string) $net, 'AH' => '', 'AI' => '',
            'AJ' => '15-10-2026',
        ];
        foreach ($values as $column => $value) {
            $reference = $column.'3';
            $cell = $xpath->query('//x:c[@r="'.$reference.'"]')->item(0);
            $this->assertInstanceOf(\DOMElement::class, $cell, "Template harus memiliki sel {$reference}.");
            while ($cell->firstChild !== null) {
                $cell->removeChild($cell->firstChild);
            }

            if (in_array($column, ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'AH', 'AI', 'AJ'], true)) {
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

        $updatedXml = $document->saveXML();
        $this->assertIsString($updatedXml);
        $this->assertTrue($archive->addFromString('xl/worksheets/sheet1.xml', $updatedXml, ZipArchive::FL_OVERWRITE));
        $this->assertTrue($archive->close());
        $contents = file_get_contents($temporaryPath);
        @unlink($temporaryPath);
        $this->assertIsString($contents);
        $this->lastPayrollFixtureContents = $contents;

        return UploadedFile::fake()->createWithContent('payroll_synthetic.xlsx', $contents);
    }

    private function payrollConfirmationFile(): UploadedFile
    {
        $this->assertNotSame('', $this->lastPayrollFixtureContents);

        return UploadedFile::fake()->createWithContent('payroll_synthetic.xlsx', $this->lastPayrollFixtureContents);
    }

    private function workbookSheetNames(string $path, int $expectedWorksheetRelationshipCount = 1): array
    {
        $archive = new ZipArchive;
        $this->assertSame(true, $archive->open($path) === true, 'File harus berupa arsip XLSX yang valid.');
        try {
            $xml = $archive->getFromName('xl/workbook.xml');
            $this->assertIsString($xml, 'Workbook harus memiliki metadata tab.');

            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($xml, LIBXML_NONET));
            $names = [];
            foreach ($document->getElementsByTagNameNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'sheet') as $sheet) {
                $names[] = $sheet->getAttribute('name');
            }

            $relationshipsXml = $archive->getFromName('xl/_rels/workbook.xml.rels');
            $this->assertIsString($relationshipsXml, 'Workbook harus memiliki relasi tab.');
            $relationships = new DOMDocument;
            $this->assertTrue($relationships->loadXML($relationshipsXml, LIBXML_NONET));
            $worksheetRelationshipCount = 0;
            foreach ($relationships->getElementsByTagNameNS('http://schemas.openxmlformats.org/package/2006/relationships', 'Relationship') as $relationship) {
                if (str_ends_with($relationship->getAttribute('Type'), '/worksheet')) {
                    $worksheetRelationshipCount++;
                }
            }
            $this->assertSame($expectedWorksheetRelationshipCount, $worksheetRelationshipCount, 'Workbook memiliki jumlah relasi tab yang tidak sesuai.');

            return $names;
        } finally {
            $archive->close();
        }
    }

    private function adminSession(): array
    {
        return [
            'id_user' => 'test-admin',
            'hak_akses' => 'admin',
        ];
    }

    private function seedPayrollDirectory(bool $includeEmployee = true): void
    {
        DB::table('tb_kantor')->insert([
            'kode_kantor_detail' => '000000',
            'kode_cabang' => '000',
            'nama_kantor' => 'Kantor Uji Sintetis',
            'level' => 'KP',
        ]);
        DB::table('tb_user')->insert([
            'id_user' => 'test-admin',
            'nama_user' => 'SIMPEG ADMIN',
            'id_pegawai' => null,
            'hak_akses' => 'admin',
            'status_aktif' => 'Y',
        ]);

        if ($includeEmployee) {
            DB::table('tb_pegawai')->insert([
                'id_peg' => 'TEST-0001',
                'nama' => 'SIMPEG TEST',
                'nik' => '1234567890123456',
                'status_kepeg' => 'Tetap',
                'status_aktif' => '1',
                'tmt_kerja' => '2020-01-01',
            ]);
            DB::table('tb_jabatan')->insert([
                'id_peg' => 'TEST-0001',
                'jabatan' => 'Jabatan Uji',
                'unit_kerja' => '000000',
                'tmt_jabatan' => '2026-01-01',
                'status_jab' => 'Aktif',
            ]);
        }
    }

    private function seedSalaryMaster(): void
    {
        DB::table('payroll_salary_masters')->insert([
            'employee_id' => 'TEST-0001',
            'office_code' => '000000',
            'bank_office_code' => '001',
            'no_rekening' => '00123456789',
            'nama_nasabah' => 'SIMPEG TEST',
            'tgl_gajian' => '2026-10-15',
            'gaji_pokok' => '1200000.00',
            'total' => '1200000.00',
            'created_by' => 'test-admin',
            'updated_by' => 'test-admin',
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('tb_config', function (Blueprint $table): void {
            $table->string('id_app')->primary();
            $table->string('nama_app')->nullable();
            $table->string('alias_app')->nullable();
            $table->string('desc_app')->nullable();
            $table->string('logo')->nullable();
        });

        Schema::create('tb_kantor', function (Blueprint $table): void {
            $table->string('kode_kantor_detail')->primary();
            $table->string('kode_cabang')->nullable();
            $table->string('nama_kantor')->nullable();
            $table->string('level')->nullable();
        });

        Schema::create('tb_pegawai', function (Blueprint $table): void {
            $table->string('id_peg')->collation('NOCASE')->primary();
            $table->string('nama')->nullable();
            $table->string('nik')->nullable();
            $table->string('status_kepeg')->nullable();
            $table->string('status_aktif')->nullable();
            $table->date('tmt_kerja')->nullable();
        });

        Schema::create('tb_jabatan', function (Blueprint $table): void {
            $table->id('id_jab');
            $table->string('id_peg');
            $table->string('jabatan')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->date('tmt_jabatan')->nullable();
            $table->string('status_jab')->nullable();
        });

        Schema::create('tb_master_jabatan', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_jabatan')->nullable();
            $table->string('nama_unit_kerja')->nullable();
        });

        foreach (['tb_suamiistri', 'tb_anak'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('id_peg');
            });
        }

        Schema::create('tb_user', function (Blueprint $table): void {
            $table->string('id_user')->primary();
            $table->string('nama_user')->nullable();
            $table->string('id_pegawai')->nullable();
            $table->string('hak_akses')->nullable();
            $table->string('status_aktif')->nullable();
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

        Schema::create('payroll_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payroll_import_id');
            $table->string('office_code', 20);
            $table->string('employee_id', 12);
            $table->unsignedInteger('source_row_number');
            $table->string('bank_office_code', 20)->nullable();
            $table->string('no_rekening', 64)->nullable();
            $table->string('nama_nasabah', 150)->nullable();
            $table->string('rekening_tabungan', 64)->nullable();
            $table->string('no_hp_keris', 32)->nullable();
            $table->date('tgl_gajian')->nullable();
            foreach (self::ENTRY_FIELDS as $field) {
                $table->decimal($field, 18, 2)->default(0);
            }
            $table->timestamps();
        });

        Schema::create('payroll_office_summaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('payroll_import_id');
            $table->string('office_code', 20);
            $table->string('source_label', 100)->nullable();
            $table->unsignedInteger('employee_count')->default(0);
            foreach (self::SUMMARY_FIELDS as $field) {
                $table->decimal($field, 18, 2)->default(0);
            }
            $table->timestamps();
        });

        Schema::create('payroll_salary_masters', function (Blueprint $table): void {
            $table->id();
            $table->string('employee_id', 12)->unique();
            $table->string('office_code', 20);
            $table->string('bank_office_code', 20)->nullable();
            $table->string('no_rekening', 64)->nullable();
            $table->string('nama_nasabah', 150)->nullable();
            $table->string('rekening_tabungan', 64)->nullable();
            $table->string('no_hp_keris', 32)->nullable();
            $table->date('tgl_gajian')->nullable();
            foreach (self::ENTRY_FIELDS as $field) {
                $table->decimal($field, 18, 2)->default(0);
            }
            $table->unsignedBigInteger('source_import_id')->nullable();
            $table->string('created_by', 64)->nullable();
            $table->string('updated_by', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('change_type', 20);
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->unsignedTinyInteger('bulan')->nullable();
            $table->string('source_filename', 255)->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->string('requested_by', 64);
            $table->json('office_codes');
            $table->json('summary_data')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('decision_reason')->nullable();
            $table->string('decided_by', 64)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_change_request_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_change_request_id')->constrained('payroll_change_requests')->cascadeOnDelete();
            $table->string('employee_id', 12);
            $table->string('office_code', 20);
            $table->unsignedInteger('source_row_number')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data');
            $table->timestamps();
            $table->unique(['payroll_change_request_id', 'employee_id']);
        });
    }
}
