<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntry extends Model
{
    protected $fillable = [
        'payroll_import_id', 'office_code', 'employee_id', 'source_row_number',
        'bank_office_code', 'no_rekening', 'nama_nasabah', 'tgl_gajian', 'total',
        'gaji_pokok', 'golongan_pegawai', 'tunj_suami_istri', 'tunj_anak', 'tunj_jabatan', 'tunj_ops', 'tunj_kinerja',
        'tunj_masa_kerja', 'tunj_fungsional', 'honor_kontrak', 'thl', 'tunj_pajak', 'uang_makan_kontrak',
        'jumlah_gaji', 'bpjs_kesehatan', 'bpjs_tenaga_kerja', 'absensi', 'angsuran_kredit',
        'pengembalian_tunj_keluarga', 'keris', 'jumlah_potongan', 'gaji_bersih',
        'rekening_tabungan', 'rekening_kredit', 'no_hp_keris', 'source_created', 'source_updated',
        'source_log_user', 'source_log_approval',
    ];

    protected $casts = [
        'payroll_import_id' => 'integer',
        'source_row_number' => 'integer',
        'tgl_gajian' => 'date',
    ];

    protected $hidden = [
        'rekening_tabungan', 'rekening_kredit', 'no_hp_keris', 'source_log_user', 'source_log_approval',
    ];

    public function payrollImport(): BelongsTo
    {
        return $this->belongsTo(PayrollImport::class);
    }
}
