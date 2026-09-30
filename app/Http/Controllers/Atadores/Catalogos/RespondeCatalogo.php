<?php

declare(strict_types=1);

namespace App\Http\Controllers\Atadores\Catalogos;

use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;

/**
 * Respuestas de los tres catálogos de atadores (contrato de catalog-base.ts: { success, message, data? }).
 *
 * SEC-07 (19-03): un fallo inesperado ya no devuelve getMessage() (SQL, nombres de tabla); va al log
 * y a /admin/errores con su trace_id y el usuario ve un mensaje fijo. La validación se hace antes de
 * escribir y vuelve como 422 con sus errores; un registro que no existe, como 404.
 */
trait RespondeCatalogo
{
    use HandlesApiErrors;

    /**
     * @param  callable(): mixed  $escritura  devuelve lo que se manda como `data` (o null)
     */
    private function escribir(callable $escritura, string $exito, string $error): JsonResponse
    {
        try {
            $datos = $escritura();
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Catálogo de atadores: '.$error, $error.'. Si continúa, comparte el código de referencia con Sistemas.');
        }

        $cuerpo = ['success' => true, 'message' => $exito];
        if ($datos !== null) {
            $cuerpo['data'] = $datos;
        }

        return response()->json($cuerpo);
    }

    private function noEncontrado(string $mensaje): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $mensaje], 404);
    }
}
