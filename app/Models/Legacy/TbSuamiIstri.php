<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbSuamiIstri extends Model
{
    protected $table = 'tb_suamiistri';

    protected $primaryKey = 'id_si';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'tgl_lhr' => 'date',
        'date_reg' => 'date',
    ];

    protected $guarded = [];
}
