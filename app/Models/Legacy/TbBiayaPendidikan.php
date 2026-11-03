<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbBiayaPendidikan extends Model
{
    protected $table = 'tb_biaya_pendidikan';

    protected $primaryKey = 'biaya_id';

    public $timestamps = false;

    protected $casts = [
        'jumlah_sdm' => 'integer',
        'total_biaya' => 'integer',
        'tgl_pengembangan_sdm' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $guarded = [];
}
