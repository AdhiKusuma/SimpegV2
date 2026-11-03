<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbRecycleBin extends Model
{
    protected $table = 'tb_recycle_bin';

    protected $primaryKey = 'id_trash';

    public $timestamps = false;

    protected $casts = [
        'created' => 'datetime',
    ];

    protected $guarded = [];
}
