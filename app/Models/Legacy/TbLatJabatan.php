<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbLatJabatan extends Model
{
    protected $table = 'tb_lat_jabatan';

    protected $primaryKey = 'id_lat_jabatan';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];
}
