<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\Alineacion\AlineacionController;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Alineacion\AlineacionItemsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Fija la fila completa de Alineación (todas las llaves, en orden) y la llave de caché
 * compartida con el andón de Crudo, para mover la lógica sin cambiar lo que se ve.
 */
class AlineacionItemsCharacterizationTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::forget('alineacion_items');
        parent::tearDown();
    }

    public function test_items_salen_de_la_cache_compartida(): void
    {
        $items = [['NoTelarId' => '215', 'NoProduccion' => '1']];
        Cache::put('alineacion_items', $items, 60);

        $this->assertSame($items, $this->items());
        // El controller (vista/API/exports) sigue leyendo la misma llave.
        $respuesta = app(AlineacionController::class)->apiData();
        $this->assertSame(['s' => true, 'items' => $items], $respuesta->getData(true));
    }

    public function test_fila_completa_con_catalogo_modelo_y_paro(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00'));

        $program = new ReqProgramaTejido;
        $program->setRawAttributes([
            'NoTelarId' => '215', 'SalonTejidoId' => 'JACQUARD', 'NoProduccion' => '12345',
            'EntregaCte' => '2026-11-15', 'ItemId' => 'TOW', 'InventSizeId' => '30x50',
            'TamanoClave' => 'ABC', 'NombreProducto' => 'TOALLA', 'CalibreRizo' => '12',
            'FibraRizo' => 'ALG', 'CalibrePie' => '', 'NombreCPie' => 'POL', 'Ancho' => 150,
            'LargoCrudo' => 72.5, 'PesoCrudo' => 628, 'Luchaje' => 3, 'NoTiras' => 2,
            'CalibreTrama' => '16', 'CalibreComb1' => '20', 'FibraComb1' => 'ALG',
            'TotalPedido' => 1000, 'Produccion' => 400, 'SaldoPedido' => 600, 'ProdKgDia' => 40,
            'DiasEficiencia' => 9,
        ]);
        $cat = new CatCodificados;
        $cat->setRawAttributes([
            'OrdenTejido' => '12345', 'FechaTejido' => '2026-10-01 00:00:00', 'Tolerancia' => 'N',
            'Razurada' => 'S', 'TipoRizo' => '', 'DobladilloId' => 'DOB', 'Obs5' => 'nota',
            'PesoMuestra' => '4.8500001', 'MedidaCenefa' => '', 'MedidaPlano' => '3', 'AlturaRizo' => null,
        ]);
        $modelo = new ReqModelosCodificados;
        $modelo->setRawAttributes(['TipoRizo' => 'NORMAL', 'AlturaRizo' => '5', 'MedidaCenefa' => '7/2.5']);

        $item = $this->mapear($program, ['12345' => $cat], ['215'], ['TOW|30x50|ABC' => $modelo]);

        $this->assertSame([
            'NoTelarId' => '215', 'NoProduccion' => '12345', 'FechaCambio' => '01 oct. 2026',
            'FechaCompromiso' => '15 nov. 2026', 'ItemId' => 'TOW', 'NombreProducto' => 'TOALLA',
            'Tolerancia' => 'N', 'RazSN' => 'S', 'TipoRizo' => 'NORMAL', 'CalibreRizo' => '5',
            'Ancho' => 150.0, 'LargoCrudo' => 72, 'PesoCrudo' => 628, 'Luchaje' => 3,
            'TipoPlano' => 'DOB', 'MedidaPlano' => '3', 'NoTiras' => 2, 'FibraRizo' => '12/ALG',
            'FibraPie' => '0/POL', 'CalibreTrama' => 16.0, 'PasadasComb1' => '20/ALG',
            'PasadasComb2' => '', 'PasadasComb3' => '', 'PasadasComb4' => '', 'AnchoToalla' => '7/2.5',
            'PesoGRM2' => 4.85, 'PesoMin' => 610, 'PesoMax' => 647, 'MuestraMin' => 4.753,
            'MuestraMax' => 4.947, 'TotalPedido' => 1000.0, 'ProdAcumMesAnt' => 400.0, 'ProdAcumMes' => '',
            'Produccion' => 400.0, 'SaldoPedido' => 600.0, 'DiasEficiencia' => '6.5', 'ProdKgDia' => 40.0,
            'DiasPorEjecutar' => 15.0, 'Observaciones' => 'nota', 'FechaTejido' => '2026-10-01',
            '_tieneParoActivo' => true, '_esKarlMayer' => false, '_barras' => [],
        ], $item);
    }

    public function test_fila_sin_catalogo_queda_en_blanco_y_abierta(): void
    {
        $program = new ReqProgramaTejido;
        $program->setRawAttributes(['NoTelarId' => '216', 'NoProduccion' => '999', 'ProdKgDia' => 0]);

        $item = $this->mapear($program, [], ['215'], []);

        $this->assertSame('ABIERTO', $item['DiasPorEjecutar']);
        $this->assertSame('', $item['FechaCambio']);
        $this->assertSame('', $item['FechaTejido']);
        $this->assertSame('', $item['PesoMin']);
        $this->assertSame('', $item['DiasEficiencia']);
        $this->assertFalse($item['_tieneParoActivo']);
        $this->assertSame(md5(json_encode(array_keys($item))), md5(json_encode(array_merge(
            $this->columnas(), ['FechaTejido', '_tieneParoActivo', '_esKarlMayer', '_barras']
        ))));
    }

    /** @return array<int, array<string, mixed>> */
    private function items(): array
    {
        return app(AlineacionItemsService::class)->obtenerItems();
    }

    /** @return array<int, string> */
    private function columnas(): array
    {
        return AlineacionItemsService::COLUMNAS;
    }

    /** @return array<string, mixed> */
    private function mapear(ReqProgramaTejido $r, array $cat, array $paros, array $modelos): array
    {
        return (new AlineacionItemsService)->mapearItem($r, $cat, $paros, $modelos);
    }
}
