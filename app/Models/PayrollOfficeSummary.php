<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollOfficeSummary extends Model
{
    protected $fillable = [
        'payroll_import_id', 'office_code', 'source_label', 'employee_count',
        'total',
        'gaji_pokok', 'golongan_pegawai', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_kinerja',
        'tunj_masa_kerja', 'tunj_fungsional', 'honor_kontrak', 'tunj_pajak', 'uang_makan_kontrak',
        'thl',
        'jumlah_gaji', 'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit',
        'pengembalian_tunj_keluarga', 'keris', 'jumlah_potongan', 'gaji_bersih',
        'credit_employee_count', 'credit_pokok', 'credit_bunga', 'credit_total',
    ];

    protected $casts = [
        'payroll_import_id' => 'integer',
        'employee_count' => 'integer',
        'credit_employee_count' => 'integer',
    ];

    public function payrollImport(): BelongsTo
    {
        return $this->belongsTo(PayrollImport::class);
    }
}
