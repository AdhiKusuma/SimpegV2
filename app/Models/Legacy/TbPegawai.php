<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TbPegawai extends Model
{
    protected $table = 'tb_pegawai';

    protected $primaryKey = 'id_peg';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'tgl_lhr' => 'date',
        'tmt_kerja' => 'date',
        'tgl_pensiun' => 'date',
        'date_reg' => 'date',
        'date_modify' => 'date',
    ];

    protected $guarded = [];

    public function jabatan(): HasMany
    {
        return $this->hasMany(TbJabatan::class, 'id_peg', 'id_peg');
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(TbMutasi::class, 'id_peg', 'id_peg');
    }

    public function pangkat(): HasMany
    {
        return $this->hasMany(TbPangkat::class, 'id_peg', 'id_peg');
    }

    public function pendidikan(): HasMany
    {
        return $this->hasMany(TbPendidikan::class, 'id_peg', 'id_peg');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(TbAnak::class, 'id_peg', 'id_peg');
    }

    public function orangTua(): HasMany
    {
        return $this->hasMany(TbOrtu::class, 'id_peg', 'id_peg');
    }

    public function pasangan(): HasMany
    {
        return $this->hasMany(TbSuamiIstri::class, 'id_peg', 'id_peg');
    }

    public function penghargaan(): HasMany
    {
        return $this->hasMany(TbPenghargaan::class, 'id_peg', 'id_peg');
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(TbPenugasan::class, 'id_peg', 'id_peg');
    }

    public function seminar(): HasMany
    {
        return $this->hasMany(TbSeminar::class, 'id_peg', 'id_peg');
    }

    public function cuti(): HasMany
    {
        return $this->hasMany(TbCuti::class, 'id_peg', 'id_peg');
    }

    public function bahasa(): HasMany
    {
        return $this->hasMany(TbBahasa::class, 'id_peg', 'id_peg');
    }

    public function dp3(): HasMany
    {
        return $this->hasMany(TbDp3::class, 'id_peg', 'id_peg');
    }

    public function latihanJabatan(): HasMany
    {
        return $this->hasMany(TbLatJabatan::class, 'id_peg', 'id_peg');
    }
}
