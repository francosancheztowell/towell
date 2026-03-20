<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReporteResumenSemanalEngomadoExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    protected array $datosSemanales;

    public function __construct(array $datosSemanales)
    {
        $this->datosSemanales = $datosSemanales;
    }

    public function array(): array
    {
        $result = [];
        foreach ($this->datosSemanales as $semana) {
            $result[] = [
                $semana['semana_label'],
                $semana['total_ordenes'],
                $semana['total_julios'],
                $semana['total_kg'],
                $semana['total_metros'],
                $semana['peso_promedio'],
                $semana['metros_promedio'],
                $semana['cuenta_promedio'],
                '', // Eficiencia
            ];
        }

        return $result;
    }

    public function headings(): array
    {
        return [
            '', // For SEM-WW-YY
            'ORDENES',
            'JULIOS',
            'KG',
            'METROS',
            'PESO PROM',
            'METROS PROM/JULIO',
            'CTA PROM/JULIO',
            'EFICIENCIA',
        ];
    }

    public function title(): string
    {
        return 'Resumen Semanal Engomado';
    }
}
