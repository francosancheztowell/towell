<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\Concerns;

use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;

/**
 * SEC-07 en Programa Urd / Eng: errores JSON sin getMessage() y con trace_id (HandlesApiErrors).
 *
 * Además de 'message' mandan 'error' con el mismo texto: las pantallas que aún no pasan por
 * mensajeError() (creacion-ordenes.js, Karl Mayer) leen 'error'. Las claves de $extra son las
 * que el contrato de cada endpoint ya devolvía en el error (p. ej. 'data', 'semanas').
 */
trait RespuestasErrorUrdEng
{
    use HandlesApiErrors;

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $contexto  datos extra para el log (no llegan al cliente)
     */
    protected function errorServidor(\Throwable $e, string $contextoLog, string $mensaje, array $extra = [], array $contexto = []): JsonResponse
    {
        $respuesta = $this->apiErrorResponse($e, $contextoLog, $mensaje, 500, $contexto);

        return $respuesta->setData($respuesta->getData(true) + ['error' => $mensaje] + $extra);
    }

    /** @param  array<string, mixed>  $extra */
    protected function errorNegocio(string $mensaje, int $status = 422, array $extra = []): JsonResponse
    {
        return $this->apiClientErrorResponse($mensaje, $status, [], ['error' => $mensaje] + $extra);
    }
}
