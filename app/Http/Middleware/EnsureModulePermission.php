<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Monitoreo\AccesoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Niega mutaciones si el usuario autenticado no tiene userCan($action, $module).
 *
 * No sustituye al helper de permisos: solo lo aplica en el router.
 * $module es el idrol de SYSRoles o su nombre exacto (SYSRoles.modulo).
 *
 * Modo auditar (SEC-05): `module.permission:crear,123,auditar` no bloquea. Si el usuario
 * no tendría permiso, registra SYSMonAcceso Tipo=authz_denegaria (una fila por
 * usuario+ruta+acción por hora) y deja pasar. Sirve para medir antes de pasar a enforce.
 *
 * @see userCan()
 */
final class EnsureModulePermission
{
    public const MODO_AUDITAR = 'auditar';

    /** Ventana de deduplicación del registro en modo auditar (segundos). */
    public const DEDUPLICAR_SEGUNDOS = 3600;

    public function handle(Request $request, Closure $next, string $action, string $module, ?string $modo = null): Response
    {
        $action = trim($action);
        $module = trim($module);

        if ($action !== '' && $module !== '' && userCan($action, $module)) {
            return $next($request);
        }

        if ($modo !== null && trim($modo) === self::MODO_AUDITAR) {
            $this->registrarDenegacion($request, $action, $module);

            return $next($request);
        }

        $message = 'No tienes permiso para esta acción.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }

    private function registrarDenegacion(Request $request, string $action, string $module): void
    {
        try {
            $usuario = Auth::user();
            $ruta = $request->route()?->getName() ?? $request->route()?->uri() ?? $request->path();
            $llave = 'authz_auditar:'.sha1(implode('|', [Auth::id() ?? 'anonimo', $request->method(), $ruta, $action, $module]));

            if (! Cache::add($llave, 1, self::DEDUPLICAR_SEGUNDOS)) {
                return;
            }

            app(AccesoService::class)->registrar('authz_denegaria', [
                'UsuarioId' => Auth::id() !== null ? (int) Auth::id() : null,
                'NumeroEmpleado' => data_get($usuario, 'numero_empleado'),
                'Motivo' => $action.' · '.$module.' · '.$request->method().' '.$ruta,
            ], $request);
        } catch (Throwable) {
            // Auditar nunca bloquea: si falla la caché o el registro, la request sigue.
        }
    }
}
