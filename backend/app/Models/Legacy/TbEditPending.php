<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbEditPending extends Model
{
    protected $table = 'tb_edit_pending';

    protected $primaryKey = 'id_edit';

    public $timestamps = false;

    protected $casts = [
        'tanggal_pengajuan' => 'datetime',
        'tanggal_otorisasi' => 'datetime',
    ];

    protected $guarded = [];
}
