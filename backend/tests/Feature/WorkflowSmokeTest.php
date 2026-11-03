<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkflowSmokeTest extends TestCase
{
    public function test_employee_xlsx_template_preview_runs_without_writing_data(): void
    {
        $template = file_get_contents(public_path('templates/Template_Import_Pegawai_V2.xlsx'));
        $this->assertIsString($template);

        $response = $this->withSession([
            'id_user' => 'admin-demo',
            'hak_akses' => 'admin',
        ])->post('/pegawai/import/preview', [
            'file_xlsx' => UploadedFile::fake()->createWithContent('Template_Import_Pegawai_V2.xlsx', $template),
        ]);

        $response->assertOk()->assertJsonStructure(['columns', 'rows', 'preview', 'total']);
        $this->assertCount(18, $response->json('columns'));
        $this->assertSame('ID Pegawai (Wajib)', $response->json('columns.0'));
    }

    public function test_family_submission_history_and_approval_run_in_one_transaction(): void
    {
        $employeeId = DB::table('tb_pegawai as p')
            ->join('tb_jabatan as j', 'j.id_peg', '=', 'p.id_peg')
            ->where('p.status_aktif', 1)
            ->where('j.status_jab', 'Aktif')
            ->where('j.unit_kerja', '001000')
            ->value('p.id_peg');

        if ($employeeId === null) {
            $this->markTestSkipped('Tidak ada pegawai aktif di kantor 001000 untuk smoke test approval.');
        }

        $userSession = [
            'id_user' => 'smoke-user',
            'hak_akses' => 'user',
            'id_pegawai' => $employeeId,
        ];
        $suffix = (string) random_int(100000, 999999);
        $requests = [
            'children' => [
                'id_peg' => $employeeId,
                'nik' => '20261103'.$suffix,
                'nama' => 'Smoke Anak',
                'tmp_lhr' => 'Semarang',
                'tgl_lhr' => '2020-01-01',
                'pendidikan' => 'SD',
                'pekerjaan' => 'Pelajar',
                'status_hub' => 'Anak Kandung',
            ],
            'parents' => [
                'id_peg' => $employeeId,
                'nik' => '19700101'.$suffix,
                'nama' => 'Smoke Orang Tua',
                'tmp_lhr' => 'Semarang',
                'tgl_lhr' => '1970-01-01',
                'pendidikan' => 'SD',
                'pekerjaan' => 'Pensiunan',
                'status_hub' => 'Ayah Kandung',
            ],
            'spouses' => [
                'id_peg' => $employeeId,
                'nik' => '19900101'.$suffix,
                'nama' => 'Smoke Pasangan',
                'tmp_lhr' => 'Semarang',
                'tgl_lhr' => '1990-01-01',
                'pendidikan' => 'SMA',
                'pekerjaan' => 'Karyawan',
                'status_hub' => 'Istri',
                'hp' => '081234567890',
            ],
        ];

        DB::beginTransaction();

        try {
            foreach ($requests as $type => $payload) {
                $response = $this->withSession($userSession)->post("/keluarga/{$type}", $payload);
                $response->assertRedirect()->assertSessionHasNoErrors();
            }

            $pending = DB::table('tb_edit_pending')
                ->where('id_user', 'smoke-user')
                ->where('id_peg', $employeeId)
                ->where('jenis_data', 'keluarga_anak_create')
                ->where('status_otorisasi', 'Menunggu')
                ->latest('id_edit')
                ->first();

            $this->assertNotNull($pending);
            $this->assertSame('001', $pending->kode_kantor);
            $this->assertGreaterThan(
                0,
                DB::table('tb_notifikasi')
                    ->where('judul', 'Pengajuan menunggu persetujuan')
                    ->where('link_aksi', route('authorizations.show', ['id' => $pending->id_edit]))
                    ->where('status_baca', 'unread')
                    ->count(),
            );
            $this->assertDatabaseHas('tb_notifikasi', [
                'id_user' => '102-007',
                'judul' => 'Pengajuan menunggu persetujuan',
                'link_aksi' => route('authorizations.show', ['id' => $pending->id_edit]),
            ]);
            $this->assertDatabaseMissing('tb_notifikasi', [
                'id_user' => '103-018',
                'judul' => 'Pengajuan menunggu persetujuan',
                'link_aksi' => route('authorizations.show', ['id' => $pending->id_edit]),
            ]);

            $history = $this->withSession($userSession)->get('/pengajuan');
            $history->assertOk()->assertSee('Authorization\/History');

            $approval = $this->withSession([
                'id_user' => 'smoke-kepala',
                'hak_akses' => 'kepala',
                'kode_kantor' => '001000',
            ])->post("/otorisasi/{$pending->id_edit}/process", [
                'action' => 'approve',
                'comment' => 'Smoke test approval',
            ]);

            $approval->assertRedirect();
            $this->assertDatabaseHas('tb_edit_pending', [
                'id_edit' => $pending->id_edit,
                'status_otorisasi' => 'Disetujui',
            ]);
            $this->assertDatabaseHas('tb_anak', [
                'id_peg' => $employeeId,
                'nik' => $requests['children']['nik'],
                'nama' => 'Smoke Anak',
            ]);
            $this->assertDatabaseHas('tb_notifikasi', [
                'id_user' => 'smoke-user',
                'judul' => 'Status Permintaan Edit',
                'status_baca' => 'unread',
            ]);

            $historyAfterApproval = $this->withSession($userSession)->get('/pengajuan');
            $historyAfterApproval->assertOk()->assertInertia(fn ($page) => $page
                ->where('notificationSummary.unread_count', 1));
        } finally {
            DB::rollBack();
        }
    }

    public function test_kepala_can_reject_a_pending_authorization(): void
    {
        $pending = DB::table('tb_edit_pending')
            ->where('status_otorisasi', 'pending')
            ->latest('id_edit')
            ->first();

        if ($pending === null) {
            $this->markTestSkipped('Tidak ada pengajuan menunggu untuk smoke test penolakan.');
        }

        DB::beginTransaction();

        try {
            $response = $this->withSession([
                'id_user' => 'smoke-kepala',
                'hak_akses' => 'kepala',
                'kode_kantor' => '001000',
            ])->post("/otorisasi/{$pending->id_edit}/process", [
                'action' => 'reject',
                'comment' => 'Smoke test rejection',
            ]);

            $response->assertRedirectToRoute('authorizations.show', ['id' => $pending->id_edit]);
            $this->assertDatabaseHas('tb_edit_pending', [
                'id_edit' => $pending->id_edit,
                'status_otorisasi' => 'Ditolak',
                'komentar_otorisasi' => 'Smoke test rejection',
            ]);
        } finally {
            DB::rollBack();
        }
    }
}
