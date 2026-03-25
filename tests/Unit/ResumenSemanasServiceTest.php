<?php

namespace Tests\Unit;

use App\Services\ProgramaUrdEng\ResumenSemanasService;
use Tests\TestCase;

class ResumenSemanasServiceTest extends TestCase
{
    public function test_validar_telares_consistentes_permite_salones_distintos(): void
    {
        $service = new ResumenSemanasService();

        $resultado = $service->validarTelaresConsistentes([
            [
                'no_telar' => '299',
                'tipo' => 'Rizo',
                'calibre' => 12,
                'hilo' => 'ALG',
                'salon' => 'ITEMA',
            ],
            [
                'no_telar' => '300',
                'tipo' => 'Rizo',
                'calibre' => 12,
                'hilo' => 'ALG',
                'salon' => 'SMIT',
            ],
        ]);

        $this->assertFalse($resultado['error']);
        $this->assertSame('RIZO', $resultado['tipo']);
        $this->assertSame('12', $resultado['calibre']);
        $this->assertArrayNotHasKey('salon', $resultado);
    }
}
