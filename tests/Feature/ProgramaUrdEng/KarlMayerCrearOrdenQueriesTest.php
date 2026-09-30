<?php

namespace Tests\Feature\ProgramaUrdEng;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ProgramaUrdEng\Concerns\ModuloProgramaUrdEng;
use Tests\TestCase;

/**
 * PERF-08 (19-05): la orden Karl Mayer insertaba UrdConsumoHilo y UrdJuliosOrden fila por fila.
 */
class KarlMayerCrearOrdenQueriesTest extends TestCase
{
    use ModuloProgramaUrdEng;

    private const MATERIALES = 25;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-30 10:15:00');
        $this->prepararSqlite();
        $this->prepararTablasDeOrdenes();
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
                'itemId' => "HIL-{$i}", 'configId' => 'A20', 'inventSizeId' => $i === 1 ? '' : 'T2', 'inventColorId' => 'C1',
                'inventLocationId' => 'A-JUL', 'inventBatchId' => 'B9', 'wmsLocationId' => 'W1',
                'inventSerialId' => $i === 1 ? '00061-744' : "S{$i}", 'kilos' => $i * 2, 'conos' => $i,
                'loteProv' => "LP{$i}", 'noProv' => 'P1', 'prodDate' => $i === 2 ? '1900-01-01' : '2026-08-15',
            ];
        }

        return [
            'no_telar' => 'KM1', 'barras' => '1', 'fibra' => 'A20', 'tamano' => 'T1', 'cuenta' => '3480', 'calibre' => '12',
            'metros' => 1500, 'fecha_programada' => '2026-10-02', 'tipo_atado' => 'Normal', 'bom_id' => 'URD KM',
            'julios' => ['1', '2', '', '4', '5', '6', '7', '8'], 'hilos' => ['100', '200', '', '400', '500', '600', '700', ''],
            'obs' => ['a', '', '', 'd'], 'materiales' => $materiales, 'fechaRequerimiento' => '2026-10-05',
        ];
    }

    public function test_crea_la_orden_con_menos_queries_y_mismo_resultado_en_bd(): void
    {
        $usuario = $this->usuarioCon([self::MODULO_PROGRAMA_URD_ENG => ['acceso', 'crear']]);
        $this->actingAs($usuario);

        $respuesta = null;
        $queries = $this->contarQueries(function () use (&$respuesta): void {
            $respuesta = $this->postJson(route('programa.urd.eng.crear.orden.karl.mayer'), $this->payload());
        });

        $respuesta->assertOk()->assertJson(['success' => true, 'data' => ['folio' => 'UE00021', 'folio_consumo' => 'CH00011']]);
        // Antes 42: 25 inserts de consumo + 7 de julios, más permisos, folios, urdido y auditoría.
        // Ahora 12: consumo y julios en un insert cada uno.
        $this->assertSame(12, $queries);

        $consumos = $this->filasDe('UrdConsumoHilo');
        $this->assertCount(self::MATERIALES, $consumos);
        $this->assertSame([
            'Folio' => 'UE00021', 'FolioConsumo' => 'CH00011', 'ItemId' => 'HIL-1', 'ConfigId' => 'A20', 'InventSizeId' => 'T1',
            'InventColorId' => 'C1', 'InventLocationId' => 'A-JUL', 'InventBatchId' => '00061', 'WMSLocationId' => 'W1',
            'InventSerialId' => '00061-744', 'InventQty' => 2.0, 'ProdDate' => '2026-08-15 00:00:00', 'Status' => 'Programado',
            'NumeroEmpleado' => '100', 'NombreEmpl' => 'Usuario prueba', 'Conos' => 1, 'LoteProv' => 'LP1', 'NoProv' => 'P1',
            'Registrado' => null, 'FechaRegistro' => '2026-09-30 10:15:00', 'FechaRequerimiento' => '2026-10-05 00:00:00',
        ], $consumos[0]);
        // 1900-01-01 es "sin fecha" en AX: cae a hoy.
        $this->assertSame(['T2', 'B9', '2026-09-30 00:00:00'], [$consumos[1]['InventSizeId'], $consumos[1]['InventBatchId'], $consumos[1]['ProdDate']]);

        $this->assertSame([
            ['Folio' => 'UE00021', 'Julios' => 1, 'Hilos' => 100, 'Obs' => 'a'],
            ['Folio' => 'UE00021', 'Julios' => 2, 'Hilos' => 200, 'Obs' => null],
            ['Folio' => 'UE00021', 'Julios' => 4, 'Hilos' => 400, 'Obs' => 'd'],
            ['Folio' => 'UE00021', 'Julios' => 5, 'Hilos' => 500, 'Obs' => null],
            ['Folio' => 'UE00021', 'Julios' => 6, 'Hilos' => 600, 'Obs' => null],
            ['Folio' => 'UE00021', 'Julios' => 7, 'Hilos' => 700, 'Obs' => null],
            ['Folio' => 'UE00021', 'Julios' => 8, 'Hilos' => null, 'Obs' => null],
        ], $this->filasDe('UrdJuliosOrden'));
        $this->assertSame('Karl Mayer', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->value('MaquinaId'));
    }
}
