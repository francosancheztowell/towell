<?php

declare(strict_types=1);

namespace App\Services\Ventas;

/**
 * Datos temporales para construir la interfaz de Ventas sin depender de Excel
 * ni de las tablas de reportes.
 */
final class PvVsOcMockPayload
{
    /**
     * @return array{meta: array{archivo: string, fecha: string}, records: list<array<string, mixed>>}
     */
    public function build(): array
    {
        $records = [];
        $empresas = ['Towell', 'Textil'];
        $tipos = ['CE', 'CE HT', 'RS'];
        $clientes = [
            ['C-1042', 'Hotel Marriott'],
            ['C-2180', 'Liverpool'],
            ['C-3301', 'Walmart'],
            ['C-4410', 'Amazon MX'],
        ];
        $articulos = [
            ['7408', 'JQ Soft Dry Natura Avmex', 'MB', '70 x 140', 'Azul rey listada'],
            ['7409', 'JQ Grid Palace Avmex', 'MB', '70 x 140', 'Beige'],
            ['7425', 'Trans JQ Conejito Avmex', 'TRA', '50 x 100', 'Rosa 1009'],
        ];
        $estatus = ['En meta', 'Parcial', 'Bajo'];

        foreach ($empresas as $empresaIndex => $empresa) {
            foreach ($tipos as $tipoIndex => $tipo) {
                foreach ($clientes as $clienteIndex => [$clienteCode, $cliente]) {
                    foreach ($articulos as $articuloIndex => [$articuloCode, $articulo, $linea, $tamano, $color]) {
                        $seed = 1250 + ($empresaIndex * 450) + ($tipoIndex * 180) + ($clienteIndex * 135) + ($articuloIndex * 80);
                        $planPiezas = $seed;
                        $pedidoPiezas = (int) round($planPiezas * (0.72 + (($clienteIndex + $tipoIndex) % 3) * 0.09));
                        $realPiezas = (int) round($pedidoPiezas * (0.7 + (($articuloIndex + $empresaIndex) % 3) * 0.1));

                        $records[] = [
                            'anio' => 2026,
                            'mes' => ($clienteIndex % 6) + 1,
                            'semana' => ($articuloIndex * 2) + 1,
                            'empresa' => $empresa,
                            'tipo' => $tipo,
                            'clienteCodigo' => $clienteCode,
                            'cliente' => $cliente,
                            'articuloCodigo' => $articuloCode,
                            'articulo' => $articulo,
                            'linea' => $linea,
                            'tamano' => $tamano,
                            'color' => $color,
                            'estatus' => $estatus[($clienteIndex + $articuloIndex) % count($estatus)],
                            'plan' => $this->metricas($planPiezas),
                            'pedido' => $this->metricas($pedidoPiezas),
                            'real' => $this->metricas($realPiezas),
                        ];
                    }
                }
            }
        }

        return [
            'meta' => [
                'archivo' => 'Datos de demostración',
                'fecha' => now()->format('d/m/Y H:i'),
            ],
            'records' => $records,
        ];
    }

    /** @return array{piezas: int, kilos: float, vn: float} */
    private function metricas(int $piezas): array
    {
        return [
            'piezas' => $piezas,
            'kilos' => round($piezas * 0.42, 2),
            'vn' => round($piezas * 80.445, 2),
        ];
    }
}
