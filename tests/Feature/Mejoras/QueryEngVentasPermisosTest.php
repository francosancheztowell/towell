<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Models\Sistema\Usuario;
use App\Services\ModuloService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SiembraPermisos;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Rama claude/mejora-query-ventas-traza-resto (permisos):
 *
 * - ModulosController::sincronizarPermisos: antes 2 consultas por usuario; ahora un UPDATE
 *   (solo filas de usuarios que existen) + INSERTs por lotes para los que no tienen fila.
 * - ModuloPermisosController::update: INSERT de usuarios nuevos en lotes (límite de 2100
 *   parámetros de SQL Server; sqlite no lo tiene, por eso se cuentan los bindings).
 * - UsuarioController::updatePermiso: un UPDATE y, si no tocó filas, un create; campo validado.
 * - UsuarioController::obtenerEmpleados: solo numero_empleado y nombre.
 */
final class QueryEngVentasPermisosTest extends TestCase
{
    use SiembraPermisos;
    use UsesSqlsrvSqlite;

    private const IDROL_MODULOS = 101;

    private const IDROL_USUARIOS = 59;

    private const LIMITE_PARAMETROS_SQLSRV = 2100;

    private int $idrol;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        $schema = Schema::connection('sqlsrv');

        $schema->create('SYSRoles', function (Blueprint $table) {
            $table->increments('idrol');
            $table->string('orden');
            $table->string('modulo');
            foreach (['acceso', 'crear', 'modificar', 'eliminar', 'reigstrar'] as $campo) {
                $table->integer($campo)->default(0);
            }
            $table->string('imagen')->nullable();
            $table->string('Ruta')->nullable();
            $table->integer('Nivel')->nullable();
            $table->string('Dependencia')->nullable();
            $table->timestamps();
        });
        $schema->create('SYSUsuariosRoles', function (Blueprint $table) {
            $table->integer('idusuario');
            $table->integer('idrol');
            foreach (['acceso', 'crear', 'modificar', 'eliminar', 'registrar'] as $campo) {
                $table->integer($campo)->default(0);
            }
            $table->dateTime('assigned_at')->nullable();
        });
        // Usuario apunta a dbo.SYSUsuario y SYSUsuario a SYSUsuario: en sqlite el nombre sin
        // esquema resuelve a la base adjunta 'dbo' si no existe en main, así que es una sola tabla.
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        $schema->create('dbo.SYSUsuario', function (Blueprint $table) {
            $table->increments('idusuario');
            $table->string('nombre')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('area')->nullable();
            $table->string('puesto')->nullable();
            $table->string('correo')->nullable();
            $table->string('telefono')->nullable();
            $table->string('foto')->nullable();
            $table->string('contrasenia')->nullable();
            $table->string('turno')->nullable();
            $table->timestamps();
        });

        $this->idrol = (int) DB::connection('sqlsrv')->table('SYSRoles')->insertGetId([
            'orden' => '104', 'modulo' => 'Nuevo', 'acceso' => 1, 'crear' => 0, 'modificar' => 1, 'eliminar' => 0, 'reigstrar' => 1,
        ]);
    }

    private function sembrarUsuarios(int $n): void
    {
        foreach (array_chunk(range(1, $n), 100) as $lote) {
            DB::connection('sqlsrv')->table('dbo.SYSUsuario')->insert(array_map(
                fn (int $i) => ['idusuario' => $i, 'nombre' => 'U'.$i, 'numero_empleado' => (string) (1000 + $i), 'area' => 'Tejido'],
                $lote
            ));
        }
    }

    private function editor(): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Editor de permisos']);
        $usuario->idusuario = 999301;
        $this->sembrarPermisos($usuario->idusuario, [
            self::IDROL_MODULOS => 'Modulos',
            self::IDROL_USUARIOS => 'Usuarios',
        ]);

        return $usuario;
    }

    /**
     * Ejecuta $fn y devuelve el máximo de bindings de un INSERT a SYSUsuariosRoles.
     */
    private function maxBindingsInsert(callable $fn): int
    {
        $max = 0;
        DB::connection('sqlsrv')->listen(function ($q) use (&$max) {
            if (stripos($q->sql, 'insert into') !== false && stripos($q->sql, 'SYSUsuariosRoles') !== false) {
                $max = max($max, count($q->bindings));
            }
        });
        $fn();

        return $max;
    }

    // ─── ModulosController::sincronizarPermisos ──────────────────────────

    public function test_sincronizar_actualiza_las_filas_existentes_y_crea_las_faltantes(): void
    {
        $this->sembrarUsuarios(3);
        $db = DB::connection('sqlsrv');
        // Usuario 1 ya tiene fila (con valores viejos); 2 y 3 no. 777 es una fila huérfana.
        $db->table('SYSUsuariosRoles')->insert([
            ['idusuario' => 1, 'idrol' => $this->idrol, 'acceso' => 0, 'crear' => 1, 'modificar' => 0, 'eliminar' => 1, 'registrar' => 0],
            ['idusuario' => 777, 'idrol' => $this->idrol, 'acceso' => 0, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
            // Fila de otro módulo: no se toca.
            ['idusuario' => 1, 'idrol' => 9999, 'acceso' => 0, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
        ]);

        $this->actingAs($this->editor())
            ->postJson(route('configuracion.utileria.modulos.sincronizar.permisos', $this->idrol))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('registros', 3);

        $filas = $db->table('SYSUsuariosRoles')->where('idrol', $this->idrol)->get()->keyBy('idusuario');
        foreach ([1, 2, 3] as $id) {
            $this->assertTrue($filas->has($id), "Falta la fila del usuario {$id}");
            $f = $filas[$id];
            // acceso/modificar/registrar(reigstrar) vienen de SYSRoles.
            $this->assertSame([1, 0, 1, 0, 1], array_map('intval', [$f->acceso, $f->crear, $f->modificar, $f->eliminar, $f->registrar]), "Permisos del usuario {$id}");
        }
        // Una sola fila por usuario: el UPDATE no duplicó.
        $this->assertSame(1, $db->table('SYSUsuariosRoles')->where('idrol', $this->idrol)->where('idusuario', 1)->count());
        // La huérfana no se actualiza (no tiene usuario).
        $this->assertSame(0, (int) $db->table('SYSUsuariosRoles')->where('idrol', $this->idrol)->where('idusuario', 777)->value('acceso'));
        // Otro módulo intacto.
        $this->assertSame(0, (int) $db->table('SYSUsuariosRoles')->where('idrol', 9999)->value('acceso'));
    }

    public function test_sincronizar_con_muchos_usuarios_parte_el_insert_bajo_2100_parametros(): void
    {
        $this->sembrarUsuarios(450);
        $this->actingAs($this->editor());

        $max = $this->maxBindingsInsert(function () {
            $this->postJson(route('configuracion.utileria.modulos.sincronizar.permisos', $this->idrol))
                ->assertOk()
                ->assertJsonPath('registros', 450);
        });

        $this->assertGreaterThan(0, $max);
        $this->assertLessThanOrEqual(self::LIMITE_PARAMETROS_SQLSRV, $max);
        $this->assertSame(450, DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idrol', $this->idrol)->count());

        // Idempotente: una segunda sincronización no inserta duplicados.
        $this->postJson(route('configuracion.utileria.modulos.sincronizar.permisos', $this->idrol))->assertOk();
        $this->assertSame(450, DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idrol', $this->idrol)->count());
    }

    // ─── ModuloPermisosController::update ────────────────────────────────

    public function test_permisos_en_grupo_con_500_usuarios_nuevos_no_rebasa_2100_parametros(): void
    {
        $this->sembrarUsuarios(500);
        $this->actingAs($this->editor());

        $max = $this->maxBindingsInsert(function () {
            $this->putJson(route('configuracion.utileria.modulos.permisos.update', $this->idrol), [
                'usuarios' => range(1, 500),
                'permisos' => ['acceso' => true, 'eliminar' => true],
            ])->assertOk();
        });

        $this->assertLessThanOrEqual(self::LIMITE_PARAMETROS_SQLSRV, $max);
        $db = DB::connection('sqlsrv');
        $this->assertSame(500, $db->table('SYSUsuariosRoles')->where('idrol', $this->idrol)->count());
        // Todas las filas con el mismo contenido sin importar el lote (llaves en el mismo orden).
        $this->assertSame(500, $db->table('SYSUsuariosRoles')->where('idrol', $this->idrol)
            ->where('acceso', 1)->where('eliminar', 1)->where('crear', 0)->where('modificar', 0)->where('registrar', 0)
            ->count());
    }

    // ─── UsuarioController::updatePermiso ────────────────────────────────

    public function test_update_permiso_rechaza_campos_que_no_son_permisos(): void
    {
        $this->sembrarUsuarios(1);
        $this->actingAs($this->editor());

        foreach (['idrol', 'reigstrar', 'acceso = 1, idrol', ''] as $campo) {
            $this->postJson(route('configuracion.usuarios.permisos.update', 1), [
                'idrol' => $this->idrol, 'campo' => $campo, 'valor' => true,
            ])->assertStatus(422)->assertJsonPath('success', false);
        }

        $this->assertSame(0, DB::connection('sqlsrv')->table('SYSUsuariosRoles')->count());
    }

    public function test_update_permiso_cambia_solo_ese_campo_de_la_fila_existente(): void
    {
        $this->sembrarUsuarios(1);
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insert([
            'idusuario' => 1, 'idrol' => $this->idrol, 'acceso' => 1, 'crear' => 1, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 1,
        ]);

        $this->actingAs($this->editor())
            ->postJson(route('configuracion.usuarios.permisos.update', 1), [
                'idrol' => $this->idrol, 'campo' => 'eliminar', 'valor' => true,
            ])->assertOk()->assertJsonPath('success', true);

        $filas = DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idusuario', 1)->get();
        $this->assertCount(1, $filas);
        $f = $filas->first();
        $this->assertSame([1, 1, 0, 1, 1], array_map('intval', [$f->acceso, $f->crear, $f->modificar, $f->eliminar, $f->registrar]));
        $this->assertNotNull($f->assigned_at);
    }

    public function test_update_permiso_crea_la_fila_que_falta_y_no_la_duplica_despues(): void
    {
        $this->sembrarUsuarios(1);
        $this->actingAs($this->editor());
        $url = route('configuracion.usuarios.permisos.update', 1);

        $this->postJson($url, ['idrol' => $this->idrol, 'campo' => 'registrar', 'valor' => 1])->assertOk();
        $this->postJson($url, ['idrol' => $this->idrol, 'campo' => 'acceso', 'valor' => 1])->assertOk();
        // Valor que el mismo dato ya tiene: SQL Server cuenta la fila igual; no debe crear otra.
        $this->postJson($url, ['idrol' => $this->idrol, 'campo' => 'acceso', 'valor' => 1])->assertOk();

        $filas = DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idusuario', 1)->where('idrol', $this->idrol)->get();
        $this->assertCount(1, $filas);
        $f = $filas->first();
        $this->assertSame([1, 0, 0, 0, 1], array_map('intval', [$f->acceso, $f->crear, $f->modificar, $f->eliminar, $f->registrar]));
    }

    public function test_update_permiso_limpia_la_cache_de_modulos_del_usuario(): void
    {
        $this->sembrarUsuarios(1);
        $servicio = $this->mock(ModuloService::class);
        $servicio->shouldReceive('limpiarCacheUsuario')->once()->with(1);

        $this->actingAs($this->editor())
            ->postJson(route('configuracion.usuarios.permisos.update', 1), [
                'idrol' => $this->idrol, 'campo' => 'acceso', 'valor' => true,
            ])->assertOk();
    }

    // ─── UsuarioController::obtenerEmpleados ─────────────────────────────

    public function test_obtener_empleados_devuelve_clave_y_nombre_del_area(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('dbo.SYSUsuario')->insert([
            ['idusuario' => 1, 'nombre' => 'Ana', 'numero_empleado' => '501', 'area' => 'Atadores', 'correo' => 'a@x.mx', 'telefono' => '555', 'contrasenia' => 'secreto'],
            ['idusuario' => 2, 'nombre' => 'Beto', 'numero_empleado' => '502', 'area' => 'Tejido', 'correo' => null, 'telefono' => null, 'contrasenia' => 'x'],
        ]);

        $json = $this->actingAs($this->editor())
            ->getJson(route('usuarios.obtener-empleados', 'Atadores'))
            ->assertOk()
            ->json();

        // proceso-km.ts lee numero_empleado y nombre (cveDeEmpleado / nombreDeEmpleado).
        $this->assertSame([['numero_empleado' => '501', 'nombre' => 'Ana']], $json);
    }
}
