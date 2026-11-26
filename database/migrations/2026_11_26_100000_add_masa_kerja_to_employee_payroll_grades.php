<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_payroll_grades') && ! Schema::hasColumn('employee_payroll_grades', 'masa_kerja')) {
            Schema::table('employee_payroll_grades', function (Blueprint $table): void {
                $table->unsignedSmallInteger('masa_kerja')->nullable()->after('payroll_grade_master_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_payroll_grades') && Schema::hasColumn('employee_payroll_grades', 'masa_kerja')) {
            Schema::table('employee_payroll_grades', function (Blueprint $table): void {
                $table->dropColumn('masa_kerja');
            });
        }
    }
};
