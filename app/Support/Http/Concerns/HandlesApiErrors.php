<?php

namespace App\Support\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use WeakMap;

trait HandlesApiErrors
{
    /** SEC-04: lo único que ve el cliente de un 5xx con APP_DEBUG=false. */
    public const MENSAJE_ERROR_SERVIDOR = 'Ocurrió un error en el servidor. Si continúa, comparte el código de referencia con Sistemas.';

    /**
     * Genera una respuesta JSON de error con trace_id y registra el contexto.
     */
    protected function apiErrorResponse(
        \Throwable $e,
        string $logMessage,
        string $clientMessage,
        int $status = 500,
        array $context = []
    ): JsonResponse {
        // Monitoreo (MON-07): que el error quede agrupado en SYSMonError aunque el
        // controller lo haya atrapado. No cambia la respuesta.
        report($e);

        $traceId = $this->traceIdDeError($e);

        Log::error($logMessage, array_merge($context, [
            'trace_id' => $traceId,
            'exception_class' => get_class($e),
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]));

        return response()->json([
            'success' => false,
            'message' => $clientMessage,
            'trace_id' => $traceId,
        ], $status);
    }

    /**
     * 5xx que ya reportó el handler (render central de bootstrap/app.php): mensaje genérico,
     * sin getMessage(), clase, archivo ni SQL. No vuelve a reportar.
     *
     * @param  array<string, string>  $headers
     */
    protected function serverErrorResponse(\Throwable $e, int $status = 500, array $headers = []): JsonResponse
    {
        $eventoId = $this->eventoIdDeError($e);
        $traceId = $eventoId ?? (string) Str::uuid();

        if ($eventoId === null) {
            // Sin evento en SYSMonErrorEvento (monitoreo apagado, tope diario, excepción
            // ignorada): el uuid queda en el log junto a la excepción para poder buscarlo.
            Log::error('Error de servidor en respuesta JSON', [
                'trace_id' => $traceId,
                'exception_class' => get_class($e),
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => self::MENSAJE_ERROR_SERVIDOR,
            'trace_id' => $traceId,
        ], $status, $headers);
    }

    /**
     * Id del evento de SYSMonErrorEvento que registró el report() de ESTA excepción (el código
     * de referencia de la página 500); si no se registró, un uuid.
     */
    protected function traceIdDeError(\Throwable $e): string
    {
        return $this->eventoIdDeError($e) ?? (string) Str::uuid();
    }

    private function eventoIdDeError(\Throwable $e): ?string
    {
        // Excepción => Id de evento, lo llena el callback de report() de bootstrap/app.php. No se
        // usa EstadoRequest::eventoId porque es el ÚLTIMO error de la request: si otro se
        // registró antes y este no (tope diario, clase ignorada), daría el código equivocado.
        if (! app()->bound('monitoreo.eventos_por_excepcion')) {
            return null;
        }
        /** @var WeakMap<\Throwable, int> $eventos */
        $eventos = app('monitoreo.eventos_por_excepcion');

        for ($actual = $e; $actual !== null; $actual = $actual->getPrevious()) {
            if (isset($eventos[$actual])) {
                return (string) $eventos[$actual];
            }
        }

        return null;
    }

    /**
     * Genera una respuesta JSON de error para validaciones/reglas de cliente (4xx).
     */
    protected function apiClientErrorResponse(
        string $message,
        int $status = 422,
        array $context = [],
        array $extra = []
    ): JsonResponse {
        $traceId = (string) Str::uuid();

        Log::warning($message, array_merge($context, [
            'trace_id' => $traceId,
            'status' => $status,
        ]));

        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
            'trace_id' => $traceId,
        ], $extra), $status);
    }
}
