<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbSeminar extends Model
{
    protected $table = 'tb_seminar';

    protected $primaryKey = 'id_seminar';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
        'tgl_piagam' => 'date',
    ];

    protected $guarded = [];
}
