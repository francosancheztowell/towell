<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Cobertura de comportamiento de LiberarOrdenesController::liberar().
 *
 * Verifica la PERSISTENCIA del flujo transaccional de liberación:
 *  - Caso feliz con noProduccion manual (evita FolioHelper): fórmulas Excel
 *    (Repeticiones = TRUNC((PesoRollo/PesoCrudo)/NoTiras × 1000), PzasRollo = Rep × Tiras,
 *    TotalRollos = CEIL(SaldoPedido/PzasRollo), TotalPzas = PzasRollo × TotalRollos,
 *    SaldoMarbete = TotalRollos) quedan en ReqProgramaTejido y sincronizadas a CatCodificados.
 *  - Folio duplicado dentro del lote: 422 y rollback total.
 *  - Métricas inválidas (NoTiras = 0): 422 con mensaje de tiras y rollback.
 *
 * Notas de diseño:
 *  - DB::commit() ocurre ANTES de generar el Excel (OrdenDeCambioFelpaController), por lo que
 *    la persistencia se asevera contra BD sin importar si el paso de Excel devuelve el archivo
 *    (200 + fileData) o un error controlado (500 JSON de generarExcelDesdeBD, que atrapa sus
 *    propias excepciones y nunca revienta la transacción ya commiteada).
 *  - Se envía bomId/bomName en el request para no tocar la conexión externa sqlsrv_ti.
 */
class LiberarOrdenesLiberarTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        $schema = Schema::connection('sqlsrv');

        // Esquema mínimo con TODAS las columnas que liberar() asigna/lee
        // (incluye auditoría, porque AuditoriaHelper consulta Schema::getColumnListing).
        $schema->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('Prioridad')->nullable();
            $table->date('Programado')->nullable();
            $table->dateTime('FechaInicio')->nullable();
            $table->dateTime('FechaFinal')->nullable();
            $table->float('SaldoPedido')->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('PesoCrudo')->nullable();
            $table->integer('NoTiras')->nullable();
            $table->integer('LargoCrudo')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('NoExisteBase')->nullable();
            $table->float('Ancho')->nullable();
            $table->float('Repeticiones')->nullable();
            $table->integer('SaldoMarbete')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->float('RollosProgramados')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->float('Densidad')->nullable();
            $table->string('BomId')->nullable();
            $table->string('BomName')->nullable();
            $table->string('HiloAX')->nullable();
            $table->string('CombinaTram')->nullable();
            $table->string('Observaciones')->nullable();
            $table->string('CambioHilo')->nullable();
            $table->boolean('CreaProd')->nullable();
            $table->boolean('ActualizaLmat')->nullable();
            $table->float('EficienciaSTD')->nullable();
            $table->string('CategoriaCalidad')->nullable();
            $table->string('CustName')->nullable();
            $table->float('PesoMuestra')->nullable();
            $table->integer('OrdPrincipal')->nullable();
            $table->integer('OrdCompartida')->nullable();
            $table->boolean('OrdCompartidaLider')->nullable();
            $table->date('FechaCreacion')->nullable();
            $table->string('HoraCreacion')->nullable();
            $table->string('UsuarioCrea')->nullable();
            $table->date('FechaModificacion')->nullable();
            $table->string('HoraModificacion')->nullable();
            $table->string('UsuarioModifica')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
            $table->integer('Posicion')->nullable();
            $table->boolean('EnProceso')->default(false);
            $table->string('Ultimo')->nullable();
        });

        // Tabla real del modelo ReqPesosRollosTejido (ojo: singular "Rollo") — fuente
        // del peso rollo maestro cuando el request no manda pesoRollo.
        $schema->create('ReqPesosRolloTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->float('PesoRollo')->nullable();
            $table->date('FechaCreacion')->nullable();
            $table->date('FechaModificacion')->nullable();
        });

        // Columnas que liberar() consulta (validación de unicidad, resolverCodigoDibujo) y
        // sincroniza vía actualizarCatCodificados (payload filtrado por getColumnListing).
        $schema->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('Departamento')->nullable();
            $table->string('CodigoDibujo')->nullable();
            $table->string('BomId')->nullable();
            $table->string('BomName')->nullable();
            $table->string('HiloAX')->nullable();
            $table->float('MtsRollo')->nullable();
            $table->float('PzasRollo')->nullable();
            $table->float('TotalRollos')->nullable();
            $table->float('TotalPzas')->nullable();
            $table->integer('Repeticiones')->nullable();
            $table->integer('NoTiras')->nullable();
            $table->float('NoMarbete')->nullable();
            $table->string('CombinaTram')->nullable();
            $table->string('CambioRepaso')->nullable();
            $table->float('Densidad')->nullable();
            $table->string('Obs5')->nullable();
            $table->boolean('CreaProd')->nullable();
            $table->boolean('ActualizaLmat')->nullable();
            $table->string('CategoriaCalidad')->nullable();
            $table->string('CustName')->nullable();
            $table->float('PesoMuestra')->nullable();
            $table->integer('OrdPrincipal')->nullable();
            $table->integer('OrdCompartida')->nullable();
            $table->integer('OrdCompartidaLider')->nullable();
            $table->date('FechaCreacion')->nullable();
            $table->string('HoraCreacion')->nullable();
            $table->string('UsuarioCrea')->nullable();
            $table->date('FechaModificacion')->nullable();
            $table->string('HoraModificacion')->nullable();
            $table->string('UsuarioModifica')->nullable();
        });

        // Consultada por actualizarReqModelosCodificados (y por el paso de Excel post-commit).
        $schema->create('ReqModelosCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->dateTime('FechaTejido')->nullable();
            $table->float('PesoMuestra')->nullable();
            $table->integer('OrdPrincipal')->nullable();
        });

        // El observer de ReqProgramaTejido puede intentar regenerar líneas diarias.
        $schema->create('ReqProgramaTejidoLine', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('ProgramaId')->nullable();
            $table->date('Fecha')->nullable();
            $table->float('Cantidad')->nullable();
            $table->float('Kilos')->nullable();
            $table->float('Aplicacion')->nullable();
            $table->float('Trama')->nullable();
            $table->float('Combina1')->nullable();
            $table->float('Combina2')->nullable();
            $table->float('Combina3')->nullable();
            $table->float('Combina4')->nullable();
            $table->float('Combina5')->nullable();
            $table->float('Pie')->nullable();
            $table->float('Rizo')->nullable();
            $table->float('MtsRizo')->nullable();
            $table->float('MtsPie')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $schema = Schema::connection('sqlsrv');
        foreach (['ReqProgramaTejidoLine', 'ReqModelosCodificados', 'CatCodificados', 'ReqPesosRolloTejido', 'ReqProgramaTejido'] as $tabla) {
            $schema->dropIfExists($tabla);
        }
        parent::tearDown();
    }

    /**
     * Inserta un registro base listo para liberar (vía query builder: sin observers).
     */
    private function sembrarRegistro(array $overrides = []): int
    {
        return DB::connection('sqlsrv')->table('ReqProgramaTejido')->insertGetId(array_merge([
            'NoProduccion' => null,
            'SalonTejidoId' => 'JACQUARD',
            'NoTelarId' => '201',
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'TamanoClave' => 'MB-ARIA',
            'NombreProducto' => 'MB-ARIA SC',
            'PesoCrudo' => 455,
            'NoTiras' => 3,
            'LargoCrudo' => 142,
            'SaldoPedido' => 12891,
            'TotalPedido' => 12891,
            'CustName' => 'CLIENTE X',
            'FechaInicio' => Carbon::now()->subDay()->format('Y-m-d H:i:s'),
            'EnProceso' => 0,
        ], $overrides));
    }

    private function liberar(array $registros)
    {
        $request = Request::create('/planeacion/liberar-ordenes/liberar', 'POST', [
            'registros' => $registros,
        ]);

        return (new LiberarOrdenesController)->liberar($request);
    }

    public function test_liberar_exitoso_con_no_produccion_manual_persiste_formulas_y_sincroniza_cat_codificados(): void
    {
        $id = $this->sembrarRegistro();

        // Peso rollo maestro = 41 kg para el InventSizeId del registro.
        DB::connection('sqlsrv')->table('ReqPesosRolloTejido')->insert([
            'InventSizeId' => 'STD',
            'PesoRollo' => 41,
            'FechaModificacion' => '2026-01-01',
        ]);

        // Fila preexistente en CatCodificados para el mismo telar: liberar() debe actualizarla.
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '77001',
            'TelarId' => '201',
            'ItemId' => 'IT100',
            'InventSizeId' => 'STD',
            'Departamento' => 'JACQUARD',
        ]);

        $response = $this->liberar([
            [
                'id' => $id,
                'bomId' => 'BOM-CRUDO-01',
                'bomName' => 'LISTA MATERIALES CRUDO 01',
                'noProduccion' => '77001',
                'cambioRepaso' => 'NO',
            ],
        ]);

        // El paso de Excel (post-commit) genera el archivo real desde la plantilla
        // ordfelpa.xlsx del repo y responde JSON success con el binario en base64.
        // Nota: aunque el Excel fallara (500 controlado de generarExcelDesdeBD), el
        // commit ya ocurrió — las aserciones de BD de abajo son las que protegen el negocio.
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['fileData']);

        $registro = DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->first();

        // Folio manual asignado (FolioHelper NO interviene).
        $this->assertSame('77001', $registro->NoProduccion);

        // Repeticiones = TRUNC((41 / 455) / 3 × 1000) = 30
        $this->assertSame(30.0, (float) $registro->Repeticiones);

        // PzasRollo = Repeticiones × NoTiras = 30 × 3 = 90
        $this->assertSame(90.0, (float) $registro->PzasRollo);

        // TotalRollos = CEIL(12891 / 90) = 144
        $this->assertSame(144.0, (float) $registro->TotalRollos);

        // TotalPzas = PzasRollo × TotalRollos = 90 × 144 = 12960
        $this->assertSame(12960.0, (float) $registro->TotalPzas);

        // Invariante: TotalPzas == PzasRollo × TotalRollos
        $this->assertSame(
            (float) $registro->TotalPzas,
            (float) $registro->PzasRollo * (float) $registro->TotalRollos
        );

        // SaldoMarbete / NoMarbete / RollosProgramados = TotalRollos
        $this->assertSame(144, (int) $registro->SaldoMarbete);
        $this->assertSame(144.0, (float) $registro->NoMarbete);
        $this->assertSame(144.0, (float) $registro->RollosProgramados);

        // MtsRollo = (LargoCrudo × Repeticiones) / 100 = 142 × 30 / 100 = 42.6
        $this->assertEqualsWithDelta(42.6, (float) $registro->MtsRollo, 0.001);

        // Campos del request y flags de liberación
        $this->assertSame('BOM-CRUDO-01', $registro->BomId);
        $this->assertSame('LISTA MATERIALES CRUDO 01', $registro->BomName);
        $this->assertSame('NO', $registro->CambioHilo);
        $this->assertSame(1, (int) $registro->CreaProd);

        // Auditoría sin usuario autenticado → 'Sistema'
        $this->assertSame('Sistema', $registro->UsuarioModifica);

        // CatCodificados sincronizado (misma orden + mismo telar)
        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->first();
        $this->assertNotNull($cat);
        $this->assertSame(144.0, (float) $cat->NoMarbete);
        $this->assertSame(144.0, (float) $cat->TotalRollos);
        $this->assertSame(12960.0, (float) $cat->TotalPzas);
        $this->assertSame(90.0, (float) $cat->PzasRollo);
        $this->assertSame(30, (int) $cat->Repeticiones);
        $this->assertSame(3, (int) $cat->NoTiras);
        $this->assertSame('BOM-CRUDO-01', $cat->BomId);
        $this->assertSame('LISTA MATERIALES CRUDO 01', $cat->BomName);
        $this->assertSame('NO', $cat->CambioRepaso);
        $this->assertSame('CLIENTE X', $cat->CustName);
    }

    public function test_folio_manual_duplicado_en_el_lote_devuelve_422_y_no_persiste_nada(): void
    {
        $idA = $this->sembrarRegistro(['NoTelarId' => '201']);
        $idB = $this->sembrarRegistro(['NoTelarId' => '202']);

        DB::connection('sqlsrv')->table('ReqPesosRolloTejido')->insert([
            'InventSizeId' => 'STD',
            'PesoRollo' => 41,
            'FechaModificacion' => '2026-01-01',
        ]);

        $response = $this->liberar([
            [
                'id' => $idA,
                'bomId' => 'BOM-A',
                'bomName' => 'LMAT A',
                'noProduccion' => '88001',
            ],
            [
                'id' => $idB,
                'bomId' => 'BOM-B',
                'bomName' => 'LMAT B',
                'noProduccion' => '88001',
            ],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('duplicado', $data['message']);

        // Rollback total: el primer registro (ya guardado dentro de la transacción)
        // también debe quedar sin folio ni campos de liberación.
        foreach ([$idA, $idB] as $id) {
            $registro = DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->first();
            $this->assertNull($registro->NoProduccion, "Registro {$id} no debió conservar NoProduccion tras rollback");
            $this->assertNull($registro->BomId);
            $this->assertNull($registro->UsuarioModifica);
        }
    }

    public function test_metricas_invalidas_tiras_en_cero_devuelve_422_con_mensaje_de_tiras_y_no_persiste(): void
    {
        $id = $this->sembrarRegistro(['NoTiras' => 0]);

        $response = $this->liberar([
            [
                'id' => $id,
                'bomId' => 'BOM-CRUDO-01',
                'bomName' => 'LISTA MATERIALES CRUDO 01',
                'noProduccion' => '99001',
            ],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('tiras', mb_strtolower($data['message']));

        // Nada persistido
        $registro = DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->first();
        $this->assertNull($registro->NoProduccion);
        $this->assertNull($registro->Repeticiones);
        $this->assertNull($registro->BomId);
    }
}
