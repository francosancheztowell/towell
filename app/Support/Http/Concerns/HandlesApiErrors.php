<?php

namespace App\Support\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait HandlesApiErrors
{
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
        $traceId = (string) Str::uuid();

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
