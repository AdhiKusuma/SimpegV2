<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tb_dp3')) {
            Schema::create('tb_dp3', function (Blueprint $table): void {
                $table->charset = 'latin1';
                $table->collation = 'latin1_swedish_ci';
                $table->string('id_dp3', 8);
                $table->string('id_peg', 8);
                $table->date('periode_awal');
                $table->date('periode_akhir');
                $table->string('pejabat_penilai', 64);
                $table->string('atasan_pejabat_penilai', 64);
                $table->integer('nilai_kesetiaan');
                $table->integer('nilai_prestasi');
                $table->integer('nilai_tgjwb');
                $table->integer('nilai_ketaatan');
                $table->integer('nilai_kejujuran');
                $table->integer('nilai_kerjasama');
                $table->integer('nilai_prakarsa');
                $table->integer('nilai_kepemimpinan');
                $table->string('hasil_penilaian', 12);
                $table->primary('id_dp3');
            });
        } else {
            DB::statement('ALTER TABLE `tb_dp3` CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci');
        }

        if (! Schema::hasTable('tb_lat_jabatan')) {
            Schema::create('tb_lat_jabatan', function (Blueprint $table): void {
                $table->charset = 'latin1';
                $table->collation = 'latin1_swedish_ci';
                $table->string('id_lat_jabatan', 8);
                $table->string('id_peg', 12)->nullable();
                $table->string('nama_pelatih', 64);
                $table->string('tahun_lat', 4);
                $table->string('jml_jam', 3);
                $table->primary('id_lat_jabatan');
                $table->foreign('id_peg')->references('id_peg')->on('tb_pegawai')->nullOnDelete()->cascadeOnUpdate();
            });
        } else {
            DB::statement('ALTER TABLE `tb_lat_jabatan` CONVERT TO CHARACTER SET latin1 COLLATE latin1_swedish_ci');
        }

        $hasLatihanForeignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'tb_lat_jabatan')
            ->where('COLUMN_NAME', 'id_peg')
            ->where('REFERENCED_TABLE_NAME', 'tb_pegawai')
            ->exists();

        if (! $hasLatihanForeignKey) {
            Schema::table('tb_lat_jabatan', function (Blueprint $table): void {
                $table->foreign('id_peg')->references('id_peg')->on('tb_pegawai')->nullOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Legacy tables are intentionally preserved during rollback.
    }
};
