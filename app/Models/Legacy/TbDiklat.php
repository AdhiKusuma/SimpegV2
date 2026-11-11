<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;

class TbDiklat extends Model
{
    protected $table = 'tb_diklat';

    protected $primaryKey = 'id_diklat';

    public $timestamps = false;

    protected $casts = [
        'biaya' => 'integer',
        'biaya_per_peserta' => 'integer',
        'diklat_id' => 'integer',
        'date_reg' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $guarded = [];

    public function masterKegiatan()
    {
        return $this->belongsTo(TbBiayaPendidikan::class, 'diklat_id', 'biaya_id');
    }
}
