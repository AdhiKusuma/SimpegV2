<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPublication extends Model
{
    protected $fillable = [
        'payroll_import_id',
        'mode',
        'status',
        'scheduled_at',
        'sent_at',
        'total_recipients',
        'sent_count',
        'failed_count',
        'created_by',
        'updated_by',
        'deleted_by',
        'deletion_reason',
        'last_error',
    ];

    protected $casts = [
        'payroll_import_id' => 'integer',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'deleted_at' => 'datetime',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function payrollImport(): BelongsTo
    {
        return $this->belongsTo(PayrollImport::class);
    }
}
