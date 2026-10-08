<?php

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Caracterización de ReqProgramaTejidoObserver::saved() (2026-10-07).
 *
 * Congela lo que el observer hace HOY al guardar con Eloquent: fórmulas de eficiencia y de
 * producción persistidas en la cabecera, cantidad y contenido de las líneas diarias, la
 * sincronización a CatCodificados y el rollback cuando falla dentro de una transacción.
 * No afirma que los números sean correctos: afirma que no cambian sin una decisión.
 * Valores obtenidos corriendo el código tal cual sobre sqlite en memoria.
 */
class ReqProgramaTejidoObserverSavedCharacterizationTest extends TestCase
{
    use ProgramaTejidoFixtures;

    private const DELTA = 1e-6;

    /** Límite de parámetros por sentencia de SQL Server (2008 R2 incluido). */
    private const MAX_PARAMETROS_SQLSRV = 2100;

    /** @var list<string> */
    private array $errores = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:15:00');

        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->usarSuperficie('programa');

        // Maestros que lee el observer: pesos de rollo, aplicaciones (dbo.) y matriz de hilos.
        Schema::create('ReqPesosRolloTejido', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('InventSizeId')->nullable();
            $t->float('PesoRollo')->nullable();
            $t->dateTime('FechaModificacion')->nullable();
        });
        DB::table('ReqPesosRolloTejido')->insert([
            ['InventSizeId' => 'TAM01', 'PesoRollo' => 45, 'FechaModificacion' => '2026-01-01'],
            ['InventSizeId' => 'DEF', 'PesoRollo' => 41.5, 'FechaModificacion' => '2026-01-01'],
        ]);

        $this->createTablaDbo('ReqAplicaciones', ['Id' => 'INTEGER PRIMARY KEY', 'AplicacionId' => 'TEXT', 'Nombre' => 'TEXT', 'Factor' => 'REAL']);
        DB::statement('INSERT INTO dbo."ReqAplicaciones" ("AplicacionId", "Nombre", "Factor") VALUES (?, ?, ?)', ['APL1', 'Aplicación 1', 0.02]);

        Schema::create('ReqMatrizHilos', function (Blueprint $t) {
            $t->increments('Id');
            foreach (['Hilo', 'CalibreAX', 'Fibra', 'CodColor', 'NombreColor'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['Calibre', 'Calibre2', 'N1', 'N2'] as $c) {
                $t->float($c)->nullable();
            }
        });
        DB::table('ReqMatrizHilos')->insert(['Hilo' => 'H-RIZO', 'N1' => 12, 'N2' => 14]);

        Log::listen(function ($evento) {
            if ($evento->level === 'error') {
                $this->errores[] = $evento->message;
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Programa con todos los insumos que lee el observer. */
    private function atributosCompletos(array $extra = []): array
    {
        return array_merge([
            'SalonTejidoId' => 'SMIT', 'NoTelarId' => '207', 'Posicion' => 1, 'EnProceso' => 0,
            'NoProduccion' => '30007', 'InventSizeId' => 'TAM01',
            'TotalPedido' => 1200, 'SaldoPedido' => 900, 'PesoCrudo' => 450, 'NoTiras' => 2,
            'LargoCrudo' => 70, 'MedidaPlano' => 5, 'AnchoToalla' => 50, 'LargoToalla' => 90,
            'VelocidadSTD' => 400, 'EficienciaSTD' => 85,
            'PasadasTrama' => 20, 'CalibreTrama2' => 12,
            'PasadasComb1' => 10, 'CalibreComb12' => 16,
            'CalibrePie2' => 10, 'CuentaPie' => 60,
            'CuentaRizo' => 50, 'FibraRizo' => 'H-RIZO', 'AplicacionId' => 'APL1',
            'FechaInicio' => '2026-09-10 06:30:00', 'FechaFinal' => '2026-09-13 14:30:00',
        ], $extra);
    }

    private function lineas(int $programaId): array
    {
        return DB::table('ReqProgramaTejidoLine')->where('ProgramaId', $programaId)->orderBy('Fecha')->get()
            ->map(fn ($l) => (array) $l)->all();
    }

    private function crearConCat(array $extra = []): ReqProgramaTejido
    {
        DB::table('CatCodificados')->insert(['OrdenTejido' => '30007', 'TelarId' => 207, 'Departamento' => 'SMIT', 'Pedido' => 1200]);

        return ReqProgramaTejido::create($this->atributosCompletos($extra));
    }

    /** @param list<array<string, mixed>> $lineas */
    private function columna(array $lineas, string $campo): array
    {
        return array_map(fn ($l) => $l[$campo] === null ? null : (float) $l[$campo], $lineas);
    }

    public function test_crear_persiste_formulas_de_eficiencia_y_de_produccion_en_la_cabecera(): void
    {
        $p = $this->crearConCat();

        $cab = DB::table('ReqProgramaTejido')->where('Id', $p->Id)->first();

        // Eficiencia (sin TamanoClave → sin modelo: StdToaHra/StdDia/ProdKgDia/HorasProd quedan null).
        $this->assertEqualsWithDelta(3.33, $cab->DiasEficiencia, self::DELTA);
        $this->assertEqualsWithDelta(11.25, $cab->StdHrsEfect, self::DELTA);
        $this->assertEqualsWithDelta(121.5, $cab->ProdKgDia2, self::DELTA);
        $this->assertNull($cab->StdToaHra);
        $this->assertNull($cab->StdDia);
        $this->assertNull($cab->ProdKgDia);
        $this->assertNull($cab->HorasProd);

        // Producción: en un create isDirty() sigue en true al disparar saved → recalcula.
        // Maestro TAM01 = 45 kg → Repeticiones = TRUNC(45/450/2*1000) = 50.
        $this->assertEquals(50, $cab->Repeticiones);
        $this->assertEquals(100, $cab->PzasRollo);
        $this->assertEqualsWithDelta(35.0, $cab->MtsRollo, self::DELTA);
        $this->assertEquals(12, $cab->TotalRollos); // CEIL(1200 / 100), sobre TotalPedido
        $this->assertEquals(1200, $cab->TotalPzas);
        $this->assertEquals(12, $cab->RollosProgramados);
        $this->assertSame('2026-10-07 10:15:00', $cab->UpdatedAt);
        $this->assertSame([], $this->errores);
    }

    public function test_crear_genera_una_linea_por_dia_con_consumos(): void
    {
        $p = $this->crearConCat();
        $lineas = $this->lineas($p->Id);

        // 80 h: 17.5 h el 10, 24 h el 11 y 12, 14.5 h el 13; 900 piezas / 80 h = 11.25 pzas/h.
        $this->assertSame(['2026-09-10', '2026-09-11', '2026-09-12', '2026-09-13'], array_map(fn ($l) => substr($l['Fecha'], 0, 10), $lineas));
        $this->assertEqualsWithDelta([196.875, 270.0, 270.0, 163.125], $this->columna($lineas, 'Cantidad'), self::DELTA);
        $this->assertEqualsWithDelta(900.0, array_sum($this->columna($lineas, 'Cantidad')), self::DELTA);
        $this->assertEqualsWithDelta([88.59375, 121.5, 121.5, 73.40625], $this->columna($lineas, 'Kilos'), self::DELTA);
        $this->assertEqualsWithDelta([1.771875, 2.43, 2.43, 1.468125], $this->columna($lineas, 'Aplicacion'), self::DELTA);
        $this->assertEqualsWithDelta([0.096894, 0.132883, 0.132883, 0.080283], $this->columna($lineas, 'Trama'), self::DELTA);
        $this->assertEqualsWithDelta([0.036335, 0.049831, 0.049831, 0.030106], $this->columna($lineas, 'Combina1'), self::DELTA);
        $this->assertSame([null, null, null, null], $this->columna($lineas, 'Combina2'));
        $this->assertSame([null, null, null, null], $this->columna($lineas, 'Combina5'));
        $this->assertEqualsWithDelta([0.128672, 0.176465, 0.176465, 0.106614], $this->columna($lineas, 'Pie'), self::DELTA);
        $this->assertEqualsWithDelta([88.331849, 121.140822, 121.140822, 73.189246], $this->columna($lineas, 'Rizo'), self::DELTA);
        $this->assertEqualsWithDelta([39556.499184, 54248.913166, 54248.913166, 32775.385038], $this->columna($lineas, 'MtsRizo'), self::DELTA);
        $this->assertEqualsWithDelta([36.936885, 50.6563, 50.6563, 30.604848], $this->columna($lineas, 'MtsPie'), self::DELTA);
    }

    public function test_crear_propaga_las_formulas_de_produccion_a_cat_codificados(): void
    {
        $this->crearConCat();

        $cat = DB::table('CatCodificados')->where('OrdenTejido', '30007')->where('TelarId', 207)->first();
        $this->assertEquals(50, $cat->Repeticiones);
        $this->assertEquals(100, $cat->PzasRollo);
        $this->assertEqualsWithDelta(35.0, $cat->MtsRollo, self::DELTA);
        $this->assertEquals(12, $cat->TotalRollos);
        $this->assertEquals(1200, $cat->TotalPzas);
        $this->assertSame('2026-10-07', $cat->FechaModificacion);
        $this->assertSame('10:15:00', $cat->HoraModificacion);
        $this->assertSame('Sistema', $cat->UsuarioModifica);
        // En un create wasChanged() está vacío: sincronizarCatCodificados no copia nada.
        $this->assertNull($cat->Saldos);
        $this->assertNull($cat->P_crudo);
    }

    public function test_editar_campo_solo_sincronizable_copia_a_su_telar_y_no_regenera_lineas_ni_recalcula(): void
    {
        $antes = $this->lineas(3);

        $registro = ReqProgramaTejido::find(3); // orden 30003 repartida en telares 202 y 203
        $registro->ItemId = 'ITEM-9';
        $registro->NombreProyecto = 'PROYECTO X';
        $registro->save();

        $cat202 = DB::table('CatCodificados')->where('OrdenTejido', '30003')->where('TelarId', 202)->first();
        $cat203 = DB::table('CatCodificados')->where('OrdenTejido', '30003')->where('TelarId', 203)->first();
        $this->assertSame('ITEM-9', $cat202->ItemId);
        $this->assertSame('PROYECTO X', $cat202->NombreProyecto);
        $this->assertSame('2026-10-07', $cat202->FechaModificacion);
        $this->assertNull($cat203->ItemId, 'El sync pisó el otro telar de la misma orden');
        $this->assertSame($antes, $this->lineas(3), 'ItemId/NombreProyecto no están en CAMPOS_RELEVANTES');
        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 3)->value('Repeticiones'));
    }

    public function test_editar_saldo_regenera_lineas_sincroniza_saldos_y_mantiene_total_rollos(): void
    {
        $p = $this->crearConCat();
        $idsAntes = array_column($this->lineas($p->Id), 'Id');

        $p->refresh();
        $p->SaldoPedido = 600;
        $p->save();

        $lineas = $this->lineas($p->Id);
        $this->assertCount(4, $lineas);
        $this->assertNotSame($idsAntes, array_column($lineas, 'Id'), 'DELETE + INSERT: Ids nuevos');
        $this->assertEqualsWithDelta([131.25, 180.0, 180.0, 108.75], $this->columna($lineas, 'Cantidad'), self::DELTA);

        $cab = DB::table('ReqProgramaTejido')->where('Id', $p->Id)->first();
        $this->assertEqualsWithDelta(7.5, $cab->StdHrsEfect, self::DELTA);
        $this->assertEquals(12, $cab->TotalRollos, 'TotalRollos sale de TotalPedido, no de SaldoPedido');

        $cat = DB::table('CatCodificados')->where('OrdenTejido', '30007')->first();
        $this->assertEquals(600, $cat->Saldos);
        $this->assertEquals(12, $cat->TotalRollos);
    }

    public function test_si_fallan_las_lineas_dentro_de_una_transaccion_se_revierte_todo(): void
    {
        Schema::table('ReqProgramaTejidoLine', fn (Blueprint $t) => $t->dropColumn('MtsPie'));
        $catAntes = (array) DB::table('CatCodificados')->where('OrdenTejido', '30001')->first();

        $registro = ReqProgramaTejido::find(1);
        $registro->SaldoPedido = 700;
        $registro->PesoCrudo = 500;

        $fallo = null;
        try {
            DB::transaction(fn () => $registro->save());
        } catch (\Throwable $e) {
            $fallo = $e;
        }

        $this->assertNotNull($fallo, 'Con transactionLevel() > 0 el observer relanza');
        $fila = DB::table('ReqProgramaTejido')->where('Id', 1)->first();
        $this->assertEquals(800, $fila->SaldoPedido);
        $this->assertEquals(450, $fila->PesoCrudo);
        $this->assertNull($fila->Repeticiones);
        $this->assertNull($fila->StdHrsEfect, 'El UPDATE de eficiencia previo a las líneas también se revierte');
        $this->assertSame([['Cantidad' => 10.0]], array_map(fn ($l) => ['Cantidad' => (float) $l['Cantidad']], $this->lineas(1)));
        // El sync a Cat corre después de las líneas: con el throw nunca llega.
        $this->assertSame($catAntes, (array) DB::table('CatCodificados')->where('OrdenTejido', '30001')->first());
        $this->assertContains('ReqProgramaTejidoObserver::generarLineasDiarias error', $this->errores);
    }

    public function test_si_fallan_las_lineas_fuera_de_transaccion_la_cabecera_y_cat_quedan_y_no_hay_excepcion(): void
    {
        Schema::table('ReqProgramaTejidoLine', fn (Blueprint $t) => $t->dropColumn('MtsPie'));

        $registro = ReqProgramaTejido::find(1);
        $registro->SaldoPedido = 700;
        $this->assertTrue($registro->save());

        $this->assertEquals(700, DB::table('ReqProgramaTejido')->where('Id', 1)->value('SaldoPedido'));
        // La línea previa sobrevive (DELETE + INSERT atómicos).
        $this->assertCount(1, $this->lineas(1));
        $this->assertEquals(10, $this->lineas(1)[0]['Cantidad']);
        // Sync y recálculo sí corren.
        $cat = DB::table('CatCodificados')->where('OrdenTejido', '30001')->first();
        $this->assertEquals(700, $cat->Saldos);
        // Sin InventSizeId → maestro DEF 41.5 kg → Rep 46, PzasRollo 92, CEIL(1000 / 92) = 11.
        $this->assertEquals(46, DB::table('ReqProgramaTejido')->where('Id', 1)->value('Repeticiones'));
        $this->assertEquals(11, DB::table('ReqProgramaTejido')->where('Id', 1)->value('TotalRollos'));
        $this->assertEquals(11, $cat->TotalRollos);
        $this->assertContains('ReqProgramaTejidoObserver::generarLineasDiarias error', $this->errores);
    }

    public function test_horizonte_largo_genera_una_linea_por_dia(): void
    {
        // 2026-01-01 06:30 → 2026-07-01 14:30: 182 fechas, 4 352 h. 182 × 15 columnas = 2 730
        // parámetros si van en un solo INSERT.
        $p = $this->crearConCat(['FechaInicio' => '2026-01-01 06:30:00', 'FechaFinal' => '2026-07-01 14:30:00']);
        $lineas = $this->lineas($p->Id);

        $this->assertCount(182, $lineas);
        $this->assertGreaterThan(141, count($lineas));
        $this->assertSame('2026-01-01', substr($lineas[0]['Fecha'], 0, 10));
        $this->assertSame('2026-07-01', substr($lineas[181]['Fecha'], 0, 10));
        $this->assertEqualsWithDelta(900 / 4352 * 17.5, (float) $lineas[0]['Cantidad'], self::DELTA);
        $this->assertEqualsWithDelta(900 / 4352 * 24, (float) $lineas[90]['Cantidad'], self::DELTA);
        $this->assertEqualsWithDelta(900 / 4352 * 14.5, (float) $lineas[181]['Cantidad'], self::DELTA);
        $this->assertEqualsWithDelta(900.0, array_sum($this->columna($lineas, 'Cantidad')), 1e-3);
        $this->assertSame(array_fill(0, 182, 15), array_map(fn ($l) => count($l) - 1, $lineas), '15 columnas + Id');
    }

    /**
     * SQL Server rechaza más de 2 100 parámetros por sentencia; sqlite no, por eso aquí se
     * cuenta con DB::listen. Con 182 líneas × 15 columnas, el bloque debe ser ≤ 139 filas
     * (InsercionEnBloques::filasPorBloque).
     */
    public function test_ningun_insert_de_lineas_supera_2100_parametros(): void
    {
        $inserts = [];
        DB::listen(function (QueryExecuted $q) use (&$inserts) {
            if (preg_match('/^\s*insert\s+into\s+"?ReqProgramaTejidoLine"?/i', $q->sql)) {
                $inserts[] = count($q->bindings);
            }
        });

        $p = $this->crearConCat(['FechaInicio' => '2026-01-01 06:30:00', 'FechaFinal' => '2026-07-01 14:30:00']);

        $this->assertSame(182, DB::table('ReqProgramaTejidoLine')->where('ProgramaId', $p->Id)->count());
        $this->assertNotEmpty($inserts);
        $this->assertSame(182 * 15, array_sum($inserts));
        $this->assertLessThanOrEqual(
            self::MAX_PARAMETROS_SQLSRV,
            max($inserts),
            'Un INSERT a ReqProgramaTejidoLine trae '.max($inserts).' parámetros; SQL Server admite 2100.'
        );
    }
}
