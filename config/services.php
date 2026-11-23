<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'sso' => [
        'enabled' => filter_var(env('SSO_ENABLED', false), FILTER_VALIDATE_BOOL),
        'base_url' => env('SSO_BASE_URL', 'http://localhost/rest_api_sso'),
        'app' => env('SSO_APP', 'simpeg'),
        'timeout' => (int) env('SSO_TIMEOUT', 10),
        'connect_timeout' => (int) env('SSO_CONNECT_TIMEOUT', 5),
        'verify' => filter_var(env('SSO_VERIFY', true), FILTER_VALIDATE_BOOL),
        'ca_bundle' => env('SSO_CA_BUNDLE', base_path('resources/certificates/lets-encrypt-yr1.pem')),
    ],

    'ip_geolocation' => [
        // Opt-in because enabling this sends public IPs to the configured provider.
        'enabled' => filter_var(env('IP_GEOLOCATION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'base_url' => env('IP_GEOLOCATION_BASE_URL', 'https://ipwho.is'),
        'timeout' => (int) env('IP_GEOLOCATION_TIMEOUT', 2),
        'cache_days' => (int) env('IP_GEOLOCATION_CACHE_DAYS', 30),
    ],

];
