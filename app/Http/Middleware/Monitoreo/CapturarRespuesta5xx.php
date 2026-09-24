<?php

namespace App\Http\Middleware\Monitoreo;

use App\Services\Monitoreo\ErrorRecorder;
use App\Services\Monitoreo\EstadoRequest;
use App\Services\Monitoreo\Monitoreo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra como `http5xx` las respuestas >= 500 que no pasaron por el handler de
 * excepciones: los catch que devuelven response()->json([...], 500) directamente.
 */
class CapturarRespuesta5xx
{
    public function __construct(
        private readonly ErrorRecorder $errores,
        private readonly EstadoRequest $estado,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 500 && Monitoreo::activo() && ! $this->estado->errorReportado) {
            Monitoreo::seguro('capturar respuesta 5xx', fn () => $this->errores->capturarRespuesta($request, $response));
        }

        return $response;
    }
}
