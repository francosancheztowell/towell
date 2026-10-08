<?php

namespace Tests\Unit\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Support\Planeacion\ProgramaTejido\ColumnasGrillaProgramaTejido;
use PHPUnit\Framework\TestCase;

/**
 * Caracterización de la definición de columnas de la grilla de Programa Tejido.
 * Fija orden, labels y dateType (hash del array completo) para que mover la
 * definición de clase no cambie lo que reciben la vista, el board Livewire y el read service.
 */
class ColumnasGrillaProgramaTejidoTest extends TestCase
{
    private const HASH = '57c7fdec50138340e74b3f94c62c8827';

    public function test_definicion_de_columnas_no_cambia(): void
    {
        $columnas = ColumnasGrillaProgramaTejido::todas();

        $this->assertCount(116, $columnas);
        $this->assertSame(['field' => 'EnProceso', 'label' => 'Estado', 'dateType' => null], $columnas[0]);
        $this->assertSame('PasadasBarra4', $columnas[115]['field']);
        $this->assertSame(self::HASH, md5((string) json_encode($columnas)));
    }

    public function test_adaptador_de_utility_helpers_devuelve_lo_mismo(): void
    {
        $this->assertSame(ColumnasGrillaProgramaTejido::todas(), UtilityHelpers::getTableColumns());
    }
}
