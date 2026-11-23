<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbAngkat extends Model
{
    protected $table = 'tb_angkat';

    protected $primaryKey = 'id_angkat';

    public $timestamps = false;

    protected $casts = [
        'tgl_mutasi' => 'date',
        'tmt' => 'date',
        'tmt_selesai' => 'date',
        'date_reg' => 'datetime',
    ];

    protected $guarded = [];
}
