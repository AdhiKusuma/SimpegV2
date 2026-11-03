<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbMasteresl extends Model
{
    protected $table = 'tb_masteresl';

    protected $primaryKey = 'id_masteresl';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
