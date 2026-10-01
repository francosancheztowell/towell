<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\FiltroAx;
use PHPUnit\Framework\TestCase;

class FiltroAxTest extends TestCase
{
    public function test_meses_por_nombre_abreviatura_o_numero(): void
    {
        $this->assertSame(1, FiltroAx::aMes('Enero'));
        $this->assertSame(2, FiltroAx::aMes(' feb '));
        $this->assertSame(9, FiltroAx::aMes('sept'));
        $this->assertSame(9, FiltroAx::aMes('set'));
        $this->assertSame(12, FiltroAx::aMes('12'));
        $this->assertNull(FiltroAx::aMes('13'));
        $this->assertNull(FiltroAx::aMes('ma'), 'dos letras son ambiguas (marzo/mayo)');
        $this->assertSame(3, FiltroAx::aMes('mar'));
        $this->assertSame(5, FiltroAx::aMes('may'));
        $this->assertNull(FiltroAx::aMes('pepe'));
    }

    public function test_sintaxis_ax(): void
    {
        $mes = FiltroAx::aMes(...);

        $this->assertSame(['negada' => false, 'op' => '=', 'a' => 1], FiltroAx::condicion('enero', $mes));
        $this->assertSame(['negada' => false, 'op' => '..', 'a' => 1, 'b' => 3], FiltroAx::condicion('ene..mar', $mes));
        $this->assertSame(['negada' => false, 'op' => '..', 'a' => 6, 'b' => null], FiltroAx::condicion('jun..', $mes));
        $this->assertSame(['negada' => false, 'op' => '>=', 'a' => 6], FiltroAx::condicion('>=6', $mes));
        $this->assertSame(['negada' => true, 'op' => '=', 'a' => 12], FiltroAx::condicion('!dic', $mes));
        $this->assertNull(FiltroAx::condicion('xyz..mar', $mes));
        $this->assertNull(FiltroAx::condicion('..', $mes));

        $this->assertSame(['negada' => false, 'op' => '..', 'a' => 2026, 'b' => 2027], FiltroAx::condicion('2026..2027', FiltroAx::aNumero(...)));
        $this->assertNull(FiltroAx::condicion('dos mil', FiltroAx::aNumero(...)));
    }
}
