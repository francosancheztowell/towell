<?php

declare(strict_types=1);

namespace Tests\Feature\Ux;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Fase 17-02 (UX global): lo que el layout garantiza en TODAS las pantallas.
 * UX-01 flash, UX-02 title, UX-03 un solo h1, UX-04 zoom, UX-05 selección,
 * HANDOFF 18-01 #1 (towell-ruta sin nombre de ruta).
 */
class LayoutGlobalTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        $this->crearTablasModulos();

        View::addNamespace('uxtest', __DIR__.'/vistas');
        foreach (['con-page-title', 'titulo-plano', 'con-title', 'sin-titulo', 'andon'] as $vista) {
            Route::middleware(['web', 'auth'])->get('/ux-prueba/'.$vista.'/{id?}', fn () => view('uxtest::'.$vista));
        }
        Route::getRoutes()->refreshNameLookups();
    }

    private function crearTablasModulos(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->create('SYSRoles', function (Blueprint $table) {
            $table->integer('idrol')->primary();
            $table->string('orden');
            $table->string('modulo');
            $table->string('imagen')->nullable();
            $table->string('Ruta')->nullable();
            $table->integer('Nivel');
            $table->string('Dependencia')->nullable();
        });
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

    private function pagina(string $url): TestResponse
    {
        return $this->actingAs($this->createUsuario(), 'web')->get($url)->assertOk();
    }

    private function titulo(TestResponse $respuesta): string
    {
        preg_match('/<title>(.*?)<\/title>/s', (string) $respuesta->getContent(), $m);

        return html_entity_decode(trim($m[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function contarH1(TestResponse $respuesta): int
    {
        return preg_match_all('/<h1[\s>]/i', (string) $respuesta->getContent());
    }

    public function test_title_sale_del_page_title_y_hay_un_solo_h1(): void
    {
        $respuesta = $this->pagina('/ux-prueba/con-page-title');

        $this->assertSame('Inventario & Telas · Towell', $this->titulo($respuesta));
        $this->assertSame(1, $this->contarH1($respuesta), 'navbar + x-layout.page-title no deben anidar dos <h1>');
    }

    public function test_page_title_de_texto_plano_lo_envuelve_el_h1_del_navbar(): void
    {
        $respuesta = $this->pagina('/ux-prueba/titulo-plano');

        $this->assertSame('Órdenes de trabajo · Towell', $this->titulo($respuesta));
        $this->assertSame(1, $this->contarH1($respuesta));
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Órdenes de trabajo\s*<\/h1>/u', (string) $respuesta->getContent());
    }

    public function test_section_title_manda_sobre_page_title(): void
    {
        $this->assertSame('Catálogo de Calibres · Towell', $this->titulo($this->pagina('/ux-prueba/con-title')));
    }

    public function test_sin_titulo_usa_el_modulo_de_la_ruta_o_el_default(): void
    {
        $this->assertSame('Producción Towell', $this->titulo($this->pagina('/ux-prueba/sin-titulo')));

        DB::connection('sqlsrv')->table('SYSRoles')->insert([
            'idrol' => 9, 'orden' => '900', 'modulo' => 'Pantalla de Prueba', 'Ruta' => '/ux-prueba/sin-titulo', 'Nivel' => 1,
        ]);
        olvidarModulosPorRuta();

        $this->assertSame('Pantalla de Prueba · Towell', $this->titulo($this->pagina('/ux-prueba/sin-titulo')));
    }

    public function test_pinch_zoom_habilitado_salvo_andon(): void
    {
        $normal = $this->pagina('/ux-prueba/sin-titulo');
        $normal->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">', false);
        $normal->assertDontSee('user-scalable=no', false);

        $this->pagina('/ux-prueba/andon')->assertSee('user-scalable=no', false);
    }

    public function test_el_body_no_bloquea_la_seleccion_de_datos(): void
    {
        $html = (string) $this->pagina('/ux-prueba/con-page-title')->getContent();

        preg_match('/<body[^>]*>/', $html, $body);
        $this->assertStringNotContainsString('user-select', $body[0]);
        $this->assertStringNotContainsString('touch-callout', $body[0]);
        $this->assertStringContainsString('F-00123', $html);
    }

    public function test_towell_ruta_usa_la_plantilla_de_uri_si_la_ruta_no_tiene_nombre(): void
    {
        $this->pagina('/ux-prueba/sin-titulo/123')
            ->assertSee('<meta name="towell-ruta" content="ux-prueba/sin-titulo/{id?}">', false)
            ->assertDontSee('content="ux-prueba/sin-titulo/123"', false);
    }

    public function test_el_home_muestra_el_flash_de_error_del_layout(): void
    {
        $this->actingAs($this->createUsuario(), 'web')
            ->withSession(['error' => 'No tienes acceso a este módulo.'])
            ->get('/produccionProceso')
            ->assertOk()
            ->assertSee('data-ui-flash', false)
            ->assertSee('No tienes acceso a este módulo.');
    }

    public function test_el_home_sin_modulos_muestra_estado_vacio(): void
    {
        $this->pagina('/produccionProceso')
            ->assertSee('No tienes módulos asignados')
            ->assertSee('<title>Producción en Proceso · Towell</title>', false);
    }

    public function test_el_home_con_modulos_pinta_la_cuadricula(): void
    {
        $usuario = $this->createUsuario();
        DB::connection('sqlsrv')->table('SYSRoles')->insert([
            'idrol' => 1, 'orden' => '100', 'modulo' => 'Planeación', 'Ruta' => '/planeacion', 'Nivel' => 1,
        ]);
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->insert([
            'idusuario' => $usuario->idusuario, 'idrol' => 1, 'acceso' => 1,
        ]);

        $this->actingAs($usuario, 'web')->get('/produccionProceso')
            ->assertOk()
            ->assertSee('Planeación')
            ->assertDontSee('No tienes módulos asignados');
    }

    public function test_programa_tejido_recibe_los_datos_del_modal_de_dias_sin_script_inline(): void
    {
        Route::middleware(['web', 'auth'])->get('/planeacion/muestras', fn () => view('uxtest::sin-titulo'));

        $html = (string) $this->actingAs($this->createUsuario(), 'web')
            ->withSession(['liberar_ordenes_dias' => 7.5])
            ->get('/planeacion/muestras')
            ->getContent();

        $this->assertStringContainsString('id="navbar-dias-liberar"', $html);
        $this->assertStringContainsString('data-dias="7.5"', $html);
        $this->assertStringContainsString('data-base="/planeacion/muestras"', $html);
        $this->assertStringNotContainsString('function mostrarModalDiasLiberar', $html);
    }
}
