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

    /**
     * El caso real: el programa trae TamanoClave = 'PULLMAN7630' pero el modelo tiene
     * ClaveModelo = '(MODELO NUEVO)', el marcador que arrastra la mitad del catalogo
     * (3013 de 6172 renglones). La clave de tres partes no empataba nunca y Med. Cen.,
     * Tipo Rizo y Alt Rizo salian vacias en los 36 renglones en proceso. Con el par
     * ItemId|InventSizeId el respaldo si encuentra el modelo.
     */
    public function test_med_cen_respaldo_por_par_cuando_la_clave_del_modelo_es_marcador(): void
    {
        $program = $this->programaConClave();
        $modelo = new ReqModelosCodificados;
        $modelo->setRawAttributes([
            'ItemId' => 'TOW',
            'InventSizeId' => '30x50',
            'ClaveModelo' => '(MODELO NUEVO)',
            'MedidaCenefa' => '4',
            'TipoRizo' => 'NORMAL',
            'AlturaRizo' => '5',
        ]);

        // Indexado solo por el par, que es como queda cuando ClaveModelo no sirve.
        $item = $this->mapear($program, [], ['TOW|30x50' => $modelo]);

        $this->assertSame('4', $item['AnchoToalla']);
        $this->assertSame('NORMAL', $item['TipoRizo']);
        $this->assertSame('5', $item['CalibreRizo']);
    }

    /** La clave exacta sigue mandando sobre el par cuando el modelo si la tiene. */
    public function test_la_clave_exacta_gana_al_par(): void
    {
        $program = $this->programaConClave();

        $exacto = new ReqModelosCodificados;
        $exacto->setRawAttributes(['ItemId' => 'TOW', 'InventSizeId' => '30x50', 'ClaveModelo' => 'ABC', 'MedidaCenefa' => '7/2.5']);

        $otro = new ReqModelosCodificados;
        $otro->setRawAttributes(['ItemId' => 'TOW', 'InventSizeId' => '30x50', 'ClaveModelo' => '(MODELO NUEVO)', 'MedidaCenefa' => '9']);

        $item = $this->mapear($program, [], [
            $this->claveDe($program) => $exacto,
            'TOW|30x50' => $otro,
        ]);

        $this->assertSame('7/2.5', $item['AnchoToalla']);
    }

    /**
     * Un modelo que empata exacto pero trae el campo vacio no debe tapar al del par:
     * el respaldo se resuelve campo por campo, no eligiendo un solo modelo.
     */
    public function test_un_exacto_sin_dato_cae_al_par(): void
    {
        $program = $this->programaConClave();

        $exacto = new ReqModelosCodificados;
        $exacto->setRawAttributes(['ItemId' => 'TOW', 'InventSizeId' => '30x50', 'ClaveModelo' => 'ABC', 'MedidaCenefa' => '']);

        $otro = new ReqModelosCodificados;
        $otro->setRawAttributes(['ItemId' => 'TOW', 'InventSizeId' => '30x50', 'ClaveModelo' => '(MODELO NUEVO)', 'MedidaCenefa' => '9']);

        $item = $this->mapear($program, [], [
            $this->claveDe($program) => $exacto,
            'TOW|30x50' => $otro,
        ]);

        $this->assertSame('9', $item['AnchoToalla']);
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
