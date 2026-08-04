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
        foreach ([1875.5, 0.0] as $production) {
            $program = new ReqProgramaTejido;
            $program->setRawAttributes([
                'NoTelarId' => '215',
                'Produccion' => $production,
            ]);

            $method = new ReflectionMethod(AlineacionController::class, 'mapearProgramaTejidoAItem');
            $item = $method->invoke(new AlineacionController, $program, [], []);

            $this->assertSame($item['Produccion'], $item['ProdAcumMesAnt']);
            $this->assertSame($production, $item['ProdAcumMesAnt']);
        }
    }
}
