<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_role',
        'module',
        'action',
        'description',
        'entity_type',
        'entity_id',
        'route_name',
        'method',
        'url',
        'ip_address',
        'location_source',
        'location_label',
        'location_latitude',
        'location_longitude',
        'location_accuracy_meters',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
