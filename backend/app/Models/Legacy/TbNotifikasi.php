<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbNotifikasi extends Model
{
    protected $table = 'tb_notifikasi';

    protected $primaryKey = 'id_notif';

    public $timestamps = false;

    protected $casts = [
        'waktu_notif' => 'datetime',
    ];

    protected $guarded = [];
}
