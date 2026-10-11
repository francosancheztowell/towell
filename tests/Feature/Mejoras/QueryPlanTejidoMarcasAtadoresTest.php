<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Http\Controllers\Atadores\ProgramaAtadores\AtaDevolucionesController;
use App\Http\Controllers\Tejido\MarcasFinales\MarcasController;
use App\Models\Inventario\InvSecuenciaMarcas;
use App\Models\Tejido\TejMarcas;
use App\Models\Tejido\TejMarcasLine;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\Feature\Tejido\Concerns\ModuloTejido;
use Tests\TestCase;

/**
 * Rama claude/mejora-query-tejido-tejedores-atadores (Tejido y Atadores):
 *  - Marcas finales: el reporte del día lee folios y líneas de los 3 turnos en 2 consultas.
 *  - Devoluciones: la sugerencia de julios lee solo 4 columnas de AtaMontadoTelas.
 */
class QueryPlanTejidoMarcasAtadoresTest extends TestCase
{
    use ModuloTejido;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaTejido(TejMarcas::class);
        $this->tablaTejido(TejMarcasLine::class);
        $this->tablaTejido(InvSecuenciaMarcas::class);
        DB::connection('sqlsrv')->table('InvSecuenciaMarcas')->insert([
            ['NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Orden' => 1],
            ['NoTelarId' => '202', 'SalonTejidoId' => 'JACQUARD', 'Orden' => 2],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function marca(string $folio, string $fecha, int $turno, array $lineas, string $empleado = '100'): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('TejMarcas')->insert(['Folio' => $folio, 'Date' => $fecha, 'Turno' => $turno, 'Status' => 'Finalizado', 'numero_empleado' => $empleado, 'nombreEmpl' => 'Emp '.$folio]);
        foreach ($lineas as $telar => $marcas) {
            $db->table('TejMarcasLine')->insert(['Folio' => $folio, 'Date' => $fecha, 'Turno' => $turno, 'NoTelarId' => (string) $telar, 'Marcas' => $marcas]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function reporte(string $fecha): array
    {
        $m = new ReflectionMethod(MarcasController::class, 'obtenerDatosReporte');

        return $m->invoke(app(MarcasController::class), $fecha);
    }

    public function test_reporte_reparte_lineas_por_turno_sin_mezclar_folios_ni_fechas(): void
    {
        $this->marca('FM0001', '2026-09-28', 1, [201 => 10, 202 => 11]);
        $this->marca('FM0003', '2026-09-28', 3, [202 => 30]);
        // Otro día (mismo turno): no debe colarse.
        $this->marca('FM0009', '2026-09-27', 2, [201 => 99]);

        $tablas = $this->reporte('2026-09-28');

        $this->assertSame([1, 2, 3], array_column($tablas, 'turno'));
        $this->assertSame(['FM0001', null, 'FM0003'], array_column($tablas, 'folio'));

        $t1 = $tablas[0]['lineas'];
        $this->assertSame(['201', '202'], $t1->keys()->map(fn ($k) => (string) $k)->all());
        $this->assertSame(10, $t1->get('201')->Marcas);
        $this->assertSame(11, $t1->get('202')->Marcas);

        $this->assertTrue($tablas[1]['lineas']->isEmpty());
        $this->assertNull($tablas[1]['empleado']);

        $this->assertSame(['202'], $tablas[2]['lineas']->keys()->map(fn ($k) => (string) $k)->all());
        $this->assertSame(30, $tablas[2]['lineas']->get('202')->Marcas);
        $this->assertSame('Emp FM0003', $tablas[2]['empleado']);
        $this->assertSame(['201', '202'], $tablas[2]['telares']->map(fn ($t) => (string) $t)->all());
    }

    public function test_reporte_usa_dos_consultas_y_ninguna_de_lineas_sin_folios(): void
    {
        $this->marca('FM0001', '2026-09-28', 1, [201 => 1]);
        $this->marca('FM0002', '2026-09-28', 2, [201 => 2]);
        $this->marca('FM0003', '2026-09-28', 3, [201 => 3]);

        $contar = function (string $fecha): array {
            $q = [];
            DB::connection('sqlsrv')->flushQueryLog();
            DB::connection('sqlsrv')->enableQueryLog();
            $this->reporte($fecha);
            foreach (DB::connection('sqlsrv')->getQueryLog() as $entrada) {
                if (preg_match('/"?TejMarcas(Line)?"?/', $entrada['query'], $m)) {
                    $q[] = $m[0];
                }
            }
            DB::connection('sqlsrv')->disableQueryLog();

            return $q;
        };

        $this->assertCount(2, $contar('2026-09-28'), 'folios + líneas de los 3 turnos');
        $sinFolios = $contar('2020-01-01');
        $this->assertCount(1, $sinFolios, 'sin folios del día no se consultan líneas');
    }

    private function crearTablasDevoluciones(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->create('AtaMontadoTelas', function (Blueprint $t): void {
            $t->increments('Id');
            $t->string('Estatus')->nullable();
            $t->date('Fecha')->nullable();
            $t->string('Turno')->nullable();
            $t->string('NoJulio')->nullable();
            $t->string('NoProduccion')->nullable();
            $t->string('Tipo')->nullable();
            $t->string('NoTelarId')->nullable();
            $t->string('ConfigId')->nullable();
        });
        $schema->create('TejHistorialInventarioTelares', function (Blueprint $t): void {
            $t->increments('Id');
            $t->string('NoTelarId')->nullable();
            $t->string('NoJulio')->nullable();
            $t->string('NoProduccion')->nullable();
            $t->string('Cuenta')->nullable();
            $t->string('Calibre')->nullable();
            $t->string('Status')->nullable();
            $t->dateTime('FechaAtado')->nullable();
        });
    }

    /**
     * Contrato JSON de /atadores/devoluciones/julios con el select reducido: julio, cuenta,
     * calibre (del historial), hilo (ConfigId), lote DEV y tipo; del más reciente al más antiguo.
     */
    public function test_julios_de_devolucion_conserva_el_contrato_con_select_reducido(): void
    {
        Carbon::setTestNow('2026-10-10 10:00:00');
        $this->crearTablasDevoluciones();
        $db = DB::connection('sqlsrv');
        $db->table('AtaMontadoTelas')->insert([
            ['Estatus' => 'Autorizado', 'Fecha' => '2026-10-05', 'Turno' => '1', 'NoJulio' => 'J10', 'NoProduccion' => '36160', 'Tipo' => 'Rizo', 'NoTelarId' => '201', 'ConfigId' => 'A20'],
            ['Estatus' => 'Calificado', 'Fecha' => '2026-10-08', 'Turno' => '2', 'NoJulio' => 'J11', 'NoProduccion' => '36170', 'Tipo' => 'Rizo', 'NoTelarId' => '201', 'ConfigId' => 'A30'],
            // Otro tipo, otro telar y uno en proceso: fuera.
            ['Estatus' => 'Autorizado', 'Fecha' => '2026-10-09', 'Turno' => '1', 'NoJulio' => 'J12', 'NoProduccion' => '36180', 'Tipo' => 'Pie', 'NoTelarId' => '201', 'ConfigId' => 'A20'],
            ['Estatus' => 'Autorizado', 'Fecha' => '2026-10-09', 'Turno' => '1', 'NoJulio' => 'J13', 'NoProduccion' => '36190', 'Tipo' => 'Rizo', 'NoTelarId' => '202', 'ConfigId' => 'A20'],
            ['Estatus' => 'En Proceso', 'Fecha' => '2026-10-09', 'Turno' => '3', 'NoJulio' => 'J14', 'NoProduccion' => '36200', 'Tipo' => 'Rizo', 'NoTelarId' => '201', 'ConfigId' => 'A20'],
        ]);
        $db->table('TejHistorialInventarioTelares')->insert([
            ['NoTelarId' => '201', 'NoJulio' => 'J11', 'NoProduccion' => '36170', 'Cuenta' => '3480', 'Calibre' => '12', 'Status' => 'Completado', 'FechaAtado' => '2026-10-08 10:00:00'],
        ]);

        $request = Request::create('/atadores/devoluciones/julios', 'GET', ['telar' => '201', 'tipo' => 'Rizo']);
        $json = app(AtaDevolucionesController::class)->julios($request)->getData(true);

        $this->assertTrue($json['ok']);
        $this->assertSame(['J11', 'J10'], $json['julios']);
        $this->assertSame('J11', $json['sugerido']);
        $this->assertSame('3480', $json['cuenta']);
        $this->assertSame('12', $json['calibre']);
        $this->assertSame('A30', $json['hilo']);
        $this->assertSame(
            ['julio' => 'J11', 'cuenta' => '3480', 'calibre' => '12', 'hilo' => 'A30', 'lote' => 'DEV36170', 'tipo' => 'Rizo'],
            $json['registros'][0]
        );
        $this->assertSame(['julio' => 'J10', 'cuenta' => null, 'calibre' => null, 'hilo' => 'A20', 'lote' => 'DEV36160', 'tipo' => 'Rizo'], $json['registros'][1]);
    }
}
