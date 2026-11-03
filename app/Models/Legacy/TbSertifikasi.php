<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbSertifikasi extends Model
{
    protected $table = 'tb_sertifikasi';

    protected $primaryKey = 'id_sertif';

    public $timestamps = false;

    protected $casts = [
        'tgl_sertifikat' => 'date',
        'tgl_expired' => 'date',
        'date_reg' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $guarded = [];
}
