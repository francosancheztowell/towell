<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\Alineacion\AlineacionController;
use App\Models\Planeacion\Catalogos\CatCodificados;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Los rangos deben dar lo mismo que las formulas AH/AI/AJ/AK de la hoja ALINEACION.
 */
class AlineacionRangosTest extends TestCase
{
    private function llamar(string $metodo, array $args): array
    {
        $m = new ReflectionMethod(AlineacionController::class, $metodo);
        $m->setAccessible(true);

        return $m->invokeArgs(app(AlineacionController::class), $args);
    }

    private function cat(?string $tolerancia, $pesoMuestra = null): CatCodificados
    {
        $cat = new CatCodificados;
        $cat->Tolerancia = $tolerancia;
        $cat->PesoMuestra = $pesoMuestra;

        return $cat;
    }

    /** Fila 14 de la hoja: Pe.C 628, tolerancia N -> 628/1.03 y 628*1.03. */
    public function test_peso_con_tolerancia_n(): void
    {
        $this->assertSame([610, 647], $this->llamar('rangoPesoAlineacion', [$this->cat('N'), 628]));
    }

    /** Sin "N": el minimo es el peso nominal y el maximo +5%. */
    public function test_peso_sin_tolerancia_n(): void
    {
        $this->assertSame([628, 659], $this->llamar('rangoPesoAlineacion', [$this->cat('S'), 628]));
    }

    /** Fila 14: muestra 4.85 con "N" -> 4.85*0.98 y 4.85*1.02, area 1.0875 m2 = "Mu". */
    public function test_muestra_articulo_mu(): void
    {
        $this->assertSame(
            [4.753, 4.947],
            $this->llamar('rangoMuestraAlineacion', [$this->cat('N', 4.85), 628, 150, 72.5])
        );
    }

    /** Fila 13: Pe.C 94 <= 220 -> "To", la muestra no aplica. */
    public function test_muestra_articulo_to_queda_vacia(): void
    {
        $this->assertSame(
            ['', ''],
            $this->llamar('rangoMuestraAlineacion', [$this->cat('N', 2.9), 94, 130, 17.8])
        );
    }
}
