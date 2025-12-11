<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MarcasFinalesExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected $tablas;
    protected $fecha;

    public function __construct($tablas, $fecha)
    {
        $this->tablas = $tablas;
        $this->fecha = $fecha;
    }

    public function collection()
    {
        $data = collect([]);
        
        // Indexar por turno para acceso directo
        $porTurno = collect($this->tablas)->keyBy('turno');
        
        // Unificar lista de telares
        $telares = collect([]);
        foreach ($this->tablas as $t) {
            $telares = $telares->merge($t['telares']);
        }
        $telares = $telares->unique()->sort()->values();

        $get = function($turno, $telar) use ($porTurno) {
            return optional(optional($porTurno->get($turno))['lineas'])->get($telar);
        };

        $fmtEfi = function($linea) {
            if (!$linea) return '';
            $e = $linea->Eficiencia ?? $linea->EficienciaSTD ?? $linea->EficienciaStd ?? null;
            if ($e === null || $e === '') return '';
            if (is_numeric($e) && $e <= 1) $e = $e * 100;
            return intval(round($e)) . '%';
        };

        $val = function($l, $c) {
            return $l ? ($l->$c ?? '') : '';
        };

        foreach ($telares as $telar) {
            $t1 = $get(1, $telar);
            $t2 = $get(2, $telar);
            $t3 = $get(3, $telar);

            $data->push([
                'telar' => $telar,
                // Turno 1
                't1_eficiencia' => $fmtEfi($t1),
                't1_marcas' => $val($t1, 'Marcas'),
                't1_trama' => $val($t1, 'Trama'),
                't1_pie' => $val($t1, 'Pie'),
                't1_rizo' => $val($t1, 'Rizo'),
                't1_otros' => $val($t1, 'Otros'),
                // Turno 2
                't2_eficiencia' => $fmtEfi($t2),
                't2_marcas' => $val($t2, 'Marcas'),
                't2_trama' => $val($t2, 'Trama'),
                't2_pie' => $val($t2, 'Pie'),
                't2_rizo' => $val($t2, 'Rizo'),
                't2_otros' => $val($t2, 'Otros'),
                // Turno 3
                't3_eficiencia' => $fmtEfi($t3),
                't3_marcas' => $val($t3, 'Marcas'),
                't3_trama' => $val($t3, 'Trama'),
                't3_pie' => $val($t3, 'Pie'),
                't3_rizo' => $val($t3, 'Rizo'),
                't3_otros' => $val($t3, 'Otros'),
            ]);
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Telar',
            // Turno 1
            '% Ef T1',
            'Marcas T1',
            'TRAMA T1',
            'PIE T1',
            'RIZO T1',
            'OTROS T1',
            // Turno 2
            '% Ef T2',
            'Marcas T2',
            'TRAMA T2',
            'PIE T2',
            'RIZO T2',
            'OTROS T2',
            // Turno 3
            '% Ef T3',
            'Marcas T3',
            'TRAMA T3',
            'PIE T3',
            'RIZO T3',
            'OTROS T3',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 10, 'C' => 12, 'D' => 12, 'E' => 10, 'F' => 10, 'G' => 12,
            'H' => 10, 'I' => 12, 'J' => 12, 'K' => 10, 'L' => 10, 'M' => 12,
            'N' => 10, 'O' => 12, 'P' => 12, 'Q' => 10, 'R' => 10, 'S' => 12,
        ];
    }

    public function title(): string
    {
        return 'Marcas Finales ' . $this->fecha;
    }
}
