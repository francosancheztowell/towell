<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
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
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        Schema::connection('sqlsrv')->dropIfExists('ReqModelosCodificados');
        parent::tearDown();
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * Clear the private static calendar cache on BalancearTejido between tests.
     */
    private function resetCalendarioCache(): void
    {
        $reflection = new \ReflectionClass(BalancearTejido::class);
        $prop = $reflection->getProperty('calLinesCache');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    private function makeReg(array $attrs): ReqProgramaTejido
    {
        return ReqProgramaTejido::create(array_merge([
            'OrdCompartida' => 1,
            'SalonTejidoId' => 'A',
            'NoTelarId'     => '01',
            'TotalPedido'   => 5000,
            'SaldoPedido'   => 5000,
            'Produccion'    => 0,
            'VelocidadSTD'  => 100,
            'EficienciaSTD' => 0.85,
            'EnProceso'     => 0,
            'FechaInicio'   => now()->subDays(5)->format('Y-m-d H:i:s'),
            'CalendarioId'  => null,
            'Ultimo'        => '0',
            'NoTiras'       => 4,
            'Luchaje'       => 200,
            'TamanoClave'   => null,
        ], $attrs));
    }

    private function makeModelo(array $attrs = []): ReqModelosCodificados
    {
        return ReqModelosCodificados::create(array_merge([
            'TamanoClave'  => 'TEST-TOA',
            'SalonTejidoId'=> 'A',
            'NoTiras'      => 4,
            'Repeticiones' => 16,
            'Luchaje'      => 200,
            'Total'        => 600,
        ], $attrs));
    }

    private function callBalanceoAuto(int $ordCompartida, string $fechaFinObjetivo): array
    {
        $request = \Illuminate\Http\Request::create('/test', 'POST', [
            'ord_compartida'     => $ordCompartida,
            'fecha_fin_objetivo' => $fechaFinObjetivo,
        ]);
        $response = BalancearTejido::balancearAutomatico($request);
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
        $this->assertContains((int)$reg1->Id, $idsEnCambios, 'El ID del registro 1 debe estar en cambios');
        $this->assertContains((int)$reg2->Id, $idsEnCambios, 'El ID del registro 2 debe estar en cambios');
        $this->assertContains((int)$reg3->Id, $idsEnCambios, 'El ID del registro 3 debe estar en cambios');
        $this->assertContains((int)$reg4->Id, $idsEnCambios, 'El ID del registro 4 debe estar en cambios');
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
            'NoTelarId'     => '01',
            'FechaInicio'   => $fechaInicio,
            'TotalPedido'   => 5000,
            'SaldoPedido'   => 5000,
            'Produccion'    => 0,
            'TamanoClave'   => 'TEST-TOA',
        ]);
        $reg2 = $this->makeReg([
            'OrdCompartida' => 20,
            'NoTelarId'     => '02',
            'FechaInicio'   => $fechaInicio,
            'TotalPedido'   => 5000,
            'SaldoPedido'   => 5000,
            'Produccion'    => 0,
            'TamanoClave'   => 'TEST-TOA',
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
        $this->assertArrayHasKey((int)$reg1->Id, $cambiosPorId, 'El primer registro debe estar en cambios');
        $this->assertEquals(
            5000,
            (float)$cambiosPorId[(int)$reg1->Id]['total_pedido'],
            'El primer registro en caso de 2 no debe cambiar (protegido)'
        );

        // El ÚLTIMO registro (reg2, NoTelarId '02', index 1) absorbe el ajuste — debe diferir de 5000
        $this->assertArrayHasKey((int)$reg2->Id, $cambiosPorId, 'El último registro debe estar en cambios');
        $this->assertGreaterThan(0, (float)$cambiosPorId[(int)$reg2->Id]['total_pedido']);
        $this->assertNotEquals(5000, (float)$cambiosPorId[(int)$reg2->Id]['total_pedido'], 'El último registro absorbe el ajuste y debe diferir de 5000');
    }

    /**
     * Test 3: EnProceso record gets a recalculated pedido and its total_pedido >= Produccion.
     */
    public function test_enproceso_registro_recibe_cambio_razonable(): void
    {
        $this->makeReg([
            'OrdCompartida' => 30,
            'NoTelarId'     => '01',
            'FechaInicio'   => now()->subDays(10)->format('Y-m-d H:i:s'),
            'TotalPedido'   => 5000,
            'SaldoPedido'   => 5000,
            'Produccion'    => 0,
            'EnProceso'     => 0,
        ]);

        $this->makeReg([
            'OrdCompartida' => 30,
            'NoTelarId'     => '02',
            'FechaInicio'   => now()->subDays(10)->format('Y-m-d H:i:s'),
            'TotalPedido'   => 5000,
            'SaldoPedido'   => 5000,
            'Produccion'    => 0,
            'EnProceso'     => 0,
        ]);

        $regEnProceso = $this->makeReg([
            'OrdCompartida' => 30,
            'NoTelarId'     => '03',
            'FechaInicio'   => now()->subDays(60)->format('Y-m-d H:i:s'),
            'TotalPedido'   => 5000,
            'SaldoPedido'   => 4000,
            'Produccion'    => 1000,
            'EnProceso'     => 1,
        ]);

        $result = $this->callBalanceoAuto(30, now()->addDays(20)->format('Y-m-d'));

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['cambios']);

        $idsEnCambios = array_column($result['cambios'], 'id');
        $this->assertContains((int)$regEnProceso->Id, $idsEnCambios, 'El registro EnProceso debe estar en cambios');

        // Find the EnProceso entry
        $cambioEnProceso = null;
        foreach ($result['cambios'] as $c) {
            if ((int)$c['id'] === (int)$regEnProceso->Id) {
                $cambioEnProceso = $c;
                break;
            }
        }

        $this->assertNotNull($cambioEnProceso, 'El cambio del registro EnProceso debe encontrarse');
        $this->assertGreaterThanOrEqual(
            1000,
            (float)$cambioEnProceso['total_pedido'],
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
            $telarId = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $reg = $this->makeReg([
                'OrdCompartida' => 40,
                'NoTelarId'     => $telarId,
                'FechaInicio'   => now()->subDays(5)->format('Y-m-d H:i:s'),
                'TotalPedido'   => 5000,
                'SaldoPedido'   => 5000,
                'Produccion'    => 0,
            ]);
            $ids[] = (int)$reg->Id;
        }

        $result = $this->callBalanceoAuto(40, now()->addDays(90)->format('Y-m-d'));

        $this->assertTrue($result['success']);
        $this->assertCount(8, $result['cambios'], 'Deben retornarse 8 cambios para 8 registros');

        $idsEnCambios = array_column($result['cambios'], 'id');
        foreach ($ids as $id) {
            $this->assertContains($id, $idsEnCambios, "El ID $id debe estar en cambios");
        }

        foreach ($result['cambios'] as $cambio) {
            $this->assertGreaterThanOrEqual(1, (float)$cambio['total_pedido'], 'Cada total_pedido debe ser al menos 1');
        }
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

}
