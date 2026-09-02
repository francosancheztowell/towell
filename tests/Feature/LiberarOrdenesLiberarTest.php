<?php

namespace Tests\Feature;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Models\Planeacion\ReqProgramaTejido;
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

        // liberar() revalida el L.Mat contra AX (BOMTABLE + BOMVERSION). Sin este
        // doble en sqlite la prueba pegaba al TI_PRO real y siempre daba 422.
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv_ti');

        Schema::connection('sqlsrv_ti')->create('BOMTABLE', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('NAME')->nullable();
            $table->string('ITEMGROUPID')->nullable();
            $table->string('TWINVENTSIZEID')->nullable();
            $table->string('TWSALON')->nullable();
            $table->integer('Vigente')->default(1);
        });

        Schema::connection('sqlsrv_ti')->create('BOMVERSION', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('ITEMID');
        });

        // index() lee los tipos de hilo del catálogo de AX.
        Schema::connection('sqlsrv_ti')->create('INVENTTABLE', function (Blueprint $table) {
            $table->string('TwTipoHiloId')->nullable();
        });

        // Catálogo de flogs: item+talla (de cualquier artículo, pese al nombre de la tabla
        // en AX) cuyos renglones deciden si la orden lleva flog. Se siembra por prueba.
        Schema::connection('sqlsrv_ti')->create('TwArticulosFelpas', function (Blueprint $table) {
            $table->string('ITEMID');
            $table->string('INVENTSIZEID')->nullable();
            $table->string('ITEMNAME')->nullable();
        });

        // L.Mat válido para el registro base (IT100 / STD / JACQUARD).
        $this->sembrarBomCrudo('BOM-CRUDO-01', 'IT100', 'STD');

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
            $table->float('PesoRollo')->nullable();
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
            $table->string('FlogsId')->nullable();
            $table->string('NombreProyecto')->nullable();
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
            $table->string('FlogsId')->nullable();
            $table->string('NombreProyecto')->nullable();
            // Espeja el DEFAULT ((1)) de dbo.CatCodificados: toda orden lleva flog salvo
            // que el usuario lo baje a 0 en un renglón del catálogo.
            $table->boolean('AsignarFlogs')->default(true);
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
        foreach (['BOMTABLE', 'BOMVERSION', 'TwArticulosFelpas', 'INVENTTABLE'] as $tabla) {
            Schema::connection('sqlsrv_ti')->dropIfExists($tabla);
        }
        parent::tearDown();
    }

    /**
     * Alta de un L.Mat CRUDO en el doble de AX.
     *
     * @param  int  $versiones  Filas en BOMVERSION. AX guarda varias por item, y ese
     *                          era el origen de la duplicación que rompía el autollenado.
     */
    private function sembrarBomCrudo(
        string $bomId,
        string $itemId,
        string $inventSizeId,
        string $salon = 'JACQUARD',
        int $versiones = 1
    ): void {
        DB::connection('sqlsrv_ti')->table('BOMTABLE')->insert([
            'BOMID' => $bomId,
            'NAME' => 'LISTA MATERIALES '.$bomId,
            'ITEMGROUPID' => 'CRUDO',
            'TWINVENTSIZEID' => $inventSizeId,
            'TWSALON' => $salon,
            'Vigente' => 1,
        ]);

        for ($i = 0; $i < $versiones; $i++) {
            DB::connection('sqlsrv_ti')->table('BOMVERSION')->insert([
                'BOMID' => $bomId,
                'ITEMID' => $itemId.'-1',
            ]);
        }
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

    /**
     * Renglón FUERA del catálogo TwArticulosFelpas: la pantalla no dibuja el check, el front
     * manda asignarFlogs = null y el servidor NO debe tocar CatCodificados.AsignarFlogs.
     *
     * Es la regresión que motivó el cambio: antes se escribía `$asignarFlogs ? 1 : 0` sin
     * condición, así que un renglón sin check (o cualquier renglón con AX caído) bajaba el
     * flag a 0 en cada liberación.
     */
    public function test_renglon_fuera_del_catalogo_no_modifica_asignar_flogs(): void
    {
        $conFlog = $this->sembrarRegistro(['NoTelarId' => '201']);
        $sinFlog = $this->sembrarRegistro(['NoTelarId' => '202']);

        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => '78001', 'TelarId' => '201', 'AsignarFlogs' => 1],
            ['OrdenTejido' => '78002', 'TelarId' => '202', 'AsignarFlogs' => 0],
        ]);

        // Sin la clave asignarFlogs: exactamente lo que manda el front cuando la fila no
        // tiene check (`row.querySelector('.flog-check')` es null → se envía null).
        $this->liberar([
            [
                'id' => $conFlog,
                'bomId' => 'BOM-CRUDO-01',
                'bomName' => 'LISTA MATERIALES CRUDO 01',
                'noProduccion' => '78001',
                'asignarFlogs' => null,
            ],
            [
                'id' => $sinFlog,
                'bomId' => 'BOM-CRUDO-01',
                'bomName' => 'LISTA MATERIALES CRUDO 01',
                'noProduccion' => '78002',
                'asignarFlogs' => null,
            ],
        ]);

        // La liberación sí ocurrió (el commit va antes del paso de Excel).
        $this->assertSame('78001', DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $conFlog)->value('NoProduccion'));

        // Cada fila conserva el valor que ya tenía: ni se fuerza a 0 ni se fuerza a 1.
        $this->assertSame(1, (int) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '78001')->value('AsignarFlogs'));
        $this->assertSame(0, (int) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '78002')->value('AsignarFlogs'));
    }

    /**
     * Renglón DENTRO del catálogo: el check nace marcado y el usuario decide. Marcado → 1,
     * desmarcado → 0, cada renglón del lote por su cuenta.
     */
    public function test_renglon_del_catalogo_guarda_la_decision_del_usuario(): void
    {
        $marcado = $this->sembrarRegistro(['NoTelarId' => '201']);
        $desmarcado = $this->sembrarRegistro(['NoTelarId' => '202']);

        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            ['OrdenTejido' => '78101', 'TelarId' => '201', 'AsignarFlogs' => 0],
            ['OrdenTejido' => '78102', 'TelarId' => '202', 'AsignarFlogs' => 1],
        ]);

        $this->liberar([
            [
                'id' => $marcado,
                'bomId' => 'BOM-CRUDO-01',
                'bomName' => 'LISTA MATERIALES CRUDO 01',
                'noProduccion' => '78101',
                'asignarFlogs' => true,
            ],
            [
                'id' => $desmarcado,
                'bomId' => 'BOM-CRUDO-01',
                'bomName' => 'LISTA MATERIALES CRUDO 01',
                'noProduccion' => '78102',
                'asignarFlogs' => false,
            ],
        ]);

        // Se parte de valores invertidos justo para que un "no escribió nada" no pase la prueba.
        $this->assertSame(1, (int) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '78101')->value('AsignarFlogs'));
        $this->assertSame(0, (int) DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '78102')->value('AsignarFlogs'));
    }

    /**
     * Quién entra al catálogo: el cruce es por item + talla exactos. Mismo item con otra
     * talla NO decide, y un item ausente tampoco — esos renglones no muestran el check.
     */
    public function test_catalogo_de_flogs_empata_por_item_y_talla_exactos(): void
    {
        DB::connection('sqlsrv_ti')->table('TwArticulosFelpas')->insert([
            // AX guarda el item CON sufijo aquí ('6598-1' en producción) pero sin él en
            // TwFlogsItemLine. Si el cruce no normaliza, el catálogo nunca empata.
            ['ITEMID' => 'IT100-1', 'INVENTSIZEID' => 'STD', 'ITEMNAME' => 'ARTICULO CON SUFIJO AX'],
            ['ITEMID' => 'IT200', 'INVENTSIZEID' => 'STD', 'ITEMNAME' => 'ARTICULO SIN SUFIJO'],
        ]);

        $registros = collect([
            ['ItemId' => 'IT100', 'InventSizeId' => 'STD'],   // empata con la fila 'IT100-1' de AX
            ['ItemId' => 'it100', 'InventSizeId' => 'std'],   // empata (la clave normaliza a mayúsculas)
            ['ItemId' => 'IT200', 'InventSizeId' => 'STD'],   // empata con la fila sin sufijo
            ['ItemId' => 'IT100', 'InventSizeId' => 'FEL'],   // mismo item, otra talla
            ['ItemId' => 'IT999', 'InventSizeId' => 'STD'],   // item fuera del catálogo
        ])->map(function (array $attrs) {
            $registro = new ReqProgramaTejido;
            $registro->setAttribute('ItemId', $attrs['ItemId']);
            $registro->setAttribute('InventSizeId', $attrs['InventSizeId']);

            return $registro;
        });

        $controller = new LiberarOrdenesController;
        $metodo = new \ReflectionMethod($controller, 'clavesArticulosConFlog');
        $metodo->setAccessible(true);
        $claves = $metodo->invoke($controller, $registros);

        $decide = fn (ReqProgramaTejido $r) => isset($claves[
            mb_strtoupper(trim((string) $r->ItemId)).'|'.mb_strtoupper(trim((string) $r->InventSizeId))
        ]);

        $this->assertTrue($decide($registros[0]));
        $this->assertTrue($decide($registros[1]));
        $this->assertTrue($decide($registros[2]));
        $this->assertFalse($decide($registros[3]));
        $this->assertFalse($decide($registros[4]));
    }

    /**
     * La fila espejo en CatCodificados no existe: la liberación NO se frena, pero la respuesta
     * dice cuáles órdenes no se copiaron al catálogo. Antes era un return mudo y el usuario
     * leía "liberadas correctamente" con codificados vacío.
     */
    public function test_orden_sin_fila_en_codificados_se_libera_pero_se_avisa(): void
    {
        $id = $this->sembrarRegistro();
        // A propósito NO se siembra CatCodificados.

        $response = $this->liberar([[
            'id' => $id,
            'bomId' => 'BOM-CRUDO-01',
            'bomName' => 'LISTA MATERIALES CRUDO 01',
            'noProduccion' => '79001',
        ]]);

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame(['79001 (telar 201)'], $data['sinSincronizar']);
        $this->assertStringContainsString('no se encontró renglón en codificados', $data['message']);

        // La orden sí quedó liberada: el aviso no bloquea.
        $this->assertSame('79001', DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('NoProduccion'));
    }

    /** Con la fila presente no hay aviso. */
    public function test_orden_sincronizada_no_reporta_aviso(): void
    {
        $id = $this->sembrarRegistro();
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '79002', 'TelarId' => '201',
        ]);

        $data = $this->liberar([[
            'id' => $id,
            'bomId' => 'BOM-CRUDO-01',
            'bomName' => 'LISTA MATERIALES CRUDO 01',
            'noProduccion' => '79002',
        ]])->getData(true);

        $this->assertSame([], $data['sinSincronizar']);
        $this->assertStringNotContainsString('Aviso:', $data['message']);
    }

    /**
     * Si AX no responde no se puede validar el flog. Antes esta rama aceptaba el valor
     * capturado: un timeout era MÁS permisivo que un AX que contesta "no existe".
     */
    public function test_flog_se_rechaza_cuando_ax_no_responde(): void
    {
        $id = $this->sembrarRegistro();

        // El doble de sqlsrv_ti no tiene TwFlogsTable: consultarla lanza excepción.
        $response = $this->liberar([[
            'id' => $id,
            'bomId' => 'BOM-CRUDO-01',
            'bomName' => 'LISTA MATERIALES CRUDO 01',
            'noProduccion' => '79003',
            'flogsId' => 'CE-INVENTADO-001',
        ]]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('No se pudo validar el flog', $response->getData(true)['message']);

        // Rollback: nada se liberó.
        $this->assertNull(DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('NoProduccion'));
    }

    /** index() esconde los renglones con NoExisteBase; liberar() los rechaza con el mismo criterio. */
    public function test_registro_con_no_existe_base_no_se_puede_liberar(): void
    {
        $id = $this->sembrarRegistro(['NoExisteBase' => 'SIN BASE']);

        $response = $this->liberar([[
            'id' => $id,
            'bomId' => 'BOM-CRUDO-01',
            'bomName' => 'LISTA MATERIALES CRUDO 01',
            'noProduccion' => '79004',
        ]]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('No existe base', $response->getData(true)['message']);
        $this->assertNull(DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('NoProduccion'));
    }

    /**
     * AX guarda el mismo salón con varios nombres: un L.Mat con TWSALON = 'ITEMA' es del salón
     * SMIT. Antes se normalizaba sólo el lado del programa (SMIT) y se comparaba contra el valor
     * crudo de AX, así que ese L.Mat era inalcanzable por cualquier ruta.
     */
    public function test_lmat_guardado_con_variante_de_salon_de_ax_es_valido(): void
    {
        $this->sembrarBomCrudo('BOM-ITEMA-01', 'IT300', 'STD', 'ITEMA');
        $id = $this->sembrarRegistro(['ItemId' => 'IT300', 'SalonTejidoId' => 'SMIT']);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '79005', 'TelarId' => '201',
        ]);

        $response = $this->liberar([[
            'id' => $id,
            'bomId' => 'BOM-ITEMA-01',
            'bomName' => 'LISTA MATERIALES BOM-ITEMA-01',
            'noProduccion' => '79005',
        ]]);

        $this->assertNotSame(422, $response->getStatusCode());
        $this->assertSame('BOM-ITEMA-01', DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->value('BomId'));
    }

    /**
     * La pantalla debe costar lo mismo con 1 renglón que con 12: ninguna consulta puede
     * depender del número de filas.
     *
     * Medido contra la BD real antes de quitar los N+1: 3 renglones = 15 queries (5 por fila) y
     * 7.9 s, de los cuales 7.6 s eran consultas a AX una por renglón. Con 20-60 renglones —lo
     * que trae el departamento nuevo— eso son minutos de carga. Este test es el guard: si
     * alguien vuelve a meter una consulta dentro del each de renglones, se pone rojo.
     */
    public function test_index_no_hace_consultas_por_renglon(): void
    {
        DB::connection('sqlsrv')->table('ReqPesosRolloTejido')->insert([
            'InventSizeId' => 'STD', 'PesoRollo' => 41, 'FechaModificacion' => '2026-01-01',
        ]);

        $contar = function (): int {
            foreach (['sqlsrv', 'sqlsrv_ti'] as $conexion) {
                DB::connection($conexion)->flushQueryLog();
                DB::connection($conexion)->enableQueryLog();
            }

            // Instancia nueva: los caches del controlador son por request, no entre requests.
            (new LiberarOrdenesController)->index(Request::create('/liberar-ordenes', 'GET', ['dias' => 10.999]));

            return count(DB::connection('sqlsrv')->getQueryLog())
                + count(DB::connection('sqlsrv_ti')->getQueryLog());
        };

        $this->sembrarRegistro(['NoTelarId' => '201']);
        $conUno = $contar();

        // Once renglones más, repartidos en varios telares y salones para que la resolución del
        // "registro anterior" tenga trabajo real que hacer.
        for ($i = 0; $i < 11; $i++) {
            $this->sembrarRegistro([
                'NoTelarId' => (string) (202 + $i),
                'SalonTejidoId' => $i % 2 === 0 ? 'JACQUARD' : 'SMIT',
                'ItemId' => 'IT'.(100 + $i),
                'InventSizeId' => $i % 3 === 0 ? 'FEL' : 'STD',
            ]);
        }
        $conDoce = $contar();

        $this->assertSame(
            $conUno,
            $conDoce,
            "index() hace consultas por renglón: {$conUno} queries con 1 registro y {$conDoce} con 12."
        );
    }

    /**
     * Modal "Editar marbetes" de Programa Tejido: el preview recalcula con el peso enviado
     * manteniendo la regla FEL (marbetes ×2, mts/pzas ÷2) porque depende del tamaño del
     * registro, no del peso; y el guardado manual persiste en ReqProgramaTejido + CatCodificados.
     */
    public function test_marbetes_preview_respeta_regla_fel_y_guardado_sincroniza_cat_codificados(): void
    {
        $id = $this->sembrarRegistro(['InventSizeId' => 'FEL', 'NoProduccion' => '77001']);
        DB::connection('sqlsrv')->table('CatCodificados')->insert([
            'OrdenTejido' => '77001',
            'TelarId' => '201',
        ]);

        $controller = new LiberarOrdenesController;

        // PesoRollo 41 → Rep = TRUNC((41/455)/3×1000) = 30
        $preview = $controller->marbetes(
            Request::create('/planeacion/programa-tejido/marbetes', 'GET', ['id' => $id, 'pesoRollo' => 41])
        )->getData(true);

        $this->assertTrue($preview['esFel']);
        $this->assertSame(30, $preview['valores']['repeticiones']);
        $this->assertEqualsWithDelta(21.3, $preview['valores']['mtsRollo'], 0.001);  // 142×30/100 ÷2
        $this->assertEqualsWithDelta(45, $preview['valores']['pzasRollo'], 0.001);   // 30×3 ÷2
        $this->assertEqualsWithDelta(287, $preview['valores']['totalRollos'], 0.001); // ceil(12891/45)
        $this->assertSame(287, $preview['valores']['noMarbete']);                    // = TotalRollos (pendientes, sin producidos)
        $this->assertEqualsWithDelta(12915, $preview['valores']['totalPzas'], 0.001);

        // Repeticiones capturadas a mano sustituyen a la fórmula y arrastran la cadena,
        // con la regla FEL aplicada igual: 20×3 = 60 ÷2 = 30 pzas; 142×20/100 = 28.4 ÷2 = 14.2
        $conReps = $controller->marbetes(
            Request::create('/planeacion/programa-tejido/marbetes', 'GET', [
                'id' => $id, 'pesoRollo' => 41, 'repeticiones' => 20,
            ])
        )->getData(true)['valores'];

        $this->assertSame(20, $conReps['repeticiones']);
        $this->assertEqualsWithDelta(30, $conReps['pzasRollo'], 0.001);
        $this->assertEqualsWithDelta(14.2, $conReps['mtsRollo'], 0.001);
        $this->assertEqualsWithDelta(430, $conReps['totalRollos'], 0.001);     // ceil(12891/30)
        $this->assertSame(430, $conReps['noMarbete']);                        // = TotalRollos

        // TotalRollos capturado a mano solo re-deriva TotalPzas (= PzasRollo × TotalRollos)
        $conRollos = $controller->marbetes(
            Request::create('/planeacion/programa-tejido/marbetes', 'GET', [
                'id' => $id, 'pesoRollo' => 41, 'repeticiones' => 20, 'pzasRollo' => 30, 'totalRollos' => 100,
            ])
        )->getData(true)['valores'];

        $this->assertEqualsWithDelta(100, $conRollos['totalRollos'], 0.001);
        $this->assertEqualsWithDelta(3000, $conRollos['totalPzas'], 0.001);

        // Guardado con overrides manuales del usuario
        $guardar = $controller->guardarMarbetes(
            Request::create('/planeacion/programa-tejido/marbetes', 'POST', [
                'id' => $id,
                'pesoRollo' => 41,
                'repeticiones' => 30,
                'mtsRollo' => 21.3,
                'pzasRollo' => 45,
                'noMarbete' => 300,
                'totalRollos' => 287.2,
                'totalPzas' => 12915,
            ])
        );

        $this->assertSame(200, $guardar->getStatusCode());

        $registro = DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->first();
        $this->assertEqualsWithDelta(41, $registro->PesoRollo, 0.001);
        $this->assertEqualsWithDelta(30, $registro->Repeticiones, 0.001);
        $this->assertEqualsWithDelta(300, $registro->NoMarbete, 0.001);
        $this->assertSame(300, (int) $registro->SaldoMarbete);
        $this->assertEqualsWithDelta(288, $registro->TotalRollos, 0.001); // ceil(287.2)

        $cat = DB::connection('sqlsrv')->table('CatCodificados')->where('OrdenTejido', '77001')->first();
        $this->assertSame(30, (int) $cat->Repeticiones);
        $this->assertEqualsWithDelta(21.3, $cat->MtsRollo, 0.001);
        $this->assertEqualsWithDelta(45, $cat->PzasRollo, 0.001);
        $this->assertEqualsWithDelta(300, $cat->NoMarbete, 0.001);
        $this->assertEqualsWithDelta(288, $cat->TotalRollos, 0.001);
        $this->assertEqualsWithDelta(12915, $cat->TotalPzas, 0.001);
    }

    public function test_folio_manual_duplicado_en_el_lote_devuelve_422_y_no_persiste_nada(): void
    {
        $idA = $this->sembrarRegistro(['NoTelarId' => '201']);
        $idB = $this->sembrarRegistro(['NoTelarId' => '202']);

        // Ambos L.Mat válidos: lo que debe reventar es el folio repetido, no la validación previa.
        $this->sembrarBomCrudo('BOM-A', 'IT100', 'STD');
        $this->sembrarBomCrudo('BOM-B', 'IT100', 'STD');

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

    /**
     * El campo L.Mat es texto libre: un BOMID vigente pero de OTRO producto no
     * debe poder liberarse. Antes solo se comprobaba que el BOMID existiera.
     */
    public function test_liberar_rechaza_lmat_vigente_que_pertenece_a_otro_item(): void
    {
        $id = $this->sembrarRegistro();

        // Vigente y CRUDO, pero amarrado a IT999 — no al IT100 del renglón.
        $this->sembrarBomCrudo('BOM-AJENO-99', 'IT999', 'STD');

        $response = $this->liberar([
            [
                'id' => $id,
                'bomId' => 'BOM-AJENO-99',
                'bomName' => 'LISTA MATERIALES BOM-AJENO-99',
                'noProduccion' => '78001',
            ],
        ]);

        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('BOM-AJENO-99', $data['message']);

        $registro = DB::connection('sqlsrv')->table('ReqProgramaTejido')->where('Id', $id)->first();
        $this->assertNull($registro->NoProduccion);
    }

    /**
     * Un L.Mat con varias versiones en BOMVERSION sigue siendo uno solo: el JOIN
     * lo devolvía duplicado y eso rompía el conteo de opciones.
     */
    public function test_lmat_con_varias_versiones_en_ax_cuenta_como_una_sola_opcion(): void
    {
        $this->sembrarBomCrudo('BOM-MULTI-03', 'IT700', 'STD', 'JACQUARD', versiones: 3);

        $registro = $this->sembrarRegistro(['ItemId' => 'IT700']);
        $modelo = ReqProgramaTejido::find($registro);

        $metodo = new \ReflectionMethod(LiberarOrdenesController::class, 'resolverBomCrudoOpciones');
        $metodo->setAccessible(true);
        $opciones = $metodo->invoke(new LiberarOrdenesController, $modelo);

        $this->assertCount(1, $opciones, 'Las 3 versiones de AX deben colapsar en una sola opción.');
        $this->assertSame('BOM-MULTI-03', $opciones[0]['bomId']);
    }

    /**
     * Las L.Mat 'ESTAND ...' cuelgan de cientos de items en AX, así que nunca se
     * autoasignan: el renglón se queda vacío para que alguien las elija a mano.
     */
    public function test_lmat_estandar_no_se_autoasigna(): void
    {
        $metodo = new \ReflectionMethod(LiberarOrdenesController::class, 'bomAutoAsignable');
        $metodo->setAccessible(true);
        $decidir = fn (array $opciones) => $metodo->invoke(null, $opciones);

        $estand = ['bomId' => 'ESTAND JS 3060-3524', 'bomName' => 'ESTANDAR JACQUARD SMIT'];
        $propia = ['bomId' => 'TEJ MB SD NAT', 'bomName' => 'TEJIDO MB'];

        $this->assertNull($decidir([$estand]), 'Una ESTAND sola no debe autoasignarse.');
        $this->assertNull($decidir([$estand, ['bomId' => 'estand l 2524-2876', 'bomName' => 'x']]));
        $this->assertSame($propia, $decidir([$propia]), 'La L.Mat propia sí se autoasigna.');
        $this->assertSame($propia, $decidir([$estand, $propia]), 'Con una ESTAND al lado, gana la propia.');
        $this->assertNull($decidir([$propia, ['bomId' => 'TEJ OTRA', 'bomName' => 'y']]), 'Dos propias siguen siendo ambiguas.');
        $this->assertNull($decidir([]));
    }
}
