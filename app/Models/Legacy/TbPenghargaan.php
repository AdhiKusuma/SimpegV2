<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbPenghargaan extends Model
{
    protected $table = 'tb_penghargaan';

    protected $primaryKey = 'id_penghargaan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $guarded = [];
}
