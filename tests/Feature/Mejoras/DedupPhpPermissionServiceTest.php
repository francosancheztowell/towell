<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Services\PermissionService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Rama claude/mejora-dedup-php: guardarPermisos pasó de beginTransaction/commit manual
 * a DB::transaction. Fija que reemplaza los permisos del usuario y que un fallo a mitad
 * deja los permisos anteriores intactos (rollback).
 */
class DedupPhpPermissionServiceTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        // En producción la conexión por defecto es sqlsrv: DB::transaction() la usa.
        config()->set('database.default', 'sqlsrv');

        Schema::connection('sqlsrv')->create('SYSRoles', function (Blueprint $t) {
            $t->integer('idrol')->primary();
            $t->integer('orden')->nullable();
            $t->string('modulo')->nullable();
            $t->timestamps();
        });
        DB::connection('sqlsrv')->table('SYSRoles')->insert([
            ['idrol' => 10, 'orden' => 2, 'modulo' => 'B'],
            ['idrol' => 20, 'orden' => 1, 'modulo' => 'A'],
        ]);
    }

    private function crearTablaPermisos(bool $conRegistrar = true): void
    {
        Schema::connection('sqlsrv')->create('SYSUsuariosRoles', function (Blueprint $t) use ($conRegistrar) {
            $t->integer('idusuario');
            $t->integer('idrol');
            $t->integer('acceso')->default(0);
            $t->integer('crear')->default(0);
            $t->integer('modificar')->default(0);
            $t->integer('eliminar')->default(0);
            if ($conRegistrar) {
                $t->integer('registrar')->default(0);
            }
            $t->dateTime('assigned_at')->nullable();
        });
    }

    /** @param  array<string, mixed>  $fila */
    private function banderas(array $fila): array
    {
        return array_map('intval', [$fila['acceso'], $fila['crear'], $fila['modificar'], $fila['eliminar'], $fila['registrar']]);
    }

    public function test_reemplaza_los_permisos_con_una_fila_por_modulo_segun_los_checkbox(): void
    {
        $this->crearTablaPermisos();
        $tabla = DB::connection('sqlsrv')->table('SYSUsuariosRoles');
        $tabla->insert(['idusuario' => 7, 'idrol' => 99, 'acceso' => 1, 'crear' => 1, 'modificar' => 1, 'eliminar' => 1, 'registrar' => 1]);
        $tabla->insert(['idusuario' => 8, 'idrol' => 10, 'acceso' => 1, 'crear' => 1, 'modificar' => 1, 'eliminar' => 1, 'registrar' => 1]);

        (new PermissionService)->guardarPermisos([
            'modulo_10_acceso' => 'on',
            'modulo_10_registrar' => '0', // isset: cualquier valor presente cuenta como marcado
            'modulo_20_crear' => 'on',
        ], 7);

        $filas = DB::connection('sqlsrv')->table('SYSUsuariosRoles')
            ->where('idusuario', 7)->orderBy('idrol')->get();

        $this->assertCount(2, $filas, 'La fila del rol 99 se borra y queda una por cada SYSRoles');
        $this->assertSame([10, 20], $filas->pluck('idrol')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([1, 0, 0, 0, 1], $this->banderas((array) $filas[0]));
        $this->assertSame([0, 1, 0, 0, 0], $this->banderas((array) $filas[1]));
        $this->assertNotNull($filas[0]->assigned_at);

        // No toca a otros usuarios.
        $this->assertSame(1, DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idusuario', 8)->count());
    }

    public function test_si_falla_un_insert_revierte_el_borrado_de_los_permisos_previos(): void
    {
        // Sin columna registrar el create() revienta después del delete().
        $this->crearTablaPermisos(false);
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insert(
            ['idusuario' => 7, 'idrol' => 10, 'acceso' => 1, 'crear' => 1, 'modificar' => 0, 'eliminar' => 0]
        );

        try {
            (new PermissionService)->guardarPermisos(['modulo_10_acceso' => 'on'], 7);
            $this->fail('Debió propagar la excepción del insert');
        } catch (QueryException) {
            // esperado
        }

        $this->assertSame(0, DB::connection('sqlsrv')->transactionLevel());
        $previos = DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idusuario', 7)->get();
        $this->assertCount(1, $previos, 'El rollback debe restaurar los permisos borrados');
        $this->assertSame(1, (int) $previos[0]->crear);
    }
}
