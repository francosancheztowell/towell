<?php

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\OrdCompartida;
use Tests\TestCase;

class OrdCompartidaTest extends TestCase
{
    /** OrdCompartida es texto: el NoProduccion tal cual (recortado), también el folio M de Muestras. */
    public function test_obtener_ord_compartida_desde_registro_retorna_el_no_produccion_como_texto(): void
    {
        $this->assertSame('12345', OrdCompartida::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => '12345']));
        $this->assertSame('M3175', OrdCompartida::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => ' M3175 ']));
    }

    public function test_obtener_ord_compartida_desde_registro_sin_no_produccion_retorna_null(): void
    {
        $this->assertNull(OrdCompartida::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => null]));
        $this->assertNull(OrdCompartida::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => '']));
        $this->assertNull(OrdCompartida::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => '   ']));
        $this->assertNull(OrdCompartida::obtenerOrdCompartidaDesdeRegistro(['NoProduccion' => '0']));
        $this->assertNull(OrdCompartida::obtenerOrdCompartidaDesdeRegistro(null));
    }

    public function test_lider_es_el_mas_antiguo_que_tiene_no_produccion(): void
    {
        $fila = fn (int $id, ?string $np, string $inicio) => (new ReqProgramaTejido)->forceFill(['Id' => $id, 'NoProduccion' => $np, 'FechaInicio' => $inicio]);

        $lider = OrdCompartida::seleccionarLider(collect([
            $fila(1, null, '2026-09-01 06:00:00'),   // el más antiguo, pero sin NoProduccion: se salta
            $fila(2, '500', '2026-09-03 06:00:00'),
            $fila(3, '501', '2026-09-02 06:00:00'),
        ]));

        $this->assertSame(3, (int) $lider->Id);
        $this->assertNull(OrdCompartida::seleccionarLider(collect([$fila(1, null, '2026-09-01 06:00:00')])));
    }
}
