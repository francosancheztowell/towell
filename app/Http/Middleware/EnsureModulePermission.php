<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Niega mutaciones si el usuario autenticado no tiene userCan($action, $module).
 *
 * No sustituye al helper de permisos: solo lo aplica en el router.
 * Los nombres de módulo deben coincidir con SYSRoles.modulo (menú).
 *
 * @see userCan()
 */
final class EnsureModulePermission
{
    public function handle(Request $request, Closure $next, string $action, string $module): Response
    {
        $action = trim($action);
        $module = trim($module);

        if ($action !== '' && $module !== '' && userCan($action, $module)) {
            return $next($request);
        }

        $message = 'No tienes permiso para esta acción.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}
