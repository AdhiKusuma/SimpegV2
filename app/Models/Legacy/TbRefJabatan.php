<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbRefJabatan extends Model
{
    protected $table = 'tb_ref_jabatan';

    protected $primaryKey = 'kode_jabatan';

    public $timestamps = false;

    protected $guarded = [];
}
