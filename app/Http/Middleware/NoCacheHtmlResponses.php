<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoCacheHtmlResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        $isLoginLikeRoute = $request->routeIs('login', 'home')
            || $request->is('/')
            || $request->is('login');

        // Mantener no-store estricto en login para evitar persistencia de credenciales.
        // Para el resto de HTML, usar no-cache privado para permitir bfcache del navegador.
        if ($isLoginLikeRoute) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        } else {
            $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate, max-age=0');
        }
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
