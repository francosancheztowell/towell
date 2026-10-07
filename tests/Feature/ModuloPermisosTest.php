<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SiembraPermisos;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Gestión de módulos: permisos de todos los usuarios en un módulo, y cambio en grupo
 * (p. ej. quitar a 10 usuarios de un módulo de una vez).
 */
final class ModuloPermisosTest extends TestCase
{
    use SiembraPermisos;
    use UsesSqlsrvSqlite;

    private const IDROL_MODULOS = 101;

    private const IDROL_USUARIOS = 59;

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
            $table->string('Ruta')->nullable();
            $table->integer('Nivel')->nullable();
            $table->string('Dependencia')->nullable();
        });
        $schema->create('SYSUsuariosRoles', function (Blueprint $table) {
            $table->integer('idusuario');
            $table->integer('idrol');
            foreach (['acceso', 'crear', 'modificar', 'eliminar', 'registrar'] as $campo) {
                $table->integer($campo)->default(0);
            }
            $table->dateTime('assigned_at')->nullable();
        });
        // Usuario apunta a dbo.SYSUsuario: SQLite necesita una base adjunta llamada dbo.
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        $schema->create('dbo.SYSUsuario', function (Blueprint $table) {
            $table->increments('idusuario');
            $table->string('nombre')->nullable();
            $table->string('numero_empleado')->nullable();
            $table->string('area')->nullable();
            $table->string('puesto')->nullable();
        });

        $this->idrol = (int) DB::connection('sqlsrv')->table('SYSRoles')
            ->insertGetId(['orden' => '104', 'modulo' => 'Catálogos', 'Nivel' => 2, 'Dependencia' => '100']);

        DB::connection('sqlsrv')->table('dbo.SYSUsuario')->insert([
            ['idusuario' => 1, 'nombre' => 'Beto', 'numero_empleado' => '100', 'area' => 'Tejido'],
            ['idusuario' => 2, 'nombre' => 'Ana', 'numero_empleado' => '200', 'area' => 'Planeacion'],
            ['idusuario' => 3, 'nombre' => 'Carla', 'numero_empleado' => '300', 'area' => 'Tejido'],
        ]);
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insert([
            ['idusuario' => 1, 'idrol' => $this->idrol, 'acceso' => 1, 'crear' => 1, 'modificar' => 1, 'eliminar' => 0, 'registrar' => 0],
            ['idusuario' => 2, 'idrol' => $this->idrol, 'acceso' => 1, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
        ]);
    }

    public function test_lista_todos_los_usuarios_con_sus_permisos_del_modulo(): void
    {
        $usuarios = $this->actingAs($this->editor())
            ->getJson(route('configuracion.utileria.modulos.permisos', $this->idrol))
            ->assertOk()
            ->json('usuarios');

        $this->assertSame(['Ana', 'Beto', 'Carla'], array_column($usuarios, 'nombre'));
        $beto = $usuarios[1];
        $this->assertTrue($beto['acceso'] && $beto['crear'] && $beto['modificar']);
        $this->assertFalse($beto['eliminar']);
        // Carla no tiene fila para el módulo: sale con todo en false, no se omite.
        $this->assertFalse($usuarios[2]['acceso']);
    }

    public function test_quitar_del_modulo_a_varios_usuarios_a_la_vez(): void
    {
        $this->actingAs($this->editor())
            ->putJson(route('configuracion.utileria.modulos.permisos.update', $this->idrol), [
                'usuarios' => [1, 2],
                'permisos' => array_fill_keys(['acceso', 'crear', 'modificar', 'eliminar', 'registrar'], false),
            ])
            ->assertOk()
            ->assertJson(['actualizados' => 2]);

        $this->assertSame(0, (int) DB::connection('sqlsrv')->table('SYSUsuariosRoles')
            ->where('idrol', $this->idrol)
            ->where(fn ($q) => $q->where('acceso', 1)->orWhere('crear', 1)->orWhere('modificar', 1))
            ->count());
    }

    public function test_solo_cambia_los_campos_enviados_y_crea_la_fila_que_falta(): void
    {
        $this->actingAs($this->editor())
            ->putJson(route('configuracion.utileria.modulos.permisos.update', $this->idrol), [
                'usuarios' => [1, 3, 999],
                'permisos' => ['eliminar' => true, 'idrol' => true],
            ])
            ->assertOk()
            ->assertJson(['actualizados' => 2]);

        $filas = DB::connection('sqlsrv')->table('SYSUsuariosRoles')->get()->keyBy('idusuario');

        // Beto conserva acceso/crear/modificar y gana eliminar.
        $this->assertSame([1, 1, 1, 1], array_map('intval', [$filas[1]->acceso, $filas[1]->crear, $filas[1]->modificar, $filas[1]->eliminar]));
        // Carla no tenía fila: nace solo con eliminar.
        $this->assertSame([0, 0, 1], array_map('intval', [$filas[3]->acceso, $filas[3]->crear, $filas[3]->eliminar]));
        // El 999 no existe y 'idrol' no es un permiso: ninguno se escribe.
        $this->assertFalse($filas->has(999));
        $this->assertSame(1, $filas->where('idrol', $this->idrol)->where('idusuario', 1)->count());
    }

    public function test_rechaza_peticiones_sin_usuarios_o_sin_permisos_validos(): void
    {
        $url = route('configuracion.utileria.modulos.permisos.update', $this->idrol);
        $this->actingAs($this->editor());

        $this->putJson($url, ['usuarios' => [], 'permisos' => ['acceso' => true]])->assertStatus(422);
        $this->putJson($url, ['usuarios' => [1], 'permisos' => ['idrol' => true]])->assertStatus(422);
    }

    public function test_cambiar_permisos_exige_modificar_usuarios(): void
    {
        $usuario = new Usuario(['nombre' => 'Solo módulos']);
        $usuario->idusuario = 999302;
        $this->sembrarPermisos($usuario->idusuario, [self::IDROL_MODULOS => 'Modulos']);

        $this->actingAs($usuario)
            ->putJson(route('configuracion.utileria.modulos.permisos.update', $this->idrol), [
                'usuarios' => [1],
                'permisos' => ['acceso' => false],
            ])
            ->assertForbidden();

        $this->assertSame(1, (int) DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idusuario', 1)->value('acceso'));
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
}
