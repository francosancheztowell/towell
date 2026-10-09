<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadVentasService;
use PHPUnit\Framework\TestCase;

class TrazabilidadVentasTest extends TestCase
{
    public function test_junta_lotes_y_no_mezcla_unidades_ni_monedas(): void
    {
        $fila = static fn (string $cliente, string $unidad, string $moneda, float $cantidad, float $importe, string $ultima): object => (object) compact('cliente', 'unidad', 'moneda', 'cantidad', 'importe', 'ultima');

        $resumen = TrazabilidadVentasService::resumir(collect([
            $fila('ACME', 'pza', 'MXP', 100, 1000, '2026-09-01 00:00:00'),
            $fila('ACME', 'PZA', 'MXP', -10, -100, '2026-09-05 00:00:00'), // otro lote + nota de crédito
            $fila('GLOBEX', 'kg', 'USD', 5, 50, '2026-08-01 00:00:00'),
        ]));

        $this->assertSame(['pza' => 90.0, 'kg' => 5.0], $resumen['cantidades']);
        $this->assertSame(['MXP' => 900.0, 'USD' => 50.0], $resumen['importes']);
        $this->assertSame(2, $resumen['totalClientes']);
        $this->assertSame('ACME', $resumen['clientes'][0]['cliente']);
        $this->assertSame('05/09/2026', $resumen['ultima']);
    }
}
