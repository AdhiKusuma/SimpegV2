<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kuota_jabatan')) {
            return;
        }

        if (! Schema::hasColumn('kuota_jabatan', 'kode_jabatan')) {
            Schema::table('kuota_jabatan', function (Blueprint $table): void {
                $table->string('kode_jabatan', 50)->nullable()->after('jabatan_id');
            });
        }

        if (! Schema::hasIndex('kuota_jabatan', 'kuota_jabatan_cabang_kode_custom_unique')) {
            Schema::table('kuota_jabatan', function (Blueprint $table): void {
                $table->unique(['kode_cabang', 'kode_jabatan'], 'kuota_jabatan_cabang_kode_custom_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('kuota_jabatan')) {
            return;
        }

        Schema::table('kuota_jabatan', function (Blueprint $table): void {
            if (Schema::hasIndex('kuota_jabatan', 'kuota_jabatan_cabang_kode_custom_unique')) {
                $table->dropUnique('kuota_jabatan_cabang_kode_custom_unique');
            }

            if (Schema::hasColumn('kuota_jabatan', 'kode_jabatan')) {
                $table->dropColumn('kode_jabatan');
            }
        });
    }
};
