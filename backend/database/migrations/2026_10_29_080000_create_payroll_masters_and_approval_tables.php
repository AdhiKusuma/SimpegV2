<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MASTER_AMOUNT_FIELDS = [
        'gaji_pokok', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_pajak',
        'tunj_kinerja', 'tunj_fungsional', 'tunj_masa_kerja', 'honor_kontrak', 'thl', 'uang_makan_kontrak',
        'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'keris', 'total',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('payroll_salary_masters')) {
            Schema::create('payroll_salary_masters', function (Blueprint $table): void {
                $table->id();
                $table->string('employee_id', 12)->charset('latin1')->collation('latin1_swedish_ci')->unique('payroll_salary_master_employee_unique');
                $table->string('office_code', 20)->charset('latin1')->collation('latin1_swedish_ci');
                $table->string('bank_office_code', 20)->nullable();
                $table->string('no_rekening', 64)->nullable();
                $table->string('nama_nasabah', 150)->nullable();
                $table->date('tgl_gajian')->nullable();
                foreach (self::MASTER_AMOUNT_FIELDS as $field) {
                    $table->decimal($field, 18, 2)->default(0);
                }
                $table->unsignedBigInteger('source_import_id')->nullable();
                $table->string('created_by', 64)->nullable();
                $table->string('updated_by', 64)->nullable();
                $table->timestamps();
                $table->index('office_code', 'payroll_salary_master_office_index');
            });
        }

        if (! Schema::hasTable('payroll_change_requests')) {
            Schema::create('payroll_change_requests', function (Blueprint $table): void {
                $table->id();
                $table->string('change_type', 20);
                $table->unsignedSmallInteger('tahun')->nullable();
                $table->unsignedTinyInteger('bulan')->nullable();
                $table->string('source_filename', 255)->nullable();
                $table->char('source_sha256', 64)->nullable();
                $table->string('requested_by', 64);
                $table->json('office_codes');
                $table->json('summary_data')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('decision_reason')->nullable();
                $table->string('decided_by', 64)->nullable();
                $table->timestamp('submitted_at');
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at'], 'payroll_change_status_created_index');
                $table->index(['requested_by', 'status'], 'payroll_change_requester_status_index');
            });
        }

        if (! Schema::hasTable('payroll_change_request_entries')) {
            Schema::create('payroll_change_request_entries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_change_request_id')->constrained('payroll_change_requests')->cascadeOnDelete();
                $table->string('employee_id', 12)->charset('latin1')->collation('latin1_swedish_ci');
                $table->string('office_code', 20)->charset('latin1')->collation('latin1_swedish_ci');
                $table->unsignedInteger('source_row_number')->nullable();
                $table->json('old_data')->nullable();
                $table->json('new_data');
                $table->timestamps();
                $table->unique(['payroll_change_request_id', 'employee_id'], 'payroll_change_request_employee_unique');
                $table->index('employee_id', 'payroll_change_employee_index');
            });
        }

        $this->seedMasterFromCurrentImports();
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_change_request_entries');
        Schema::dropIfExists('payroll_change_requests');
        Schema::dropIfExists('payroll_salary_masters');
    }

    private function seedMasterFromCurrentImports(): void
    {
        if (! Schema::hasTable('payroll_entries') || ! Schema::hasTable('payroll_imports')) {
            return;
        }

        $columns = [
            'e.employee_id', 'e.office_code', 'e.bank_office_code', 'e.no_rekening', 'e.nama_nasabah', 'e.tgl_gajian',
            ...array_map(fn (string $field): string => 'e.'.$field, self::MASTER_AMOUNT_FIELDS),
            'e.payroll_import_id', 'i.imported_by', 'i.id as import_order',
        ];

        DB::table('payroll_entries as e')
            ->join('payroll_imports as i', 'i.id', '=', 'e.payroll_import_id')
            ->where('i.is_current', true)
            ->select($columns)
            ->orderBy('i.id')
            ->orderBy('e.id')
            ->chunk(500, function ($rows): void {
                $latestByEmployee = [];
                foreach ($rows as $row) {
                    $record = [
                        'employee_id' => (string) $row->employee_id,
                        'office_code' => (string) $row->office_code,
                        'bank_office_code' => $row->bank_office_code,
                        'no_rekening' => $row->no_rekening,
                        'nama_nasabah' => $row->nama_nasabah,
                        'tgl_gajian' => $row->tgl_gajian,
                        'source_import_id' => $row->payroll_import_id,
                        'created_by' => $row->imported_by,
                        'updated_by' => $row->imported_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    foreach (self::MASTER_AMOUNT_FIELDS as $field) {
                        $record[$field] = $row->{$field};
                    }
                    $latestByEmployee[$record['employee_id']] = $record;
                }

                if ($latestByEmployee !== []) {
                    DB::table('payroll_salary_masters')->upsert(
                        array_values($latestByEmployee),
                        ['employee_id'],
                        [
                            'office_code', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian',
                            ...self::MASTER_AMOUNT_FIELDS, 'source_import_id', 'updated_by', 'updated_at',
                        ],
                    );
                }
            });
    }
};
