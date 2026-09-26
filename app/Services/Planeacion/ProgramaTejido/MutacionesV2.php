<?php

declare(strict_types=1);

namespace App\Services\Planeacion\ProgramaTejido;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Corte legacy ↔ v2 de las mutaciones simples (PT-05 · PT-ROL-01): flag por familia,
 * usuarios canary y telemetría. La ruta y el JSON son los mismos en las dos versiones;
 * el controller elige el handler con activa() y lo envuelve con medir().
 */
final class MutacionesV2
{
    public const FAMILIAS = ['actualizar', 'reprogramar', 'calendarios'];

    public static function activa(string $familia): bool
    {
        $modo = strtolower(trim((string) config("planeacion.mutaciones_v2.{$familia}", 'off')));

        return match ($modo) {
            'on', '1', 'true' => true,
            'canary' => in_array((int) Auth::id(), (array) config('planeacion.mutaciones_v2.usuarios_canary', []), true),
            default => false,
        };
    }

    /**
     * Ejecuta el handler y registra una línea por mutación para comparar legacy y v2 en el
     * canary (tiempo, status). Una excepción se registra y se relanza tal cual.
     *
     * @template T of Response
     *
     * @param  callable(): T  $handler
     * @return T
     */
    public static function medir(string $familia, string $version, callable $handler): Response
    {
        $inicio = hrtime(true);
        $status = null;
        $excepcion = null;

        try {
            $respuesta = $handler();
            $status = $respuesta->getStatusCode();

            return $respuesta;
        } catch (Throwable $e) {
            $excepcion = $e::class;
            throw $e;
        } finally {
            Log::info('programa_tejido.mutacion', [
                'familia' => $familia,
                'version' => $version,
                'superficie' => ProgramaTejidoSurface::actual()->value,
                'ms' => round((hrtime(true) - $inicio) / 1e6, 1),
                'status' => $status,
                'excepcion' => $excepcion,
                'usuario' => Auth::id(),
            ]);
        }
    }
}
