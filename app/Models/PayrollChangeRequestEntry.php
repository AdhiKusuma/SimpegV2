<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollChangeRequestEntry extends Model
{
    protected $table = 'payroll_change_request_entries';

    protected $guarded = [];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'source_row_number' => 'integer',
    ];

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(PayrollChangeRequest::class, 'payroll_change_request_id');
    }
}
