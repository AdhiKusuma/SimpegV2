<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollSalaryMaster extends Model
{
    protected $table = 'payroll_salary_masters';

    protected $fillable = [
        'employee_id', 'office_code', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian',
        'gaji_pokok', 'golongan_pegawai', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_pajak',
        'tunj_kinerja', 'tunj_fungsional', 'tunj_masa_kerja', 'honor_kontrak', 'thl', 'uang_makan_kontrak',
        'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit', 'keris',
        'jumlah_gaji', 'jumlah_potongan', 'gaji_bersih', 'total', 'rekening_tabungan', 'no_hp_keris',
        'source_import_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'tgl_gajian' => 'date',
        'source_import_id' => 'integer',
    ];
}
