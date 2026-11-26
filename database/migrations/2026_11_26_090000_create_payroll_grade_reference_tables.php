<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_grade_masters')) {
            Schema::create('payroll_grade_masters', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 6)->unique('payroll_grade_master_code_unique');
                $table->string('label', 100);
                $table->boolean('is_active')->default(true);
                $table->string('source', 100)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_payroll_grades')) {
            Schema::create('employee_payroll_grades', function (Blueprint $table): void {
                $table->id();
                // Legacy employee IDs are stored in latin1 on tb_pegawai.
                $table->string('employee_id', 12)->charset('latin1')->collation('latin1_swedish_ci');
                $table->foreignId('payroll_grade_master_id')
                    ->constrained('payroll_grade_masters')
                    ->restrictOnDelete();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('source_filename', 255)->nullable();
                $table->unsignedInteger('source_row_number')->nullable();
                $table->timestamps();

                // No FK to the legacy table: real deployments may install the
                // legacy schema separately, while employee_id remains indexed.
                $table->index(['employee_id', 'effective_to'], 'employee_payroll_grade_current_index');
                $table->index(['payroll_grade_master_id', 'effective_from'], 'employee_payroll_grade_master_history_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_grades');
        Schema::dropIfExists('payroll_grade_masters');
    }
};
