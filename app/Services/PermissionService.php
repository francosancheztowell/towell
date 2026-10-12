<?php

namespace App\Services;

use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * Guardar permisos de módulos para un usuario
     */
    public function guardarPermisos(array $permisos, int $idusuario): void
    {
        DB::transaction(function () use ($permisos, $idusuario): void {
            SYSUsuariosRoles::porUsuario($idusuario)->delete();

            foreach (SYSRoles::orderBy('orden')->get() as $modulo) {
                $prefijo = "modulo_{$modulo->idrol}_";

                SYSUsuariosRoles::create([
                    'idusuario' => $idusuario,
                    'idrol' => $modulo->idrol,
                    'acceso' => isset($permisos[$prefijo.'acceso']) ? 1 : 0,
                    'crear' => isset($permisos[$prefijo.'crear']) ? 1 : 0,
                    'modificar' => isset($permisos[$prefijo.'modificar']) ? 1 : 0,
                    'eliminar' => isset($permisos[$prefijo.'eliminar']) ? 1 : 0,
                    'registrar' => isset($permisos[$prefijo.'registrar']) ? 1 : 0,
                    'assigned_at' => now(),
                ]);
            }
        });
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
