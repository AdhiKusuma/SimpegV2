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

        if (! Schema::hasColumn('kuota_jabatan', 'group_jabatan')) {
            Schema::table('kuota_jabatan', function (Blueprint $table): void {
                $table->string('group_jabatan', 10)->nullable()->after('nama_jabatan');
            });
        }

        if (! Schema::hasColumn('kuota_jabatan', 'lingkup')) {
            Schema::table('kuota_jabatan', function (Blueprint $table): void {
                $table->string('lingkup', 20)->nullable()->after('group_jabatan');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('kuota_jabatan')) {
            return;
        }

        Schema::table('kuota_jabatan', function (Blueprint $table): void {
            if (Schema::hasColumn('kuota_jabatan', 'lingkup')) {
                $table->dropColumn('lingkup');
            }

            if (Schema::hasColumn('kuota_jabatan', 'group_jabatan')) {
                $table->dropColumn('group_jabatan');
            }
        });
    }
};
