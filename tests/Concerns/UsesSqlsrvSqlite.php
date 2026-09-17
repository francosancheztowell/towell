<?php

namespace Tests\Concerns;

use App\Models\Sistema\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait UsesSqlsrvSqlite
{
    protected function useSqlsrvSqlite(): void
    {
        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('auth.providers.usuarios.model', User::class);

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();
    }

    /**
     * Tabla ReqProgramaTejido con las columnas que tocan index() y balancear().
     * No son las 92: solo lo que esos dos select piden, mas Id para las reglas
     * exists:ReqProgramaTejido de los FormRequest.
     *
     * Va en la conexion por defecto, no en 'sqlsrv': el modelo no declara
     * $connection, asi que resuelve a la default (sqlite en phpunit.xml).
     */
    protected function createProgramaTejidoTable(): void
    {
        $schema = Schema::connection(config('database.default'));

        // Muestras es el mismo esquema con otro nombre: el middleware
        // ProgramaTejidoContext cambia la tabla del modelo segun la ruta.
        foreach (['ReqProgramaTejido', 'MuestrasPrograma'] as $tabla) {
            if ($schema->hasTable($tabla)) {
                continue;
            }
            $schema->create($tabla, function (Blueprint $table) {
                $table->integer('Id')->primary();
                $table->string('SalonTejidoId')->nullable();
                $table->string('NoTelarId')->nullable();
                $table->string('ItemId')->nullable();
                $table->string('NombreProducto')->nullable();
                $table->string('TamanoClave')->nullable();
                $table->string('Maquina')->nullable();
                $table->integer('Posicion')->nullable();
                $table->string('Ultimo')->nullable();
                $table->string('CambioHilo')->nullable();
                $table->string('CuentaRizo')->nullable();
                $table->string('CalibreRizo2')->nullable();
                $table->integer('EnProceso')->nullable();
                $table->string('Reprogramar')->nullable();
                $table->float('TotalPedido')->nullable();
                $table->float('PorcentajeSegundos')->nullable();
                $table->float('SaldoPedido')->nullable();
                $table->float('Produccion')->nullable();
                $table->dateTime('FechaInicio')->nullable();
                $table->dateTime('FechaFinal')->nullable();
                $table->integer('OrdCompartida')->nullable();
                $table->float('VelocidadSTD')->nullable();
                $table->float('EficienciaSTD')->nullable();
                $table->float('NoTiras')->nullable();
                $table->float('Luchaje')->nullable();
                $table->float('PesoCrudo')->nullable();
            });
        }
    }

    /**
     * Crea la tabla de un modelo derivando las columnas de su $fillable y los tipos de su
     * $casts, todo nullable menos la PK.
     *
     * Existe porque ReqProgramaTejido, ReqModelosCodificados y CatCodificados tienen 166, 144
     * y 168 columnas: enumerarlas a mano en cada test se desincroniza al primer ALTER (las
     * columnas Barra1-4 de Karl Mayer serian el primer caso). Derivarlas del modelo mantiene
     * el esquema de prueba al dia solo.
     *
     * Va en la conexion por defecto porque estos modelos no declaran $connection.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $extra  columnas que no estan en $fillable (calculadas, legacy)
     */
    protected function createTablaDesdeModelo(string $modelClass, array $extra = []): void
    {
        $modelo = new $modelClass;
        $schema = Schema::connection(config('database.default'));
        $tabla = $modelo->getTable();

        if ($schema->hasTable($tabla)) {
            return;
        }

        $pk = $modelo->getKeyName();
        $casts = $modelo->getCasts();
        $columnas = array_values(array_unique(array_merge([$pk], $modelo->getFillable(), $extra)));

        $schema->create($tabla, function (Blueprint $table) use ($columnas, $pk, $casts) {
            foreach ($columnas as $columna) {
                if ($columna === $pk) {
                    // increments() y no integer()->primary(): varios tests insertan sin pasar Id.
                    $table->increments($columna);

                    continue;
                }

                $tipo = strtok((string) ($casts[$columna] ?? 'string'), ':');

                match (true) {
                    in_array($tipo, ['int', 'integer'], true) => $table->integer($columna)->nullable(),
                    in_array($tipo, ['float', 'double', 'real', 'decimal'], true) => $table->float($columna)->nullable(),
                    in_array($tipo, ['bool', 'boolean'], true) => $table->boolean($columna)->nullable(),
                    in_array($tipo, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'], true) => $table->dateTime($columna)->nullable(),
                    default => $table->text($columna)->nullable(),
                };
            }
        });
    }

    /**
     * Adjunta un esquema sqlite llamado 'dbo' y crea una tabla dentro.
     *
     * Varias consultas escriben el prefijo de SQL Server a mano (dbo.ReqCalendarioLine,
     * dbo.SSYSFoliosSecuencias). Sqlite lo lee como nombre de esquema, no como parte del
     * nombre de la tabla, asi que basta con darle un esquema que se llame igual.
     *
     * @param  array<string, string>  $columnas  nombre => tipo sqlite
     */
    protected function createTablaDbo(string $tabla, array $columnas): void
    {
        $conexion = DB::connection(config('database.default'));

        if (! in_array('dbo', array_column($conexion->select('PRAGMA database_list'), 'name'), true)) {
            $conexion->statement("ATTACH DATABASE ':memory:' AS dbo");
        }

        $definicion = implode(', ', array_map(
            fn (string $nombre, string $tipo): string => '"'.$nombre.'" '.$tipo,
            array_keys($columnas),
            $columnas
        ));

        $conexion->statement('CREATE TABLE IF NOT EXISTS dbo."'.$tabla.'" ('.$definicion.')');
    }

    protected function createControlMermaTables(bool $includeAuthTable = false): void
    {
        // Se queda en 'sqlsrv' a proposito: EngProduccionEngomado si declara
        // $connection = 'sqlsrv', y estos tests pasan hoy. No tocar sin medir.
        $schema = Schema::connection('sqlsrv');

        $schema->create('EngProgramaEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('MaquinaEng')->nullable();
            $table->string('MaquinaUrd')->nullable();
            $table->float('MermaGoma')->nullable();
            $table->float('Merma')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaFinaliza')->nullable();
        });

        $schema->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('MaquinaId')->nullable();
        });

        $schema->create('UrdJuliosOrden', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->integer('Julios')->nullable();
            $table->string('Obs')->nullable();
            $table->integer('Hilos')->nullable();
        });

        $schema->create('UrdProduccionUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('NoJulio')->nullable();
            $table->string('CveEmpl1')->nullable();
            $table->string('NomEmpl1')->nullable();
            $table->float('Metros1')->nullable();
            $table->string('CveEmpl2')->nullable();
            $table->string('NomEmpl2')->nullable();
            $table->float('Metros2')->nullable();
            $table->string('CveEmpl3')->nullable();
            $table->string('NomEmpl3')->nullable();
            $table->float('Metros3')->nullable();
        });

        $schema->create('EngProduccionEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('NoJulio')->nullable();
            $table->string('CveEmpl1')->nullable();
            $table->string('NomEmpl1')->nullable();
            $table->float('Metros1')->nullable();
            $table->string('CveEmpl2')->nullable();
            $table->string('NomEmpl2')->nullable();
            $table->float('Metros2')->nullable();
            $table->string('CveEmpl3')->nullable();
            $table->string('NomEmpl3')->nullable();
            $table->float('Metros3')->nullable();
        });

        if ($includeAuthTable) {
            $this->createAuthTable();
        }
    }

    protected function createTejidoPromedioParosTables(bool $includeAuthTable = false): void
    {
        // Ver nota en createControlMermaTables(): TejMarcas / TejEficiencia tampoco declaran
        // $connection, por eso se creaban en 'sqlsrv' y se leian desde la default.
        $schema = Schema::connection(config('database.default'));

        $schema->create('TejMarcas', function (Blueprint $table) {
            $table->string('Folio')->primary();
            $table->date('Date')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('Status')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('nombreEmpl')->nullable();
            $table->timestamps();
        });

        $schema->create('TejMarcasLine', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Folio')->nullable();
            $table->date('Date')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->float('Eficiencia')->nullable();
            $table->float('Marcas')->nullable();
            $table->float('Trama')->nullable();
            $table->float('Pie')->nullable();
            $table->float('Rizo')->nullable();
            $table->float('Otros')->nullable();
            $table->timestamps();
        });

        $schema->create('TejEficiencia', function (Blueprint $table) {
            $table->string('Folio')->primary();
            $table->date('Date')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('Status')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('nombreEmpl')->nullable();
            $table->string('Horario1')->nullable();
            $table->string('Horario2')->nullable();
            $table->string('Horario3')->nullable();
            $table->timestamps();
        });

        $schema->create('TejEficienciaLine', function (Blueprint $table) {
            $table->increments('id');
            $table->string('Folio')->nullable();
            $table->date('Date')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->float('RpmStd')->nullable();
            $table->float('EficienciaSTD')->nullable();
            $table->float('RpmR1')->nullable();
            $table->float('EficienciaR1')->nullable();
            $table->float('RpmR2')->nullable();
            $table->float('EficienciaR2')->nullable();
            $table->float('RpmR3')->nullable();
            $table->float('EficienciaR3')->nullable();
            $table->string('ObsR1')->nullable();
            $table->string('ObsR2')->nullable();
            $table->string('ObsR3')->nullable();
            $table->string('StatusOB1')->nullable();
            $table->string('StatusOB2')->nullable();
            $table->string('StatusOB3')->nullable();
            $table->timestamps();
        });

        if ($includeAuthTable) {
            $this->createAuthTable();
        }
    }

    protected function createAuthTable(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('SYSUsuario')) {
            return;
        }

        $schema->create('SYSUsuario', function (Blueprint $table) {
            $table->increments('idusuario');
            $table->string('nombre', 150);
            $table->string('contrasenia', 255);
            $table->string('numero_empleado', 30)->nullable();
            $table->string('area', 50)->nullable();
            $table->string('foto', 300)->nullable();
            $table->string('puesto', 50)->nullable();
            $table->string('correo', 100)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->string('telefono', 12)->nullable();
            $table->string('turno', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Da de alta un módulo en SYSRoles y sus permisos para el usuario autenticado,
     * para que userCan() responda true en controladores/componentes protegidos.
     *
     * Llamar DESPUÉS de actingAs(): la clave es Auth::id(), que es lo que lee
     * userPermissions(). Llamarlo antes de la primera consulta de permisos, que
     * el helper memoiza por request.
     *
     * @param  array<int, string>  $acciones  acceso|crear|modificar|eliminar|registrar
     */
    protected function grantModulo(string $modulo, array $acciones = ['acceso'], ?int $idUsuario = null, int $idRol = 1): void
    {
        $idUsuario ??= (int) Auth::id();

        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('SYSRoles')) {
            $schema->create('SYSRoles', function (Blueprint $table) {
                $table->increments('idrol');
                $table->string('orden')->nullable();
                $table->string('modulo');
                $table->string('Dependencia')->nullable();
                $table->string('Ruta')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('SYSUsuariosRoles')) {
            $schema->create('SYSUsuariosRoles', function (Blueprint $table) {
                $table->integer('idusuario');
                $table->integer('idrol');
                $table->integer('acceso')->default(0);
                $table->integer('crear')->default(0);
                $table->integer('modificar')->default(0);
                $table->integer('eliminar')->default(0);
                $table->integer('registrar')->default(0);
            });
        }

        DB::connection('sqlsrv')->table('SYSRoles')->insert([
            'idrol' => $idRol,
            'orden' => (string) $idRol,
            'modulo' => $modulo,
        ]);

        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insert([
            'idusuario' => $idUsuario,
            'idrol' => $idRol,
            'acceso' => (int) in_array('acceso', $acciones, true),
            'crear' => (int) in_array('crear', $acciones, true),
            'modificar' => (int) in_array('modificar', $acciones, true),
            'eliminar' => (int) in_array('eliminar', $acciones, true),
            'registrar' => (int) in_array('registrar', $acciones, true),
        ]);
    }

    protected function createUsuario(array $attributes = []): User
    {
        $defaults = [
            'nombre' => 'Usuario Prueba',
            'contrasenia' => 'password',
            'numero_empleado' => '1001',
            'area' => 'Engomado',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return User::create(array_merge($defaults, $attributes));
    }
}
