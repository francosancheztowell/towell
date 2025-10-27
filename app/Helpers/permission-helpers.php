<?php

use App\Models\SYSUsuariosRoles;
use App\Models\SYSRoles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

if (!function_exists('userCan')) {
    /**
     * Verificar si el usuario actual tiene un permiso específico
     *
     * @param string $action - 'crear', 'modificar', 'eliminar', 'acceso', 'registrar'
     * @param string|int $module - Nombre del módulo o ID del rol
     * @return bool
     */
    function userCan(string $action, $module): bool
    {
        $userId = Auth::id();

        if (!$userId) {
            return false;
        }

        try {
            $rol = null;

            if (is_numeric($module)) {
                // Si es un ID de rol directo
                $rolId = $module;
            } else {
                // Buscar por nombre del módulo
                $rol = SYSRoles::where('modulo', $module)->first();

                if (!$rol) {
                    return false;
                }

                $rolId = $rol->idrol;
            }

            $permission = SYSUsuariosRoles::where('idusuario', $userId)
                ->where('idrol', $rolId)
                ->first();

            if (!$permission) {
                return false;
            }

            return isset($permission->$action) && $permission->$action == 1;

        } catch (\Exception $e) {
            Log::error('Error checking permission', [
                'action' => $action,
                'module' => $module,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}

if (!function_exists('userPermissions')) {
    /**
     * Obtener todos los permisos del usuario para un módulo
     *
     * @param string|int $module - Nombre del módulo o ID del rol
     * @return object|null
     */
    function userPermissions($module)
    {
        $userId = Auth::id();

        if (!$userId) {
            return null;
        }

        try {
            if (is_numeric($module)) {
                $rolId = $module;
            } else {
                $rol = SYSRoles::where('modulo', $module)->first();

                if (!$rol) {
                    return null;
                }

                $rolId = $rol->idrol;
            }

            return SYSUsuariosRoles::where('idusuario', $userId)
                ->where('idrol', $rolId)
                ->first();

        } catch (\Exception $e) {
            Log::error('Error getting user permissions', [
                'module' => $module,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }
}
