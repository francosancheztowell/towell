<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\CatCodificadosDesarrolladorService;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\MovimientoDesarrolladorService;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MovimientoDesarrolladorServiceTest extends TestCase
{
    protected MovimientoDesarrolladorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlsrv');
        Config::set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->getPdo();

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->boolean('EnProceso')->default(false);
            $table->string('Reprogramar')->nullable();
            $table->integer('Posicion')->nullable();
            $table->dateTime('FechaInicio')->nullable();
            $table->dateTime('FechaFinal')->nullable();
            $table->dateTime('FechaArranque')->nullable();
            $table->dateTime('FechaFinaliza')->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('SaldoPedido')->nullable();
            $table->integer('OrdCompartida')->nullable();
            $table->boolean('OrdCompartidaLider')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->float('NoTiras')->nullable();
            $table->float('Luchaje')->nullable();
            $table->float('Repeticiones')->nullable();
            $table->float('VelocidadSTD')->nullable();
            $table->float('EficienciaSTD')->nullable();
            $table->float('HorasProd')->nullable();
            $table->float('PesoCrudo')->nullable();
            $table->float('LargoToalla')->nullable();
            $table->float('AnchoToalla')->nullable();
            $table->string('FibraRizo')->nullable();
            $table->string('CuentaPie')->nullable();
            $table->float('CalibrePie2')->nullable();
            $table->float('MedidaPlano')->nullable();
            $table->float('LargoCrudo')->nullable();
            $table->string('AplicacionId')->nullable();
            $table->string('CalendarioId')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->string('Ultimo')->nullable();
            $table->string('CambioHilo')->nullable();
            $table->float('StdToaHra')->nullable();
            $table->float('PesoGRM2')->nullable();
            $table->float('DiasEficiencia')->nullable();
            $table->float('StdDia')->nullable();
            $table->float('ProdKgDia')->nullable();
            $table->float('StdHrsEfect')->nullable();
            $table->float('ProdKgDia2')->nullable();
            $table->float('DiasJornada')->nullable();
            $table->date('EntregaPT')->nullable();
            $table->date('EntregaProduc')->nullable();
            $table->dateTime('EntregaCte')->nullable();
            $table->float('PTvsCte')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
        });

        Schema::connection('sqlsrv')->create('CatCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('OrdenTejido')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->dateTime('FechaArranque')->nullable();
            $table->dateTime('FechaFinaliza')->nullable();
            $table->float('Pedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('Saldos')->nullable();
            $table->integer('OrdCompartida')->nullable();
            $table->boolean('OrdCompartidaLider')->nullable();
        });

        $this->service = new MovimientoDesarrolladorService(new CatCodificadosDesarrolladorService());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_actualizar_fechas_arranque_finaliza_sincroniza_fecha_finaliza_actual_en_cat_codificados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 18:45:00'));

        $programa = $this->createPrograma([
            'NoProduccion' => 'ORD-100',
            'NoTelarId' => '101',
            'FechaInicio' => '2026-03-18 08:00:00',
        ]);

        CatCodificados::query()->create([
            'OrdenTejido' => 'ORD-100',
            'TelarId' => '101',
        ]);

        $resultado = $this->service->actualizarFechasArranqueFinaliza($programa, null, 'now');

        $this->assertTrue($resultado);

        $programa->refresh();
        $registro = CatCodificados::query()->first();

        $this->assertSame('2026-03-18 08:00:00', optional($programa->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-18 18:45:00', optional($programa->FechaFinaliza)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-18 08:00:00', optional($registro->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-18 18:45:00', optional($registro->FechaFinaliza)->format('Y-m-d H:i:s'));
    }

    public function test_actualizar_fechas_arranque_finaliza_sincroniza_fecha_arranque_actual_en_cat_codificados(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 19:10:00'));

        $programa = $this->createPrograma([
            'NoProduccion' => 'ORD-200',
            'NoTelarId' => '202',
            'FechaInicio' => '2026-03-18 09:00:00',
            'FechaFinaliza' => '2026-03-18 17:00:00',
        ]);

        CatCodificados::query()->create([
            'OrdenTejido' => 'ORD-200',
            'TelarId' => '202',
            'FechaFinaliza' => '2026-03-18 17:00:00',
        ]);

        $resultado = $this->service->actualizarFechasArranqueFinaliza($programa, 'now', null);

        $this->assertTrue($resultado);

        $programa->refresh();
        $registro = CatCodificados::query()->first();

        $this->assertSame('2026-03-18 19:10:00', optional($programa->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertNull($programa->FechaFinaliza);
        $this->assertSame('2026-03-18 19:10:00', optional($registro->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertNull($registro->FechaFinaliza);
    }

    public function test_actualizar_fechas_arranque_finaliza_actualiza_cat_codificados_por_orden_aunque_no_coincida_telar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 20:00:00'));

        $programa = $this->createPrograma([
            'NoProduccion' => 'ORD-300',
            'NoTelarId' => '303',
            'FechaInicio' => '2026-03-18 10:00:00',
        ]);

        CatCodificados::query()->create([
            'OrdenTejido' => 'ORD-300',
            'TelarId' => '999',
        ]);

        $resultado = $this->service->actualizarFechasArranqueFinaliza($programa, 'now', null);

        $this->assertTrue($resultado);

        $registro = CatCodificados::query()->first();

        $this->assertSame('2026-03-18 20:00:00', optional($registro->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertNull($registro->FechaFinaliza);
    }

    public function test_mover_registro_en_proceso_reprogramar_siguiente_cierra_actual_y_arranca_nuevo_sin_borrar_duplicados_previos(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 21:00:00'));

        $actual = $this->createPrograma([
            'NoProduccion' => 'ORD-A',
            'NoTelarId' => '101',
            'SalonTejidoId' => 'JAC',
            'EnProceso' => true,
            'Reprogramar' => '1',
            'Posicion' => 1,
            'FechaInicio' => '2026-03-18 08:00:00',
            'FechaFinal' => '2026-03-18 10:00:00',
        ]);

        $nuevo = $this->createPrograma([
            'NoProduccion' => 'ORD-B',
            'NoTelarId' => '101',
            'SalonTejidoId' => 'JAC',
            'EnProceso' => false,
            'Posicion' => 2,
            'FechaInicio' => '2026-03-18 10:00:00',
            'FechaFinal' => '2026-03-18 12:00:00',
        ]);

        $catActualAnterior = CatCodificados::query()->create(['OrdenTejido' => 'ORD-A', 'TelarId' => '101']);
        $catActualCanonico = CatCodificados::query()->create(['OrdenTejido' => 'ORD-A', 'TelarId' => '999']);
        $catNuevoAnterior = CatCodificados::query()->create(['OrdenTejido' => 'ORD-B', 'TelarId' => '101']);
        $catNuevoCanonico = CatCodificados::query()->create(['OrdenTejido' => 'ORD-B', 'TelarId' => '999']);

        $this->service->moverRegistroEnProceso($nuevo, true);

        $actual->refresh();
        $nuevo->refresh();

        $this->assertFalse((bool) $actual->EnProceso);
        $this->assertNull($actual->Reprogramar);
        $this->assertSame(2, (int) $actual->Posicion);
        $this->assertSame('2026-03-18 08:00:00', optional($actual->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-18 21:00:00', optional($actual->FechaFinaliza)->format('Y-m-d H:i:s'));

        $this->assertTrue((bool) $nuevo->EnProceso);
        $this->assertSame(1, (int) $nuevo->Posicion);
        $this->assertSame('2026-03-18 21:00:00', optional($nuevo->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertNull($nuevo->FechaFinaliza);

        $this->assertSame(2, CatCodificados::query()->where('OrdenTejido', 'ORD-A')->count());
        $this->assertSame(2, CatCodificados::query()->where('OrdenTejido', 'ORD-B')->count());

        $catActual = CatCodificados::query()->whereKey($catActualCanonico->Id)->firstOrFail();
        $catNuevo = CatCodificados::query()->whereKey($catNuevoCanonico->Id)->firstOrFail();
        $catActualSinTocar = CatCodificados::query()->whereKey($catActualAnterior->Id)->firstOrFail();
        $catNuevoSinTocar = CatCodificados::query()->whereKey($catNuevoAnterior->Id)->firstOrFail();

        $this->assertSame($catActualCanonico->Id, $catActual->Id);
        $this->assertSame($catNuevoCanonico->Id, $catNuevo->Id);
        $this->assertSame('2026-03-18 21:00:00', optional($catActual->FechaFinaliza)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-18 21:00:00', optional($catNuevo->FechaArranque)->format('Y-m-d H:i:s'));
        $this->assertNull($catNuevo->FechaFinaliza);
        $this->assertNull($catActualSinTocar->FechaFinaliza);
        $this->assertNull($catNuevoSinTocar->FechaArranque);
    }

    public function test_mover_registro_en_proceso_reprogramar_final_envia_la_orden_actual_al_final(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 21:30:00'));

        $actual = $this->createPrograma([
            'NoProduccion' => 'ORD-CUR',
            'NoTelarId' => '101',
            'SalonTejidoId' => 'JAC',
            'EnProceso' => true,
            'Reprogramar' => '2',
            'Posicion' => 1,
            'FechaInicio' => '2026-03-18 08:00:00',
            'FechaFinal' => '2026-03-18 10:00:00',
        ]);

        $nuevo = $this->createPrograma([
            'NoProduccion' => 'ORD-NEW',
            'NoTelarId' => '101',
            'SalonTejidoId' => 'JAC',
            'Posicion' => 2,
            'FechaInicio' => '2026-03-18 10:00:00',
            'FechaFinal' => '2026-03-18 12:00:00',
        ]);

        $tercero = $this->createPrograma([
            'NoProduccion' => 'ORD-LAST',
            'NoTelarId' => '101',
            'SalonTejidoId' => 'JAC',
            'Posicion' => 3,
            'FechaInicio' => '2026-03-18 12:00:00',
            'FechaFinal' => '2026-03-18 14:00:00',
        ]);

        CatCodificados::query()->create(['OrdenTejido' => 'ORD-CUR', 'TelarId' => '101']);
        CatCodificados::query()->create(['OrdenTejido' => 'ORD-NEW', 'TelarId' => '101']);

        $this->service->moverRegistroEnProceso($nuevo, true);

        $actual->refresh();
        $nuevo->refresh();
        $tercero->refresh();

        $this->assertSame(3, (int) $actual->Posicion);
        $this->assertFalse((bool) $actual->EnProceso);
        $this->assertSame('2026-03-18 21:30:00', optional($actual->FechaFinaliza)->format('Y-m-d H:i:s'));

        $this->assertSame(1, (int) $nuevo->Posicion);
        $this->assertTrue((bool) $nuevo->EnProceso);
        $this->assertSame('2026-03-18 21:30:00', optional($nuevo->FechaArranque)->format('Y-m-d H:i:s'));

        $this->assertSame(2, (int) $tercero->Posicion);
        $this->assertFalse((bool) $tercero->EnProceso);
    }

    public function test_mover_registro_con_cambio_telar_reprogramar_final_arranca_nuevo_en_destino(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18 22:00:00'));

        $movido = $this->createPrograma([
            'NoProduccion' => 'ORD-MOVE',
            'NoTelarId' => '101',
            'SalonTejidoId' => 'JAC',
            'Posicion' => 1,
            'FechaInicio' => '2026-03-18 07:00:00',
            'FechaFinal' => '2026-03-18 09:00:00',
        ]);

        $actualDestino = $this->createPrograma([
            'NoProduccion' => 'ORD-DEST-ACT',
            'NoTelarId' => '202',
            'SalonTejidoId' => 'JAC',
            'EnProceso' => true,
            'Posicion' => 1,
            'FechaInicio' => '2026-03-18 09:00:00',
            'FechaFinal' => '2026-03-18 11:00:00',
        ]);

        $colaDestino = $this->createPrograma([
            'NoProduccion' => 'ORD-DEST-COLA',
            'NoTelarId' => '202',
            'SalonTejidoId' => 'JAC',
            'Posicion' => 2,
            'FechaInicio' => '2026-03-18 11:00:00',
            'FechaFinal' => '2026-03-18 13:00:00',
        ]);

        CatCodificados::query()->create(['OrdenTejido' => 'ORD-MOVE', 'TelarId' => '101']);
        CatCodificados::query()->create(['OrdenTejido' => 'ORD-DEST-ACT', 'TelarId' => '202']);

        $resultado = $this->service->moverRegistroConCambioTelarEnProceso($movido, 'JAC', '202', '2');

        $this->assertNotNull($resultado);

        $movido->refresh();
        $actualDestino->refresh();
        $colaDestino->refresh();

        $this->assertSame('202', $movido->NoTelarId);
        $this->assertSame('JAC', $movido->SalonTejidoId);
        $this->assertTrue((bool) $movido->EnProceso);
        $this->assertSame(1, (int) $movido->Posicion);
        $this->assertSame('2026-03-18 22:00:00', optional($movido->FechaArranque)->format('Y-m-d H:i:s'));

        $this->assertFalse((bool) $actualDestino->EnProceso);
        $this->assertSame(3, (int) $actualDestino->Posicion);
        $this->assertSame('2026-03-18 22:00:00', optional($actualDestino->FechaFinaliza)->format('Y-m-d H:i:s'));

        $this->assertSame(2, (int) $colaDestino->Posicion);
    }

    private function createPrograma(array $attributes = []): ReqProgramaTejido
    {
        return ReqProgramaTejido::query()->create(array_merge([
            'NoProduccion' => null,
            'NoTelarId' => '101',
            'SalonTejidoId' => 'JAC',
            'EnProceso' => false,
            'Reprogramar' => null,
            'Posicion' => 1,
            'FechaInicio' => '2026-03-18 08:00:00',
            'FechaFinal' => '2026-03-18 10:00:00',
            'FechaArranque' => null,
            'FechaFinaliza' => null,
            'TotalPedido' => 0,
            'Produccion' => 0,
            'SaldoPedido' => 0,
            'OrdCompartida' => null,
            'OrdCompartidaLider' => null,
            'TamanoClave' => null,
            'NoTiras' => 1,
            'Luchaje' => 1,
            'Repeticiones' => 1,
            'VelocidadSTD' => 120,
            'EficienciaSTD' => 100,
            'HorasProd' => 2,
            'PesoCrudo' => 0,
            'LargoToalla' => 0,
            'AnchoToalla' => 0,
            'FibraRizo' => '',
            'CuentaPie' => null,
            'CalibrePie2' => null,
            'MedidaPlano' => null,
            'LargoCrudo' => null,
            'AplicacionId' => null,
            'CalendarioId' => null,
            'NombreProducto' => 'PRODUCTO',
            'Ultimo' => null,
            'CambioHilo' => null,
            'UpdatedAt' => '2026-03-18 08:00:00',
        ], $attributes));
    }
}
