<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_imports')) {
            Schema::table('payroll_imports', function (Blueprint $table): void {
                $table->index(
                    ['tahun', 'bulan', 'source_sha256', 'is_current'],
                    'payroll_import_source_lookup_index',
                );
            });
        }

        if (Schema::hasTable('payroll_change_requests')) {
            Schema::table('payroll_change_requests', function (Blueprint $table): void {
                $table->index(
                    ['change_type', 'tahun', 'bulan', 'source_sha256', 'status'],
                    'payroll_change_source_lookup_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_change_requests')) {
            Schema::table('payroll_change_requests', function (Blueprint $table): void {
                $table->dropIndex('payroll_change_source_lookup_index');
            });
        }

        if (Schema::hasTable('payroll_imports')) {
            Schema::table('payroll_imports', function (Blueprint $table): void {
                $table->dropIndex('payroll_import_source_lookup_index');
            });
        }
    }
};
