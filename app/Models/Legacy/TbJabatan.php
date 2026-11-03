<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbJabatan extends Model
{
    protected $table = 'tb_jabatan';

    protected $primaryKey = 'id_jab';

    public $timestamps = false;

    protected $casts = [
        'tmt_jabatan' => 'date',
        'sampai_tgl' => 'date',
        'tgl_sk' => 'date',
        'date_reg' => 'date',
    ];

    protected $guarded = [];
}
