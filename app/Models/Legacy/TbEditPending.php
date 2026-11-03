<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function statusOtorisasi(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => match ($value) {
                'pending', 'Menunggu', null, '' => 'Menunggu',
                'approved', 'Disetujui' => 'Disetujui',
                'rejected', 'Ditolak' => 'Ditolak',
                default => $value,
            },
            set: fn (?string $value): ?string => match ($value) {
                'Menunggu', 'pending' => 'Menunggu',
                'Disetujui', 'approved' => 'Disetujui',
                'Ditolak', 'rejected' => 'Ditolak',
                default => $value,
            },
        );
    }

    protected $guarded = [];
}
