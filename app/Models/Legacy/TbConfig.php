<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbConfig extends Model
{
    protected $table = 'tb_config';

    protected $primaryKey = 'id_app';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
