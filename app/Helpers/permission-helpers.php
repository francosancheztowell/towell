<?php

use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

if (! function_exists('userCan')) {
    /**
     * Verificar si el usuario actual tiene un permiso específico
     *
     * @param  string  $action  - 'crear', 'modificar', 'eliminar', 'acceso', 'registrar'
     * @param  string|int  $module  - Nombre del módulo o ID del rol
     */
    function userCan(string $action, $module): bool
    {
        $userId = Auth::id();

        if (! $userId) {
            return false;
        }

        try {
            $permission = userPermissions($module);

            if (! $permission) {
                return false;
            }

            return isset($permission->$action) && $permission->$action == 1;

        } catch (\Exception $e) {
            Log::error('Error checking permission', [
                'action' => $action,
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

if (! function_exists('moduleNameForRoute')) {
    /**
     * Obtener el nombre del módulo en SYSRoles para una ruta.
     * Útil para validar permisos en pantallas que tienen su propio módulo (ej. Producción Urdido).
     *
     * @param  string|null  $path  Ruta a buscar (ej. 'urdido/modulo-produccion-urdido'). Si null, usa request()->path()
     * @return string|null Nombre del módulo o null si no se encuentra
     */
    function moduleNameForRoute(?string $path = null): ?string
    {
        $ruta = $path ?? request()->path();
        $rutaNormalizada = '/'.ltrim($ruta, '/');

        // 1. Buscar coincidencia exacta
        $modulo = SYSRoles::where('Ruta', $rutaNormalizada)->select('modulo')->first();
        if ($modulo) {
            return $modulo->modulo;
        }

        // 2. Buscar por prefijo (ruta más específica)
        $modulo = SYSRoles::where('Ruta', 'LIKE', $rutaNormalizada.'%')
            ->select('modulo')
            ->orderByRaw('LEN(Ruta) DESC')
            ->first();
        if ($modulo) {
            return $modulo->modulo;
        }

        // 3. Buscar por última parte de la ruta (ej. modulo-produccion-urdido)
        $partes = array_filter(explode('/', trim($rutaNormalizada, '/')));
        if (count($partes) > 0) {
            $ultimaParte = end($partes);
            $modulo = SYSRoles::where('Ruta', 'LIKE', '%'.$ultimaParte.'%')
                ->select('modulo')
                ->orderByRaw('LEN(Ruta) DESC')
                ->first();
            if ($modulo) {
                return $modulo->modulo;
            }
        }

        return null;
    }
}

if (! function_exists('userPermissions')) {
    /**
     * Obtener todos los permisos del usuario para un módulo
     *
     * @param  string|int  $module  - Nombre del módulo o ID del rol
     * @return object|null
     */
    function userPermissions($module, ?int $userId = null)
    {
        $userId = $userId ?? Auth::id();

        if (! $userId) {
            return null;
        }

        try {
            // Memoización por request: SYSRoles es un catálogo chico y cada query paga ~40ms de red,
            // así que se cargan completos una sola vez en lugar de un query por módulo consultado.
            static $rolesPorModulo = null;
            static $permisosPorRol = [];

            if ($rolesPorModulo === null) {
                // Solo idrol/modulo: evita arrastrar 'imagen' y demás columnas pesadas
                $rolesPorModulo = SYSRoles::select('idrol', 'modulo')->get()->keyBy(fn ($r) => mb_strtolower($r->modulo));
            }

            if (is_numeric($module)) {
                $rolId = (int) $module;
            } else {
                $rol = $rolesPorModulo[mb_strtolower($module)] ?? null;

                if (! $rol) {
                    return null;
                }

                $rolId = $rol->idrol;
            }

            if (! array_key_exists($userId, $permisosPorRol)) {
                $permisosPorRol[$userId] = SYSUsuariosRoles::where('idusuario', $userId)->get()->keyBy('idrol');
            }

            return $permisosPorRol[$userId][$rolId] ?? null;

        } catch (\Exception $e) {
            Log::error('Error getting user permissions', [
                'module' => $module,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
