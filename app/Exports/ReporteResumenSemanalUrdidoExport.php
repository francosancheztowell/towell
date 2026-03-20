<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
// Removed WithHeadings
use Maatwebsite\Excel\Concerns\WithTitle;

class ReporteResumenSemanalUrdidoExport implements FromArray, WithTitle, ShouldAutoSize
{
    protected array $datosSemanales;

    public function __construct(array $datosSemanales)
    {
        $this->datosSemanales = $datosSemanales;
    }

    public function array(): array
    {
        $headings = [
            '', // Based on image, first column is empty for SEM-WW-YY
            'No. de ORDENES',
            'No. Julios',
            'KG',
            'Metros',
            'cuentas (Campo interno e invisible)', // Exact string from image
            'Peso promedio por julio',
            'Metros promedio por julio',
            'Cuenta promedio por julio',
            'EFICIENCIA EN %',
        ];

        $result = [$headings]; // Prepend headings

        foreach ($this->datosSemanales as $semana) {
            $result[] = [
                $semana['semana_label'],
                number_format($semana['total_ordenes'], 0, ',', '.'),
                number_format($semana['total_julios'], 0, ',', '.'),
                number_format($semana['total_kg'], 2, ',', '.'),
                number_format($semana['total_metros'], 2, ',', '.'),
                number_format($semana['total_cuenta'], 2, ',', '.'),
                number_format($semana['peso_promedio'], 2, ',', '.'),
                number_format($semana['metros_promedio'], 2, ',', '.'),
                number_format($semana['cuenta_promedio'], 2, ',', '.'),
                '', // Eficiencia - still blank as requested
            ];
        }

        return $result;
    }

    // Removed headings() method

    public function title(): string
    {
        return 'Resumen Semanal Urdido';
    }
}
