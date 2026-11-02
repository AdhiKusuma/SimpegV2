<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbAnak extends Model
{
    protected $table = 'tb_anak';

    protected $primaryKey = 'id_anak';

    public $timestamps = false;

    protected $casts = [
        'tgl_lhr' => 'date',
        'date_reg' => 'date',
    ];

    protected $guarded = [];
}
