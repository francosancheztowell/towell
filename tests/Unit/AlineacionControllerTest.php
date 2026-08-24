<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\Alineacion\AlineacionController;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use ReflectionMethod;
use Tests\TestCase;

class AlineacionControllerTest extends TestCase
{
    public function test_previous_month_accumulated_matches_accumulated_production(): void
    {
        // Un cero significa "no capturado" y se muestra vacío, de ahí el segundo par.
        foreach ([[1875.5, 1875.5], [0.0, '']] as [$production, $esperado]) {
            $program = new ReqProgramaTejido;
            $program->setRawAttributes([
                'NoTelarId' => '215',
                'Produccion' => $production,
            ]);

            $item = $this->mapear($program);

            $this->assertSame($item['Produccion'], $item['ProdAcumMesAnt']);
            $this->assertSame($esperado, $item['ProdAcumMesAnt']);
        }
    }

    public function test_zero_values_are_blanked(): void
    {
        $program = new ReqProgramaTejido;
        $program->setRawAttributes([
            'NoTelarId' => '215',
            'PesoCrudo' => 0,          // cero entero
            'Luchaje' => 0.0,          // cero flotante
            'CalibreComb1' => 0,       // se concatena como "0/0"
            'FibraComb1' => 0,
            'CalibreComb2' => 0,       // mixto: sí hay dato, se conserva
            'FibraComb2' => 'ALG',
            'LargoCrudo' => 140,       // valor normal, intacto
        ]);

        $item = $this->mapear($program);

        $this->assertSame('', $item['PesoCrudo']);
        $this->assertSame('', $item['Luchaje']);
        $this->assertSame('', $item['PasadasComb1']);
        $this->assertSame('0/ALG', $item['PasadasComb2']);
        $this->assertSame(140, $item['LargoCrudo']);
    }

    public function test_med_cen_usa_cat_codificados_cuando_existe(): void
    {
        $program = $this->programaConClave();
        $cat = new CatCodificados;
        $cat->setRawAttributes(['OrdenTejido' => '12345', 'MedidaCenefa' => '7/2.5']);
        $modelo = new ReqModelosCodificados;
        $modelo->setRawAttributes([
            'ItemId' => 'TOW',
            'InventSizeId' => '30x50',
            'ClaveModelo' => 'ABC',
            'MedidaCenefa' => '1/1/1/1',
        ]);

        $item = $this->mapear($program, ['12345' => $cat], [$this->claveDe($program) => $modelo]);

        $this->assertSame('7/2.5', $item['AnchoToalla']);
    }

    public function test_med_cen_respaldo_req_modelos_si_no_hay_cat_codificados(): void
    {
        $program = $this->programaConClave();
        $modelo = new ReqModelosCodificados;
        $modelo->setRawAttributes([
            'ItemId' => 'TOW',
            'InventSizeId' => '30x50',
            'ClaveModelo' => 'ABC',
            'MedidaCenefa' => '1/1/1/1',
        ]);

        $item = $this->mapear($program, [], [$this->claveDe($program) => $modelo]);

        $this->assertSame('1/1/1/1', $item['AnchoToalla']);
    }

    public function test_med_cen_respaldo_req_modelos_si_cat_no_trae_medida(): void
    {
        $program = $this->programaConClave();
        $cat = new CatCodificados;
        $cat->setRawAttributes(['OrdenTejido' => '12345', 'MedidaCenefa' => '']);
        $modelo = new ReqModelosCodificados;
        $modelo->setRawAttributes([
            'ItemId' => 'TOW',
            'InventSizeId' => '30x50',
            'ClaveModelo' => 'ABC',
            'MedidaCenefa' => '6/2',
        ]);

        $item = $this->mapear($program, ['12345' => $cat], [$this->claveDe($program) => $modelo]);

        $this->assertSame('6/2', $item['AnchoToalla']);
    }

    private function programaConClave(): ReqProgramaTejido
    {
        $program = new ReqProgramaTejido;
        $program->setRawAttributes([
            'NoTelarId' => '215',
            'NoProduccion' => '12345',
            'ItemId' => 'TOW',
            'InventSizeId' => '30x50',
            'TamanoClave' => 'ABC',
        ]);

        return $program;
    }

    private function claveDe(ReqProgramaTejido $program): string
    {
        return trim((string) $program->ItemId).'|'.trim((string) $program->InventSizeId).'|'.trim((string) $program->TamanoClave);
    }

    /**
     * @param  array<string, CatCodificados>  $catPorOrden
     * @param  array<string, ReqModelosCodificados>  $modelosPorClave
     * @return array<string, mixed>
     */
    private function mapear(ReqProgramaTejido $program, array $catPorOrden = [], array $modelosPorClave = []): array
    {
        $method = new ReflectionMethod(AlineacionController::class, 'mapearProgramaTejidoAItem');

        return $method->invoke(new AlineacionController, $program, $catPorOrden, [], $modelosPorClave);
    }
}
