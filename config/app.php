<?php

return [
    'name' => env('APP_NAME', 'MeatinOS'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) env('APP_URL', ''), '/'),
    'force_https' => filter_var(env('APP_FORCE_HTTPS', 'false'), FILTER_VALIDATE_BOOL),
    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),
    'key' => env('APP_KEY', ''),
    'session' => [
        'name' => env('SESSION_NAME', 'meatinos_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 120),
    ],
];
