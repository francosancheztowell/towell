<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Configuración de HTTPS
        $config = config('force_https', []);
        $forceHttps = $config['force_https'] ?? false;
        $environments = $config['environments'] ?? [];

        // Verificar si debemos forzar HTTPS según el entorno
        $currentEnv = app()->environment();
        $shouldForceHttps = $forceHttps || ($environments[$currentEnv] ?? false);

        // Forzar HTTPS si es necesario
        if ($shouldForceHttps && !$request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        // Configurar headers de seguridad para HTTPS
        if ($request->secure()) {
            $response = $next($request);

            // Agregar headers de seguridad
            $securityHeaders = $config['security_headers'] ?? [];
            foreach ($securityHeaders as $header => $value) {
                $response->headers->set($header, $value);
            }

            return $response;
        }

        return $next($request);
    }
}
