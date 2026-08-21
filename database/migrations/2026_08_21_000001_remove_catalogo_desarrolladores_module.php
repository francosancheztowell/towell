<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retira del menu el modulo "Catalogo Desarrolladores" (idrol 170).
 *
 * La pantalla apuntaba a la tabla cat_desarrolladores, que NO existe en ProdTowel:
 * cualquiera que la abriera recibia "Invalid object name". El modulo de
 * desarrolladores nunca la leyo; la lista sale de Usuario::porArea('Desarrolladores').
 * Al eliminarse el controlador y sus rutas, la entrada del menu quedaria apuntando
 * a una URL inexistente para los 17 usuarios que la tienen visible.
 *
 * Tras ejecutarla hay que limpiar el cache de modulos:
 *   php artisan cache:clear   (o ModuloService::limpiarCacheUsuario())
 */
return new class extends Migration
{
    private const ID_ROL = 170;

    private const MODULO = 'Catalogo Desarrolladores';

    private const RUTA = '/tejedores/configurar/catalogodesarrolladores';

    public function up(): void
    {
        DB::table('SYSUsuariosRoles')->where('idrol', self::ID_ROL)->delete();
        DB::table('SYSRoles')->where('idrol', self::ID_ROL)->delete();
    }

    /**
     * Devuelve el modulo al menu, pero sin los permisos por usuario: quien los
     * necesite se los vuelve a asignar desde Gestion de Modulos. Restaurar 93
     * renglones a ciegas seria peor que dejarlos fuera.
     */
    public function down(): void
    {
        DB::table('SYSRoles')->insert([
            'idrol' => self::ID_ROL,
            'orden' => '604-2',
            'modulo' => self::MODULO,
            'Dependencia' => '604',
            'Nivel' => '3',
            'Ruta' => self::RUTA,
        ]);
    }
};
