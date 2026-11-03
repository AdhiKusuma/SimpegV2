<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbPenugasan extends Model
{
    protected $table = 'tb_penugasan';

    protected $primaryKey = 'id_penugasan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
