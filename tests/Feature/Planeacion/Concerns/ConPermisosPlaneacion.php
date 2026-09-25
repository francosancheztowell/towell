<?php

namespace Tests\Feature\Planeacion\Concerns;

use App\Models\Sistema\Usuario;

/**
 * Usuario autenticado con permisos sembrados en la memoización de userPermissions()
 * (mismo mecanismo que tests/Feature/PlaneacionMutationAuthorizationTest): no toca SQL Server.
 */
trait ConPermisosPlaneacion
{
    /**
     * @param  array<int, list<string>>  $permisos  idrol => acciones (acceso, crear, modificar, eliminar, registrar)
     */
    protected function usuarioConPermisos(array $permisos, int $id = 999100): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Usuario PT']);
        $usuario->idusuario = $id;

        $roles = collect([
            'programa tejido' => (object) ['idrol' => 2, 'modulo' => 'Programa Tejido'],
            'muestras' => (object) ['idrol' => 5, 'modulo' => 'Muestras'],
        ]);
        app()->instance('permisos.roles', $roles);

        $filas = [];
        foreach ($permisos as $idrol => $acciones) {
            $fila = ['acceso' => 0, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0];
            foreach ($acciones as $accion) {
                $fila[$accion] = 1;
            }
            $filas[$idrol] = (object) $fila;
        }
        app()->instance('permisos.usuario.'.$id, collect($filas));

        return $usuario;
    }
}
