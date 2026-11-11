<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengangkatanTest extends TestCase
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
            'filesystems.default' => 'public',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Storage::fake('public');

        Schema::create('tb_pegawai', function (Blueprint $table): void {
            $table->string('id_peg')->primary();
            $table->string('nama')->nullable();
        });
        Schema::create('tb_user', function (Blueprint $table): void {
            $table->string('id_user')->primary();
            $table->string('id_pegawai')->nullable();
            $table->string('hak_akses')->nullable();
            $table->string('status_aktif')->nullable();
        });
        Schema::create('tb_angkat', function (Blueprint $table): void {
            $table->increments('id_angkat');
            $table->string('id_peg', 12);
            $table->string('jns_mutasi', 32);
            $table->string('id_peg_baru', 12);
            $table->date('tgl_mutasi')->nullable();
            $table->string('no_mutasi', 50)->nullable();
            $table->date('tmt')->nullable();
            $table->string('sk_mutasi', 255)->nullable();
            $table->dateTime('date_reg')->nullable();
        });
        Schema::create('tb_recycle_bin', function (Blueprint $table): void {
            $table->increments('id_trash');
            $table->string('tabel_by', 50);
            $table->longText('data_json');
            $table->text('alasan')->nullable();
            $table->dateTime('created')->nullable();
            $table->string('created_by', 50)->nullable();
        });

        DB::table('tb_user')->insert([
            ['id_user' => 'admin-test', 'id_pegawai' => null, 'hak_akses' => 'admin', 'status_aktif' => 'Y'],
            ['id_user' => 'user-test', 'id_pegawai' => null, 'hak_akses' => 'user', 'status_aktif' => 'Y'],
        ]);
    }

    public function test_admin_can_move_appointment_to_recycle_bin_and_remove_its_document(): void
    {
        DB::table('tb_pegawai')->insert(['id_peg' => '102-157', 'nama' => 'Adhi Candra']);
        DB::table('tb_angkat')->insert([
            'id_peg' => '102-157',
            'jns_mutasi' => 'Kontrak',
            'id_peg_baru' => '102-157',
            'tgl_mutasi' => '2026-01-01',
            'no_mutasi' => 'SK-001',
            'tmt' => '2026-01-01',
            'sk_mutasi' => 'SK_102-157.pdf',
            'date_reg' => now(),
        ]);
        Storage::disk('public')->put('sk_angkat/SK_102-157.pdf', '%PDF-1.4');
        $id = DB::table('tb_angkat')->value('id_angkat');

        $this->withSession(['id_user' => 'admin-test', 'hak_akses' => 'admin'])
            ->delete('/pengangkatan/'.$id, ['reason' => 'Data duplikat'])
            ->assertRedirectToRoute('pengangkatan.index')
            ->assertSessionHas('success', 'Data pengangkatan dipindahkan ke Recycle Bin.');

        $this->assertDatabaseMissing('tb_angkat', ['id_angkat' => $id]);
        $this->assertDatabaseHas('tb_recycle_bin', [
            'tabel_by' => 'tb_angkat',
            'alasan' => 'Data duplikat',
            'created_by' => 'Admin',
        ]);
        Storage::disk('public')->assertMissing('sk_angkat/SK_102-157.pdf');
    }

    public function test_non_admin_cannot_delete_appointment(): void
    {
        $this->withSession(['id_user' => 'user-test', 'hak_akses' => 'user'])
            ->delete('/pengangkatan/1', ['reason' => 'Tidak boleh'])
            ->assertForbidden();
    }
}
