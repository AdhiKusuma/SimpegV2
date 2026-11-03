<?php

return [
    'proxies' => env(
        'TRUSTED_PROXIES',
        env('APP_ENV', 'production') === 'local' ? '*' : null,
    ),
];
