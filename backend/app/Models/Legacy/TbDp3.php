<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbDp3 extends Model
{
    protected $table = 'tb_dp3';

    protected $primaryKey = 'id_dp3';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'periode_awal' => 'date',
        'periode_akhir' => 'date',
        'nilai_kesetiaan' => 'integer',
        'nilai_prestasi' => 'integer',
        'nilai_tgjwb' => 'integer',
        'nilai_ketaatan' => 'integer',
        'nilai_kejujuran' => 'integer',
        'nilai_kerjasama' => 'integer',
        'nilai_prakarsa' => 'integer',
        'nilai_kepemimpinan' => 'integer',
    ];

    protected $guarded = [];
}
