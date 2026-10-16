<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Configuracion\EditarUsuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class EditarUsuarioLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private int $objetivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        Cache::flush();

        Schema::connection('sqlsrv')->create('SYSRoles', function (Blueprint $t) {
            $t->integer('idrol')->primary();
            $t->string('orden');
            $t->string('modulo');
            foreach (['imagen', 'Ruta', 'Nivel', 'Dependencia', 'acceso', 'crear', 'modificar', 'eliminar', 'reigstrar'] as $c) {
                $t->string($c)->nullable();
            }
            $t->timestamps();
        });
        Schema::connection('sqlsrv')->create('SYSUsuariosRoles', function (Blueprint $t) {
            $t->integer('idusuario');
            $t->integer('idrol');
            foreach (['acceso', 'crear', 'modificar', 'eliminar', 'registrar'] as $c) {
                $t->integer($c)->default(0);
            }
            $t->dateTime('assigned_at')->nullable();
        });
        $this->createTablaDbo('SysDepartamentos', ['Depto' => 'varchar(50)']);

        // Orden como texto: Trazabilidad (1000) queda entre Planeación (100) y su hijo (101).
        DB::connection('sqlsrv')->table('SYSRoles')->insert([
            ['idrol' => 1, 'orden' => '100', 'modulo' => 'Planeación', 'Nivel' => '1', 'Dependencia' => null],
            ['idrol' => 190, 'orden' => '1000', 'modulo' => 'Trazabilidad', 'Nivel' => '1', 'Dependencia' => null],
            ['idrol' => 2, 'orden' => '101', 'modulo' => 'Programa Tejido', 'Nivel' => '2', 'Dependencia' => '100'],
            ['idrol' => 8, 'orden' => '101-1', 'modulo' => 'Telares', 'Nivel' => '3', 'Dependencia' => '101'],
            ['idrol' => 59, 'orden' => '900', 'modulo' => 'Usuarios', 'Nivel' => '1', 'Dependencia' => null],
        ]);

        // Auth::id() es lo que lee userPermissions() (ver grantModulo).
        $this->actingAs($this->createUsuario(), 'web');
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insert(
            ['idusuario' => (int) auth()->id(), 'idrol' => 59, 'acceso' => 1, 'modificar' => 1]
        );

        // El modelo Usuario (el que edita la pantalla) lee dbo.SYSUsuario; el de auth, SYSUsuario.
        $this->createTablaDbo('SYSUsuario', [
            'idusuario' => 'integer primary key', 'numero_empleado' => 'varchar', 'nombre' => 'varchar',
            'contrasenia' => 'varchar', 'area' => 'varchar', 'puesto' => 'varchar', 'turno' => 'varchar',
            'telefono' => 'varchar', 'correo' => 'varchar', 'foto' => 'varchar', 'remember_token' => 'varchar',
            'created_at' => 'datetime', 'updated_at' => 'datetime',
        ]);
        $this->objetivo = 2;
        DB::connection('sqlsrv')->table('dbo.SYSUsuario')->insert(
            ['idusuario' => 2, 'numero_empleado' => '2001', 'nombre' => 'Objetivo', 'turno' => '1', 'correo' => 'x@y.com']
        );
        // Crear sin acceso: no vale, se muestra apagado.
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insert(
            ['idusuario' => $this->objetivo, 'idrol' => 190, 'acceso' => 0, 'crear' => 1]
        );
    }

    public function test_sin_permiso_de_modificar_usuarios_devuelve_403(): void
    {
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idrol', 59)->update(['modificar' => 0]);

        Livewire::test(EditarUsuario::class, ['usuarioId' => $this->objetivo])->assertForbidden();
    }

    public function test_pinta_en_orden_de_arbol_y_normaliza_lo_guardado_sin_acceso(): void
    {
        $c = Livewire::test(EditarUsuario::class, ['usuarioId' => $this->objetivo])->assertOk();

        $this->assertSame([1, 2, 8, 59, 190], $c->instance()->modulos->pluck('idrol')->map(fn ($v) => (int) $v)->all());
        $this->assertFalse($c->get('permisos')[190]['crear']);
    }

    public function test_nivel_1_arrastra_a_sus_descendientes_y_permiso_da_acceso(): void
    {
        $c = Livewire::test(EditarUsuario::class, ['usuarioId' => $this->objetivo])
            ->call('alternar', 1, 'crear');

        foreach ([1, 2, 8] as $id) {
            $this->assertTrue($c->get('permisos')[$id]['crear'], "idrol $id sin crear");
            $this->assertTrue($c->get('permisos')[$id]['acceso'], "idrol $id sin acceso");
        }
        $this->assertFalse($c->get('permisos')[190]['crear'], 'Trazabilidad no es hija de Planeación');

        $c->call('alternar', 1, 'acceso');
        $this->assertSame(array_fill_keys(EditarUsuario::CAMPOS, false), $c->get('permisos')[8]);
    }

    public function test_guardar_persiste_datos_y_permisos(): void
    {
        Livewire::test(EditarUsuario::class, ['usuarioId' => $this->objetivo])
            ->set('form.nombre', 'Nuevo Nombre')
            ->set('form.correo', '')
            ->call('alternar', 2, 'modificar')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('configuracion.usuarios.select'));

        $fila = DB::connection('sqlsrv')->table('SYSUsuariosRoles')
            ->where('idusuario', $this->objetivo)->where('idrol', 2)->first();
        $this->assertSame([1, 1, 0], [(int) $fila->acceso, (int) $fila->modificar, (int) $fila->crear]);
        $this->assertSame(5, DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idusuario', $this->objetivo)->count());
        $this->assertNull(DB::connection('sqlsrv')->table('dbo.SYSUsuario')->where('idusuario', $this->objetivo)->value('correo'));
        $this->assertSame('Nuevo Nombre', DB::connection('sqlsrv')->table('dbo.SYSUsuario')->where('idusuario', $this->objetivo)->value('nombre'));
    }
}
