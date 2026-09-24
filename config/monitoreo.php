<?php

/*
 * Monitoreo de dispositivos, sesiones, navegación y errores.
 * Contrato: .planning/phases/11-mon-servidor/11-CONTRACT.md (§8).
 */
return [
    // Kill switch: false apaga middleware de captura, endpoints (204 sin BD) y el script cliente.
    'enabled' => env('MONITOREO_ENABLED', true),

    // Áreas (SYSUsuario.area) que pueden abrir /admin. CSV; se comparan sin acentos ni mayúsculas.
    'areas_admin' => array_filter(array_map('trim', explode(',', env('MONITOREO_AREAS_ADMIN', 'Sistemas')))),

    'latido_seg' => ['visible' => 60, 'oculta' => 300],
    'touch_cache_seg' => 30,
    'en_linea_seg' => 150,
    'inactivo_seg' => 600,
    'sesion_expira_min' => 120,
    'umbrales' => ['servidor_ms' => 800, 'carga_ms' => 3000],
    'poll_seconds' => 15,

    'errores' => [
        'max_eventos_dia' => 50,
        'alertas_max_hora' => 10,
        // Destinatario fijo de las alertas de errores nuevos o regresiones (decisión del owner, 11-03).
        'correo_alertas' => env('MONITOREO_ALERTA_CORREO', 'francost15@gmail.com'),
        'throttle_cliente_min' => 30,
        // Excepciones que no se registran (además del dontReport interno de Laravel).
        'ignorar' => [
            Illuminate\Validation\ValidationException::class,
            Illuminate\Auth\AuthenticationException::class,
            Illuminate\Auth\Access\AuthorizationException::class,
            Illuminate\Session\TokenMismatchException::class,
            Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            Illuminate\Database\Eloquent\ModelNotFoundException::class,
            Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        ],
    ],

    'retencion' => [
        'vista_dias' => 90,
        'evento_dias' => 90,
        'sesion_dias' => 180,
        'acceso_dias' => 365,
        'error_resuelto_dias' => 180,
    ],
];
