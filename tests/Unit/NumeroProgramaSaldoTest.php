<?php

namespace Tests\Unit;

use App\Support\Planeacion\NumeroPrograma;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Regla del owner (2026-10-10): Saldo = Pedido × (1 + %seg/100) − Producción, en todas las pantallas. */
class NumeroProgramaSaldoTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'ejemplo del owner' => [1000, 200, 5, 850.0],
            'sin segundas' => [1000, 200, 0, 800.0],
            'segundas como texto' => [1000, 0, '10', 1100.0],
            'producción mayor al pedido no da negativo' => [100, 500, 5, 0.0],
            'segundas negativas cuentan como 0' => [1000, 0, -5, 1000.0],
            'segundas vacías' => [1000, 100, null, 900.0],
        ];
    }

    #[DataProvider('casos')]
    public function test_saldo_desde_pedido(float $pedido, float $produccion, mixed $seg, float $esperado): void
    {
        $this->assertSame($esperado, NumeroPrograma::saldoDesdePedido($pedido, $produccion, $seg));
    }

    public function test_pedido_desde_saldo_es_el_inverso(): void
    {
        foreach ([[850, 200, 5], [440, 0, 10], [1234.5, 77, 3]] as [$saldo, $prod, $seg]) {
            $pedido = NumeroPrograma::pedidoDesdeSaldo($saldo, $prod, $seg);
            $this->assertEqualsWithDelta($saldo, NumeroPrograma::saldoDesdePedido($pedido, $prod, $seg), 0.02);
        }
        $this->assertSame(1000.0, NumeroPrograma::pedidoDesdeSaldo(850, 200, 5));
    }
}
