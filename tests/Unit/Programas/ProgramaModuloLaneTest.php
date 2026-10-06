<?php

declare(strict_types=1);

namespace Tests\Unit\Programas;

use App\Support\Programas\ProgramaModulo;
use PHPUnit\Framework\TestCase;

/** Máquina de urdido -> tarjeta (antes, 3 copias de extractMcCoyNumber en controllers). */
class ProgramaModuloLaneTest extends TestCase
{
    public function test_urdido_mapea_mc_coy_1_a_3_y_karl_mayer_a_4(): void
    {
        $m = ProgramaModulo::Urdido;

        $this->assertSame(1, $m->laneNumber('Mc Coy 1'));
        $this->assertSame(3, $m->laneNumber(' mc coy 3 '));
        $this->assertSame(4, $m->laneNumber('Karl Mayer'));
        $this->assertNull($m->laneNumber('Mc Coy 4'));
        $this->assertNull($m->laneNumber('Otra'));
        $this->assertNull($m->laneNumber(null));
    }
}
