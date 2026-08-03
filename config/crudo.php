<?php

declare(strict_types=1);

$pollSeconds = max(5, (int) env('CRUDO_POLL_SECONDS', 15));

return [
    /*
    |--------------------------------------------------------------------------
    | Fuentes de datos (solo lectura)
    |--------------------------------------------------------------------------
    |
    | El módulo nunca escribe en estas conexiones. Los nombres de tabla se
    | mantienen configurables para poder usar SQLite en las pruebas.
    |
    */
    'connections' => [
        'source' => env('CRUDO_SOURCE_CONNECTION', 'sqlsrv_ti'),
        'catalog' => env('CRUDO_CATALOG_CONNECTION', 'sqlsrv'),
    ],

    'tables' => [
        'headers' => env('CRUDO_HEADERS_TABLE', 'dbo.TWCRUDOTABLE'),
        'lines' => env('CRUDO_LINES_TABLE', 'dbo.TWCRUDOLINE'),
        'machines' => env('CRUDO_MACHINES_TABLE', 'dbo.ReqTelares'),
        'sequence' => env('CRUDO_SEQUENCE_TABLE', 'dbo.InvSecuenciaTelares'),
        'paros' => env('CRUDO_PAROS_TABLE', 'dbo.ManFallasParos'),
    ],

    'data_area_id' => env('CRUDO_DATA_AREA_ID', 'pro'),

    /*
    |--------------------------------------------------------------------------
    | Actualización
    |--------------------------------------------------------------------------
    */
    'poll_seconds' => $pollSeconds,
    'cache_fresh_seconds' => max(1, (int) env('CRUDO_CACHE_FRESH_SECONDS', $pollSeconds - 1)),
    'cache_stale_seconds' => max(60, (int) env('CRUDO_CACHE_STALE_SECONDS', 300)),
    'cache_lock_seconds' => max(15, (int) env('CRUDO_CACHE_LOCK_SECONDS', 60)),
    'catalog_cache_seconds' => max(0, (int) env('CRUDO_CATALOG_CACHE_SECONDS', 300)),
    'line_query_chunk_size' => min(1000, max(100, (int) env('CRUDO_LINE_QUERY_CHUNK_SIZE', 700))),

    // Tope del filtro por rango de fechas: acota captura + defectos consultados en una sola petición.
    'max_range_days' => min(90, max(1, (int) env('CRUDO_MAX_RANGE_DAYS', 31))),

    /*
    |--------------------------------------------------------------------------
    | Reglas provisionales del semáforo
    |--------------------------------------------------------------------------
    |
    | Estos valores se confirmarán con negocio. Tenerlos aquí permite
    | corregirlos sin tocar la consulta, Livewire o el diseño.
    |
    */
    'bad_quality_percent' => (float) env('CRUDO_BAD_QUALITY_PERCENT', 10),
    'daily_kg_target' => [
        'Karl Mayer' => (float) env('CRUDO_KG_TARGET_KARL_MAYER', 300),
        'Jacquard' => (float) env('CRUDO_KG_TARGET_JACQUARD', 300),
        'Smith' => (float) env('CRUDO_KG_TARGET_SMITH', 300),
        'Sin clasificar' => (float) env('CRUDO_KG_TARGET_DEFAULT', 300),
    ],
    'turns_per_day' => 4,

    'salons' => [
        'KM' => 'Karl Mayer',
        'KARL MAYER' => 'Karl Mayer',
        'JACQUARD' => 'Jacquard',
        'SMITH' => 'Smith',
        'SMIT' => 'Smith',
    ],

    'catalog_salons' => ['KM', 'Jacquard', 'Smith'],

    /*
    |--------------------------------------------------------------------------
    | Distribución física de telares
    |--------------------------------------------------------------------------
    |
    | Cada arreglo representa una columna de arriba hacia abajo. Los telares
    | no configurados se agregan a la columna más corta sin perderse.
    |
    */
    'floor_layout' => [
        'Karl Mayer' => [
            ['401', '402'],
        ],
        'Jacquard' => [
            ['201', '203', '205', '207', '209'],
            ['202', '204', '206', '208', '210'],
            ['215', '214', '213', '212', '211'],
        ],
        'Smith' => [
            ['299', '301', '303', '305', '307', '309'],
            ['300', '302', '304', '306', '308', '310'],
            ['319', '317', '315', '313', '311'],
            ['320', '318', '316', '314', '312'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Acceso
    |--------------------------------------------------------------------------
    |
    | La ruta ya está protegida por auth. Al configurar un nombre se exige
    | además userCan('acceso', nombre). Vacío mantiene acceso autenticado.
    |
    */
    'permission_module' => env('CRUDO_PERMISSION_MODULE'),
];
