<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbPendidikan extends Model
{
    protected $table = 'tb_pendidikan';

    protected $primaryKey = 'id_pendidikan';

    public $timestamps = false;

    protected $casts = [
        'tgl_ijazah' => 'date',
        'date_reg' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $guarded = [];
}
