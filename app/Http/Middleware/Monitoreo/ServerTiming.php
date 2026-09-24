<?php

namespace App\Http\Middleware\Monitoreo;

use App\Services\Monitoreo\EstadoRequest;
use App\Services\Monitoreo\Monitoreo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header Server-Timing en respuestas HTML de GET para que el cliente lo lea con
 * Navigation Timing (contrato §4): `app;dur=<ms>, db;dur=<ms>;desc="<n> q"`.
 * Las consultas las cuenta un listener de QueryExecuted en MonitoreoServiceProvider.
 */
class ServerTiming
{
    public function __construct(private readonly EstadoRequest $estado) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Monitoreo::activo() || ! $request->isMethod('GET')
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $inicio = defined('LARAVEL_START') ? LARAVEL_START : (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%.1f, db;dur=%.1f;desc="%d q"',
            max(0, (microtime(true) - $inicio) * 1000),
            $this->estado->consultasMs,
            $this->estado->consultasN,
        ));

        return $response;
    }
}
