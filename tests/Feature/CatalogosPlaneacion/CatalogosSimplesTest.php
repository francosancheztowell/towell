<?php

namespace Tests\Feature\CatalogosPlaneacion;

use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Models\Planeacion\ReqTelares;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\CatalogosPlaneacion\Concerns\CatalogosFixtures;
use Tests\TestCase;

/**
 * Caracterización (19-06b) de Telares, Aplicaciones, Matriz de Hilos y Pesos por Rollos: vista,
 * CRUD JSON y efectos sobre el programa de tejido. Se escribió ANTES de mover la lógica.
 */
class CatalogosSimplesTest extends TestCase
{
    use CatalogosFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogos();
    }

    /** @return array<string, array{string, string, string}> */
    public static function pantallas(): array
    {
        return [
            'telares' => ['/planeacion/catalogos/telares', 'catalagoTelares', 'telares'],
            'aplicaciones' => ['/planeacion/catalogos/aplicaciones', 'aplicaciones', 'aplicaciones'],
            'matriz-hilos' => ['/planeacion/catalogos/matriz-hilos', 'matriz-hilos', 'matriz-hilos'],
            'matriz-calibres' => ['/planeacion/catalogos/matrizcalibres', 'matriz-calibres', 'matriz-calibres'],
            'pesos-rollos' => ['/planeacion/catalogos/pesos-rollos', 'pesos-rollos', 'pesos-rollos'],
            'eficiencia' => ['/planeacion/catalogos/eficiencia', 'comun/estandar', 'estandar'],
            'velocidad' => ['/planeacion/catalogos/velocidad', 'comun/estandar', 'estandar'],
        ];
    }

    #[DataProvider('pantallas')]
    public function test_la_vista_monta_el_catalogo_ts_con_sus_modales(string $url, string $vista, string $bundle): void
    {
        $html = $this->get($url)->assertOk()->getContent();

        $fuente = (string) file_get_contents(resource_path("views/catalagos/{$vista}.blade.php"));
        $this->assertStringContainsString("@vite('resources/js/modulos/catalogos-planeacion/{$bundle}/index.ts')", $fuente);
        $this->assertMatchesRegularExpression('/<dialog id="formModal"/', $html);
        $this->assertMatchesRegularExpression('/<dialog id="deleteModal"/', $html);
        $config = $this->configDeVista($html);
        $this->assertNotEmpty($config['endpoint'] ?? null);
        $this->assertNotEmpty($config['campos'] ?? []);
        $this->assertStringNotContainsString('js/catalogs/', $html);
    }

    public function test_catalog_actions_sin_onclick_con_su_runtime_y_excel_solo_con_ruta(): void
    {
        $telares = $this->get('/planeacion/catalogos/telares')->assertOk()->getContent();
        $this->assertStringContainsString('data-accion-catalogo="subir-excel"', $telares);
        preg_match("/data-catalogo-acciones='([^']+)'/", $telares, $m);
        $this->assertSame(['ruta' => 'telares', 'routeJs' => 'Telares', 'excel' => '/planeacion/telares/excel'], json_decode(html_entity_decode($m[1] ?? '{}'), true));
        $this->assertStringContainsString(
            "@vite('resources/js/modulos/catalogos-planeacion/acciones/index.ts')",
            (string) file_get_contents(resource_path('views/components/buttons/catalog-actions.blade.php')),
        );

        // Pesos por Rollos no tiene ruta de Excel: antes el botón posteaba a una URL inexistente.
        $pesos = $this->get('/planeacion/catalogos/pesos-rollos')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-accion-catalogo="subir-excel"', $pesos);
        $this->assertStringContainsString('data-accion-catalogo="agregar"', $pesos);
    }

    public function test_telares_crud(): void
    {
        $this->postJson('/planeacion/telares', ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'Nombre' => '', 'Grupo' => 'A'])
            ->assertOk()->assertJson(['success' => true, 'message' => "Telar 'JAC 201' creado exitosamente"]);
        $this->postJson('/planeacion/telares', ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201'])
            ->assertStatus(422)->assertJson(['success' => false, 'message' => 'Ya existe un telar con el mismo salón y número']);

        $this->putJson('/planeacion/telares/JACQUARD_201', ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '202', 'Nombre' => 'Mío'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('Mío', ReqTelares::where('NoTelarId', '202')->value('Nombre'));
        $this->putJson('/planeacion/telares/JACQUARD_999', ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '1'])
            ->assertNotFound()->assertJson(['success' => false, 'message' => 'Telar no encontrado']);
        $this->putJson('/planeacion/telares/SINGUION', ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '1'])
            ->assertStatus(400);

        $this->deleteJson('/planeacion/telares/JACQUARD_202')->assertOk()->assertJson(['success' => true]);
        $this->assertSame(0, ReqTelares::count());
    }

    public function test_aplicaciones_crud_y_factor_recalcula_lineas(): void
    {
        $this->postJson('/planeacion/aplicaciones', ['AplicacionId' => 'BOR', 'Nombre' => 'Bordado', 'Factor' => 2])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Aplicación creada exitosamente']);
        $this->postJson('/planeacion/aplicaciones', ['AplicacionId' => 'BOR', 'Nombre' => 'Otra'])
            ->assertStatus(422)->assertJsonValidationErrors('AplicacionId');
        $id = ReqAplicaciones::value('Id');

        $p = $this->programa(['Id' => 1, 'NoTelarId' => '201', 'AplicacionId' => 'BOR']);
        $this->programa(['Id' => 2, 'NoTelarId' => '202', 'AplicacionId' => 'OTRA']);
        foreach ([[1, 10.0], [1, 0.0], [2, 10.0]] as $i => [$prog, $kilos]) {
            ReqProgramaTejidoLine::query()->insert(['Id' => $i + 1, 'ProgramaId' => $prog, 'Kilos' => $kilos, 'Aplicacion' => 5]);
        }

        $this->putJson("/planeacion/aplicaciones/{$id}", ['AplicacionId' => 'BOR', 'Nombre' => 'Bordado', 'Factor' => 1.5])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Aplicación actualizada exitosamente']);
        $this->assertEquals(15.0, ReqProgramaTejidoLine::find(1)->Aplicacion, 'Aplicacion = Factor × Kilos');
        $this->assertEquals(5, ReqProgramaTejidoLine::find(2)->Aplicacion, 'sin kilos no se toca');
        $this->assertEquals(5, ReqProgramaTejidoLine::find(3)->Aplicacion, 'otra aplicación no se toca');
        $this->assertSame(1, $p->Id);

        // En uso por un programa: no se borra.
        $this->deleteJson("/planeacion/aplicaciones/{$id}")->assertStatus(422)->assertJson(['success' => false]);
        $this->putJson('/planeacion/aplicaciones/NOEXISTE', ['AplicacionId' => 'X', 'Nombre' => 'X'])->assertNotFound();
    }

    public function test_aplicaciones_borrado_por_clave_sin_uso(): void
    {
        ReqAplicaciones::create(['AplicacionId' => 'EST', 'Nombre' => 'Estampado', 'Factor' => 1]);
        $this->deleteJson('/planeacion/aplicaciones/EST')->assertOk()->assertJson(['success' => true]);
        $this->assertSame(0, ReqAplicaciones::count());
    }

    public function test_matriz_hilos_crud_y_mts_rizo(): void
    {
        $this->postJson('/planeacion/catalogos/matriz-hilos', ['Hilo' => 'H-1', 'N1' => 10, 'N2' => 20])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Registro creado exitosamente']);
        $this->postJson('/planeacion/catalogos/matriz-hilos', ['Calibre' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['Hilo', 'Calibre']);
        $id = ReqMatrizHilos::value('Id');
        $this->getJson("/planeacion/catalogos/matriz-hilos/{$id}")->assertOk()->assertJsonPath('data.Hilo', 'H-1');

        $this->programa(['Id' => 1, 'FibraRizo' => 'H-1', 'CuentaRizo' => '100']);
        ReqProgramaTejidoLine::query()->insert([
            ['Id' => 1, 'ProgramaId' => 1, 'Rizo' => 2, 'MtsRizo' => 0],
            ['Id' => 2, 'ProgramaId' => 1, 'Rizo' => 0, 'MtsRizo' => 7],
        ]);

        // En uso: no se puede renombrar.
        $this->putJson("/planeacion/catalogos/matriz-hilos/{$id}", ['Hilo' => 'H-2', 'N1' => 10, 'N2' => 20])
            ->assertStatus(422)->assertJson(['success' => false]);

        // Cambia N1: MtsRizo = ((n1·rizo·1000/0.59/2 + n2·rizo·1000/0.59/2) / cuenta) · 1.0162
        $this->putJson("/planeacion/catalogos/matriz-hilos/{$id}", ['Hilo' => 'H-1', 'N1' => 30, 'N2' => 20])->assertOk();
        $esperado = (((30 * 2000) / 0.59 / 2 + (20 * 2000) / 0.59 / 2) / 100) * 1.0162;
        $this->assertEqualsWithDelta($esperado, (float) ReqProgramaTejidoLine::find(1)->MtsRizo, 0.0001);
        $this->assertEquals(7, ReqProgramaTejidoLine::find(2)->MtsRizo);

        $this->deleteJson("/planeacion/catalogos/matriz-hilos/{$id}")->assertStatus(422);
        $this->deleteJson('/planeacion/catalogos/matriz-hilos/999')->assertNotFound();
    }

    public function test_pesos_rollos_crud(): void
    {
        $alta = ['ItemId' => 'TOA-1', 'ItemName' => 'Toalla', 'InventSizeId' => 'GR', 'PesoRollo' => 25.5];
        $this->postJson('/planeacion/catalogos/pesos-rollos', $alta)->assertOk()->assertJson(['success' => true]);
        $this->postJson('/planeacion/catalogos/pesos-rollos', $alta)->assertStatus(422)->assertJson(['success' => false]);
        $this->postJson('/planeacion/catalogos/pesos-rollos', ['ItemId' => 'X'])->assertStatus(422)->assertJson(['success' => false]);
        $registro = ReqPesosRollosTejido::first();
        $this->assertSame('Usuario Prueba', $registro->UsuarioCrea);

        $this->putJson("/planeacion/catalogos/pesos-rollos/{$registro->Id}", ['PesoRollo' => 30] + $alta)->assertOk();
        $this->assertEquals(30, ReqPesosRollosTejido::first()->PesoRollo);
        $this->deleteJson("/planeacion/catalogos/pesos-rollos/{$registro->Id}")->assertOk()->assertJson(['success' => true]);
        // Antes: el ModelNotFound se atrapaba y salía 500 con getMessage() (SEC-07). Ahora 404.
        $this->deleteJson("/planeacion/catalogos/pesos-rollos/{$registro->Id}")->assertNotFound();
    }

    public function test_matriz_calibres_crud_y_consultas_de_lmat(): void
    {
        $salida = ['ItemId' => 'JULIO-URDIDO', 'ConfigId' => 'ALG-OPEN', 'InventSizeId' => '2028-370/1', 'InventColorId' => '1000'];
        $this->postJson('/planeacion/catalogos/matrizcalibres', ['Tipo' => 'barra2', 'Calibre' => 75, 'FibraId' => 'FIL'] + $salida)
            ->assertStatus(422)->assertJson(['success' => false])->assertJsonValidationErrors('Cuenta');
        $this->postJson('/planeacion/catalogos/matrizcalibres', ['Tipo' => 'rizo', 'Calibre' => 12, 'FibraId' => 'ALG', 'Cuenta' => '3040'] + $salida)
            ->assertOk()->assertJson(['success' => true, 'message' => 'Registro creado exitosamente'])->assertJsonPath('data.Tipo', 'RIZO');
        $id = \App\Models\Planeacion\Catalogos\CatMatrizCalibres::value('Id');

        $this->getJson('/planeacion/lmat/api/matriz-calibre?tipo=rizo&calibre=12&fibraId=ALG&cuenta=3040')
            ->assertOk()->assertJson(['success' => true, 'found' => true]);
        $this->getJson('/planeacion/lmat/api/matriz-calibre?tipo=rizo&calibre=12&fibraId=ALG')
            ->assertStatus(422)->assertJsonValidationErrors('cuenta');
        $this->postJson('/planeacion/lmat/api/matriz-calibre/lote', ['claves' => [
            ['key' => 'a', 'tipo' => 'RIZO', 'calibre' => 12, 'fibraId' => 'ALG', 'cuenta' => '3040'],
            ['key' => 'b', 'tipo' => 'TRAMA', 'calibre' => 20, 'fibraId' => 'ALG'],
        ]])->assertOk()->assertJsonPath('data.b', null)->assertJsonPath('data.a.Id', $id);

        $this->putJson("/planeacion/catalogos/matrizcalibres/{$id}", ['Tipo' => 'RIZO', 'Calibre' => 14, 'FibraId' => 'ALG', 'Cuenta' => '3040'] + $salida)->assertOk();
        $this->putJson('/planeacion/catalogos/matrizcalibres/999', ['Tipo' => 'RIZO', 'Calibre' => 14, 'FibraId' => 'ALG', 'Cuenta' => '3040'] + $salida)->assertNotFound();
        $this->deleteJson("/planeacion/catalogos/matrizcalibres/{$id}")->assertOk()->assertJson(['success' => true]);
    }
}
