<?php

namespace App\Http\Middleware\Monitoreo;

use App\Services\Monitoreo\DispositivoService;
use App\Services\Monitoreo\EstadoRequest;
use App\Services\Monitoreo\Monitoreo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Da a cada navegador una identidad estable (cookie towell_disp). No escribe en BD:
 * la actividad la registra el latido de telemetría. El touch que había en
 * terminate() se quitó porque con Apache + mod_php terminate() corre antes de
 * liberar al navegador y cada UPDATE pagaba la conexión a SQL Server (~600 ms).
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
}
