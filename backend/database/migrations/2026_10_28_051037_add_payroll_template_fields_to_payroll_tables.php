<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_entries')) {
            Schema::table('payroll_entries', function (Blueprint $table): void {
                if (! Schema::hasColumn('payroll_entries', 'bank_office_code')) {
                    $table->string('bank_office_code', 20)->nullable()->after('employee_id');
                }
                if (! Schema::hasColumn('payroll_entries', 'no_rekening')) {
                    $table->string('no_rekening', 64)->nullable()->after('bank_office_code');
                }
                if (! Schema::hasColumn('payroll_entries', 'nama_nasabah')) {
                    $table->string('nama_nasabah', 150)->nullable()->after('no_rekening');
                }
                if (! Schema::hasColumn('payroll_entries', 'tgl_gajian')) {
                    $table->date('tgl_gajian')->nullable()->after('nama_nasabah');
                }
                if (! Schema::hasColumn('payroll_entries', 'total')) {
                    $table->decimal('total', 18, 2)->default(0)->after('tgl_gajian');
                }
            });
        }

        if (Schema::hasTable('payroll_office_summaries')) {
            Schema::table('payroll_office_summaries', function (Blueprint $table): void {
                if (! Schema::hasColumn('payroll_office_summaries', 'thl')) {
                    $table->decimal('thl', 18, 2)->default(0)->after('honor_kontrak');
                }
                if (! Schema::hasColumn('payroll_office_summaries', 'total')) {
                    $table->decimal('total', 18, 2)->default(0)->after('employee_count');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payroll_entries')) {
            Schema::table('payroll_entries', function (Blueprint $table): void {
                foreach (['bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian', 'total'] as $column) {
                    if (Schema::hasColumn('payroll_entries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('payroll_office_summaries')) {
            Schema::table('payroll_office_summaries', function (Blueprint $table): void {
                $columns = array_values(array_filter(
                    ['thl', 'total'],
                    fn (string $column): bool => Schema::hasColumn('payroll_office_summaries', $column),
                ));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
