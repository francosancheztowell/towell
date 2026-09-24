<?php

use App\Http\Middleware\AuthenticateRedboothApiKey;
use App\Http\Middleware\EnsureModulePermission;
use App\Http\Middleware\Monitoreo\AplicarCierreRemoto;
use App\Http\Middleware\Monitoreo\IdentificarDispositivo;
use App\Http\Middleware\NoCacheHtmlResponses;
use App\Http\Middleware\ProgramaTejidoContext;
use App\Http\Middleware\SetSqlContextInfo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'redbooth.api-key' => AuthenticateRedboothApiKey::class,
            'module.permission' => EnsureModulePermission::class,
        ]);

        // Trust all proxies to work behind any proxy or load balancer
        $middleware->trustProxies(at: '*');

        // Redirecciones consistentes para guest/auth
        $middleware->redirectUsersTo('/produccionProceso');
        $middleware->redirectGuestsTo('/login');

        // Middleware para establecer contexto de SQL Server antes de queries
        // Esto permite que los triggers capturen informacion del usuario
        $middleware->web(append: [
            SetSqlContextInfo::class,
            ProgramaTejidoContext::class,
            NoCacheHtmlResponses::class,
            // Monitoreo (fase 11): identidad del dispositivo y cierre remoto por dispositivo.
            IdentificarDispositivo::class,
            AplicarCierreRemoto::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Si expira la sesion/CSRF, redirigir a login en vez de mostrar 419.
        $exceptions->render(function (TokenMismatchException $_exception, Request $request): ?Response {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'La sesion expiro. Inicia sesion nuevamente.'], 419);
            }

            return redirect()->route('login')->with('error', 'Tu sesion expiro. Inicia sesion nuevamente.');
        });

        $exceptions->render(function (NotFoundHttpException $_exception, Request $request): ?Response {
            if (! $request->hasHeader('X-Livewire')) {
                return null;
            }

            $components = $request->input('components');

            Log::warning('Livewire update returned 404', [
                'host' => $request->getHost(),
                'method' => $request->method(),
                'path' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'route_uri' => $request->route()?->uri(),
                'content_type' => $request->header('Content-Type'),
                'component_count' => is_array($components) ? count($components) : null,
            ]);

            return response()->json([
                'message' => 'No fue posible sincronizar la pantalla. Recarga la página.',
                'code' => 'livewire_endpoint_not_found',
            ], 404);
        });
    })->create();
