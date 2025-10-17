<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Force HTTPS Configuration
    |--------------------------------------------------------------------------
    |
    | Esta configuración permite forzar HTTPS en diferentes entornos.
    |
    */

    'force_https' => env('FORCE_HTTPS', false),

    'environments' => [
        'production' => true,
        'staging' => true,
        'local' => false,
        'testing' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Si tu aplicación está detrás de un proxy (como Cloudflare, AWS Load Balancer),
    | configura las IPs de los proxies confiables.
    |
    */

    'trusted_proxies' => [
        '127.0.0.1',
        '::1',
        // Agregar IPs de tus proxies aquí
        // '192.168.1.1',
        // '10.0.0.1',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTPS Headers
    |--------------------------------------------------------------------------
    |
    | Headers de seguridad para HTTPS.
    |
    */

    'security_headers' => [
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ],
];

