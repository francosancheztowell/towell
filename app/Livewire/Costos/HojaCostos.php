<?php

declare(strict_types=1);

namespace App\Livewire\Costos;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Hoja de costo unitario de un artículo: indicadores arriba y el costo por etapa
 * (Urdido y Engomado, Rizo y Pie) en una rejilla 2×2. Los filtros de la izquierda eligen el artículo.
 *
 * ponytail: solo front. Los datos son los del diseño (FENDI BD III, abril); cuando exista el
 * servicio de costeo, etapas() y 'kpi' se llenan de él y los filtros pasan a wire:model.live.
 */
class HojaCostos extends Component
{
    /** 'absorbente' = todos los gastos; 'directo' = sin fijos. ponytail: aún no cambia los números (no hay desglose fijo/variable). */
    #[Url(except: 'absorbente')]
    public string $costeo = 'absorbente';

    public string $modo = 'linea';

    public string $periodo = '4';

    public string $tamano = 'MB';

    public string $composicion = '1';

    public string $telar = '303';

    /**
     * Etapas con sus cuentas: [cuenta, cuota de referencia, $/kg]. kg base es el hilo de la etapa.
     * 'mp' es materia prima que viene de la etapa anterior: suma al $/kg pero se muestra como nota.
     *
     * @return list<array<string, mixed>>
     */
    public function etapas(): array
    {
        $etapas = [
            ['titulo' => 'Urdido — Rizo', 'total' => 'Urdido Rizo', 'tono' => 'blue', 'kg' => 0.169,
                'chips' => ['Tamaño 2766', '0.046 min', 'Hilo 0.169 kg', '3.713 kg/min', '0.2694 min/kg'],
                'filas' => [['Materia prima', '$46.0502/kg', 46.050], ['Mano de obra', '$3.3927/min', 0.914], ['Gastos', '$5.4781/min', 1.476]]],
            ['titulo' => 'Urdido — Pie', 'total' => 'Urdido Pie', 'tono' => 'amber', 'kg' => 0.089,
                'chips' => ['Tamaño 3278', '0.023 min', 'Hilo 0.089 kg', '3.919 kg/min', '0.2552 min/kg'],
                'filas' => [['Materia prima', '$43.9523/kg', 43.952], ['Mano de obra', '$3.3927/min', 0.866], ['Gastos', '$5.4781/min', 1.398]]],
            ['titulo' => 'Engomado — Rizo', 'total' => 'Engomado Rizo', 'tono' => 'emerald', 'kg' => 0.169, 'mp' => ['Urdido Rizo', 48.440],
                'chips' => ['Tamaño 2766', '0.041 min', 'Hilo 0.169 kg', '4.167 kg/min', '0.2400 min/kg'],
                'filas' => [['Mano de obra', '$7.6712/min', 1.841], ['Gastos', '$3.455/kg', 3.455], ['MIV · ENF0014-CB3.5-RIZO', '$/kg material', 1.725]]],
            ['titulo' => 'Engomado — Pie', 'total' => 'Engomado Pie', 'tono' => 'rose', 'kg' => 0.089, 'mp' => ['Urdido Pie', 46.216],
                'chips' => ['Tamaño 3278', '0.017 min', 'Hilo 0.089 kg', '5.174 kg/min', '0.1933 min/kg'],
                'filas' => [['Mano de obra', '$7.6712/min', 1.483], ['Gastos', '$2.783/kg', 2.783], ['MIV · ENF0032-001-10/1 OPC', '$/kg material', 3.165]]],
        ];

        foreach ($etapas as &$e) {
            $e['porKg'] = ($e['mp'][1] ?? 0) + array_sum(array_column($e['filas'], 2));
            $e['importe'] = $e['porKg'] * $e['kg'];
        }

        return $etapas;
    }

    public function render(): View
    {
        return view('livewire.costos.hoja-costos', [
            'etapas' => $this->etapas(),
            'kpi' => ['totalKg' => 165.88, 'totalPza' => 50.20, 'ventaKg' => 125.92, 'ventaPza' => 38.11, 'crudo' => 0.335, 'terminado' => 0.303],
        ]);
    }
}
