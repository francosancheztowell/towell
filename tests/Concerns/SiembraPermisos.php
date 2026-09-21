<?php

declare(strict_types=1);

namespace Tests\Concerns;

/**
 * Siembra la memoizacion de userPermissions() para un usuario de test.
 *
 * Las rutas que mutan exigen module.permission, y userPermissions() lee SYSRoles /
 * SYSUsuariosRoles, que no existen en la conexion sqlite de los tests. Sin esto, cualquier
 * test que haga POST/PUT a una ruta gateada recibe 403 en vez de su 201/422.
 *
 * Es para tests cuyo tema NO son los permisos. Los que si prueban autorizacion
 * (PlaneacionMutationAuthorizationTest, MantenimientoParosAuthorizationTest) siembran
 * a mano para poder distinguir "pasa" de "no pasa".
 */
trait SiembraPermisos
{
    /**
     * @param  array<int, string>  $modulos  [idrol => 'Nombre en SYSRoles']
     */
    protected function sembrarPermisos(int $idusuario, array $modulos): void
    {
        $roles = [];
        $permisos = [];

        foreach ($modulos as $idrol => $nombre) {
            $roles[mb_strtolower($nombre)] = (object) ['idrol' => $idrol, 'modulo' => $nombre];
            $permisos[$idrol] = (object) [
                'acceso' => 1,
                'crear' => 1,
                'modificar' => 1,
                'eliminar' => 1,
                'registrar' => 1,
            ];
        }

        app()->instance('permisos.roles', collect($roles));
        app()->instance('permisos.usuario.'.$idusuario, collect($permisos));
    }
}
