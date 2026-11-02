<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbPangkat extends Model
{
    protected $table = 'tb_pangkat';

    protected $primaryKey = 'id_pangkat';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'tgl_sk' => 'date',
        'tmt_pangkat' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $guarded = [];
}
