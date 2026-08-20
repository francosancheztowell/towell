<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\Alineacion\AlineacionController;
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

    /**
     * @return array<string, mixed>
     */
    private function mapear(ReqProgramaTejido $program): array
    {
        $method = new ReflectionMethod(AlineacionController::class, 'mapearProgramaTejidoAItem');

        return $method->invoke(new AlineacionController, $program, [], []);
    }
}
