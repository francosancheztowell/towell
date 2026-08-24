<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Da de alta el modulo "Catalogo Calibres" bajo Tejedores > Configurar.
 *
 * La tabla TejCatMatrizDesarrolladores solo se poblaba por migracion, asi que
 * corregir un divisor equivocado o dar de baja un hilo exigia entrar a SQL Server.
 * Ahora hay pantalla, y la pantalla necesita su renglon en SYSRoles para que el
 * menu la muestre y userCan() la pueda gobernar.
 *
 * Los permisos se copian de "Configurar" (idrol 142): quien ya administra la
 * configuracion de tejedores es quien mantiene este catalogo. Al resto se le crea
 * el renglon en ceros --como hace ModulosController al crear un modulo-- para que
 * aparezca en Gestion de Modulos y se pueda asignar sin tocar la base.
 *
 * Tras ejecutarla hay que limpiar el cache de modulos:
 *   php artisan cache:clear   (o ModuloService::limpiarCacheUsuario())
 */
return new class extends Migration
{
    private const MODULO = 'Catalogo Calibres';

    private const RUTA = '/tejedores/configurar/catalogo-calibres';

    /** "Configurar", el modulo padre del que se heredan los permisos. */
    private const ID_ROL_PADRE = 142;

    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasTable('SYSRoles')) {
            return;
        }

        if (DB::table('SYSRoles')->where('Ruta', self::RUTA)->exists()) {
            return;
        }

        // idrol es identity: se deja que SQL Server lo asigne y se lee de vuelta.
        $idRol = (int) DB::table('SYSRoles')->insertGetId([
            'orden' => '604-4',
            'modulo' => self::MODULO,
            'Dependencia' => '604',
            'Nivel' => '3',
            'Ruta' => self::RUTA,
            'acceso' => 1,
            'crear' => 1,
            'modificar' => 1,
            'eliminar' => 0,
            // El typo es de la tabla, no de aqui: en SYSRoles la columna se llama asi.
            'reigstrar' => 0,
        ], 'idrol');

        if (! Schema::connection('sqlsrv')->hasTable('SYSUsuariosRoles')) {
            return;
        }

        // Un renglon por usuario, como al crear un modulo desde Gestion de Modulos, y
        // en UNA sentencia: armar el lote en PHP y reinsertarlo tras un reintento del
        // driver choca contra la PK (idusuario, idrol).
        DB::statement(
            'INSERT INTO dbo.SYSUsuariosRoles (idusuario, idrol, acceso, crear, modificar, eliminar, registrar, assigned_at)
             SELECT u.idusuario, ?,
                    CASE WHEN p.acceso = 1 THEN 1 ELSE 0 END,
                    CASE WHEN p.acceso = 1 THEN 1 ELSE 0 END,
                    CASE WHEN p.acceso = 1 THEN 1 ELSE 0 END,
                    0, 0, GETDATE()
             FROM dbo.SYSUsuario u
             LEFT JOIN dbo.SYSUsuariosRoles p ON p.idusuario = u.idusuario AND p.idrol = ?
             WHERE NOT EXISTS (
                 SELECT 1 FROM dbo.SYSUsuariosRoles x WHERE x.idusuario = u.idusuario AND x.idrol = ?
             )',
            [$idRol, self::ID_ROL_PADRE, $idRol]
        );
    }

    public function down(): void
    {
        $idRol = DB::table('SYSRoles')->where('Ruta', self::RUTA)->value('idrol');

        if ($idRol === null) {
            return;
        }

        DB::table('SYSUsuariosRoles')->where('idrol', $idRol)->delete();
        DB::table('SYSRoles')->where('idrol', $idRol)->delete();
    }
};
