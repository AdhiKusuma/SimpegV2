<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $createdUnits = false;
        if (! Schema::hasTable('payroll_unit_masters')) {
            Schema::create('payroll_unit_masters', function (Blueprint $table): void {
                $table->id();
                $table->string('lookup_key', 64)->unique('payroll_unit_master_lookup_unique');
                $table->string('name', 191);
                $table->string('office_code', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['office_code', 'is_active'], 'payroll_unit_master_office_active_index');
            });
            $createdUnits = true;
        }

        $createdAssignments = false;
        if (! Schema::hasTable('employee_payroll_assignments')) {
            Schema::create('employee_payroll_assignments', function (Blueprint $table): void {
                $table->id();
                $table->string('employee_id', 12)->charset('latin1')->collation('latin1_swedish_ci');
                $table->foreignId('payroll_grade_master_id')->nullable()->constrained('payroll_grade_masters')->nullOnDelete();
                $table->foreignId('payroll_unit_master_id')->nullable()->constrained('payroll_unit_masters')->nullOnDelete();
                $table->unsignedSmallInteger('masa_kerja')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->string('source_filename', 255)->nullable();
                $table->unsignedInteger('source_row_number')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'effective_to'], 'payroll_assignment_current_index');
                $table->index(['payroll_grade_master_id', 'effective_from'], 'payroll_assignment_grade_history_index');
                $table->index(['payroll_unit_master_id', 'effective_from'], 'payroll_assignment_unit_history_index');
            });
            $createdAssignments = true;
        }

        if (! $createdAssignments || ! Schema::hasTable('employee_payroll_grades')) {
            return;
        }

        $legacyColumns = [
            'legacy.employee_id',
            'legacy.payroll_grade_master_id',
            'legacy.effective_from',
            'legacy.effective_to',
            'legacy.source_filename',
            'legacy.source_row_number',
            'legacy.created_at',
            'legacy.updated_at',
        ];
        if (Schema::hasColumn('employee_payroll_grades', 'masa_kerja')) {
            $legacyColumns[] = 'legacy.masa_kerja';
        }
        if (Schema::hasColumn('employee_payroll_grades', 'unit_kerja')) {
            $legacyColumns[] = 'legacy.unit_kerja';
        }

        $legacyRows = DB::table('employee_payroll_grades as legacy')->get($legacyColumns);
        if ($legacyRows->isEmpty()) {
            return;
        }

        $unitRows = [];
        foreach ($legacyRows as $row) {
            $unitName = trim((string) ($row->unit_kerja ?? ''));
            if ($unitName === '') {
                continue;
            }

            $lookupKey = $this->unitLookupKey(null, $unitName);
            $unitRows[$lookupKey] = [
                'lookup_key' => $lookupKey,
                'name' => $unitName,
                'office_code' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($unitRows !== []) {
            DB::table('payroll_unit_masters')->upsert(
                array_values($unitRows),
                ['lookup_key'],
                ['name', 'office_code', 'is_active', 'updated_at'],
            );
        }

        $unitIds = DB::table('payroll_unit_masters')->pluck('id', 'lookup_key');
        foreach ($legacyRows as $row) {
            $unitName = trim((string) ($row->unit_kerja ?? ''));
            $lookupKey = $unitName === '' ? null : $this->unitLookupKey(null, $unitName);

            DB::table('employee_payroll_assignments')->insert([
                'employee_id' => $row->employee_id,
                'payroll_grade_master_id' => $row->payroll_grade_master_id,
                'payroll_unit_master_id' => $lookupKey === null ? null : ($unitIds[$lookupKey] ?? null),
                'masa_kerja' => $row->masa_kerja ?? null,
                'effective_from' => $row->effective_from,
                'effective_to' => $row->effective_to,
                'source_filename' => $row->source_filename,
                'source_row_number' => $row->source_row_number,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_assignments');
        Schema::dropIfExists('payroll_unit_masters');
    }

    private function unitLookupKey(?string $officeCode, string $name): string
    {
        $officeCode = strtolower(trim((string) $officeCode));
        $name = strtolower((string) preg_replace('/\s+/u', ' ', trim($name)));

        return hash('sha256', $officeCode.'|'.$name);
    }
};
