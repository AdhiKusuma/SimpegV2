<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataDiriDocumentTest extends TestCase
{
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
            'filesystems.default' => 'local',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Storage::fake('local');

        Schema::create('tb_pegawai', function (Blueprint $table): void {
            $table->string('id_peg')->primary();
            $table->string('nama')->nullable();
            $table->string('nik')->nullable();
        });
        Schema::create('tb_user', function (Blueprint $table): void {
            $table->string('id_user')->primary();
            $table->string('nama_user')->nullable();
            $table->string('id_pegawai')->nullable();
            $table->string('hak_akses')->nullable();
        });
        Schema::create('tb_config', function (Blueprint $table): void {
            $table->string('id_app')->primary();
            $table->string('nama_app')->nullable();
            $table->string('alias_app')->nullable();
            $table->text('desc_app')->nullable();
            $table->string('logo')->nullable();
        });
        Schema::create('tb_notifikasi', function (Blueprint $table): void {
            $table->increments('id_notif');
            $table->string('id_user', 64);
            $table->string('judul', 255);
            $table->text('pesan');
            $table->string('link_aksi', 500)->nullable();
            $table->string('status_baca', 20);
            $table->dateTime('waktu_notif');
        });
        Schema::create('data_diri_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('pegawai_id', 32);
            $table->string('jenis', 32);
            $table->string('nama_asli', 255);
            $table->string('path', 512);
            $table->unsignedBigInteger('ukuran');
            $table->char('hash', 64);
            $table->string('diunggah_oleh', 64)->nullable();
            $table->timestamps();
            $table->unique(['pegawai_id', 'jenis']);
        });

        DB::table('tb_pegawai')->insert([
            ['id_peg' => '102-157', 'nama' => 'Adhi Candra', 'nik' => '123'],
            ['id_peg' => '102-158', 'nama' => 'Pegawai Lain', 'nik' => '456'],
        ]);
        DB::table('tb_user')->insert([
            ['id_user' => 'admin-1', 'nama_user' => 'Admin', 'id_pegawai' => '102-157', 'hak_akses' => 'admin'],
            ['id_user' => 'user-1', 'nama_user' => 'Adhi Candra', 'id_pegawai' => '102-157', 'hak_akses' => 'user'],
            ['id_user' => 'user-2', 'nama_user' => 'Pegawai Lain', 'id_pegawai' => '102-158', 'hak_akses' => 'user'],
        ]);
    }

    public function test_user_can_upload_replace_preview_download_and_delete_own_document(): void
    {
        $session = ['id_user' => 'user-1', 'id_pegawai' => '102-157', 'hak_akses' => 'user'];

        $upload = $this->withSession($session)->post('/files/data-diri/102-157', [
            'jenis' => 'ktp',
            'file_pdf' => $this->pdf('ktp-lama.pdf', 'lama'),
        ]);
        $upload->assertRedirect()->assertSessionHasNoErrors();

        $document = DB::table('data_diri_documents')->where('pegawai_id', '102-157')->where('jenis', 'ktp')->first();
        $this->assertNotNull($document);
        $this->assertStringStartsWith('data-diri/102-157/ktp/', $document->path);
        $this->assertStringEndsWith('.pdf', $document->path);
        $this->assertNotSame('lama', $document->path);
        $oldPath = $document->path;
        Storage::disk('local')->assertExists($oldPath);

        $preview = $this->withSession($session)->get("/files/data-diri/102-157/document/{$document->id}/preview");
        $preview->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('%PDF-', $preview->streamedContent());

        $replace = $this->withSession($session)->post('/files/data-diri/102-157', [
            'jenis' => 'ktp',
            'file_pdf' => $this->pdf('ktp-baru.pdf', 'baru'),
        ]);
        $replace->assertRedirect()->assertSessionHasNoErrors();

        $replacement = DB::table('data_diri_documents')->where('pegawai_id', '102-157')->where('jenis', 'ktp')->first();
        $this->assertSame(1, DB::table('data_diri_documents')->where('pegawai_id', '102-157')->where('jenis', 'ktp')->count());
        $this->assertNotSame($oldPath, $replacement->path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($replacement->path);

        $download = $this->withSession($session)->get("/files/data-diri/102-157/document/{$replacement->id}/download");
        $download->assertOk()->assertHeader('Content-Disposition');

        $delete = $this->withSession($session)->delete("/files/data-diri/102-157/document/{$replacement->id}");
        $delete->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success', 'KTP berhasil dihapus.');
        $this->assertDatabaseMissing('data_diri_documents', ['id' => $replacement->id]);
        Storage::disk('local')->assertMissing($replacement->path);
    }

    public function test_user_cannot_read_another_employees_document(): void
    {
        DB::table('data_diri_documents')->insert([
            'pegawai_id' => '102-158',
            'jenis' => 'kk',
            'nama_asli' => 'kk.pdf',
            'path' => 'data-diri/102-158/kk/test.pdf',
            'ukuran' => 12,
            'hash' => hash('sha256', 'other'),
            'diunggah_oleh' => 'admin-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put('data-diri/102-158/kk/test.pdf', '%PDF-1.4 other');
        $documentId = DB::table('data_diri_documents')->where('pegawai_id', '102-158')->value('id');

        $this->withSession(['id_user' => 'user-1', 'id_pegawai' => '102-157', 'hak_akses' => 'user'])
            ->get("/files/data-diri/102-158/document/{$documentId}/preview")
            ->assertForbidden();
    }

    public function test_admin_can_select_and_manage_another_employee(): void
    {
        $session = ['id_user' => 'admin-1', 'id_pegawai' => '102-157', 'hak_akses' => 'admin'];

        $index = $this->withSession($session)->get('/files/data-diri?pegawai_id=102-158');
        $index->assertOk()->assertInertia(fn ($page) => $page
            ->component('Files/DataDiri/Index')
            ->where('selectedEmployee.id_peg', '102-158')
            ->has('employees', 2));

        $this->withSession($session)->post('/files/data-diri/102-158', [
            'jenis' => 'ijazah',
            'file_pdf' => $this->pdf('ijazah.pdf', 'admin-upload'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('data_diri_documents', ['pegawai_id' => '102-158', 'jenis' => 'ijazah', 'diunggah_oleh' => 'admin-1']);
        $this->assertDatabaseHas('tb_notifikasi', [
            'id_user' => 'user-2',
            'judul' => 'Dokumen data diri diperbarui',
            'link_aksi' => '/profile/data-diri',
            'status_baca' => 'unread',
        ]);
    }

    public function test_non_user_profile_shortcut_opens_own_documents_without_employee_selector(): void
    {
        $this->withSession(['id_user' => 'admin-1', 'id_pegawai' => '102-157', 'hak_akses' => 'admin'])
            ->get('/profile/data-diri')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Files/DataDiri/Index')
                ->where('selectedEmployee.id_peg', '102-157')
                ->where('isAdministrative', false)
                ->has('employees', 0));
    }

    public function test_user_can_clear_only_read_notifications_for_their_own_account(): void
    {
        DB::table('tb_notifikasi')->insert([
            [
                'id_user' => 'user-1',
                'judul' => 'Sudah dibaca',
                'pesan' => 'Pesan lama',
                'status_baca' => 'read',
                'waktu_notif' => now()->subDays(10),
            ],
            [
                'id_user' => 'user-1',
                'judul' => 'Belum dibaca',
                'pesan' => 'Pesan baru',
                'status_baca' => 'unread',
                'waktu_notif' => now(),
            ],
            [
                'id_user' => 'user-2',
                'judul' => 'Milik pegawai lain',
                'pesan' => 'Tetap ada',
                'status_baca' => 'read',
                'waktu_notif' => now()->subDays(10),
            ],
        ]);

        $this->withSession(['id_user' => 'user-1', 'id_pegawai' => '102-157', 'hak_akses' => 'user'])
            ->delete('/notifikasi/read')
            ->assertRedirectToRoute('notifications.index')
            ->assertSessionHas('success', '1 notifikasi yang sudah dibaca berhasil dihapus.');

        $this->assertDatabaseMissing('tb_notifikasi', ['id_user' => 'user-1', 'judul' => 'Sudah dibaca']);
        $this->assertDatabaseHas('tb_notifikasi', ['id_user' => 'user-1', 'judul' => 'Belum dibaca']);
        $this->assertDatabaseHas('tb_notifikasi', ['id_user' => 'user-2', 'judul' => 'Milik pegawai lain']);
    }

    public function test_clicking_a_notification_marks_only_that_notification_as_read(): void
    {
        DB::table('tb_notifikasi')->insert([
            [
                'id_user' => 'user-1',
                'judul' => 'Slip gaji tersedia',
                'pesan' => 'Slip periode 11-2026 tersedia.',
                'link_aksi' => '/files',
                'status_baca' => 'unread',
                'waktu_notif' => now(),
            ],
            [
                'id_user' => 'user-1',
                'judul' => 'Pengajuan menunggu persetujuan',
                'pesan' => 'Ada pengajuan baru.',
                'link_aksi' => '/pengajuan',
                'status_baca' => 'unread',
                'waktu_notif' => now(),
            ],
        ]);

        $notificationId = DB::table('tb_notifikasi')
            ->where('judul', 'Slip gaji tersedia')
            ->value('id_notif');

        $this->withSession(['id_user' => 'user-1', 'id_pegawai' => '102-157', 'hak_akses' => 'user'])
            ->post("/notifikasi/{$notificationId}/open")
            ->assertRedirect('/files');

        $this->assertDatabaseHas('tb_notifikasi', [
            'id_notif' => $notificationId,
            'status_baca' => 'read',
        ]);
        $this->assertDatabaseHas('tb_notifikasi', [
            'id_user' => 'user-1',
            'judul' => 'Pengajuan menunggu persetujuan',
            'status_baca' => 'unread',
        ]);
    }

    public function test_opening_the_notification_panel_marks_all_notifications_as_read(): void
    {
        DB::table('tb_notifikasi')->insert([
            [
                'id_user' => 'user-1',
                'judul' => 'Slip gaji tersedia',
                'pesan' => 'Slip periode 11-2026 tersedia.',
                'status_baca' => 'unread',
                'waktu_notif' => now(),
            ],
            [
                'id_user' => 'user-1',
                'judul' => 'Dokumen data diri diperbarui',
                'pesan' => 'Dokumen Anda diperbarui admin.',
                'status_baca' => 'unread',
                'waktu_notif' => now(),
            ],
            [
                'id_user' => 'user-2',
                'judul' => 'Milik pegawai lain',
                'pesan' => 'Tetap belum dibaca.',
                'status_baca' => 'unread',
                'waktu_notif' => now(),
            ],
        ]);

        $this->withSession(['id_user' => 'user-1', 'id_pegawai' => '102-157', 'hak_akses' => 'user'])
            ->post('/notifikasi/read')
            ->assertRedirect();

        $this->assertSame(0, DB::table('tb_notifikasi')->where('id_user', 'user-1')->where('status_baca', 'unread')->count());
        $this->assertSame(1, DB::table('tb_notifikasi')->where('id_user', 'user-2')->where('status_baca', 'unread')->count());
    }

    public function test_notification_prune_removes_only_old_read_rows_in_batches(): void
    {
        DB::table('tb_notifikasi')->insert([
            [
                'id_user' => 'user-1',
                'judul' => 'Sudah lama dibaca',
                'pesan' => 'Dibersihkan',
                'status_baca' => 'read',
                'waktu_notif' => now()->subDays(91),
            ],
            [
                'id_user' => 'user-1',
                'judul' => 'Masih baru',
                'pesan' => 'Tetap ada',
                'status_baca' => 'read',
                'waktu_notif' => now()->subDays(30),
            ],
            [
                'id_user' => 'user-1',
                'judul' => 'Belum dibaca lama',
                'pesan' => 'Tetap ada',
                'status_baca' => 'unread',
                'waktu_notif' => now()->subDays(91),
            ],
        ]);

        $this->artisan('notifications:prune', ['--execute' => true, '--batch' => 1])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('tb_notifikasi', ['judul' => 'Sudah lama dibaca']);
        $this->assertDatabaseHas('tb_notifikasi', ['judul' => 'Masih baru']);
        $this->assertDatabaseHas('tb_notifikasi', ['judul' => 'Belum dibaca lama']);
    }

    private function pdf(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n{$content}\n%%EOF");
    }
}
