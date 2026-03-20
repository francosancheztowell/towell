<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejido\Reportes\ReporteInvTelasController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ReporteInvTelasControllerTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'sqlite');

        DB::purge('sqlite');
        DB::connection('sqlite')->getPdo();

        $this->useSqlsrvSqlite();
        $this->crearTablasReporte();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_no_julio_se_muestra_sin_prefijo_nj(): void
    {
        Carbon::setTestNow('2026-03-19 08:00:00');
        session()->start();
        session(['liberar_ordenes_dias' => 2]);

        DB::connection('sqlite')->table('tej_inventario_telares')->insert([
            'id' => 1,
            'no_telar' => '207',
            'status' => 'Activo',
            'tipo' => 'Rizo',
            'cuenta' => '40',
            'calibre' => 12,
            'hilo' => 'ALG',
            'fecha' => '2026-03-20',
            'turno' => 1,
            'no_julio' => 'J-207',
            'no_orden' => null,
            'Reservado' => 1,
            'Programado' => 0,
        ]);

        $datos = $this->ejecutarReporte('2026-03-20', '2026-03-20');
        $fila = $this->buscarFilaPorTelar($datos['secciones'], '207');
        $turno1 = $fila['por_dia']['2026-03-20']['turnos'][1];

        $this->assertSame('blue', $turno1['color']);
        // Debe mostrar el julio sin prefijo "NJ: "
        $this->assertStringContainsString('J-207', $turno1['texto']);
        $this->assertStringNotContainsString('NJ:', $turno1['texto']);
        $this->assertStringNotContainsString('NJ :', $turno1['texto']);
    }

    public function test_no_orden_se_muestra_en_programado(): void
    {
        Carbon::setTestNow('2026-03-19 08:00:00');
        session()->start();
        session(['liberar_ordenes_dias' => 2]);

        DB::connection('sqlite')->table('tej_inventario_telares')->insert([
            'id' => 1,
            'no_telar' => '208',
            'status' => 'Activo',
            'tipo' => 'Pie',
            'cuenta' => '30',
            'calibre' => 10,
            'hilo' => 'COT',
            'fecha' => '2026-03-20',
            'turno' => 1,
            'no_julio' => null,
            'no_orden' => 'F208',
            'Reservado' => 0,
            'Programado' => 1,
        ]);

        $datos = $this->ejecutarReporte('2026-03-20', '2026-03-20');
        $fila = $this->buscarFilaPorTelar($datos['secciones'], '208');
        $turno1 = $fila['por_dia']['2026-03-20']['turnos'][1];

        $this->assertSame('orange', $turno1['color']);
        // Debe mostrar el no_orden sin prefijo
        $this->assertStringContainsString('F208', $turno1['texto']);
    }

    public function test_no_orden_no_se_muestra_en_reservado(): void
    {
        Carbon::setTestNow('2026-03-19 08:00:00');
        session()->start();
        session(['liberar_ordenes_dias' => 2]);

        DB::connection('sqlite')->table('tej_inventario_telares')->insert([
            'id' => 1,
            'no_telar' => '207',
            'status' => 'Activo',
            'tipo' => 'Rizo',
            'cuenta' => '40',
            'calibre' => 12,
            'hilo' => 'ALG',
            'fecha' => '2026-03-20',
            'turno' => 1,
            'no_julio' => 'J-207',
            'no_orden' => 'F207',
            'Reservado' => 1,
            'Programado' => 0,
        ]);

        $datos = $this->ejecutarReporte('2026-03-20', '2026-03-20');
        $fila = $this->buscarFilaPorTelar($datos['secciones'], '207');
        $turno1 = $fila['por_dia']['2026-03-20']['turnos'][1];

        // Reservado = blue, debe mostrar julio, NO el no_orden
        $this->assertSame('blue', $turno1['color']);
        $this->assertStringContainsString('J-207', $turno1['texto']);
        $this->assertStringNotContainsString('F207', $turno1['texto']);
    }

    public function test_multiples_turnos_mantienen_posicion(): void
    {
        Carbon::setTestNow('2026-03-19 08:00:00');
        session()->start();
        session(['liberar_ordenes_dias' => 2]);

        // Turno 1 y turno 3 con datos, turno 2 vacio
        DB::connection('sqlite')->table('tej_inventario_telares')->insert([
            [
                'id' => 1,
                'no_telar' => '207',
                'status' => 'Activo',
                'tipo' => 'Rizo',
                'cuenta' => '40',
                'calibre' => 12,
                'hilo' => 'ALG',
                'fecha' => '2026-03-20',
                'turno' => 1,
                'no_julio' => 'J-001',
                'no_orden' => null,
                'Reservado' => 1,
                'Programado' => 0,
            ],
            [
                'id' => 2,
                'no_telar' => '207',
                'status' => 'Activo',
                'tipo' => 'Rizo',
                'cuenta' => '40',
                'calibre' => 12,
                'hilo' => 'ALG',
                'fecha' => '2026-03-20',
                'turno' => 3,
                'no_julio' => 'J-002',
                'no_orden' => null,
                'Reservado' => 1,
                'Programado' => 0,
            ],
        ]);

        $datos = $this->ejecutarReporte('2026-03-20', '2026-03-20');
        $fila = $this->buscarFilaPorTelar($datos['secciones'], '207');
        $turnos = $fila['por_dia']['2026-03-20']['turnos'];

        // Turno 1 tiene datos
        $this->assertStringContainsString('J-001', $turnos[1]['texto']);
        // Turno 2 vacio
        $this->assertSame('', $turnos[2]['texto']);
        // Turno 3 tiene datos
        $this->assertStringContainsString('J-002', $turnos[3]['texto']);
    }

    public function test_colores_correctos_reservado_programado_amarillo(): void
    {
        Carbon::setTestNow('2026-03-19 08:00:00');
        session()->start();
        session(['liberar_ordenes_dias' => 2]);

        DB::connection('sqlite')->table('tej_inventario_telares')->insert([
            [
                'id' => 1,
                'no_telar' => '207',
                'status' => 'Activo',
                'tipo' => 'Rizo',
                'cuenta' => '40',
                'calibre' => 12,
                'hilo' => 'ALG',
                'fecha' => '2026-03-20',
                'turno' => 1,
                'no_julio' => 'J-207',
                'no_orden' => null,
                'Reservado' => 1,
                'Programado' => 0,
            ],
            [
                'id' => 2,
                'no_telar' => '208',
                'status' => 'Activo',
                'tipo' => 'Pie',
                'cuenta' => '30',
                'calibre' => 10,
                'hilo' => 'COT',
                'fecha' => '2026-03-20',
                'turno' => 1,
                'no_julio' => null,
                'no_orden' => 'F208',
                'Reservado' => 0,
                'Programado' => 1,
            ],
            [
                'id' => 3,
                'no_telar' => '210',
                'status' => 'Activo',
                'tipo' => 'Rizo',
                'cuenta' => '70',
                'calibre' => 16,
                'hilo' => 'POL',
                'fecha' => '2026-03-20',
                'turno' => 1,
                'no_julio' => null,
                'no_orden' => null,
                'Reservado' => 0,
                'Programado' => 0,
            ],
        ]);

        // Telar 209: amarillo via ReqProgramaTejido
        DB::connection('sqlite')->table('ReqProgramaTejido')->insert([
            'Id' => 1,
            'NoTelarId' => '209',
            'EnProceso' => 1,
            'CuentaRizo' => '50',
            'CuentaPie' => null,
            'FibraRizo' => 'PES',
            'FibraPie' => null,
            'CalibreRizo' => 14,
            'CalibrePie' => null,
            'NoProduccion' => '',
            'NoExisteBase' => null,
            'FechaInicio' => '2026-03-20 00:00:00',
        ]);

        $datos = $this->ejecutarReporte('2026-03-20', '2026-03-20');

        $fila207 = $this->buscarFilaPorTelar($datos['secciones'], '207');
        $fila208 = $this->buscarFilaPorTelar($datos['secciones'], '208');
        $fila209 = $this->buscarFilaPorTelar($datos['secciones'], '209');
        $fila210 = $this->buscarFilaPorTelar($datos['secciones'], '210');

        // 207: Reservado = blue
        $this->assertSame('blue', $fila207['por_dia']['2026-03-20']['turnos'][1]['color']);
        // 208: Programado = orange
        $this->assertSame('orange', $fila208['por_dia']['2026-03-20']['turnos'][1]['color']);
        // 209: amarillo (via ReqProgramaTejido sin produccion)
        $this->assertSame('yellow', $fila209['por_dia']['2026-03-20']['turnos'][1]['color']);
        // 210: sin reserva ni programado = yellow
        $this->assertSame('yellow', $fila210['por_dia']['2026-03-20']['turnos'][1]['color']);

        // Verificar que fallback aplica fibra y cuenta de ReqProgramaTejido
        $this->assertSame('PES', $fila209['fibra']);
        $this->assertSame('50', $fila209['cuenta_rizo']);
    }

    public function test_excel_usa_3_columnas_por_dia_con_alineacion_por_turno(): void
    {
        Carbon::setTestNow('2026-03-19 08:00:00');
        session()->start();
        session(['liberar_ordenes_dias' => 2]);

        // T1 reservado con julio, T3 programado con orden
        DB::connection('sqlite')->table('tej_inventario_telares')->insert([
            [
                'id' => 1,
                'no_telar' => '207',
                'status' => 'Activo',
                'tipo' => 'Rizo',
                'cuenta' => '40',
                'calibre' => 12,
                'hilo' => 'ALG',
                'fecha' => '2026-03-20',
                'turno' => 1,
                'no_julio' => 'J-001',
                'no_orden' => null,
                'Reservado' => 1,
                'Programado' => 0,
            ],
            [
                'id' => 2,
                'no_telar' => '207',
                'status' => 'Activo',
                'tipo' => 'Rizo',
                'cuenta' => '40',
                'calibre' => 12,
                'hilo' => 'ALG',
                'fecha' => '2026-03-20',
                'turno' => 3,
                'no_julio' => null,
                'no_orden' => 'F207',
                'Reservado' => 0,
                'Programado' => 1,
            ],
        ]);

        $datos = $this->ejecutarReporte('2026-03-20', '2026-03-20');
        $fila = $this->buscarFilaPorTelar($datos['secciones'], '207');
        $turnos = $fila['por_dia']['2026-03-20']['turnos'];

        // Turno 1: tiene julio, alineacion izquierda en Excel
        $this->assertStringContainsString('J-001', $turnos[1]['texto']);
        $this->assertSame('blue', $turnos[1]['color']);

        // Turno 2: vacio
        $this->assertSame('', $turnos[2]['texto']);

        // Turno 3: tiene no_orden, alineacion derecha en Excel
        $this->assertStringContainsString('F207', $turnos[3]['texto']);
        $this->assertSame('orange', $turnos[3]['color']);
    }

    private function ejecutarReporte(string $fechaIni, string $fechaFin): array
    {
        $controller = new class extends ReporteInvTelasController
        {
            public function datos(string $fechaIni, string $fechaFin): array
            {
                return $this->obtenerDatosReporte($fechaIni, $fechaFin);
            }
        };

        return $controller->datos($fechaIni, $fechaFin);
    }

    private function crearTablasReporte(): void
    {
        Schema::connection('sqlite')->create('tej_inventario_telares', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_telar')->nullable();
            $table->string('status')->nullable();
            $table->string('tipo')->nullable();
            $table->string('cuenta')->nullable();
            $table->float('calibre')->nullable();
            $table->string('hilo')->nullable();
            $table->date('fecha')->nullable();
            $table->integer('turno')->default(1);
            $table->string('no_julio')->nullable();
            $table->string('no_orden')->nullable();
            $table->boolean('Reservado')->default(false);
            $table->boolean('Programado')->default(false);
        });

        Schema::connection('sqlite')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoTelarId')->nullable();
            $table->boolean('EnProceso')->default(false);
            $table->string('CuentaRizo')->nullable();
            $table->string('CuentaPie')->nullable();
            $table->string('FibraRizo')->nullable();
            $table->string('FibraPie')->nullable();
            $table->float('CalibreRizo')->nullable();
            $table->float('CalibrePie')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('NoExisteBase')->nullable();
            $table->dateTime('FechaInicio')->nullable();
        });

        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('RizoPie')->nullable();
            $table->string('Status')->nullable();
        });

        Schema::connection('sqlsrv')->create('EngProgramaEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('RizoPie')->nullable();
            $table->string('Status')->nullable();
        });
    }

    private function buscarFilaPorTelar(array $secciones, string $noTelar): array
    {
        foreach ($secciones as $seccion) {
            foreach (($seccion['filas'] ?? []) as $fila) {
                if ((string) ($fila['no_telar'] ?? '') === $noTelar) {
                    return $fila;
                }
            }
        }

        $this->fail("No se encontro la fila del telar {$noTelar}");
    }
}
