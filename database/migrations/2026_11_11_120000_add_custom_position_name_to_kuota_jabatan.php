<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kuota_jabatan')) {
            return;
        }

        if (! Schema::hasColumn('kuota_jabatan', 'nama_jabatan')) {
            Schema::table('kuota_jabatan', function (Blueprint $table): void {
                $table->string('nama_jabatan', 100)->nullable()->after('jabatan_id');
            });
        }

        // A custom quota does not reference tb_master_jabatan, so its master ID is null.
        DB::statement('ALTER TABLE `kuota_jabatan` MODIFY `jabatan_id` INT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('kuota_jabatan')) {
            return;
        }

        if (Schema::hasColumn('kuota_jabatan', 'nama_jabatan')) {
            Schema::table('kuota_jabatan', function (Blueprint $table): void {
                $table->dropColumn('nama_jabatan');
            });
        }

        DB::table('kuota_jabatan')->whereNull('jabatan_id')->delete();
        DB::statement('ALTER TABLE `kuota_jabatan` MODIFY `jabatan_id` INT UNSIGNED NOT NULL');
    }
};
