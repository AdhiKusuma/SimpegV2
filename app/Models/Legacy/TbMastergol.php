<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbMastergol extends Model
{
    protected $table = 'tb_mastergol';

    protected $primaryKey = 'id_mastergol';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
