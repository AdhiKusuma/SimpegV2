<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbOrtu extends Model
{
    protected $table = 'tb_ortu';

    protected $primaryKey = 'id_ortu';

    public $timestamps = false;

    protected $casts = [
        'tgl_lhr' => 'date',
        'date_reg' => 'date',
    ];

    protected $guarded = [];
}
