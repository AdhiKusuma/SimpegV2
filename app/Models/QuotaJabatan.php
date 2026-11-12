<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotaJabatan extends Model
{
    protected $table = 'kuota_jabatan';

    protected $fillable = [
        'kode_cabang',
        'jabatan_id',
        'nama_jabatan',
        'kuota_pegawai',
    ];

    protected $casts = [
        'jabatan_id' => 'integer',
        'kuota_pegawai' => 'integer',
    ];
}
