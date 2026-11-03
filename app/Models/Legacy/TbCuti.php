<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbCuti extends Model
{
    protected $table = 'tb_cuti';

    protected $primaryKey = 'id_cuti';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'tgl_suratcuti' => 'date',
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
    ];

    protected $guarded = [];
}
