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
                'pending', null, '' => 'Menunggu',
                'approved' => 'Disetujui',
                'rejected' => 'Ditolak',
                default => $value,
            },
            set: fn (?string $value): ?string => match ($value) {
                'Menunggu', 'pending' => 'pending',
                'Disetujui', 'approved' => 'approved',
                'Ditolak', 'rejected' => 'rejected',
                default => $value,
            },
        );
    }

    protected $guarded = [];
}
