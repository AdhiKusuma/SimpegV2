<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbMutasi extends Model
{
    protected $table = 'tb_mutasi';

    protected $primaryKey = 'id_mutasi';

    public $timestamps = false;

    protected $casts = [
        'tgl_mutasi' => 'date',
        'tmt' => 'date',
        'date_reg' => 'date',
    ];

    protected $guarded = [];
}
