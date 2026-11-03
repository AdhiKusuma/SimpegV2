<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbHukuman extends Model
{
    protected $table = 'tb_hukuman';

    protected $primaryKey = 'id_hukum';

    public $timestamps = false;

    protected $casts = [
        'tgl_sk' => 'date',
        'tgl_pulih' => 'date',
        'date_reg' => 'date',
        'date_update' => 'date',
    ];

    protected $guarded = [];
}
