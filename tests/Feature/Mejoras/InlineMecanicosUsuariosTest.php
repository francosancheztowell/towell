<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Rama claude/mejora-ts-inline-mecanicos-usuarios.
 *
 * Usuarios: el script inline de la lista pasó a resources/js/modulos/usuarios/lista/index.ts,
 * que engancha por data-* (data-usuarios-crear, data-usuarios-filtrar, data-confirmar-eliminar);
 * el alta del modal dejó de ser fetch y es un POST normal que redirige con flash, y los
 * avisos de sesión/validación los pinta x-ui.flash en vez de <script> inline.
 *
 * Mecánicos: los botones del navbar de Estado de Máquina despachan con Alpine ($dispatch)
 * en vez de onclick, a través de <x-navbar.button-create>.
 */
final class InlineMecanicosUsuariosTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        Schema::connection('sqlsrv')->create('SYSUsuariosRoles', function (Blueprint $table) {
            $table->integer('idusuario');
            $table->integer('idrol');
            $table->integer('acceso')->default(0);
            $table->integer('crear')->default(0);
            $table->integer('modificar')->default(0);
            $table->integer('eliminar')->default(0);
            $table->integer('registrar')->default(0);
            $table->dateTime('assigned_at')->nullable();
            $table->primary(['idusuario', 'idrol']);
        });

        Schema::connection('sqlsrv')->create('SYSRoles', function (Blueprint $table) {
            $table->increments('idrol');
            $table->string('orden')->nullable();
            $table->string('modulo');
            $table->string('imagen')->nullable();
            $table->string('Dependencia')->nullable();
            $table->string('Ruta')->nullable();
            $table->integer('Nivel')->nullable();
            $table->integer('acceso')->default(1);
            $table->integer('crear')->default(1);
            $table->integer('modificar')->default(1);
            $table->integer('eliminar')->default(1);
            $table->integer('reigstrar')->default(1);
            $table->timestamps();
        });

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
            'idusuario' => 77,
            'nombre' => 'Ana Perez',
            'contrasenia' => 'x',
            'numero_empleado' => '4321',
            'area' => 'Tejido',
            'turno' => '1',
        ]);

        $this->createTablaDbo('SysDepartamentos', [
            'Depto' => 'TEXT PRIMARY KEY',
            'Descripcion' => 'TEXT',
        ]);
        DB::table('dbo.SysDepartamentos')->insert([
            ['Depto' => 'Tejido', 'Descripcion' => 'Tejido'],
            ['Depto' => 'Urdido', 'Descripcion' => 'Urdido'],
        ]);
    }

    private function actuarComoAdmin(array $acciones = ['acceso', 'crear', 'eliminar']): void
    {
        $admin = $this->createUsuario(['numero_empleado' => '1000', 'nombre' => 'Admin']);
        $this->actingAs($admin, 'web');
        $this->grantModulo('Usuarios', $acciones, idRol: 59);
    }

    /* ── Lista: ganchos que lee resources/js/modulos/usuarios/lista/index.ts ── */

    public function test_la_lista_expone_los_ganchos_data_que_usa_el_ts_y_ya_no_onclick(): void
    {
        $this->withoutVite();
        $this->actuarComoAdmin();

        $html = $this->get(route('configuracion.usuarios.select'))->assertOk()->getContent();

        // <x-navbar.button-create> debe reenviar el atributo suelto data-usuarios-crear al <button>.
        $this->assertMatchesRegularExpression('/<button[^>]*\bdata-usuarios-crear\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*\bdata-usuarios-filtrar\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*\bdata-usuarios-cerrar-crear\b[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<form[^>]*\bdata-confirmar-eliminar\b[^>]*>/', $html);

        // Ningún llamador quedó apuntando a las funciones globales que se borraron.
        foreach (['abrirModalCrearUsuario', 'cerrarModalCrearUsuario', 'abrirModalFiltros', 'confirmarEliminacion'] as $fn) {
            $this->assertStringNotContainsString($fn, $html, "Quedó un llamador de {$fn}() que ya no existe");
        }

        // El contenedor y el modal que busca el TS.
        $this->assertStringContainsString('id="usuarios-container"', $html);
        $this->assertStringContainsString('id="modalCrearUsuario"', $html);
        $this->assertStringContainsString('id="area_modal"', $html);
    }

    public function test_los_items_de_la_lista_llevan_los_data_de_filtro(): void
    {
        $this->withoutVite();
        $this->actuarComoAdmin();

        $this->get(route('configuracion.usuarios.select'))
            ->assertOk()
            ->assertSee('data-numero-empleado="4321"', false)
            ->assertSee('data-area="Tejido"', false)
            ->assertSee('data-turno="1"', false);
    }

    public function test_el_form_del_modal_es_un_post_normal_a_store_con_csrf_y_areas(): void
    {
        $this->withoutVite();
        $this->actuarComoAdmin();

        $html = $this->get(route('configuracion.usuarios.select'))->assertOk()->getContent();

        $this->assertSame(1, preg_match('/<form[^>]*id="formCrearUsuario"[^>]*>/s', $html, $m));
        $form = $m[0];
        $this->assertMatchesRegularExpression('/method="POST"/i', $form);
        $this->assertStringContainsString('action="'.route('configuracion.usuarios.store').'"', $form);

        // El POST normal necesita el _token dentro del form (antes iba en el header del fetch).
        $inicio = strpos($html, 'id="formCrearUsuario"');
        $fin = strpos($html, '</form>', (int) $inicio);
        $modal = substr($html, (int) $inicio, $fin - $inicio);
        $this->assertStringContainsString('name="_token"', $modal);

        // Las áreas vienen en la vista (antes se copiaban de la página create con un GET).
        $this->assertStringContainsString('<option value="Tejido"', $modal);
        $this->assertStringContainsString('<option value="Urdido"', $modal);
    }

    /* ── Alta: POST normal (sin Accept JSON) ── */

    public function test_alta_desde_el_modal_sigue_en_la_edicion_y_el_flash_muestra_el_exito(): void
    {
        $this->withoutVite();
        $this->actuarComoAdmin();

        $res = $this->from(route('configuracion.usuarios.select'))
            ->post(route('configuracion.usuarios.store'), [
                'numero_empleado' => '5555',
                'nombre' => 'Luis Gomez',
                'area' => 'Tejido',
                'turno' => '2',
                'contrasenia' => 'secreta123',
            ]);

        $nuevo = DB::table('dbo.SYSUsuario')->where('numero_empleado', '5555')->first();
        $this->assertNotNull($nuevo);
        $this->assertTrue(Hash::check('secreta123', $nuevo->contrasenia));

        // El alta arranca sin permisos: sigue en la edición para copiarlos y asignar departamentos.
        $res->assertRedirect(route('configuracion.usuarios.edit', $nuevo->idusuario))
            ->assertSessionHas('success', 'Usuario registrado. Asigna sus permisos y departamentos.');

        // El <script> inline de éxito se quitó: ahora lo pinta x-ui.flash del layout, una vez.
        $html = $this->get(route('configuracion.usuarios.select'))->assertOk()->getContent();
        $this->assertStringContainsString('data-ui-flash', $html);
        $this->assertSame(1, substr_count($html, 'Usuario registrado. Asigna sus permisos y departamentos.'));
    }

    public function test_alta_con_numero_repetido_vuelve_a_la_lista_y_muestra_el_error_de_validacion(): void
    {
        $this->withoutVite();
        $this->actuarComoAdmin();

        $this->from(route('configuracion.usuarios.select'))
            ->post(route('configuracion.usuarios.store'), [
                // 1000 es el admin (tabla SYSUsuario que consulta Rule::unique).
                'numero_empleado' => '1000',
                'nombre' => 'Repetido',
                'contrasenia' => 'secreta123',
            ])
            ->assertRedirect(route('configuracion.usuarios.select'))
            ->assertSessionHasErrors('numero_empleado');

        $this->assertSame(0, DB::table('dbo.SYSUsuario')->where('nombre', 'Repetido')->count());

        $html = $this->get(route('configuracion.usuarios.select'))->assertOk()->getContent();
        $this->assertStringContainsString('data-ui-flash', $html);
        $this->assertStringContainsString('Este número de empleado ya está registrado', $html);
    }

    /* ── Estado de Máquina: botones del navbar con Alpine ── */

    public function test_button_create_con_x_data_suelto_renderiza_un_x_data_valido_para_alpine(): void
    {
        $html = Blade::render(
            '<x-navbar.button-create id="btn-x" title="Filtrar" text="Filtrar" :checkPermission="false" '
            .'x-data x-on:click="$dispatch(\'verifica-maquina-abrir-filtros\')" />'
        );

        $this->assertSame(1, preg_match('/<button[^>]*>/s', $html, $m));
        $boton = $m[0];

        // x-data="x-data" sería una expresión inválida para Alpine (ReferenceError: data is not defined).
        $this->assertDoesNotMatchRegularExpression('/x-data="x-data"/', $boton);
        $this->assertMatchesRegularExpression('/\sx-data(="")?[\s>]/', $boton);
        $this->assertStringContainsString('x-on:click=', $boton);
        $this->assertStringContainsString('verifica-maquina-abrir-filtros', html_entity_decode($boton, ENT_QUOTES));
        $this->assertStringNotContainsString('onclick=', $boton);
    }

    public function test_los_eventos_que_despacha_el_navbar_tienen_listener_window_en_el_livewire(): void
    {
        $navbar = (string) file_get_contents(resource_path('views/modulos/mecanicos/estado-maquina/index.blade.php'));
        $livewire = (string) file_get_contents(resource_path('views/livewire/mecanicos/verifica-maquina/index.blade.php'));

        foreach (['verifica-maquina-abrir-filtros', 'verifica-maquina-abrir-modal'] as $evento) {
            $this->assertStringContainsString("\$dispatch('{$evento}')", $navbar);
            // $dispatch burbujea desde el botón del navbar (fuera del componente): solo .window lo recibe.
            $this->assertStringContainsString("@{$evento}.window=", $livewire);
        }
    }
}
