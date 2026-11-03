<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbKantor extends Model
{
    protected $table = 'tb_kantor';

    protected $primaryKey = 'kode_kantor_detail';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
