<?php

namespace Tests\Feature\ProgramaUrdEng;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Services\ProgramaUrdEng\ResumenSemanasService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ProgramaUrdEng\Concerns\ModuloProgramaUrdEng;
use Tests\TestCase;

/**
 * PERF-08 (19-05), medición: los tres loops de ResumenSemanasService (líneas por programa, hilo
 * por telar y resumen por tipo) recorren datos ya cargados. Programas y líneas van con eager
 * loading, así que el número de consultas no crece con los telares ni con los programas.
 * No hubo nada que corregir; el test fija que siga así.
 */
class ResumenSemanasQueriesTest extends TestCase
{
    use ModuloProgramaUrdEng;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:00:00');
        $this->prepararSqlite();
        $this->prepararTablasDeOrdenes();
        $this->tablaDe(ReqProgramaTejido::class);
        $this->tablaDe(ReqProgramaTejidoLine::class, ['Pie', 'Rizo', 'MtsRizo', 'MtsPie']);

        $db = DB::connection('sqlsrv');
        $programa = 0;
        foreach (range(301, 306) as $telar) {
            $db->table('tej_inventario_telares')->insert(['no_telar' => (string) $telar, 'status' => 'Activo', 'tipo' => 'Rizo', 'hilo' => 'A20']);
            foreach (['M1', 'M2', 'M3'] as $modelo) {
                $programa++;
                $db->table('ReqProgramaTejido')->insert([
                    'Id' => $programa, 'NoTelarId' => (string) $telar, 'CuentaRizo' => '3480', 'FibraRizo' => 'A20',
                    'CalibreRizo' => 12, 'ItemId' => $modelo,
                ]);
                foreach (['2026-09-30', '2026-10-07', '2026-10-14'] as $fecha) {
                    $db->table('ReqProgramaTejidoLine')->insert(['ProgramaId' => $programa, 'Fecha' => $fecha, 'MtsRizo' => 100, 'Rizo' => 5]);
                }
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<int, array<string, string>> */
    private function telares(int $n): array
    {
        return array_map(fn (int $t) => ['no_telar' => (string) $t, 'tipo' => 'Rizo', 'calibre' => '12', 'hilo' => 'A20'], range(301, 300 + $n));
    }

    public function test_el_numero_de_consultas_no_crece_con_los_telares(): void
    {
        $servicio = app(ResumenSemanasService::class);

        $conUno = $this->contarQueries(fn () => $servicio->generar($this->telares(1)));
        $resultado = null;
        $conSeis = $this->contarQueries(function () use ($servicio, &$resultado): void {
            $resultado = $servicio->generar($this->telares(6));
        });

        // Hilos de telares + programas + líneas (eager): 3 con 1 telar y con 6 (18 programas, 54 líneas).
        $this->assertSame(3, $conUno);
        $this->assertSame(3, $conSeis);
        $this->assertTrue($resultado['success']);
        $this->assertCount(18, $resultado['data']['rizo']);
        $this->assertSame(300.0, $resultado['data']['rizo'][0]['Total']);
    }
}
