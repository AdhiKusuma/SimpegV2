<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbMasterJabatan extends Model
{
    protected $table = 'tb_master_jabatan';

    protected $primaryKey = 'kode_jabatan';

    public $timestamps = false;

    protected $casts = [
        'kode_jabatan' => 'integer',
        'kuota' => 'integer',
        'parent_kode_jabatan' => 'integer',
    ];

    protected $guarded = [];
}
