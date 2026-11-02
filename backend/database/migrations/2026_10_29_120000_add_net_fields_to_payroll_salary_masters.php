<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_salary_masters')) {
            return;
        }

        Schema::table('payroll_salary_masters', function (Blueprint $table): void {
            foreach (['absensi', 'angsuran_kredit', 'jumlah_gaji', 'jumlah_potongan', 'gaji_bersih'] as $column) {
                if (! Schema::hasColumn('payroll_salary_masters', $column)) {
                    $table->decimal($column, 18, 2)->default(0);
                }
            }
            if (! Schema::hasColumn('payroll_salary_masters', 'rekening_tabungan')) {
                $table->string('rekening_tabungan', 64)->nullable();
            }
            if (! Schema::hasColumn('payroll_salary_masters', 'no_hp_keris')) {
                $table->string('no_hp_keris', 32)->nullable();
            }
        });

        if (! Schema::hasTable('payroll_entries')) {
            return;
        }

        $fields = [
            'absensi' => 'absensi',
            'angsuran_kredit' => 'angsuran_kredit',
            'rekening_tabungan' => 'rekening_tabungan',
            'no_hp_keris' => 'no_hp_keris',
        ];
        $updates = [];
        foreach ($fields as $masterColumn => $entryColumn) {
            if (Schema::hasColumn('payroll_entries', $entryColumn)) {
                $updates['m.'.$masterColumn] = DB::raw('e.'.$entryColumn);
            }
        }
        if ($updates !== []) {
            DB::table('payroll_salary_masters as m')
                ->join('payroll_entries as e', function ($join): void {
                    $join->on('e.employee_id', '=', 'm.employee_id')
                        ->on('e.payroll_import_id', '=', 'm.source_import_id');
                })
                ->update($updates);
        }

        $earnings = [
            'gaji_pokok', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_pajak',
            'tunj_kinerja', 'tunj_fungsional', 'tunj_masa_kerja', 'honor_kontrak', 'thl', 'uang_makan_kontrak',
        ];
        $deductions = ['bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit', 'keris'];
        $grossSql = '('.implode(' + ', $earnings).')';
        $deductionsSql = '('.implode(' + ', $deductions).')';
        $netSql = 'GREATEST(0, '.$grossSql.' - '.$deductionsSql.')';

        DB::table('payroll_salary_masters')->update([
            'jumlah_gaji' => DB::raw($grossSql),
            'jumlah_potongan' => DB::raw($deductionsSql),
            'gaji_bersih' => DB::raw($netSql),
            'total' => DB::raw($netSql),
        ]);
    }

    public function down(): void {}
};
