<?php

use App\Http\Middleware\AuthenticateRedboothApiKey;
use App\Http\Middleware\EnsureModulePermission;
use App\Http\Middleware\Monitoreo\AplicarCierreRemoto;
use App\Http\Middleware\Monitoreo\CapturarRespuesta5xx;
use App\Http\Middleware\Monitoreo\IdentificarDispositivo;
use App\Http\Middleware\Monitoreo\ServerTiming;
use App\Http\Middleware\NoCacheHtmlResponses;
use App\Http\Middleware\ProgramaTejidoContext;
use App\Http\Middleware\SetSqlContextInfo;
use App\Services\Monitoreo\ErrorRecorder;
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

        // Sin trustProxies (SEC-02): no hay proxy delante de Laragon, así que
        // request()->ip() es REMOTE_ADDR y un X-Forwarded-For del cliente no cuenta.
        // Si algún día se pone uno, confiar solo en su IP: trustProxies(at: ['IP']).

        // Redirecciones consistentes para guest/auth
        $middleware->redirectUsersTo('/produccionProceso');
        $middleware->redirectGuestsTo('/login');

        // Middleware para establecer contexto de SQL Server antes de queries
        // Esto permite que los triggers capturen informacion del usuario
        $middleware->web(append: [
            SetSqlContextInfo::class,
            ProgramaTejidoContext::class,
            NoCacheHtmlResponses::class,
            // Monitoreo (fase 11): identidad del dispositivo, cierre remoto por dispositivo,
            // header Server-Timing y 5xx que los catch devuelven sin pasar por el handler.
            IdentificarDispositivo::class,
            AplicarCierreRemoto::class,
            ServerTiming::class,
            CapturarRespuesta5xx::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Monitoreo (fase 11): agrupa por huella en SYSMonError. No detiene el log normal
        // (no ->stop()) y nunca lanza; ver App\Services\Monitoreo\ErrorRecorder.
        $exceptions->report(function (Throwable $e): void {
            try {
                app(ErrorRecorder::class)->capturar($e);
            } catch (Throwable) {
                // El monitoreo nunca debe impedir el reporte normal.
            }
        });

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
