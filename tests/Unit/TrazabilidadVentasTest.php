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

    public function test_detalle_agrupa_por_mes_cliente_y_saca_el_precio(): void
    {
        $linea = static fn (string $folio, string $fecha, string $tipo, string $cliente, float $cantidad, float $neto, string $moneda = 'MXP'): object => (object) [
            'folio' => $folio, 'fecha' => $fecha.' 00:00:00.000', 'oc' => 'OC1', 'cliente' => $cliente, 'tipo' => $tipo,
            'moneda' => $moneda, 'unidad' => 'pza', 'cantidad' => $cantidad,
            'bruto' => $neto * 1.1, 'descuento' => $neto * 0.1, 'neto' => $neto,
        ];

        $detalle = TrazabilidadVentasService::armarDetalle(collect([
            $linea('TW-2', '2026-09-10', 'FACTURA', 'GLOBEX', 60, 600),
            $linea('TW-1', '2026-08-01', 'FACTURA', 'ACME', 100, 1000),
            $linea('NC-1', '2026-09-12', 'NOTA CREDITO', 'ACME', -10, -100),
            $linea('TW-3', '2026-09-15', 'FACTURA', 'ACME', 5, 9, 'USD'), // moneda minoritaria: fuera de meses/clientes
        ]), 200.0);

        $this->assertSame('MXP', $detalle['moneda']);
        $this->assertSame([['2026-08', 1000.0, 100.0], ['2026-09', 500.0, 55.0]], array_map(
            static fn (array $m): array => [$m['mes'], $m['neto'], $m['cantidad']],
            $detalle['meses'],
        ));
        $this->assertSame([['ACME', 900.0], ['GLOBEX', 600.0]], array_map(
            static fn (array $c): array => [$c['cliente'], $c['neto']],
            $detalle['clientes'],
        ));
        $this->assertSame(10.0, $detalle['precio']); // 1500 MXP / 150 pza
        $this->assertSame('TW-3', $detalle['facturas'][0]['folio']);
        $this->assertSame(3, $detalle['totales']['facturas']);
        $this->assertSame(1, $detalle['totales']['notasCredito']);
        $this->assertSame(10.0, $detalle['totales']['devuelto']);
        $this->assertSame(200.0, $detalle['totales']['producido']);
    }
}
