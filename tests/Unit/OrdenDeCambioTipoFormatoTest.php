<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\OrdenDeCambio\Felpa\OrdenDeCambioFelpaController;
use App\Models\Planeacion\ReqProgramaTejido;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * El tipo de formato decide dos cosas en la Orden de Cambio: si K19 divide entre 2 y como se
 * titula la hoja. Karl Mayer usa TamanoClave tipo FELPA#### pero no se rige por felpa, igual
 * que en LiberarOrdenesController::debeAplicarAjusteFormatoFelRollo().
 */
class OrdenDeCambioTipoFormatoTest extends TestCase
{
    private function tipoFormato(array $atributos): string
    {
        $metodo = new ReflectionMethod(OrdenDeCambioFelpaController::class, 'determinarTipoFormatoDesdeBD');
        $metodo->setAccessible(true);

        $registro = (new ReqProgramaTejido)->forceFill($atributos);

        return $metodo->invoke(new OrdenDeCambioFelpaController, $registro);
    }

    public function test_karl_mayer_no_se_clasifica_como_felpa(): void
    {
        // El bug: FELPA6808 en un telar KM caia en la rama felpa y la hoja salia titulada FELPA
        // con el rollo dividido entre 2, contradiciendo la orden que se acababa de liberar.
        $this->assertSame('km', $this->tipoFormato([
            'SalonTejidoId' => 'KARL MAYER',
            'NoTelarId' => '401',
            'TamanoClave' => 'FELPA6808',
        ]));

        $this->assertSame('km', $this->tipoFormato([
            'SalonTejidoId' => 'KM',
            'NoTelarId' => '402',
            'TamanoClave' => 'MB7217',
        ]));
    }

    public function test_los_demas_salones_conservan_su_clasificacion(): void
    {
        $this->assertSame('felpa', $this->tipoFormato([
            'SalonTejidoId' => 'SMIT',
            'NoTelarId' => '305',
            'TamanoClave' => 'FELPA6808',
        ]));

        $this->assertSame('smit', $this->tipoFormato([
            'SalonTejidoId' => 'SMIT',
            'NoTelarId' => '305',
            'TamanoClave' => 'MB7217',
        ]));

        $this->assertSame('jacquard', $this->tipoFormato([
            'SalonTejidoId' => 'JACQUARD',
            'NoTelarId' => '201',
            'TamanoClave' => 'MB7217',
        ]));
    }
}
