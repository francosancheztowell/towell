<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * "Duplicar" en la lista de usuarios: alta con No. empleado, nombre, turno y contraseña,
 * heredando permisos, área y puesto del usuario elegido.
 */
class UsuarioDuplicarTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const ORIGEN = 77;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        // grantModulo() crea SYSUsuariosRoles sin assigned_at; el alta real sí lo escribe.
        Schema::connection('sqlsrv')->create('SYSUsuariosRoles', function (Blueprint $table) {
            $table->integer('idusuario');
            $table->integer('idrol');
            $table->integer('acceso')->default(0);
            $table->integer('crear')->default(0);
            $table->integer('modificar')->default(0);
            $table->integer('eliminar')->default(0);
            $table->integer('registrar')->default(0);
            $table->dateTime('assigned_at')->nullable();
        });

        // UsuarioRepository lee App\Models\Sistema\Usuario, que apunta a dbo.SYSUsuario.
        $this->createTablaDbo('SYSUsuario', [
            'idusuario' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'nombre' => 'TEXT',
            'contrasenia' => 'TEXT',
            'numero_empleado' => 'TEXT',
            'area' => 'TEXT',
            'turno' => 'TEXT',
            'puesto' => 'TEXT',
            'telefono' => 'TEXT',
            'correo' => 'TEXT',
            'foto' => 'TEXT',
            'enviarMensaje' => 'INTEGER',
            'remember_token' => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT',
        ]);
        DB::table('dbo.SYSUsuario')->insert([
            'idusuario' => self::ORIGEN,
            'nombre' => 'Ana Perez',
            'contrasenia' => 'x',
            'numero_empleado' => '4321',
            'area' => 'Tejido',
            'puesto' => 'Supervisor',
            'telefono' => '5512345678',
            'correo' => 'ana@towell.com',
            'turno' => '1',
        ]);
        DB::table('SYSUsuariosRoles')->insert([
            ['idusuario' => self::ORIGEN, 'idrol' => 10, 'acceso' => 1, 'crear' => 1, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 1],
            ['idusuario' => self::ORIGEN, 'idrol' => 11, 'acceso' => 1, 'crear' => 0, 'modificar' => 1, 'eliminar' => 1, 'registrar' => 0],
            ['idusuario' => self::ORIGEN, 'idrol' => 12, 'acceso' => 0, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
        ]);
    }

    private function actuarComoAdmin(array $acciones = ['acceso', 'crear']): void
    {
        $admin = $this->createUsuario(['numero_empleado' => '1000', 'nombre' => 'Admin']);
        $this->actingAs($admin, 'web');
        $this->grantModulo('Usuarios', $acciones, idRol: 59);
    }

    private function duplicar(array $datos = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('configuracion.usuarios.duplicar', self::ORIGEN), array_merge([
            'numero_empleado' => '5555',
            'nombre' => 'Luis Gomez',
            'turno' => '2',
            'contrasenia' => 'secreta',
        ], $datos));
    }

    public function test_crea_el_usuario_con_los_permisos_area_y_puesto_del_origen(): void
    {
        $this->actuarComoAdmin();

        $this->duplicar()->assertCreated()->assertJson(['success' => true]);

        $nuevo = DB::table('dbo.SYSUsuario')->where('numero_empleado', '5555')->first();
        $this->assertNotNull($nuevo);
        $this->assertSame('Luis Gomez', $nuevo->nombre);
        $this->assertSame('2', $nuevo->turno);
        $this->assertSame('Tejido', $nuevo->area);
        $this->assertSame('Supervisor', $nuevo->puesto);
        $this->assertNull($nuevo->telefono, 'El teléfono es personal: no se copia.');
        $this->assertNull($nuevo->correo, 'El correo es personal: no se copia.');
        $this->assertTrue(Hash::check('secreta', $nuevo->contrasenia));

        $columnas = ['idrol', 'acceso', 'crear', 'modificar', 'eliminar', 'registrar'];
        $esperados = DB::table('SYSUsuariosRoles')->where('idusuario', self::ORIGEN)->orderBy('idrol')->get($columnas);
        $copiados = DB::table('SYSUsuariosRoles')->where('idusuario', $nuevo->idusuario)->orderBy('idrol')->get($columnas);
        $this->assertEquals($esperados, $copiados);
    }

    public function test_no_toca_los_permisos_del_origen(): void
    {
        $this->actuarComoAdmin();

        $this->duplicar()->assertCreated();

        $this->assertSame(3, DB::table('SYSUsuariosRoles')->where('idusuario', self::ORIGEN)->count());
    }

    public function test_rechaza_numero_de_empleado_repetido(): void
    {
        $this->actuarComoAdmin();

        $this->duplicar(['numero_empleado' => '1000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('numero_empleado');
    }

    public function test_exige_numero_nombre_turno_y_contrasenia(): void
    {
        $this->actuarComoAdmin();

        $this->postJson(route('configuracion.usuarios.duplicar', self::ORIGEN), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['numero_empleado', 'nombre', 'turno', 'contrasenia']);
    }

    public function test_origen_inexistente_responde_404_sin_crear_nada(): void
    {
        $this->actuarComoAdmin();

        $this->postJson(route('configuracion.usuarios.duplicar', 999), [
            'numero_empleado' => '5555',
            'nombre' => 'Luis Gomez',
            'turno' => '2',
            'contrasenia' => 'secreta',
        ])->assertNotFound();

        $this->assertSame(0, DB::table('dbo.SYSUsuario')->where('numero_empleado', '5555')->count());
    }

    public function test_sin_permiso_de_crear_no_duplica(): void
    {
        $this->actuarComoAdmin(['acceso']);

        $this->duplicar()->assertForbidden();

        $this->assertSame(0, DB::table('dbo.SYSUsuario')->where('numero_empleado', '5555')->count());
    }

    public function test_la_lista_muestra_el_boton_y_el_modal_con_permiso_de_crear(): void
    {
        $this->withoutVite();
        $this->actuarComoAdmin();

        $this->get(route('configuracion.usuarios.select'))
            ->assertOk()
            ->assertSee('data-duplicar-usuario="'.self::ORIGEN.'"', false)
            ->assertSee('id="modalDuplicarUsuario"', false);
    }

    public function test_la_lista_oculta_duplicar_sin_permiso_de_crear(): void
    {
        $this->withoutVite();
        $this->actuarComoAdmin(['acceso']);

        $this->get(route('configuracion.usuarios.select'))
            ->assertOk()
            ->assertDontSee('data-duplicar-usuario', false)
            ->assertDontSee('id="modalDuplicarUsuario"', false);
    }
}
