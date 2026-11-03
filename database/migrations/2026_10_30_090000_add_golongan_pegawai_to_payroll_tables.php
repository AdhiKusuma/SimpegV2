<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['payroll_entries', 'payroll_office_summaries', 'payroll_salary_masters'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'golongan_pegawai')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->decimal('golongan_pegawai', 18, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['payroll_entries', 'payroll_office_summaries', 'payroll_salary_masters'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'golongan_pegawai')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('golongan_pegawai');
            });
        }
    }
};
