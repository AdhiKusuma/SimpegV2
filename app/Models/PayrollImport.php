<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollImport extends Model
{
    protected $fillable = [
        'tahun',
        'bulan',
        'source_filename',
        'source_sha256',
        'source_row_count',
        'source_summary_count',
        'is_current',
        'imported_by',
    ];

    protected $casts = [
        'tahun' => 'integer',
        'bulan' => 'integer',
        'source_row_count' => 'integer',
        'source_summary_count' => 'integer',
        'is_current' => 'boolean',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function officeSummaries(): HasMany
    {
        return $this->hasMany(PayrollOfficeSummary::class);
    }

    public function publication(): HasOne
    {
        return $this->hasOne(PayrollPublication::class);
    }
}
