<?php

namespace App\Services;

use App\Models\SYSRoles;
use App\Models\SYSUsuariosRoles;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * Guardar permisos de módulos para un usuario
     */
    public function guardarPermisos(array $permisos, int $idusuario): void
    {
        DB::beginTransaction();
        try {
            // Eliminar permisos anteriores
            SYSUsuariosRoles::porUsuario($idusuario)->delete();

            // Obtener todos los módulos
            $modulos = SYSRoles::orderBy('orden')->get();

            $permisosGuardados = [];
            foreach ($modulos as $modulo) {
                $prefijo = "modulo_{$modulo->idrol}_";

                // Verificar si el checkbox fue marcado (has() retorna true si existe en el request)
                $acceso = isset($permisos[$prefijo . 'acceso']) ? 1 : 0;
                $crear = isset($permisos[$prefijo . 'crear']) ? 1 : 0;
                $modificar = isset($permisos[$prefijo . 'modificar']) ? 1 : 0;
                $eliminar = isset($permisos[$prefijo . 'eliminar']) ? 1 : 0;
                $registrar = $crear; // Usar mismo valor que crear

                $permiso = SYSUsuariosRoles::create([
                    'idusuario' => $idusuario,
                    'idrol' => $modulo->idrol,
                    'acceso' => $acceso,
                    'crear' => $crear,
                    'modificar' => $modificar,
                    'eliminar' => $eliminar,
                    'registrar' => $registrar,
                    'assigned_at' => now(),
                ]);

                $permisosGuardados[] = $permiso;
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Obtener permisos de un usuario para un módulo
     */
    public function getPermisosUsuario(int $idusuario, int $idrol): ?SYSUsuariosRoles
    {
        return SYSUsuariosRoles::porUsuario($idusuario)
            ->porRol($idrol)
            ->first();
    }

    /**
     * Obtener todos los permisos de un usuario
     */
    public function getAllPermisosUsuario(int $idusuario)
    {
        return SYSUsuariosRoles::porUsuario($idusuario)
            ->with('rol')
            ->get()
            ->keyBy('idrol');
    }
}

