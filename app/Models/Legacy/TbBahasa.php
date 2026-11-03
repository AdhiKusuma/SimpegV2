<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbBahasa extends Model
{
    protected $table = 'tb_bahasa';

    protected $primaryKey = 'id_bhs';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
