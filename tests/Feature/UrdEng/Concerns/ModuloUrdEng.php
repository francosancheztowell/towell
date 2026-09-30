<?php

namespace Tests\Feature\UrdEng\Concerns;

use App\Models\Sistema\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Base de los tests de Urdido/Engomado (19-01): 'sqlsrv' y la conexión por defecto en el mismo
 * sqlite en memoria, tablas derivadas del modelo y permisos por módulo en SYSRoles/SYSUsuariosRoles.
 */
trait ModuloUrdEng
{
    protected function prepararSqlite(): void
    {
        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'sqlsrv');
        DB::purge('sqlsrv');

        // Funciones de SQL Server que usan algunas consultas del módulo.
        $pdo = DB::connection('sqlsrv')->getPdo();
        $pdo->sqliteCreateFunction('ISNUMERIC', fn ($v) => is_numeric($v) ? 1 : 0, 1);
        $pdo->sqliteCreateFunction('LTRIM', fn ($v) => $v === null ? null : ltrim((string) $v), 1);
        $pdo->sqliteCreateFunction('RTRIM', fn ($v) => $v === null ? null : rtrim((string) $v), 1);

        foreach (['SYSRoles' => 'idrol', 'SYSUsuariosRoles' => null] as $tabla => $pk) {
            Schema::connection('sqlsrv')->create($tabla, function (Blueprint $t) use ($pk): void {
                $pk ? $t->integer($pk)->primary() : $t->integer('idrol');
                $t->integer('idusuario')->nullable();
                $t->string('modulo')->nullable();
                foreach (['acceso', 'crear', 'modificar', 'eliminar', 'registrar', 'reigstrar'] as $p) {
                    $t->integer($p)->default(0);
                }
            });
        }
    }

    /**
     * Tabla del modelo con sus $fillable + $casts (todo nullable, PK autoincremental).
     *
     * @param  class-string<Model>  $modelo
     * @param  array<int, string>  $extra
     */
    protected function tablaDe(string $modelo, array $extra = []): void
    {
        $m = new $modelo;
        $tabla = preg_replace('/^dbo\./i', '', $m->getTable());
        $pk = $m->getKeyName();
        $casts = $m->getCasts();
        $columnas = array_values(array_unique(array_merge([$pk], $m->getFillable(), array_keys($casts), $extra)));

        Schema::connection('sqlsrv')->create($tabla, function (Blueprint $t) use ($columnas, $pk, $casts, $m): void {
            foreach ($columnas as $c) {
                if ($c === $pk) {
                    $t->increments($c);

                    continue;
                }
                $tipo = strtok((string) ($casts[$c] ?? 'string'), ':');
                match (true) {
                    in_array($tipo, ['int', 'integer', 'bool', 'boolean'], true) => $t->integer($c)->nullable(),
                    in_array($tipo, ['float', 'double', 'real', 'decimal'], true) => $t->float($c)->nullable(),
                    in_array($tipo, ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp'], true) => $t->dateTime($c)->nullable(),
                    default => $t->text($c)->nullable(),
                };
            }
            if ($m->usesTimestamps()) {
                $t->timestamps();
            }
        });
    }

    /**
     * Usuario 10 con permisos por módulo: ['Producción Engomado' => ['acceso', 'modificar'], 43 => [...]].
     *
     * @param  array<string|int, array<int, string>>  $permisos
     */
    protected function usuarioCon(array $permisos, string $area = 'Engomado'): Usuario
    {
        $idrol = 500;
        foreach ($permisos as $modulo => $acciones) {
            $id = is_int($modulo) ? $modulo : $idrol++;
            $fila = array_fill_keys(['acceso', 'crear', 'modificar', 'eliminar'], 0);
            foreach ($acciones as $a) {
                $fila[$a === 'registrar' ? 'reigstrar' : $a] = 1;
            }
            DB::connection('sqlsrv')->table('SYSRoles')->insert($fila + ['idrol' => $id, 'modulo' => is_int($modulo) ? 'rol '.$id : $modulo]);
            DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insert(
                ['idrol' => $id, 'idusuario' => 10, 'registrar' => (int) in_array('registrar', $acciones, true)]
                + array_intersect_key($fila, array_flip(['acceso', 'crear', 'modificar', 'eliminar']))
            );
        }

        $usuario = new Usuario(['numero_empleado' => '100', 'nombre' => 'Usuario prueba', 'area' => $area]);
        $usuario->idusuario = 10;
        $usuario->exists = true;

        return $usuario;
    }

    /** Número de queries que ejecuta $fn (PERF: número antes/después). */
    protected function contarQueries(callable $fn): int
    {
        $db = DB::connection('sqlsrv');
        $db->flushQueryLog();
        $db->enableQueryLog();
        $fn();
        $n = count($db->getQueryLog());
        $db->disableQueryLog();

        return $n;
    }
}
