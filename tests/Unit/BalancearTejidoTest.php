<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Planeacion\ReqCalendarioLine;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class BalancearTejidoTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();

        // Make the default connection point to sqlsrv (which is now SQLite in-memory)
        // so that models without an explicit $connection (like ReqProgramaTejido) work correctly.
        config()->set('database.default', 'sqlsrv');

        // Ensure the table name is canonical
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        // Clear the static calendar cache between tests to avoid leakage
        $this->resetCalendarioCache();

        Schema::connection('sqlsrv')->create('ReqModelosCodificados', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('TamanoClave')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->integer('NoTiras')->default(0);
            $table->integer('Repeticiones')->default(0);
            $table->integer('Luchaje')->default(0);
            $table->float('Total')->default(0);
        });

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('OrdCompartida')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('ItemId')->nullable();
            $table->float('TotalPedido')->default(0);
            $table->float('SaldoPedido')->default(0);
            $table->float('Produccion')->default(0);
            $table->float('VelocidadSTD')->default(100);
            $table->float('EficienciaSTD')->default(0.85);
            $table->integer('EnProceso')->default(0);
            $table->string('FechaInicio')->nullable();
            $table->string('FechaFinal')->nullable();
            $table->string('CalendarioId')->nullable();
            $table->string('Ultimo')->default('0');
            $table->float('NoTiras')->default(4);
            $table->float('Luchaje')->default(200);
            $table->string('TamanoClave')->nullable();
            $table->float('StdDia')->default(0);
            $table->string('OrdCompartidaLider')->nullable();
            $table->integer('FibraRizo')->nullable();
            $table->float('PorcentajeSegundos')->default(0);
            $table->float('HorasProd')->default(0);
            $table->float('PesoCrudo')->default(0);
            $table->float('StdToaHra')->default(0);
            $table->float('StdHrsEfect')->default(0);
            $table->float('DiasEficiencia')->default(0);
            $table->float('DiasJornada')->default(0);
            $table->float('ProdKgDia')->default(0);
            $table->float('ProdKgDia2')->default(0);
            $table->float('PesoGRM2')->default(0);
            $table->string('EntregaProduc')->nullable();
            $table->string('EntregaPT')->nullable();
            $table->string('EntregaCte')->nullable();
            $table->float('PTvsCte')->default(0);
            $table->integer('Posicion')->default(0);
            $table->string('OrdPrincipal')->nullable();
            $table->string('UpdatedAt')->nullable();
            $table->string('NoProduccion')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('ReqCalendarioLine');
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        Schema::connection('sqlsrv')->dropIfExists('ReqModelosCodificados');
        config()->set('planeacion.req_calendario_line_table', null);
        parent::tearDown();
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function resetCalendarioCache(): void
    {
        BalancearTejido::clearCalendarioLinesCache();
    }

    private function makeReg(array $attrs): ReqProgramaTejido
    {
        return ReqProgramaTejido::create(array_merge([
            'OrdCompartida' => 1,
            'SalonTejidoId' => 'A',
            'NoTelarId' => '01',
            'TotalPedido' => 5000,
            'SaldoPedido' => 5000,
            'Produccion' => 0,
            'VelocidadSTD' => 100,
            'EficienciaSTD' => 0.85,
            'EnProceso' => 0,
            'FechaInicio' => now()->subDays(5)->format('Y-m-d H:i:s'),
            'CalendarioId' => null,
            'Ultimo' => '0',
            'NoTiras' => 4,
            'Luchaje' => 200,
            'TamanoClave' => null,
        ], $attrs));
    }

    private function makeModelo(array $attrs = []): ReqModelosCodificados
    {
        return ReqModelosCodificados::create(array_merge([
            'TamanoClave' => 'TEST-TOA',
            'SalonTejidoId' => 'A',
            'NoTiras' => 4,
            'Repeticiones' => 16,
            'Luchaje' => 200,
            'Total' => 600,
        ], $attrs));
    }

    private function callBalanceoAuto(int $ordCompartida, string $fechaFinObjetivo): array
    {
        $request = \Illuminate\Http\Request::create('/test', 'POST', [
            'ord_compartida' => $ordCompartida,
            'fecha_fin_objetivo' => $fechaFinObjetivo,
        ]);
        $response = BalancearTejido::balancearAutomatico($request);

        return json_decode($response->getContent(), true);
    }

    private function callActualizarPedidos(int $ordCompartida, array $cambios): array
    {
        $request = \Illuminate\Http\Request::create('/test', 'POST', [
            'ord_compartida' => $ordCompartida,
            'cambios' => $cambios,
        ]);

        $response = BalancearTejido::actualizarPedidos($request);

        return json_decode($response->getContent(), true);
    }

    // =========================================================
    // Tests
    // =========================================================

    /**
     * Test 1: balancearAutomatico returns cambios for all records.
     */
    public function test_balanceo_automatico_retorna_cambios_para_todos_los_registros(): void
    {
        $reg1 = $this->makeReg(['OrdCompartida' => 10, 'NoTelarId' => '01', 'FechaInicio' => now()->subDays(30)->format('Y-m-d H:i:s')]);
        $reg2 = $this->makeReg(['OrdCompartida' => 10, 'NoTelarId' => '02', 'FechaInicio' => now()->subDays(30)->format('Y-m-d H:i:s')]);
        $reg3 = $this->makeReg(['OrdCompartida' => 10, 'NoTelarId' => '03', 'FechaInicio' => now()->subDays(30)->format('Y-m-d H:i:s')]);
        $reg4 = $this->makeReg(['OrdCompartida' => 10, 'NoTelarId' => '04', 'FechaInicio' => now()->subDays(30)->format('Y-m-d H:i:s')]);

        $result = $this->callBalanceoAuto(10, now()->addDays(60)->format('Y-m-d'));

        $this->assertTrue($result['success'], 'La respuesta debe ser exitosa');
        $this->assertArrayHasKey('cambios', $result, 'La respuesta debe tener clave cambios');
        $this->assertCount(4, $result['cambios'], 'Deben retornarse 4 cambios');

        $idsEnCambios = array_column($result['cambios'], 'id');
        $this->assertContains((int) $reg1->Id, $idsEnCambios, 'El ID del registro 1 debe estar en cambios');
        $this->assertContains((int) $reg2->Id, $idsEnCambios, 'El ID del registro 2 debe estar en cambios');
        $this->assertContains((int) $reg3->Id, $idsEnCambios, 'El ID del registro 3 debe estar en cambios');
        $this->assertContains((int) $reg4->Id, $idsEnCambios, 'El ID del registro 4 debe estar en cambios');
    }

    /**
     * Test 2: With exactly 2 records, the FIRST is protected (unchanged), the LAST absorbs adjustment.
     */
    public function test_caso_especial_2_registros_primero_protegido_ultimo_ajustado(): void
    {
        // Seed a model so calcularHorasProd returns a non-zero rate
        // (NoTiras=4, Repeticiones=16, Luchaje=200, Total=600, VelocidadSTD=100, EficienciaSTD=0.85)
        // => stdToaHra ≈ 28.4 toallas/hr → 5000 toallas ≈ 207 hours ≈ 8.6 days
        // With a 3-day target horizon, reg2's calculated pedido ≈ 5000*(3/8.6) ≈ 1743, clearly ≠ 5000
        $this->makeModelo();

        $fechaInicio = now()->subDays(10)->format('Y-m-d H:i:s');

        $reg1 = $this->makeReg([
            'OrdCompartida' => 20,
            'NoTelarId' => '01',
            'FechaInicio' => $fechaInicio,
            'TotalPedido' => 5000,
            'SaldoPedido' => 5000,
            'Produccion' => 0,
            'TamanoClave' => 'TEST-TOA',
        ]);
        $reg2 = $this->makeReg([
            'OrdCompartida' => 20,
            'NoTelarId' => '02',
            'FechaInicio' => $fechaInicio,
            'TotalPedido' => 5000,
            'SaldoPedido' => 5000,
            'Produccion' => 0,
            'TamanoClave' => 'TEST-TOA',
        ]);

        // Short 3-day horizon forces reg2 to be recalculated below 5000
        $result = $this->callBalanceoAuto(20, now()->addDays(3)->format('Y-m-d'));

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['cambios']);

        $cambiosPorId = [];
        foreach ($result['cambios'] as $c) {
            $cambiosPorId[$c['id']] = $c;
        }

        // El PRIMER registro (reg1, NoTelarId '01', index 0) está protegido — no debe cambiar
        $this->assertArrayHasKey((int) $reg1->Id, $cambiosPorId, 'El primer registro debe estar en cambios');
        $this->assertEquals(
            5000,
            (float) $cambiosPorId[(int) $reg1->Id]['total_pedido'],
            'El primer registro en caso de 2 no debe cambiar (protegido)'
        );

        // El primero queda fijo en 5000; el cierre de total se aplica solo al último telar (Posicion/NoTelarId).
        $this->assertArrayHasKey((int) $reg2->Id, $cambiosPorId, 'El último registro debe estar en cambios');
        $this->assertGreaterThan(0, (float) $cambiosPorId[(int) $reg2->Id]['total_pedido']);
        $sumPedidos = (float) $cambiosPorId[(int) $reg1->Id]['total_pedido'] + (float) $cambiosPorId[(int) $reg2->Id]['total_pedido'];
        $this->assertEqualsWithDelta(10000.0, $sumPedidos, 0.01, 'El total del grupo debe conservarse');
    }

    /**
     * Test 3: EnProceso record gets a recalculated pedido and its total_pedido >= Produccion.
     */
    public function test_enproceso_registro_recibe_cambio_razonable(): void
    {
        $this->makeReg([
            'OrdCompartida' => 30,
            'NoTelarId' => '01',
            'FechaInicio' => now()->subDays(10)->format('Y-m-d H:i:s'),
            'TotalPedido' => 5000,
            'SaldoPedido' => 5000,
            'Produccion' => 0,
            'EnProceso' => 0,
        ]);

        $this->makeReg([
            'OrdCompartida' => 30,
            'NoTelarId' => '02',
            'FechaInicio' => now()->subDays(10)->format('Y-m-d H:i:s'),
            'TotalPedido' => 5000,
            'SaldoPedido' => 5000,
            'Produccion' => 0,
            'EnProceso' => 0,
        ]);

        $regEnProceso = $this->makeReg([
            'OrdCompartida' => 30,
            'NoTelarId' => '03',
            'FechaInicio' => now()->subDays(60)->format('Y-m-d H:i:s'),
            'TotalPedido' => 5000,
            'SaldoPedido' => 4000,
            'Produccion' => 1000,
            'EnProceso' => 1,
        ]);

        $result = $this->callBalanceoAuto(30, now()->addDays(20)->format('Y-m-d'));

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['cambios']);

        $idsEnCambios = array_column($result['cambios'], 'id');
        $this->assertContains((int) $regEnProceso->Id, $idsEnCambios, 'El registro EnProceso debe estar en cambios');

        // Find the EnProceso entry
        $cambioEnProceso = null;
        foreach ($result['cambios'] as $c) {
            if ((int) $c['id'] === (int) $regEnProceso->Id) {
                $cambioEnProceso = $c;
                break;
            }
        }

        $this->assertNotNull($cambioEnProceso, 'El cambio del registro EnProceso debe encontrarse');
        $this->assertGreaterThanOrEqual(
            1000,
            (float) $cambioEnProceso['total_pedido'],
            'El total_pedido del registro EnProceso nunca debe ser menor a la Produccion ya realizada'
        );
    }

    /**
     * Test 4: With 8 records, all get a cambio entry.
     */
    public function test_balanceo_automatico_con_8_registros_todos_tienen_cambio(): void
    {
        $ids = [];
        for ($i = 1; $i <= 8; $i++) {
            $telarId = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $reg = $this->makeReg([
                'OrdCompartida' => 40,
                'NoTelarId' => $telarId,
                'FechaInicio' => now()->subDays(5)->format('Y-m-d H:i:s'),
                'TotalPedido' => 5000,
                'SaldoPedido' => 5000,
                'Produccion' => 0,
            ]);
            $ids[] = (int) $reg->Id;
        }

        $result = $this->callBalanceoAuto(40, now()->addDays(90)->format('Y-m-d'));

        $this->assertTrue($result['success']);
        $this->assertCount(8, $result['cambios'], 'Deben retornarse 8 cambios para 8 registros');

        $idsEnCambios = array_column($result['cambios'], 'id');
        foreach ($ids as $id) {
            $this->assertContains($id, $idsEnCambios, "El ID $id debe estar en cambios");
        }

        foreach ($result['cambios'] as $cambio) {
            $this->assertGreaterThanOrEqual(1, (float) $cambio['total_pedido'], 'Cada total_pedido debe ser al menos 1');
        }
    }

    public function test_actualizar_pedidos_balanceo_conserva_orden_lider_actual(): void
    {
        $liderOriginal = $this->makeReg([
            'OrdCompartida' => 50,
            'NoTelarId' => '02',
            'FechaInicio' => now()->subDays(5)->format('Y-m-d H:i:s'),
            'OrdCompartidaLider' => 1,
            'ItemId' => 'LID-AX-1',
        ]);

        $noLider = $this->makeReg([
            'OrdCompartida' => 50,
            'NoTelarId' => '01',
            'FechaInicio' => now()->subDays(10)->format('Y-m-d H:i:s'),
            'OrdCompartidaLider' => null,
        ]);

        $result = $this->callActualizarPedidos(50, [
            [
                'id' => (int) $noLider->Id,
                'total_pedido' => 4200,
                'modo' => 'total',
            ],
        ]);

        $this->assertTrue($result['success'], $result['message'] ?? 'La actualizacion de pedidos fallo');
        $this->assertFalse((bool) $noLider->fresh()->OrdCompartidaLider);
        $this->assertTrue((bool) $liderOriginal->fresh()->OrdCompartidaLider);
    }

    /**
     * Test 5: No records for the given OrdCompartida returns empty cambios.
     */
    public function test_balanceo_automatico_sin_registros_retorna_cambios_vacios(): void
    {
        // No records inserted for OrdCompartida 9999
        $result = $this->callBalanceoAuto(9999, now()->addDays(30)->format('Y-m-d'));

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('cambios', $result);
        $this->assertEmpty($result['cambios'], 'cambios debe ser array vacío cuando no hay registros');
    }

    public function test_preview_fechas_modo_saldo_respeta_total_y_saldo(): void
    {
        $this->makeModelo();
        $reg = $this->makeReg([
            'OrdCompartida' => 60,
            'TamanoClave' => 'TEST-TOA',
            'TotalPedido' => 5000,
            'SaldoPedido' => 4000,
            'Produccion' => 1000,
        ]);

        $request = Request::create('/test', 'POST', [
            'ord_compartida' => 60,
            'cambios' => [[
                'id' => (int) $reg->Id,
                'total_pedido' => 500,
                'modo' => 'saldo',
            ]],
        ]);

        $response = BalancearTejido::previewFechas($request);
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $row = $data['data'][0];
        $this->assertSame(1500.0, (float) $row['total']);
        $this->assertSame(500.0, (float) $row['saldo']);
    }

    public function test_preview_fechas_rechaza_registro_de_otro_ord_compartida(): void
    {
        $this->makeModelo();
        $reg = $this->makeReg([
            'OrdCompartida' => 61,
            'TamanoClave' => 'TEST-TOA',
        ]);

        $request = Request::create('/test', 'POST', [
            'ord_compartida' => 999,
            'cambios' => [[
                'id' => (int) $reg->Id,
                'total_pedido' => 1000,
                'modo' => 'total',
            ]],
        ]);

        $response = BalancearTejido::previewFechas($request);
        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_preview_fechas_rechaza_id_inexistente(): void
    {
        $request = Request::create('/test', 'POST', [
            'ord_compartida' => 1,
            'cambios' => [[
                'id' => 999999,
                'total_pedido' => 1000,
                'modo' => 'total',
            ]],
        ]);

        $response = BalancearTejido::previewFechas($request);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_actualizar_pedidos_rechaza_registro_de_otro_ord_compartida(): void
    {
        $this->makeModelo();
        $regOtro = $this->makeReg([
            'OrdCompartida' => 71,
            'TamanoClave' => 'TEST-TOA',
            'NoTelarId' => '99',
        ]);

        $request = Request::create('/test', 'POST', [
            'ord_compartida' => 70,
            'cambios' => [[
                'id' => (int) $regOtro->Id,
                'total_pedido' => 2000,
                'modo' => 'total',
            ]],
        ]);

        $response = BalancearTejido::actualizarPedidos($request);
        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_balanceo_automatico_con_lineas_calendario_en_sqlite(): void
    {
        config()->set('planeacion.req_calendario_line_table', 'ReqCalendarioLine');

        Schema::connection('sqlsrv')->create('ReqCalendarioLine', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('CalendarioId')->nullable();
            $table->string('FechaInicio')->nullable();
            $table->string('FechaFin')->nullable();
        });

        $this->makeModelo();
        $calId = 'CAL-UT';
        $base = Carbon::now()->subDay()->startOfDay();
        for ($d = 0; $d < 120; $d++) {
            $day = $base->copy()->addDays($d);
            ReqCalendarioLine::query()->insert([
                'CalendarioId' => $calId,
                'FechaInicio' => $day->format('Y-m-d 06:00:00'),
                'FechaFin' => $day->copy()->setTime(22, 0, 0)->format('Y-m-d H:i:s'),
            ]);
        }

        $this->makeReg([
            'OrdCompartida' => 80,
            'NoTelarId' => '01',
            'TamanoClave' => 'TEST-TOA',
            'CalendarioId' => $calId,
            'FechaInicio' => $base->copy()->format('Y-m-d 07:00:00'),
        ]);

        $result = $this->callBalanceoAuto(80, now()->addDays(25)->format('Y-m-d'));
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertArrayHasKey('cambios', $result);
        $this->assertCount(1, $result['cambios']);
    }

    public function test_clear_calendario_lines_cache_es_idempotente(): void
    {
        BalancearTejido::clearCalendarioLinesCache();
        BalancearTejido::clearCalendarioLinesCache();
        $this->assertTrue(true);
    }

    public function test_ajustar_total_exceso_solo_reduce_el_ultimo_registro(): void
    {
        $registros = [
            (object) ['Id' => 101, 'Produccion' => 0.0, 'TotalPedido' => 4000],
            (object) ['Id' => 102, 'Produccion' => 0.0, 'TotalPedido' => 4000],
            (object) ['Id' => 103, 'Produccion' => 3800.0, 'TotalPedido' => 4000],
        ];
        $nuevosPedidos = [
            101 => ['id' => 101, 'total_pedido' => 4000, 'modo' => 'total'],
            102 => ['id' => 102, 'total_pedido' => 4000, 'modo' => 'total'],
            103 => ['id' => 103, 'total_pedido' => 4000, 'modo' => 'total'],
        ];

        $meta = $this->invokeAjustarPedidosAlTotalObjetivo($nuevosPedidos, $registros, 10000.0);

        $this->assertSame(4000, $meta['nuevos_pedidos'][101]['total_pedido']);
        $this->assertSame(4000, $meta['nuevos_pedidos'][102]['total_pedido']);
        $this->assertSame(3800, $meta['nuevos_pedidos'][103]['total_pedido']);
        $this->assertNotNull($meta['advertencia_total']);
        $this->assertNotNull($meta['total_diferencia_vs_objetivo']);
    }

    public function test_ajustar_total_exceso_ultimo_absorbe_todo_sin_advertencia(): void
    {
        $registros = [
            (object) ['Id' => 201, 'Produccion' => 0.0, 'TotalPedido' => 4000],
            (object) ['Id' => 202, 'Produccion' => 0.0, 'TotalPedido' => 4000],
            (object) ['Id' => 203, 'Produccion' => 0.0, 'TotalPedido' => 4000],
        ];
        $nuevosPedidos = [
            201 => ['id' => 201, 'total_pedido' => 4000, 'modo' => 'total'],
            202 => ['id' => 202, 'total_pedido' => 4000, 'modo' => 'total'],
            203 => ['id' => 203, 'total_pedido' => 4000, 'modo' => 'total'],
        ];

        $meta = $this->invokeAjustarPedidosAlTotalObjetivo($nuevosPedidos, $registros, 10000.0);

        $this->assertSame(4000, $meta['nuevos_pedidos'][201]['total_pedido']);
        $this->assertSame(4000, $meta['nuevos_pedidos'][202]['total_pedido']);
        $this->assertSame(2000, $meta['nuevos_pedidos'][203]['total_pedido']);
        $this->assertNull($meta['advertencia_total']);
        $this->assertNull($meta['total_diferencia_vs_objetivo']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nuevosPedidosById
     * @param  array<int, object>  $registrosArray
     * @return array{nuevos_pedidos: array, advertencia_total: ?string, total_diferencia_vs_objetivo: ?float}
     */
    private function invokeAjustarPedidosAlTotalObjetivo(array $nuevosPedidosById, array $registrosArray, float $totalObjetivo): array
    {
        $method = new \ReflectionMethod(BalancearTejido::class, 'ajustarPedidosAlTotalObjetivo');
        $method->setAccessible(true);

        /** @var array{nuevos_pedidos: array, advertencia_total: ?string, total_diferencia_vs_objetivo: ?float} */
        return $method->invoke(null, $nuevosPedidosById, $registrosArray, $totalObjetivo);
    }
}
