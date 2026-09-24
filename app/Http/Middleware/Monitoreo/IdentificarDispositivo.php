<?php

namespace App\Http\Middleware\Monitoreo;

use App\Services\Monitoreo\DispositivoService;
use App\Services\Monitoreo\EstadoRequest;
use App\Services\Monitoreo\Monitoreo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Da a cada navegador una identidad estable (cookie towell_disp) y registra su
 * actividad. El touch se hace en terminate(), ya con la respuesta enviada, y
 * como máximo una vez cada touch_cache_seg por dispositivo.
 */
class IdentificarDispositivo
{
    public function __construct(private readonly DispositivoService $dispositivos) {}

    public function handle(Request $request, Closure $next): Response
    {
        // EstadoRequest es scoped, pero el kernel HTTP no limpia los scoped entre
        // requests del mismo proceso (tests, Octane): empezar de cero aquí.
        app()->forgetInstance(EstadoRequest::class);

        if (! Monitoreo::activo()) {
            return $next($request);
        }

        // Antes de $next: el listener de Login (incluida la restauración por
        // "recordarme") necesita el uuid aunque la cookie aún no exista.
        [$uuid, $nuevo] = Monitoreo::seguro(
            'identificar dispositivo',
            fn (): array => $this->dispositivos->asegurarUuid($request),
            [null, false],
        );

        $response = $next($request);

        if ($nuevo && $uuid !== null) {
            Monitoreo::seguro('poner cookie de dispositivo', function () use ($response, $uuid, $request): void {
                $response->headers->setCookie($this->dispositivos->cookie($uuid, $request));
            });
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! Monitoreo::activo()) {
            return;
        }

        Monitoreo::seguro('terminate IdentificarDispositivo', function () use ($request): void {
            $usuarioId = Auth::id();
            $ruta = $request->route();

            // El latido de telemetría ya actualiza el dispositivo con más detalle.
            if ($usuarioId === null || $request->routeIs('telemetria.*')) {
                return;
            }

            $nombre = is_object($ruta) ? ($ruta->getName() ?? $ruta->uri()) : null;
            $this->dispositivos->touch($request, (int) $usuarioId, $nombre);
        });
    }
}
