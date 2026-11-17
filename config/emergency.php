<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Emergency local account provisioning
    |--------------------------------------------------------------------------
    |
    | Disabled by default. Set the values only in the deployment environment
    | when an authorized administrator explicitly needs the local account.
    | The password is read from .env and is never committed to the repository.
    |
    */
    'enabled' => filter_var(env('EMERGENCY_ACCOUNT_ENABLED', false), FILTER_VALIDATE_BOOL),
    'id' => (string) env('EMERGENCY_ACCOUNT_ID', 'adhikusuma'),
    'password' => (string) env('EMERGENCY_ACCOUNT_PASSWORD', ''),
    'name' => (string) env('EMERGENCY_ACCOUNT_NAME', 'IT Administrator'),
];
