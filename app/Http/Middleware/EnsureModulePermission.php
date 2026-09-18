<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @file EnsureModulePermission.php
 *
 * @description AuthZ de ruta: exige userCan($action, $module) y aborta 403.
 *              JSON-friendly cuando el request espera JSON (expectsJson).
 *
 * @dependencies app/Helpers/permission-helpers.php (userCan)
 *
 * BUG-003 (esqueleto Planeación). Nombres SYSRoles.modulo verificados en UI:
 * - "Programa Tejido" — Liberar órdenes. UI: x-navbar.button-report → registrar.
 *   Navbar de Muestras usa el mismo string (no hay módulo "Muestras" en ese botón).
 * - "Codificación" — POST L.Mat guardar (modal en /planeacion/codificacion).
 *   No hay userCan en el botón L.Mat; mutación → modificar.
 * - "Utilería" — mover/finalizar. No hay userCan en la vista; mutación → modificar.
 *
 * No se usa moduleNameForRoute(): las URIs de mutación no coinciden con SYSRoles.Ruta
 * (p.ej. /planeacion/lmat/api/guardar vs menú /planeacion/codificacion;
 *  /planeacion/programa-tejido/liberar-ordenes/procesar vs /planeacion/programa-tejido).
 */
final class EnsureModulePermission
{
    public function handle(Request $request, Closure $next, string $action, string $module): Response
    {
        if (function_exists('userCan') && userCan($action, $module)) {
            return $next($request);
        }

        abort(403, 'No tienes permiso para esta acción.');
    }
}
