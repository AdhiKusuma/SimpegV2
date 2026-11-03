<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollChangeRequest extends Model
{
    protected $table = 'payroll_change_requests';

    protected $guarded = [];

    protected $casts = [
        'office_codes' => 'array',
        'summary_data' => 'array',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'tahun' => 'integer',
        'bulan' => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollChangeRequestEntry::class);
    }
}
