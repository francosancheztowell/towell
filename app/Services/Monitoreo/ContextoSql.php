<?php

namespace App\Services\Monitoreo;

use Illuminate\Http\Request;
use Laravel\Pulse\Facades\Pulse;
use Symfony\Component\HttpFoundation\Response;

/**
 * PERF-07: costo de `EXEC dbo.sp_SetAppContext` (SetSqlContextInfo) en cada request web.
 *
 * - `ctx;dur=<ms>` se anexa al header Server-Timing (DevTools lo muestra también en los
 *   POST de Livewire, que el cliente de telemetría no mide).
 * - Pulse agrega avg/max/count por hora con llave GET | POST | livewire (tipo `contexto_sql`, en µs),
 *   para decidir con números si conviene saltarlo en algún tipo de request.
 *
 * Solo mide: nunca lanza y no cambia si el contexto se sella o no.
 */
final class ContextoSql
{
    public const TIPO = 'contexto_sql';

    public static function registrar(Request $request, Response $response, float $ms): void
    {
        if (! Monitoreo::activo()) {
            return;
        }

        Monitoreo::seguro('medir contexto SQL', function () use ($request, $response, $ms): void {
            $previo = (string) $response->headers->get('Server-Timing', '');
            $response->headers->set('Server-Timing', ltrim($previo.', ', ', ').sprintf('ctx;dur=%.1f', $ms));

            if (config('pulse.enabled')) {
                $llave = $request->hasHeader('X-Livewire') ? 'livewire' : $request->method();
                Pulse::record(self::TIPO, $llave, (int) round($ms * 1000))->avg()->max()->count();
            }
        });
    }
}
