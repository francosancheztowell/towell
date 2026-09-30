<?php

namespace Tests\Feature\UrdEng;

use App\Http\Controllers\Engomado\Produccion\ModuloProduccionEngomadoController;
use App\Models\Engomado\CatUbicaciones;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProduccionFormulacionModel;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Producción Engomado (19-01, unidad E): validación de actualizar-campo-orden (hueco AuthZ de
 * 20-03), SEC-07, alta en bloque de renglones (PERF) y render de la vista migrada a TS.
 */
class ProduccionEngomadoTest extends TestCase
{
    use ModuloUrdEng;

    private const BASE = '/engomado/modulo-produccion-engomado';

    private const VISTA = 'resources/views/modulos/engomado/modulo-produccion-engomado.blade.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(EngProgramaEngomado::class);
        $this->tablaDe(EngProduccionEngomado::class);
        $this->tablaDe(EngProduccionFormulacionModel::class);
        $this->tablaDe(UrdProgramaUrdido::class);
        $this->tablaDe(UrdJuliosOrden::class);
        $this->tablaDe(CatUbicaciones::class);

        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 1, 'Folio' => 'U00101', 'Status' => 'En Proceso', 'NoTelas' => 2, 'MermaGoma' => null, 'Merma' => null,
        ]);
    }

    private function usuario(): \App\Models\Sistema\Usuario
    {
        return $this->usuarioCon(['Producción Engomado' => ['acceso', 'modificar'], 43 => ['acceso', 'modificar']]);
    }

    // ─── actualizar-campo-orden: lista blanca + valor numérico ───────────

    public function test_actualiza_merma_con_y_sin_goma(): void
    {
        $this->actingAs($this->usuario());

        $this->postJson(self::BASE.'/actualizar-campo-orden', ['orden_id' => 1, 'campo' => 'merma_con_goma', 'valor' => 12.5])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valor', 12.5);
        $this->postJson(self::BASE.'/actualizar-campo-orden', ['orden_id' => 1, 'campo' => 'merma_sin_goma', 'valor' => '3'])
            ->assertOk();

        $orden = DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->first();
        $this->assertEquals(12.5, $orden->MermaGoma);
        $this->assertEquals(3, $orden->Merma);

        // null limpia el campo
        $this->postJson(self::BASE.'/actualizar-campo-orden', ['orden_id' => 1, 'campo' => 'merma_sin_goma', 'valor' => null])
            ->assertOk();
        $this->assertNull(DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('Merma'));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function entradasInvalidas(): array
    {
        return [
            'campo fuera de la lista blanca' => [['campo' => 'Status', 'valor' => 1], 'campo'],
            'campo columna real pero no editable' => [['campo' => 'MermaGoma', 'valor' => 1], 'campo'],
            'valor no numérico' => [['campo' => 'merma_con_goma', 'valor' => 'abc'], 'valor'],
            'valor negativo' => [['campo' => 'merma_sin_goma', 'valor' => -1], 'valor'],
            'valor arreglo' => [['campo' => 'merma_sin_goma', 'valor' => [1]], 'valor'],
            'sin orden' => [['campo' => 'merma_sin_goma', 'valor' => 1, 'orden_id' => null], 'orden_id'],
        ];
    }

    /** @param  array<string, mixed>  $datos */
    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_entrada_invalida_con_422(array $datos, string $campoError): void
    {
        $this->actingAs($this->usuario())
            ->postJson(self::BASE.'/actualizar-campo-orden', $datos + ['orden_id' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$campoError]);

        $orden = DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->first();
        $this->assertNull($orden->MermaGoma);
        $this->assertNull($orden->Merma);
        $this->assertSame('En Proceso', $orden->Status);
    }

    public function test_orden_inexistente_es_404(): void
    {
        $this->actingAs($this->usuario())
            ->postJson(self::BASE.'/actualizar-campo-orden', ['orden_id' => 99, 'campo' => 'merma_con_goma', 'valor' => 1])
            ->assertNotFound();
    }

    public function test_la_ruta_sigue_en_modo_auditar(): void
    {
        // Sin permiso "modificar" la validación de entrada se aplica, pero la ruta no bloquea (SEC-06 pendiente).
        $this->actingAs($this->usuarioCon(['Producción Engomado' => ['acceso']]))
            ->postJson(self::BASE.'/actualizar-campo-orden', ['orden_id' => 1, 'campo' => 'merma_con_goma', 'valor' => 2])
            ->assertOk();
    }

    // ─── SEC-07 ──────────────────────────────────────────────────────────

    public function test_error_de_base_de_datos_no_filtra_sql(): void
    {
        Schema::connection('sqlsrv')->drop('EngProgramaEngomado');

        $r = $this->actingAs($this->usuario())
            ->postJson(self::BASE.'/actualizar-campo-orden', ['orden_id' => 1, 'campo' => 'merma_con_goma', 'valor' => 1])
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'trace_id']);

        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        $this->assertStringNotContainsString('EngProgramaEngomado', $r->getContent());
    }

    public function test_usuarios_sin_tabla_responde_mensaje_generico(): void
    {
        $r = $this->actingAs($this->usuario())->getJson(self::BASE.'/usuarios-engomado')
            ->assertStatus(500)
            ->assertJsonPath('message', 'Error al obtener usuarios');
        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
    }

    public function test_verificar_formulaciones_sin_folio_es_422_y_con_folio_cuenta(): void
    {
        $this->actingAs($this->usuario());
        $this->getJson(self::BASE.'/verificar-formulaciones')->assertStatus(422);

        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert(['Folio' => 'U00101']);
        $this->getJson(self::BASE.'/verificar-formulaciones?folio=U00101')
            ->assertOk()
            ->assertJsonPath('tieneFormulaciones', true);
    }

    public function test_finalizar_con_error_interno_no_filtra_el_mensaje(): void
    {
        $this->actingAs($this->usuario());
        $controller = new class extends ModuloProduccionEngomadoController
        {
            protected function ensureUserCanEdit(): void {}

            protected function traitHasNegativeKgNetoByFolio(string $folio): bool
            {
                throw new \RuntimeException('SQLSTATE[42S02] detalle interno');
            }
        };

        $r = $controller->finalizar(\Illuminate\Http\Request::create('/x', 'POST', ['orden_id' => 1]));

        $this->assertSame(500, $r->getStatusCode());
        $this->assertSame('Error al finalizar la orden', $r->getData(true)['message']);
        $this->assertStringNotContainsString('SQLSTATE', (string) $r->getContent());
    }

    // ─── PERF: alta de renglones en bloque ───────────────────────────────

    public function test_alta_de_renglones_faltantes_en_una_sola_insercion(): void
    {
        $this->actingAs($this->usuario());
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->update(['NoTelas' => 20, 'MetrajeTelas' => 6000]);
        $orden = EngProgramaEngomado::find(1);

        $controller = new class extends ModuloProduccionEngomadoController
        {
            public function sincronizar(EngProgramaEngomado $orden, int $total, mixed $solidos): void
            {
                $this->sincronizarRenglonesConNoTelas($orden, $total, $solidos);
            }
        };

        // Antes: 1 lock + 1 count + 20 INSERT (un create() por renglón) = 22 queries.
        // Después: 1 lock + 1 count + 1 INSERT en bloque = 3.
        $queries = $this->contarQueries(fn () => $controller->sincronizar($orden, 20, 10.5));
        $this->assertSame(3, $queries);

        $filas = DB::connection('sqlsrv')->table('EngProduccionEngomado')->where('Folio', 'U00101')->get();
        $this->assertCount(20, $filas);
        $fila = $filas->first();
        $this->assertEquals(10.5, $fila->Solidos);
        $this->assertSame('100', $fila->CveEmpl1);
        $this->assertSame('Usuario prueba', $fila->NomEmpl1);
        $this->assertEquals(6000, $fila->Metros1);
        $this->assertNotNull($fila->Turno1);
        $this->assertStringStartsWith(now()->format('Y-m-d'), (string) $fila->Fecha);
        $this->assertNull($fila->NoJulio);

        // Idempotente: sin diferencia no inserta nada más.
        $this->assertSame(2, $this->contarQueries(fn () => $controller->sincronizar($orden, 20, 10.5)));
        $this->assertSame(20, DB::connection('sqlsrv')->table('EngProduccionEngomado')->where('Folio', 'U00101')->count());
    }

    // ─── Vista migrada a TS ──────────────────────────────────────────────

    public function test_la_vista_no_tiene_js_inline(): void
    {
        $fuente = (string) file_get_contents(base_path(self::VISTA));

        $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|input)\s*=/i', $fuente);
        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)/i', $fuente);
        $this->assertStringNotContainsString('csrf_token()', $fuente);
        $this->assertStringNotContainsString('fetch(', $fuente);
        $this->assertStringNotContainsString('Swal.fire', $fuente);
        $this->assertStringContainsString("@vite('resources/js/modulos/engomado/produccion/index.ts')", $fuente);
    }

    public function test_render_sin_orden_y_con_orden(): void
    {
        $this->withoutVite();
        // Datos con ' y < : data-pagina='@json(...)' no debe romperse (flags JSON_HEX_*).
        $usuario = $this->usuario();
        $usuario->setAttribute('nombre', "Ana O'Brien <b>");
        $this->actingAs($usuario);
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert(['Folio' => 'U00101', 'Status' => 'Finalizado']);
        DB::connection('sqlsrv')->table('CatUbicaciones')->insert(['Codigo' => 'A1']);

        $vacio = $this->get(self::BASE)->assertOk();
        $vacio->assertSee('id="produccion-engomado"', false);
        $this->assertSame(null, $this->paginaDe($vacio->getContent())['orden']);

        $r = $this->get(self::BASE.'?orden_id=1')->assertOk();
        $html = (string) $r->getContent();
        $pagina = $this->paginaDe($html);
        $this->assertSame(['id' => 1, 'folio' => 'U00101'], $pagina['orden']);
        $this->assertEquals(2000, $pagina['maxKgBruto']);
        $this->assertTrue($pagina['puedeEditar']);
        $this->assertSame("Ana O'Brien <b>", $pagina['usuario']['nombre']);
        $this->assertStringNotContainsString("O'Brien", $html);
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringEndsWith('/engomado/modulo-produccion-engomado/actualizar-campo-orden', $pagina['rutas']['actualizarCampoOrden']);
        $this->assertSame(2, substr_count($html, 'class="hover:bg-gray-50" data-registro-id="'));
        $this->assertStringContainsString('data-accion="finalizar"', $html);
        $this->assertStringContainsString('data-accion="editar-cantidad"', $html);
        $this->assertStringContainsString('id="tpl-fila-oficial"', $html);
    }

    /** @return array<string, mixed> */
    private function paginaDe(string $html): array
    {
        $this->assertSame(1, preg_match("/id=\"produccion-engomado\" data-pagina='([^']*)'/", $html, $m));

        return json_decode(html_entity_decode($m[1]), true, flags: JSON_THROW_ON_ERROR);
    }
}
