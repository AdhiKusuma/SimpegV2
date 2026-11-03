<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('payroll_imports')) {
            Schema::create('payroll_imports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedSmallInteger('tahun');
                $table->unsignedTinyInteger('bulan');
                $table->string('source_filename', 255);
                $table->char('source_sha256', 64);
                $table->unsignedInteger('source_row_count')->default(0);
                $table->unsignedInteger('source_summary_count')->default(0);
                $table->boolean('is_current')->default(true);
                $table->string('imported_by', 64)->nullable();
                $table->timestamps();

                $table->index(['tahun', 'bulan', 'is_current'], 'payroll_import_period_index');
            });
        }

        if (! Schema::hasTable('payroll_entries')) {
            Schema::create('payroll_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_import_id')->constrained('payroll_imports')->cascadeOnDelete();
                $table->string('office_code', 20)->charset('latin1')->collation('latin1_swedish_ci');
                $table->string('employee_id', 12)->charset('latin1')->collation('latin1_swedish_ci');
                $table->unsignedInteger('source_row_number');
                foreach (self::payrollAmountColumns() as $column) {
                    $table->decimal($column, 18, 2)->default(0);
                }
                $table->string('rekening_tabungan', 64)->nullable();
                $table->string('rekening_kredit', 64)->nullable();
                $table->string('no_hp_keris', 32)->nullable();
                $table->string('source_created', 32)->nullable();
                $table->string('source_updated', 32)->nullable();
                $table->string('source_log_user', 64)->nullable();
                $table->string('source_log_approval', 64)->nullable();
                $table->timestamps();

                $table->unique(['payroll_import_id', 'employee_id'], 'payroll_entry_batch_employee_unique');
                $table->index(['payroll_import_id', 'office_code'], 'payroll_entry_batch_office_index');
            });
        }

        if (! Schema::hasTable('payroll_office_summaries')) {
            Schema::create('payroll_office_summaries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_import_id')->constrained('payroll_imports')->cascadeOnDelete();
                $table->string('office_code', 20)->charset('latin1')->collation('latin1_swedish_ci');
                $table->string('source_label', 100)->nullable();
                $table->unsignedInteger('employee_count')->default(0);
                foreach (self::summaryAmountColumns() as $column) {
                    $table->decimal($column, 18, 2)->default(0);
                }
                $table->unsignedInteger('credit_employee_count')->default(0);
                $table->decimal('credit_pokok', 18, 2)->nullable();
                $table->decimal('credit_bunga', 18, 2)->nullable();
                $table->decimal('credit_total', 18, 2)->nullable();
                $table->timestamps();

                $table->unique(['payroll_import_id', 'office_code'], 'payroll_office_summary_batch_office_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_office_summaries');
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_imports');
    }

    private static function payrollAmountColumns(): array
    {
        return [
            'gaji_pokok', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_kinerja',
            'tunj_masa_kerja', 'tunj_fungsional', 'honor_kontrak', 'thl', 'tunj_pajak', 'uang_makan_kontrak',
            'jumlah_gaji', 'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit',
            'pengembalian_tunj_keluarga', 'keris', 'jumlah_potongan', 'gaji_bersih',
        ];
    }

    private static function summaryAmountColumns(): array
    {
        return [
            'gaji_pokok', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_kinerja',
            'tunj_masa_kerja', 'tunj_fungsional', 'honor_kontrak', 'tunj_pajak', 'uang_makan_kontrak',
            'jumlah_gaji', 'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit',
            'pengembalian_tunj_keluarga', 'keris', 'jumlah_potongan', 'gaji_bersih',
        ];
    }
};
