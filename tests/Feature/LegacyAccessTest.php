<?php

namespace Tests\Feature;

use App\Services\XlsxExportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyAccessTest extends TestCase
{
    public function test_login_page_is_available_without_a_session(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('SIMPEG');
    }

    public function test_protected_employee_route_redirects_to_login_without_a_session(): void
    {
        $response = $this->get('/pegawai');

        $response->assertRedirectToRoute('login');
    }

    public function test_login_requires_both_legacy_credentials(): void
    {
        $response = $this->from('/login')->post('/login');

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['id_user', 'password']);
    }

    public function test_user_role_can_open_profile_but_not_staff_routes(): void
    {
        $this->withSession([
            'id_user' => 'user-demo',
            'hak_akses' => 'user',
            'id_pegawai' => 'pegawai-demo',
        ]);

        $this->get('/profile')->assertOk();
        $this->get('/pegawai')->assertForbidden();
        $this->get('/pegawai/create')->assertForbidden();
        $this->get('/pegawai/import')->assertForbidden();
    }

    public function test_user_role_can_open_notifications_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'user-demo',
            'hak_akses' => 'user',
        ])->get('/notifikasi');

        $response->assertOk();
        $response->assertSee('Notifications\/Index');
    }

    public function test_kepala_role_is_limited_to_branch_dashboard(): void
    {
        $this->withSession([
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '-',
        ]);

        $this->get('/dashboard/cabang')->assertRedirectToRoute('dashboard');
        $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->where('dashboardLocked', true)
            ->where('canSelectOffice', false));
    }

    public function test_kepala_can_open_the_inactive_employee_card_destination(): void
    {
        $response = $this->withSession([
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ])->get('/mutasi');

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Mutation/Index')
            ->where('canManage', false)
            ->has('items.data'));
    }

    public function test_admin_dashboard_contains_legacy_charts_and_position_tables_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/dashboard');

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->loadDeferredProps('dashboard-details', fn ($page) => $page
                ->has('charts.gender')
                ->has('charts.education')
                ->has('charts.employment')
                ->has('charts.violations')
                ->has('charts.positions')
                ->has('retirement')
                ->has('executivePositions')
                ->has('structuralPositions')
                ->where('charts.gender.labels', ['Laki-laki', 'Perempuan'])));
    }

    public function test_superadmin_can_view_admin_dashboard_and_read_only_modules(): void
    {
        $session = [
            'id_user' => 'superadmin-demo',
            'hak_akses' => 'superadmin',
        ];

        $dashboard = $this->withSession($session)->get('/dashboard');
        $dashboard->assertOk()->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->where('dashboardLocked', false)
            ->where('canSelectOffice', true));

        $users = $this->withSession($session)->get('/users');
        $users->assertOk()->assertInertia(fn ($page) => $page
            ->component('Users/Index')
            ->where('canManage', false));

        foreach ([
            '/pegawai/create',
            '/pegawai/import',
            '/pendidikan/create',
            '/diklat/create',
            '/jabatan/create',
            '/pengangkatan/create',
            '/mutasi/create',
            '/biaya-pendidikan/create',
            '/sertifikasi/create',
            '/users/create',
            '/config/aplikasi',
        ] as $writeRoute) {
            $this->withSession($session)->get($writeRoute)->assertForbidden();
        }

        $this->withSession($session)->post('/users', [])->assertForbidden();
    }

    public function test_user_role_cannot_open_staff_reports(): void
    {
        $this->withSession([
            'id_user' => 'user-demo',
            'hak_akses' => 'user',
        ]);

        $this->get('/laporan/formasi')->assertForbidden();
        $this->get('/laporan/rekap-diklat')->assertForbidden();
        $this->get('/laporan/rekap-biaya-pendidikan')->assertForbidden();
        $this->get('/config/aplikasi')->assertForbidden();
    }

    public function test_admin_can_open_the_formation_report_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/laporan/formasi');

        $response->assertOk();
        $response->assertSee('Reports\\/Formation');
    }

    public function test_admin_can_export_the_formation_report_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/laporan/formasi/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('laporan-formasi-', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_admin_can_open_and_export_training_costs_from_local_mysql(): void
    {
        $session = [
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ];

        $page = $this->withSession($session)->get('/laporan/rekap-diklat');
        $page->assertOk();
        $page->assertSee('Reports\\/TrainingCosts');

        $export = $this->withSession($session)->get('/laporan/rekap-diklat/export');
        $export->assertOk();
        $export->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('rekap-biaya-diklat-', (string) $export->headers->get('Content-Disposition'));
    }

    public function test_admin_can_open_and_export_education_costs_from_local_mysql(): void
    {
        $session = [
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ];

        $page = $this->withSession($session)->get('/laporan/rekap-biaya-pendidikan');
        $page->assertOk();
        $page->assertSee('Reports\\/EducationCosts');

        $export = $this->withSession($session)->get('/laporan/rekap-biaya-pendidikan/export?type=excel');
        $export->assertOk();
        $export->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('rekap-biaya-pendidikan-', (string) $export->headers->get('Content-Disposition'));

        $print = $this->withSession($session)->get('/laporan/rekap-biaya-pendidikan/export?type=pdf');
        $print->assertOk();
        $print->assertSee('Rekapitulasi Biaya Pendidikan');
    }

    public function test_admin_can_open_legacy_education_activity_management_from_local_mysql(): void
    {
        $session = [
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ];

        $this->withSession($session)->get('/biaya-pendidikan')->assertOk()->assertSee('BiayaPendidikan\/Index');
        $this->withSession($session)->get('/biaya-pendidikan/create')->assertOk()->assertSee('BiayaPendidikan\/Form');
        $this->withSession($session)->get('/biaya-pendidikan/import')->assertOk()->assertSee('BiayaPendidikan\/Import');
        $this->withSession($session)->get('/biaya-pendidikan/template')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_kepala_can_read_education_activity_list_but_cannot_manage_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/biaya-pendidikan')->assertOk();
        $this->withSession($session)->get('/biaya-pendidikan/create')->assertForbidden();
        $this->withSession($session)->get('/biaya-pendidikan/import')->assertForbidden();
    }

    public function test_user_role_cannot_open_education_activity_management(): void
    {
        $session = [
            'id_user' => 'user-demo',
            'hak_akses' => 'user',
        ];

        $this->withSession($session)->get('/biaya-pendidikan')->assertForbidden();
        $this->withSession($session)->get('/biaya-pendidikan/create')->assertForbidden();
    }

    public function test_education_activity_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/biaya-pendidikan', [
            'kode_pengembangan' => '',
            'pengembangan_sdm' => '',
            'jumlah_sdm' => 0,
            'tgl_pengembangan_sdm' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors(['kode_pengembangan', 'pengembangan_sdm', 'jumlah_sdm', 'tgl_pengembangan_sdm']);
    }

    public function test_education_activity_xlsx_preview_does_not_write_until_confirmed(): void
    {
        $xlsx = (new XlsxExportService)->make([
            ['KODE_KATEGORI', 'KODE_PIHAK', 'JUDUL', 'WAKTU', 'PIHAK', 'JUMLAH', 'BIAYA', 'TANGGAL'],
            ['not-used', 'not-used', 'Preview kegiatan', '2 Hari', 'Unit pelaksana', 2, 1000000, '2026-01-10'],
        ], 'Template Biaya Pendidikan');

        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/biaya-pendidikan/import/preview', [
            'file_excel' => UploadedFile::fake()->createWithContent('biaya.xlsx', $xlsx),
        ]);

        $response->assertOk();
        $response->assertSee('BiayaPendidikan\/Import');
        $this->assertSame(0, DB::table('tb_biaya_pendidikan')->where('pengembangan_sdm', 'Preview kegiatan')->count());
    }

    public function test_admin_can_open_application_configuration_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/config/aplikasi');

        $response->assertOk();
        $response->assertSee('Config\\/Application');
    }

    public function test_application_configuration_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/config/aplikasi?id=1', [
            'nama_app' => '',
            'url_app' => 'not-a-url',
        ]);

        $response->assertSessionHasErrors(['nama_app', 'url_app']);
    }

    public function test_admin_can_open_employee_create_form_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/pegawai/create');

        $response->assertOk();
        $response->assertSee('Employees\\/Form');
    }

    public function test_admin_can_open_purna_employee_tab_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/pegawai?tab=purna');

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Employees/Index')
            ->where('filters.tab', 'purna')
            ->has('employees.data'));
    }

    public function test_admin_can_open_education_and_training_menus_from_local_mysql(): void
    {
        $session = [
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ];

        $this->withSession($session)->get('/pendidikan')->assertOk()->assertSee('Pendidikan\\/Index');
        $this->withSession($session)->get('/diklat')->assertOk()->assertSee('Diklat\\/Index');
    }

    public function test_admin_can_open_employee_detail_with_legacy_history_sections(): void
    {
        $id = DB::table('tb_pegawai')->value('id_peg');
        $this->assertNotNull($id);

        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/pegawai/'.urlencode((string) $id));

        $response->assertOk();
        $response->assertSee('Employees\\/Show');
    }

    public function test_admin_can_open_pangkat_module_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/pangkat');

        $response->assertOk();
        $response->assertSee('Pangkat\\/Index');
    }

    public function test_kepala_can_read_pangkat_but_cannot_manage_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/pangkat')->assertOk();
        $this->withSession($session)->get('/pangkat/create')->assertForbidden();
    }

    public function test_admin_can_open_jabatan_create_flow_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/jabatan/create');

        $response->assertOk();
        $response->assertSee('Jabatan\/Form');
    }

    public function test_kepala_can_read_jabatan_but_cannot_manage_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/jabatan')->assertOk();
        $this->withSession($session)->get('/jabatan/create')->assertForbidden();
    }

    public function test_jabatan_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/jabatan', [
            'id_peg' => '',
            'form_mode' => 'invalid',
            'unit_kerja' => '',
            'no_sk' => '',
            'tgl_sk' => 'not-a-date',
            'tmt_mulai' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors(['id_peg', 'form_mode', 'kode_jabatan', 'unit_kerja', 'no_sk', 'tgl_sk', 'tmt_mulai']);
    }

    public function test_pangkat_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/pangkat', [
            'id_peg' => '',
            'pangkat' => '',
            'gol' => 'not-a-golongan',
            'jns_pangkat' => '',
            'pejabat_sk' => '',
            'no_sk' => '',
            'tgl_sk' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors(['id_peg', 'pangkat', 'gol', 'jns_pangkat', 'pejabat_sk', 'no_sk', 'tgl_sk']);
    }

    public function test_admin_can_open_pengangkatan_module_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/pengangkatan');

        $response->assertOk();
        $response->assertSee('Pengangkatan\/Index');
    }

    public function test_kepala_can_read_pengangkatan_but_cannot_create_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/pengangkatan')->assertOk();
        $this->withSession($session)->get('/pengangkatan/create')->assertForbidden();
    }

    public function test_pengangkatan_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/pengangkatan', [
            'id_peg' => '',
            'jns_mutasi' => 'Tidak valid',
            'id_peg_baru' => '',
            'tgl_mutasi' => 'not-a-date',
            'tmt' => 'not-a-date',
            'no_mutasi' => '',
        ]);

        $response->assertSessionHasErrors(['id_peg', 'jns_mutasi', 'id_peg_baru', 'tgl_mutasi', 'tmt', 'no_mutasi']);
    }

    public function test_admin_can_open_master_golongan_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/golongan');

        $response->assertOk();
        $response->assertSee('Golongan\/Index');
    }

    public function test_non_admin_cannot_manage_master_golongan(): void
    {
        $this->withSession([
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ])->get('/golongan/0001/edit')->assertForbidden();

        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/golongan', ['nama_mastergol' => '']);

        $response->assertSessionHasErrors('nama_mastergol');
    }

    public function test_admin_can_open_master_eselon_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/eselon');

        $response->assertOk();
        $response->assertSee('Eselon\/Index');
    }

    public function test_kepala_can_read_master_eselon_but_cannot_manage_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/eselon')->assertOk();
        $this->withSession($session)->get('/eselon/0001/edit')->assertForbidden();
    }

    public function test_eselon_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/eselon', ['nama_masteresl' => '']);

        $response->assertSessionHasErrors('nama_masteresl');
    }

    public function test_admin_can_open_penghargaan_module_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/penghargaan');

        $response->assertOk();
        $response->assertSee('Penghargaan\/Index');
    }

    public function test_kepala_can_read_penghargaan_but_cannot_manage_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/penghargaan')->assertOk();
        $this->withSession($session)->get('/penghargaan/create')->assertForbidden();
    }

    public function test_penghargaan_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/penghargaan', [
            'id_peg' => '',
            'penghargaan' => '',
            'tahun' => '',
            'pemberi' => '',
        ]);

        $response->assertSessionHasErrors(['id_peg', 'penghargaan', 'tahun', 'pemberi']);
    }

    public function test_admin_can_open_penugasan_module_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/penugasan');

        $response->assertOk();
        $response->assertSee('Penugasan\/Index');
    }

    public function test_kepala_can_read_penugasan_but_cannot_manage_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/penugasan')->assertOk();
        $this->withSession($session)->get('/penugasan/create')->assertForbidden();
    }

    public function test_penugasan_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/penugasan', [
            'id_peg' => '',
            'tujuan' => '',
            'tahun' => '',
            'lama' => '',
            'alasan' => '',
        ]);

        $response->assertSessionHasErrors(['id_peg', 'tujuan', 'tahun', 'lama', 'alasan']);
    }

    public function test_admin_can_open_seminar_module_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/seminar');

        $response->assertOk();
        $response->assertSee('Seminar\/Index');
    }

    public function test_kepala_can_read_seminar_but_cannot_manage_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/seminar')->assertOk();
        $this->withSession($session)->get('/seminar/create')->assertForbidden();
    }

    public function test_seminar_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/seminar', [
            'id_peg' => '',
            'seminar' => '',
            'tempat' => '',
            'penyelenggara' => '',
            'tgl_mulai' => 'not-a-date',
            'tgl_selesai' => 'not-a-date',
            'no_piagam' => '',
        ]);

        $response->assertSessionHasErrors(['id_peg', 'seminar', 'tempat', 'penyelenggara', 'tgl_mulai', 'tgl_selesai', 'no_piagam']);
    }

    public function test_admin_can_open_cuti_module_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/cuti');

        $response->assertOk();
        $response->assertSee('Cuti\/Index');
    }

    public function test_kepala_can_read_cuti_but_cannot_manage_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/cuti')->assertOk();
        $this->withSession($session)->get('/cuti/create')->assertForbidden();
    }

    public function test_cuti_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/cuti', [
            'id_peg' => '',
            'jns_cuti' => '',
            'no_suratcuti' => '',
            'tgl_suratcuti' => 'not-a-date',
            'tgl_mulai' => 'not-a-date',
            'tgl_selesai' => 'not-a-date',
        ]);

        $response->assertSessionHasErrors(['id_peg', 'jns_cuti', 'no_suratcuti', 'tgl_suratcuti', 'tgl_mulai', 'tgl_selesai']);
    }

    public function test_admin_can_open_bahasa_module_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/bahasa');

        $response->assertOk();
        $response->assertSee('Bahasa\/Index');
    }

    public function test_kepala_can_read_bahasa_but_cannot_manage_it(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/bahasa')->assertOk();
        $this->withSession($session)->get('/bahasa/create')->assertForbidden();
    }

    public function test_bahasa_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/bahasa', [
            'id_peg' => '',
            'jns_bhs' => '',
            'bahasa' => '',
            'kemampuan' => 'Tidak valid',
        ]);

        $response->assertSessionHasErrors(['id_peg', 'jns_bhs', 'bahasa', 'kemampuan']);
    }

    public function test_admin_can_open_dp3_and_latihan_jabatan_modules_from_local_mysql(): void
    {
        $session = [
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ];

        $this->withSession($session)->get('/dp3')->assertOk()->assertSee('Dp3\/Index');
        $this->withSession($session)->get('/latihan-jabatan')->assertOk()->assertSee('LatihanJabatan\/Index');
    }

    public function test_kepala_can_read_dp3_and_latihan_jabatan_but_cannot_manage_them(): void
    {
        $session = [
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ];

        $this->withSession($session)->get('/dp3')->assertOk();
        $this->withSession($session)->get('/latihan-jabatan')->assertOk();
        $this->withSession($session)->get('/dp3/create')->assertForbidden();
        $this->withSession($session)->get('/latihan-jabatan/create')->assertForbidden();
    }

    public function test_blocked_history_modules_reject_incomplete_payloads_before_writing(): void
    {
        $session = [
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ];

        $this->withSession($session)->post('/dp3', [])->assertSessionHasErrors([
            'id_peg', 'periode_awal', 'periode_akhir', 'pejabat_penilai', 'atasan_pejabat_penilai', 'hasil_penilaian',
        ]);
        $this->withSession($session)->post('/latihan-jabatan', [])->assertSessionHasErrors([
            'id_peg', 'nama_pelatih', 'tahun_lat', 'jml_jam',
        ]);
    }

    public function test_employee_create_rejects_invalid_payload_before_writing(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/pegawai', [
            'id_peg' => '',
            'nama' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['id_peg', 'nama', 'nik', 'email']);
    }

    public function test_admin_can_open_employee_import_and_reject_invalid_xlsx_preview(): void
    {
        $page = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/pegawai/import');

        $page->assertOk();
        $page->assertSee('Employees\\/Import');

        $preview = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/pegawai/import/preview', [
            'file_xlsx' => UploadedFile::fake()->createWithContent('pegawai.xlsx', "id;nama\n1;Nama"),
        ]);

        $preview->assertSessionHasErrors('file_xlsx');
    }

    public function test_admin_can_commit_a_new_employee_import_without_missing_id_error(): void
    {
        $id = 'TST-'.random_int(10000, 99999);
        $rows = [[
            'values' => [
                $id,
                '1990010120150110',
                'Pegawai Import Test',
                'Jakarta',
                '20-11-1990',
                'Islam',
                'Laki-laki',
                'O',
                'Menikah',
                'Tetap',
                'Jl. Test',
                '081234567890',
                'import-test@example.test',
                '',
                '20-11-2015',
                '20-11-2046',
                '12345678901',
                '00012345678',
            ],
            'status' => 'new',
            'existing_id' => null,
        ]];

        DB::beginTransaction();

        try {
            $response = $this->withSession([
                'id_user' => 'admin-demo',
                'hak_akses' => 'admin',
            ])->post('/pegawai/import', [
                'rows' => json_encode($rows, JSON_THROW_ON_ERROR),
            ]);

            $response->assertRedirectToRoute('employees.index');
            $this->assertDatabaseHas('tb_pegawai', [
                'id_peg' => $id,
                'nama' => 'Pegawai Import Test',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_kepala_can_open_authorization_list_from_local_mysql(): void
    {
        $response = $this->withSession([
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ])->get('/otorisasi');

        $response->assertOk();
        $response->assertSee('Authorization\/Index');
    }

    public function test_user_cannot_open_authorization_list(): void
    {
        $response = $this->withSession([
            'id_user' => 'user-demo',
            'hak_akses' => 'user',
        ])->get('/otorisasi');

        $response->assertForbidden();
    }

    public function test_admin_can_open_authorization_list(): void
    {
        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->get('/otorisasi');

        $response->assertOk();
        $response->assertSee('Authorization\/Index');
    }

    public function test_kepala_can_open_an_existing_authorization_detail(): void
    {
        $id = DB::table('tb_edit_pending')->value('id_edit');
        $this->assertNotNull($id);

        $response = $this->withSession([
            'id_user' => 'kepala-demo',
            'hak_akses' => 'kepala',
            'kode_kantor' => '000',
        ])->get("/otorisasi/{$id}/detail");

        $response->assertOk();
        $response->assertSee('Authorization\/Detail');
    }

    public function test_logout_flushes_the_legacy_session(): void
    {
        $response = $this->withSession([
            'id_user' => 'user-demo',
            'hak_akses' => 'user',
        ])->post('/logout');

        $response->assertRedirectToRoute('login');
        $response->assertSessionMissing('id_user');
        $response->assertSessionMissing('hak_akses');
    }
}
