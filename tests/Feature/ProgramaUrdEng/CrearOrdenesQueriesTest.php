<?php

namespace Tests\Feature\ProgramaUrdEng;

use App\Services\ProgramaUrdEng\CrearOrdenesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ProgramaUrdEng\Concerns\ModuloProgramaUrdEng;
use Tests\TestCase;

/**
 * PERF-08 (19-05): el alta de órdenes insertaba UrdConsumoHilo y UrdJuliosOrden fila por fila y
 * buscaba cada telar dos veces (fecha de requerimiento y marcado) antes de un update por telar.
 */
class CrearOrdenesQueriesTest extends TestCase
{
    use ModuloProgramaUrdEng;

    private const MATERIALES = 30;

    private const JULIOS = 12;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:15:00');
        $this->prepararSqlite();
        $this->prepararTablasDeOrdenes();

        $db = DB::connection('sqlsrv');
        foreach (['299' => '2026-10-08', '300' => '2026-10-03', '301' => '2026-10-01'] as $telar => $fecha) {
            $db->table('tej_inventario_telares')->insert([
                'no_telar' => $telar, 'status' => 'Activo', 'tipo' => 'Rizo', 'fecha' => $fecha,
                'Programado' => 0, 'created_at' => '2026-09-01 00:00:00', 'updated_at' => '2026-09-01 00:00:00',
            ]);
        }
        // Mismo telar pero Pie: no se toca.
        $db->table('tej_inventario_telares')->insert(['no_telar' => '299', 'status' => 'Activo', 'tipo' => 'Pie', 'fecha' => '2026-09-01', 'Programado' => 0]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $materiales = [];
        for ($i = 1; $i <= self::MATERIALES; $i++) {
            $materiales[] = [
                'itemId' => "HIL-{$i}", 'configId' => 'A20', 'inventSizeId' => 'T1', 'inventColorId' => 'C1',
                'inventLocationId' => 'A-JUL', 'inventBatchId' => $i === 1 ? '' : "L{$i}", 'wmsLocationId' => 'W1',
                'inventSerialId' => "S{$i}", 'kilos' => (string) ($i * 1.5), 'prodDate' => $i % 2 ? '2026-08-0'.($i % 9 + 1) : null,
                'conos' => (string) $i, 'loteProv' => "LP{$i}", 'noProv' => 'P1',
            ];
        }
        $julios = [];
        for ($i = 1; $i <= self::JULIOS; $i++) {
            // Los pares sin julios ni hilos no generan fila.
            $julios[] = $i % 4 === 0 ? ['julios' => '', 'hilos' => ''] : ['julios' => (string) $i, 'hilos' => (string) ($i * 100), 'observaciones' => "obs {$i}"];
        }

        return [
            'grupo' => [
                'salonTejidoId' => 'Jacquard', 'fibra' => 'A20', 'telaresStr' => '299,300,999', 'tipo' => 'Rizo',
                'cuenta' => '3480', 'calibre' => '12', 'metros' => '1000', 'kilos' => '50', 'maquinaId' => 'MC1',
                'bomId' => 'URD 1', 'tamano' => 'T1', 'tipoAtado' => 'Normal',
            ],
            'materialesEngomado' => $materiales,
            'construccionUrdido' => $julios,
            'datosEngomado' => ['bomFormula' => 'TE-PD-ENF1', 'nucleo' => 'N1', 'noTelas' => '2', 'anchoBalonas' => '3', 'metrajeTelas' => '1,200', 'cuendeadosMin' => '4', 'maquinaEngomado' => 'WP2', 'lMatEngomado' => 'ENG 1'],
            'fechaRequerimiento' => '2026-10-05',
        ];
    }

    public function test_crear_ordenes_numero_de_queries_y_mismo_resultado_en_bd(): void
    {
        $servicio = app(CrearOrdenesService::class);
        $resultado = null;

        $queries = $this->contarQueries(function () use ($servicio, &$resultado): void {
            $resultado = $servicio->crear($this->payload(), '100', 'Usuario prueba');
        });

        // Antes 57: 30 inserts de consumo + 9 de julios + 3 selects de telar para la fecha + 3 selects
        // y 2 updates para marcarlos + urdido, engomado, 2 auditorías y 6 de folios. Ahora 14: consumo
        // y julios en un insert cada uno, un select de telares y un update.
        $this->assertSame(14, $queries);
        $this->assertSame(['folio' => 'UE00021', 'folioConsumo' => 'CH00011', 'telares_actualizados' => 2], $resultado);

        $consumos = $this->filasDe('UrdConsumoHilo');
        $this->assertCount(self::MATERIALES, $consumos);
        $this->assertSame([
            'Folio' => 'UE00021', 'FolioConsumo' => 'CH00011', 'ItemId' => 'HIL-1', 'ConfigId' => 'A20', 'InventSizeId' => 'T1',
            'InventColorId' => 'C1', 'InventLocationId' => 'A-JUL', 'InventBatchId' => '', 'WMSLocationId' => 'W1',
            'InventSerialId' => 'S1', 'InventQty' => 1.5, 'ProdDate' => '2026-08-02 00:00:00', 'Status' => 'Activo',
            'NumeroEmpleado' => '100', 'NombreEmpl' => 'Usuario prueba', 'Conos' => 1, 'LoteProv' => 'LP1', 'NoProv' => 'P1',
            'Registrado' => null, 'FechaRegistro' => '2026-09-30 10:15:00', 'FechaRequerimiento' => '2026-10-05 00:00:00',
        ], $consumos[0]);
        $this->assertNull($consumos[1]['ProdDate']);
        $this->assertSame(['HIL-30', 45, 30], [$consumos[29]['ItemId'], (int) $consumos[29]['InventQty'], $consumos[29]['Conos']]);

        $this->assertSame([
            ['Folio' => 'UE00021', 'Julios' => 1, 'Hilos' => 100, 'Obs' => 'obs 1'],
            ['Folio' => 'UE00021', 'Julios' => 2, 'Hilos' => 200, 'Obs' => 'obs 2'],
            ['Folio' => 'UE00021', 'Julios' => 3, 'Hilos' => 300, 'Obs' => 'obs 3'],
        ], array_slice($this->filasDe('UrdJuliosOrden'), 0, 3));
        $this->assertCount(9, $this->filasDe('UrdJuliosOrden'));

        // Fecha de requerimiento: la más temprana de los telares del grupo (300 → 3 de octubre).
        $this->assertSame('2026-10-03 00:00:00', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->value('FechaReq'));
        $this->assertSame('2026-10-03 00:00:00', DB::connection('sqlsrv')->table('EngProgramaEngomado')->value('FechaReq'));

        $telares = DB::connection('sqlsrv')->table('tej_inventario_telares')->orderBy('id')->get(['no_telar', 'tipo', 'no_orden', 'Programado', 'updated_at'])
            ->map(fn ($t) => (array) $t)->all();
        $this->assertSame([
            ['no_telar' => '299', 'tipo' => 'Rizo', 'no_orden' => 'UE00021', 'Programado' => 1, 'updated_at' => '2026-09-30 10:15:00'],
            ['no_telar' => '300', 'tipo' => 'Rizo', 'no_orden' => 'UE00021', 'Programado' => 1, 'updated_at' => '2026-09-30 10:15:00'],
            ['no_telar' => '301', 'tipo' => 'Rizo', 'no_orden' => null, 'Programado' => 0, 'updated_at' => '2026-09-01 00:00:00'],
            ['no_telar' => '299', 'tipo' => 'Pie', 'no_orden' => null, 'Programado' => 0, 'updated_at' => null],
        ], $telares);
    }

    public function test_bloques_respetan_el_limite_de_parametros_de_sql_server(): void
    {
        $payload = $this->payload();
        $payload['materialesEngomado'] = array_fill(0, 250, $payload['materialesEngomado'][2]);

        $queries = $this->contarQueries(fn () => app(CrearOrdenesService::class)->crear($payload, '100', 'Usuario prueba'));

        // Antes 277. 20 columnas por fila → bloques de 104 (2 080 parámetros): 250 filas = 3 inserts.
        $this->assertSame(14 + 2, $queries);
        $this->assertCount(250, $this->filasDe('UrdConsumoHilo'));
    }
}
