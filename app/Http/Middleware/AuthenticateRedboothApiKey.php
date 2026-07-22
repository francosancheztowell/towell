<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateRedboothApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('redbooth.external_api_key', '');
        $providedKey = $request->bearerToken() ?: $request->header('X-API-Key', '');

        if ($configuredKey === '' || blank(config('redbooth.external_user_id'))) {
            return new JsonResponse([
                'message' => 'La API externa de Redbooth no está configurada.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (! is_string($providedKey)
            || $providedKey === ''
            || ! hash_equals($configuredKey, $providedKey)) {
            return new JsonResponse([
                'message' => 'API key inválida.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
