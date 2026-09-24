<?php

namespace App\Http\Middleware\Monitoreo;

use App\Services\Monitoreo\CierreRemotoService;
use App\Services\Monitoreo\Monitoreo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si un administrador pidió cerrar la sesión de ESTE dispositivo, lo desloguea
 * (solo este dispositivo) antes de atender la request (contrato §5).
 */
class AplicarCierreRemoto
{
    public function __construct(private readonly CierreRemotoService $cierres) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Monitoreo::activo()) {
            return $next($request);
        }

        $respuesta = Monitoreo::seguro(
            'aplicar cierre remoto',
            fn (): ?Response => $this->cierres->aplicarSiCorresponde($request),
        );

        return $respuesta ?? $next($request);
    }
}
