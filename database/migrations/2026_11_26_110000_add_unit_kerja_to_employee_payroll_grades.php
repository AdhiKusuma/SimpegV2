<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_payroll_grades') && ! Schema::hasColumn('employee_payroll_grades', 'unit_kerja')) {
            Schema::table('employee_payroll_grades', function (Blueprint $table): void {
                $table->string('unit_kerja', 191)->nullable()->after('masa_kerja');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_payroll_grades') && Schema::hasColumn('employee_payroll_grades', 'unit_kerja')) {
            Schema::table('employee_payroll_grades', function (Blueprint $table): void {
                $table->dropColumn('unit_kerja');
            });
        }
    }
};
