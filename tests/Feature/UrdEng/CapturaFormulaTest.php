<?php

namespace Tests\Feature\UrdEng;

use App\Models\Engomado\EngFormulacionLineModel;
use App\Models\Engomado\EngProduccionFormulacionModel;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Sistema\SYSUsuario;
use App\Models\Sistema\Usuario;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Support\Facades\DB;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Captura de Fórmula (19-01, unidad D): contrato de los endpoints que usa el bundle
 * resources/js/modulos/engomado/captura-formula, SEC-07, inserción en bloque de líneas (PERF)
 * y el render de la vista sin JS inline.
 */
class CapturaFormulaTest extends TestCase
{
    use ModuloUrdEng;

    private const VISTA = 'resources/views/modulos/engomado/captura-formula/index.blade.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(EngProduccionFormulacionModel::class);
        $this->tablaDe(EngFormulacionLineModel::class);
        $this->tablaDe(EngProgramaEngomado::class);
        $this->tablaDe(SYSUsuario::class);
        $this->tablaDe(URDCatalogoMaquina::class);

        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Folio' => 'E-1', 'Cuenta' => '3040', 'Calibre' => 12, 'RizoPie' => 'Pie',
            'BomFormula' => 'F-100', 'BomEng' => 'BOM-1', 'Status' => 'En Proceso',
            'MaquinaEng' => 'West Point 2', 'CveEmpl' => '1001',
        ]);
    }

    /** Usuario con todos los permisos de Captura de Formula (idrol 168, como en las rutas). */
    private function usuario(array $acciones = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar']): Usuario
    {
        $u = $this->usuarioCon([168 => $acciones]);
        DB::connection('sqlsrv')->table('SYSRoles')->where('idrol', 168)->update(['modulo' => 'Captura de Formula']);

        return $u;
    }

    /** @return array<int, array<string, mixed>> */
    private function componentes(int $n): array
    {
        return array_map(fn (int $i) => [
            'ItemId' => 'AE-0'.$i, 'ItemName' => 'Comp '.$i, 'ConfigId' => 'C'.$i,
            'ConsumoUnitario' => 0.5, 'ConsumoTotal' => 10 + $i, 'Unidad' => 'KG', 'Almacen' => 'A1',
        ], range(1, $n));
    }

    /** @return array<string, mixed> */
    private function capturaValida(int $componentes): array
    {
        return [
            'FolioProg' => 'E-1', 'Formula' => 'F-100', 'Olla' => '1', 'Kilos' => 50, 'Litros' => 200,
            'TiempoCocinado' => 30, 'Solidos' => 10.456, 'Viscocidad' => 7,
            'componentes' => json_encode($this->componentes($componentes)),
        ];
    }

    public function test_store_inserta_las_lineas_en_bloque(): void
    {
        $this->actingAs($this->usuario());

        // PERF: antes 1 INSERT por componente (10 queries con 5 componentes), ahora 1 INSERT por bloque de 233 (6).
        $n = $this->contarQueries(fn () => $this->post('/eng-formulacion', $this->capturaValida(5))
            ->assertRedirect()->assertSessionHas('success'));

        $this->assertSame(5, DB::connection('sqlsrv')->table('EngFormulacionLine')->where('EngProduccionFormulacionId', 1)->count());
        $linea = DB::connection('sqlsrv')->table('EngFormulacionLine')->orderBy('Id')->first();
        $this->assertSame('E-1', $linea->Folio);
        $this->assertSame('AE-01', $linea->ItemId);
        $this->assertSame('A1', $linea->InventLocation);
        $this->assertEquals(0.5, $linea->ConsumoUnit);
        $this->assertSame(10.46, (float) DB::connection('sqlsrv')->table('EngProduccionFormulacion')->value('Solidos'));
        $this->assertSame(self::QUERIES_STORE_5, $n);
    }

    /** Número de queries del POST con 5 componentes (incluye auth/permisos del request). */
    private const QUERIES_STORE_5 = 6; // antes 10

    public function test_store_parte_en_bloques_por_el_limite_de_parametros(): void
    {
        $this->actingAs($this->usuario());
        DB::connection('sqlsrv')->enableQueryLog();

        $this->post('/eng-formulacion', $this->capturaValida(250))->assertSessionHas('success');

        $inserts = collect(DB::connection('sqlsrv')->getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'insert into "EngFormulacionLine"'));
        // 9 columnas por fila → floor(2100 / 9) = 233 filas por INSERT → 2 sentencias para 250.
        $this->assertCount(2, $inserts);
        $this->assertSame(250, DB::connection('sqlsrv')->table('EngFormulacionLine')->count());
    }

    public function test_update_reemplaza_las_lineas_en_bloque(): void
    {
        $this->actingAs($this->usuario());
        $this->post('/eng-formulacion', $this->capturaValida(3));

        $n = $this->contarQueries(fn () => $this->put('/eng-formulacion/E-1', [
            'formulacion_id' => 1, 'Kilos' => 60, 'componentes' => json_encode($this->componentes(5)),
        ])->assertSessionHas('success'));

        $this->assertSame(5, DB::connection('sqlsrv')->table('EngFormulacionLine')->count());
        $this->assertSame(60.0, (float) DB::connection('sqlsrv')->table('EngProduccionFormulacion')->value('Kilos'));
        $this->assertSame(self::QUERIES_UPDATE_5, $n);
    }

    private const QUERIES_UPDATE_5 = 6; // antes 10

    public function test_store_rechaza_folio_finalizado(): void
    {
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Folio', 'E-1')->update(['Status' => 'Finalizado']);

        $this->actingAs($this->usuario())
            ->post('/eng-formulacion', $this->capturaValida(1))
            ->assertSessionHas('error', 'No se puede registrar una formulación para un folio finalizado: E-1');
        $this->assertSame(0, DB::connection('sqlsrv')->table('EngProduccionFormulacion')->count());
    }

    public function test_calidad_por_json(): void
    {
        $this->actingAs($this->usuario());
        $this->post('/eng-formulacion', $this->capturaValida(1));

        $this->putJson('/eng-formulacion/E-1', [
            'formulacion_id' => 1, 'obs_calidad' => 'Espuma', 'ok_tiempo' => 1, 'ok_viscocidad' => 0, 'ok_solidos' => 1,
        ])->assertOk()->assertJsonPath('success', true);

        $fila = DB::connection('sqlsrv')->table('EngProduccionFormulacion')->first();
        $this->assertSame('Espuma', $fila->obs_calidad);
        $this->assertSame(0, (int) $fila->OkViscosidad);
        // Las líneas no se tocan si no llega 'componentes'.
        $this->assertSame(1, DB::connection('sqlsrv')->table('EngFormulacionLine')->count());
    }

    public function test_by_id_devuelve_formulacion_y_componentes(): void
    {
        $this->actingAs($this->usuario());
        $this->post('/eng-formulacion', $this->capturaValida(2));

        $this->getJson('/eng-formulacion/by-id?id=1')
            ->assertOk()
            ->assertJsonPath('formulacion.Folio', 'E-1')
            ->assertJsonPath('componentes.1.ItemId', 'AE-02')
            ->assertJsonPath('componentes.1.Almacen', 'A1');
        $this->getJson('/eng-formulacion/by-id?id=99')->assertNotFound()->assertJsonPath('success', false);
    }

    /** SEC-07: un fallo de BD no llega al usuario con el texto de la excepción. */
    public function test_errores_json_no_exponen_la_excepcion(): void
    {
        $this->actingAs($this->usuario());
        DB::connection('sqlsrv')->getSchemaBuilder()->drop('EngProduccionFormulacion');
        DB::connection('sqlsrv')->getSchemaBuilder()->drop('EngFormulacionLine');

        foreach (['/eng-formulacion/by-id?id=1', '/eng-formulacion/componentes/formulacion?id=1', '/eng-formulacion/validar-folio?folio=E-1'] as $url) {
            if (str_contains($url, 'validar')) {
                DB::connection('sqlsrv')->getSchemaBuilder()->drop('EngProgramaEngomado');
            }
            $r = $this->getJson($url)->assertStatus(500)->assertJsonStructure(['message', 'trace_id']);
            $this->assertStringNotContainsString('SQLSTATE', $r->getContent(), $url);
            $this->assertStringNotContainsString('Eng', (string) $r->json('message'), $url);
            $this->assertArrayNotHasKey('trace', $r->json(), $url);
        }
    }

    /** SEC-07: los catálogos de AX (sqlsrv_ti) tampoco mandan getMessage(). */
    public function test_catalogos_ax_no_exponen_la_excepcion(): void
    {
        config()->set('database.connections.sqlsrv_ti', config('database.connections.sqlsrv'));
        DB::purge('sqlsrv_ti');
        $this->actingAs($this->usuario());

        foreach ([
            '/eng-formulacion/calibres-formula',
            '/eng-formulacion/fibras-formula?itemId=AE-014',
            '/eng-formulacion/colores-formula?itemId=AE-014',
            '/eng-formulacion/componentes/formula?formula=F-100',
        ] as $url) {
            $r = $this->getJson($url)->assertStatus(500)->assertJsonStructure(['message', 'trace_id']);
            $this->assertStringNotContainsString('SQLSTATE', $r->getContent(), $url);
            $this->assertStringNotContainsString('no such table', $r->getContent(), $url);
        }
    }

    public function test_update_json_con_error_no_expone_la_excepcion(): void
    {
        $this->actingAs($this->usuario());
        $this->post('/eng-formulacion', $this->capturaValida(1));
        DB::connection('sqlsrv')->getSchemaBuilder()->drop('EngFormulacionLine');

        $r = $this->putJson('/eng-formulacion/E-1', ['formulacion_id' => 1, 'componentes' => '[]'])
            ->assertStatus(500)->assertJsonPath('success', false)->assertJsonStructure(['message', 'trace_id']);
        $this->assertStringNotContainsString('EngFormulacionLine', $r->getContent());
    }

    public function test_redirects_con_error_no_exponen_la_excepcion(): void
    {
        $this->actingAs($this->usuario());
        $this->post('/eng-formulacion', $this->capturaValida(1));
        DB::connection('sqlsrv')->getSchemaBuilder()->drop('EngFormulacionLine');

        foreach ([
            fn () => $this->post('/eng-formulacion', $this->capturaValida(1)),
            fn () => $this->put('/eng-formulacion/E-1', ['formulacion_id' => 1, 'componentes' => '[]']),
            fn () => $this->delete('/eng-formulacion/E-1', ['formulacion_id' => 1]),
        ] as $i => $peticion) {
            $peticion()->assertRedirect()->assertSessionHas('error');
            $error = (string) session('error');
            $this->assertStringContainsString('ref:', $error, "petición $i");
            $this->assertStringNotContainsString('EngFormulacionLine', $error, "petición $i");
            $this->assertStringNotContainsString('SQLSTATE', $error, "petición $i");
        }
    }

    public function test_destroy_borra_encabezado_y_lineas_y_respeta_ax(): void
    {
        $this->actingAs($this->usuario());
        $this->post('/eng-formulacion', $this->capturaValida(2));
        $this->post('/eng-formulacion', $this->capturaValida(1));
        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->where('Id', 2)->update(['AX' => 1]);

        $this->delete('/eng-formulacion/E-1', ['formulacion_id' => 2])
            ->assertSessionHas('error', 'No se puede eliminar una formulación con AX = 1.');
        $this->delete('/eng-formulacion/E-1', ['formulacion_id' => 1])->assertSessionHas('success');

        $this->assertSame([2], DB::connection('sqlsrv')->table('EngProduccionFormulacion')->pluck('Id')->all());
        $this->assertSame(1, DB::connection('sqlsrv')->table('EngFormulacionLine')->count());
    }

    /** Filtro desde producción: Folio vacío/NULL cae a ProdId (whereRaw LTRIM/RTRIM → where/orWhere). */
    public function test_index_filtra_por_folio_con_fallback_a_prodid(): void
    {
        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert([
            ['Folio' => 'E-1', 'ProdId' => null, 'Status' => 'Creado'],
            ['Folio' => null, 'ProdId' => 'E-1', 'Status' => 'Creado'],
            ['Folio' => '', 'ProdId' => 'E-1', 'Status' => 'Creado'],
            ['Folio' => 'OTRO', 'ProdId' => 'E-1', 'Status' => 'Creado'],
            ['Folio' => null, 'ProdId' => 'E-2', 'Status' => 'Creado'],
        ]);

        $r = $this->actingAs($this->usuario())->withoutVite()->get('/engomado/capturadeformula?folio=E-1')->assertOk();

        $this->assertSame([3, 2, 1], $r->viewData('items')->pluck('Id')->all());
        $this->assertSame(['E-1'], $r->viewData('items')->pluck('folio_resuelto')->unique()->values()->all());
    }

    public function test_la_vista_carga_el_bundle_sin_js_inline(): void
    {
        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert([
            'Folio' => 'E-1', 'Formula' => 'F-100', 'Status' => 'Creado', 'obs_calidad' => "A & \"B\" O'Hara <i>", 'AX' => 0,
        ]);
        $usuario = $this->usuario();
        $usuario->setAttribute('nombre', "Ana O'Hara <b>");

        $html = $this->actingAs($usuario)->withoutVite()
            ->get('/engomado/capturadeformula?folio=E-1')->assertOk()->getContent();

        // data-pagina: JSON con JSON_HEX_* (un apóstrofo o < en los datos no rompe el atributo).
        $this->assertSame(1, preg_match("/data-pagina='([^']*)'/", $html, $m));
        $pagina = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertSame("Ana O'Hara <b>", $pagina['usuario']['nombre']);
        $this->assertSame(url('/eng-formulacion/__FOLIO__'), $pagina['rutas']['formulacion']);
        $this->assertTrue($pagina['desdeProduccion']);
        $this->assertStringContainsString('data-accion="nueva"', $html);
        // El texto de la observación llega escapado una sola vez (antes e() + {{ }} lo duplicaba).
        $this->assertStringContainsString('data-obs="A &amp; &quot;B&quot; O&#039;Hara &lt;i&gt;"', $html);

        $fuente = file_get_contents(base_path(self::VISTA));
        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $fuente);
        $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)/i', $fuente);
        $this->assertStringContainsString("@vite('resources/js/modulos/engomado/captura-formula/index.ts')", $fuente);
        $this->assertStringNotContainsString('/eng-formulacion', $fuente);
    }
}
