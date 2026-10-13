<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marca las descargas (Content-Disposition: attachment) con la cookie `towell_descarga`.
 *
 * Al hacer clic en un enlace, app-core.js muestra el loader global y lo quita `pageshow`; una
 * descarga no cambia de página, así que el loader se quedaba girando para siempre (Excel de todos
 * los reportes). componentes/loader.ts vigila esta cookie mientras está visible y se oculta en
 * cuanto cambia: cuando el servidor terminó de armar el archivo.
 */
class MarcarDescarga
{
    public const COOKIE = 'towell_descarga';

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (str_starts_with(strtolower((string) $response->headers->get('Content-Disposition', '')), 'attachment')) {
            // Legible desde JS (httpOnly false) y sin cifrar (excepción en bootstrap/app.php): no es dato sensible.
            $response->headers->setCookie(Cookie::create(self::COOKIE, (string) hrtime(true), time() + 60, '/', null, null, false, false, 'lax'));
        }

        return $response;
    }
}
