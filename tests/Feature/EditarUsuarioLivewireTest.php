<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Configuracion\EditarUsuario;
use App\Models\Sistema\Usuario;
use App\Services\UsuarioService;
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

    private int $objetivo = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        Cache::flush();

        $schema = Schema::connection('sqlsrv');
        $schema->create('SYSRoles', function (Blueprint $t) {
            $t->integer('idrol')->primary();
            $t->string('orden');
            $t->string('modulo');
            foreach (['imagen', 'Ruta', 'Nivel', 'Dependencia', 'acceso', 'crear', 'modificar', 'eliminar', 'reigstrar'] as $c) {
                $t->string($c)->nullable();
            }
            $t->timestamps();
        });
        $schema->create('SYSUsuariosRoles', function (Blueprint $t) {
            $t->integer('idusuario');
            $t->integer('idrol');
            foreach (['acceso', 'crear', 'modificar', 'eliminar', 'registrar'] as $c) {
                $t->integer($c)->default(0);
            }
            $t->dateTime('assigned_at')->nullable();
        });
        $schema->create('URDCatalogoMaquinas', function (Blueprint $t) {
            $t->string('MaquinaId')->primary();
            $t->string('Nombre')->nullable();
            $t->string('Departamento');
            $t->integer('Secuencia')->nullable();
        });
        $schema->create('SYSMaquinasAsignacion', function (Blueprint $t) {
            $t->increments('Id');
            $t->integer('idusuario');
            $t->string('MaquinaId');
            $t->boolean('Supervisor')->default(false);
        });
        $this->createTablaDbo('SysDepartamentos', ['Depto' => 'varchar(50)']);
        // El modelo Usuario (el que edita la pantalla) lee dbo.SYSUsuario; el de auth, SYSUsuario.
        $this->createTablaDbo('SYSUsuario', [
            'idusuario' => 'integer primary key', 'numero_empleado' => 'varchar', 'nombre' => 'varchar',
            'contrasenia' => 'varchar', 'area' => 'varchar', 'puesto' => 'varchar', 'turno' => 'varchar',
            'telefono' => 'varchar', 'correo' => 'varchar', 'foto' => 'varchar', 'remember_token' => 'varchar',
            'created_at' => 'datetime', 'updated_at' => 'datetime',
        ]);

        $db = DB::connection('sqlsrv');
        // Orden como texto: Trazabilidad (1000) queda entre Planeación (100) y su hijo (101).
        $db->table('SYSRoles')->insert([
            ['idrol' => 1, 'orden' => '100', 'modulo' => 'Planeación', 'Nivel' => '1', 'Dependencia' => null],
            ['idrol' => 190, 'orden' => '1000', 'modulo' => 'Trazabilidad', 'Nivel' => '1', 'Dependencia' => null],
            ['idrol' => 2, 'orden' => '101', 'modulo' => 'Programa Tejido', 'Nivel' => '2', 'Dependencia' => '100'],
            ['idrol' => 8, 'orden' => '101-1', 'modulo' => 'Telares', 'Nivel' => '3', 'Dependencia' => '101'],
            ['idrol' => 59, 'orden' => '900', 'modulo' => 'Usuarios', 'Nivel' => '1', 'Dependencia' => null],
        ]);
        $db->table('URDCatalogoMaquinas')->insert([
            ['MaquinaId' => '201', 'Departamento' => 'Jacquard'],
            ['MaquinaId' => '202', 'Departamento' => 'Jacquard'],
            ['MaquinaId' => 'C-1', 'Departamento' => 'Costura'],
        ]);

        // Auth::id() es lo que lee userPermissions() (ver grantModulo).
        $this->actingAs($this->createUsuario(), 'web');
        $db->table('SYSUsuariosRoles')->insert(['idusuario' => (int) auth()->id(), 'idrol' => 59, 'acceso' => 1, 'modificar' => 1]);

        $db->table('dbo.SYSUsuario')->insert([
            'idusuario' => $this->objetivo, 'numero_empleado' => '2001', 'nombre' => 'Objetivo', 'turno' => '1',
            'puesto' => 'Mecanico', 'correo' => 'x@y.com', 'telefono' => '2221112233',
        ]);
        // Crear sin acceso: no vale, se muestra apagado.
        $db->table('SYSUsuariosRoles')->insert(['idusuario' => $this->objetivo, 'idrol' => 190, 'acceso' => 0, 'crear' => 1]);
        $db->table('SYSMaquinasAsignacion')->insert(['idusuario' => $this->objetivo, 'MaquinaId' => 'C-1']);
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

    public function test_puesto_guardado_se_lleva_al_de_la_lista(): void
    {
        Livewire::test(EditarUsuario::class, ['usuarioId' => $this->objetivo])
            ->assertSet('form.puesto', 'Mecánico');
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

    public function test_casilla_de_departamento_marca_y_desmarca_todas_sus_maquinas(): void
    {
        $c = Livewire::test(EditarUsuario::class, ['usuarioId' => $this->objetivo])
            ->call('alternarDepartamento', 'Jacquard');
        $this->assertEqualsCanonicalizing(['C-1', '201', '202'], $c->get('maquinas'));

        $c->call('alternarDepartamento', 'Jacquard');
        $this->assertSame(['C-1'], $c->get('maquinas'));
    }

    public function test_copiar_de_trae_permisos_maquinas_y_supervisor_sin_guardar(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('dbo.SYSUsuario')->insert(['idusuario' => 3, 'numero_empleado' => '3001', 'nombre' => 'Modelo']);
        $db->table('SYSUsuariosRoles')->insert(['idusuario' => 3, 'idrol' => 2, 'acceso' => 1, 'crear' => 1]);
        $db->table('SYSMaquinasAsignacion')->insert([
            ['idusuario' => 3, 'MaquinaId' => '201', 'Supervisor' => 1],
            ['idusuario' => 3, 'MaquinaId' => '202', 'Supervisor' => 1],
        ]);

        $c = Livewire::test(EditarUsuario::class, ['usuarioId' => $this->objetivo])->call('copiarDe', 3);

        $this->assertTrue($c->get('permisos')[2]['crear']);
        $this->assertEqualsCanonicalizing(['201', '202'], $c->get('maquinas'));
        $this->assertTrue($c->get('supervisor'));
        $this->assertSame(1, $db->table('SYSMaquinasAsignacion')->where('idusuario', $this->objetivo)->count());
    }

    public function test_guardar_persiste_datos_permisos_y_maquinas_sin_borrar_telefono_ni_correo(): void
    {
        Livewire::test(EditarUsuario::class, ['usuarioId' => $this->objetivo])
            ->set('form.nombre', 'Nuevo Nombre')
            ->call('alternar', 2, 'modificar')
            ->set('maquinas', ['201'])
            ->set('supervisor', true)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('configuracion.usuarios.select'));

        $db = DB::connection('sqlsrv');
        $fila = $db->table('SYSUsuariosRoles')->where('idusuario', $this->objetivo)->where('idrol', 2)->first();
        $this->assertSame([1, 1, 0], [(int) $fila->acceso, (int) $fila->modificar, (int) $fila->crear]);
        $this->assertSame(5, $db->table('SYSUsuariosRoles')->where('idusuario', $this->objetivo)->count());

        $usuario = $db->table('dbo.SYSUsuario')->where('idusuario', $this->objetivo)->first();
        $this->assertSame(['Nuevo Nombre', 'Mecánico', 'x@y.com', '2221112233'], [$usuario->nombre, $usuario->puesto, $usuario->correo, $usuario->telefono]);

        $maquinas = $db->table('SYSMaquinasAsignacion')->where('idusuario', $this->objetivo)->get();
        $this->assertSame([['201', 1]], $maquinas->map(fn ($m) => [$m->MaquinaId, (int) $m->Supervisor])->all());
    }

    public function test_maquina_fuera_del_catalogo_no_se_guarda(): void
    {
        Livewire::test(EditarUsuario::class, ['usuarioId' => $this->objetivo])
            ->set('maquinas', ['NO-EXISTE'])
            ->call('guardar')
            ->assertHasErrors('maquinas.0');
    }

    public function test_duplicar_copia_maquinas_y_supervisor(): void
    {
        DB::connection('sqlsrv')->table('SYSMaquinasAsignacion')->where('idusuario', $this->objetivo)->update(['Supervisor' => 1]);

        $nuevo = app(UsuarioService::class)->duplicar(Usuario::findOrFail($this->objetivo), [
            'numero_empleado' => '4001', 'nombre' => 'Copia', 'turno' => '2', 'contrasenia' => 'secreta',
        ]);

        $copiadas = DB::connection('sqlsrv')->table('SYSMaquinasAsignacion')->where('idusuario', $nuevo->idusuario)->get();
        $this->assertSame([['C-1', 1]], $copiadas->map(fn ($m) => [$m->MaquinaId, (int) $m->Supervisor])->all());
    }
}
