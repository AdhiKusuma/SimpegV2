<?php

namespace App\Models;

use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataDiriDocument extends Model
{
    public const TYPES = [
        'ktp' => 'KTP',
        'kk' => 'KK',
        'npwp' => 'NPWP',
        'ijazah' => 'Ijazah',
    ];

    protected $table = 'data_diri_documents';

    protected $fillable = [
        'pegawai_id',
        'jenis',
        'nama_asli',
        'path',
        'ukuran',
        'hash',
        'diunggah_oleh',
    ];

    protected $casts = [
        'ukuran' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(TbPegawai::class, 'pegawai_id', 'id_peg');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(TbUser::class, 'diunggah_oleh', 'id_user');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->jenis] ?? $this->jenis;
    }
}
