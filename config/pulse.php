<?php

use Laravel\Pulse\Http\Middleware\Authorize;
use Laravel\Pulse\Recorders;

/*
 * Laravel Pulse (fase 14, 14-CONTEXT.md). Métricas agregadas de servidor en /admin/pulse.
 *
 * - Guarda en la conexión SQLite `pulse` (storage/pulse/pulse.sqlite), que registra
 *   App\Services\Monitoreo\PulseConexion en runtime: Pulse no soporta sqlsrv.
 * - Acceso: Gate `viewPulse` = Gate `admin` (área Sistemas), ver MonitoreoServiceProvider.
 * - Fallback (MON-32): PULSE_ENABLED=false apaga la captura, la migración se salta y
 *   /admin/pulse muestra un aviso. El panel /admin sigue con las tablas SYSMon*.
 * - Errores: el recorder Exceptions está apagado; la fuente única es SYSMonError.
 */
return [

    'domain' => env('PULSE_DOMAIN'),

    'path' => env('PULSE_PATH', 'admin/pulse'),

    'enabled' => env('PULSE_ENABLED', true),

    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),

        'trim' => [
            'keep' => env('PULSE_STORAGE_KEEP', '7 days'),
        ],

        'database' => [
            'connection' => env('PULSE_DB_CONNECTION', 'pulse'),
            'chunk' => 1000,
            // Archivo de la conexión `pulse` (default storage/pulse/pulse.sqlite; en tests, en memoria).
            'sqlite' => env('PULSE_DB_DATABASE'),
        ],
    ],

    'ingest' => [
        'driver' => env('PULSE_INGEST_DRIVER', 'storage'),

        'buffer' => env('PULSE_INGEST_BUFFER', 5_000),

        'trim' => [
            'lottery' => [1, 1_000],
            'keep' => env('PULSE_INGEST_KEEP', '7 days'),
        ],

        'redis' => [
            'connection' => env('PULSE_REDIS_CONNECTION'),
            'chunk' => 1000,
        ],
    ],

    'cache' => env('PULSE_CACHE_DRIVER'),

    // `auth` antes que Authorize: el invitado va al login en vez de ver un 403.
    'middleware' => [
        'web',
        'auth',
        Authorize::class,
    ],

    /*
     * Encendidos: requests, queries, jobs y requests salientes lentos, y uso por usuario.
     * Fuera de la lista (apagados): Exceptions (fuente única SYSMonError), Servers
     * (requiere el daemon pulse:check), CacheInteractions, Queues y UserJobs.
     */
    'recorders' => [
        Recorders\SlowJobs::class => [
            'enabled' => env('PULSE_SLOW_JOBS_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_JOBS_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_JOBS_THRESHOLD', 1000),
            'ignore' => [],
        ],

        Recorders\SlowOutgoingRequests::class => [
            'enabled' => env('PULSE_SLOW_OUTGOING_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_OUTGOING_REQUESTS_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_OUTGOING_REQUESTS_THRESHOLD', 1000),
            'ignore' => [],
            'groups' => [
                '#^https://api\.telegram\.org/bot[^/]+/(.*)$#' => 'api.telegram.org/bot*/\1',
            ],
        ],

        Recorders\SlowQueries::class => [
            'enabled' => env('PULSE_SLOW_QUERIES_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_QUERIES_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_QUERIES_THRESHOLD', 500),
            'location' => env('PULSE_SLOW_QUERIES_LOCATION', true),
            'max_query_length' => env('PULSE_SLOW_QUERIES_MAX_QUERY_LENGTH', 2000),
            'ignore' => [
                '/(["`])pulse_[\w]+?\1/', // Tablas de Pulse...
            ],
        ],

        Recorders\SlowRequests::class => [
            'enabled' => env('PULSE_SLOW_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_REQUESTS_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_REQUESTS_THRESHOLD', 1000),
            'ignore' => [
                '#^/admin/pulse#', // El propio dashboard...
                '#^/telemetria/#', // Latidos y vistas del cliente de monitoreo...
            ],
        ],

        // `livewire*/update` se muestrea a 0.1 con Pulse::filter (MonitoreoServiceProvider).
        Recorders\UserRequests::class => [
            'enabled' => env('PULSE_USER_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_USER_REQUESTS_SAMPLE_RATE', 1),
            'ignore' => [
                '#^/admin/pulse#',
                '#^/telemetria/#',
            ],
        ],
    ],

    // Muestreo de las llamadas de Livewire en UserRequests (1 = todas, 0.1 = una de cada diez).
    'livewire_sample_rate' => env('PULSE_LIVEWIRE_SAMPLE_RATE', 0.1),
];
