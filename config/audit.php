<?php

return [
    'enabled' => (bool) env('AUDIT_LOG_ENABLED', true),
    'queue_connection' => env('AUDIT_LOG_QUEUE_CONNECTION', 'database'),
    'queue' => env('AUDIT_LOG_QUEUE', 'audit'),
    'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 180),
];
