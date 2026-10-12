<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Costo por julio de Urdido (Cuotas → Costo por julio): un renglón por julio, con todos los decimales. */
class CostoJuliosExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  Collection<int, array<string, mixed>>  $julios  CostoJulios::julios()
     * @param  array<string, string>  $conceptos  columna => nombre (los visibles en pantalla)
     */
    public function __construct(private Collection $julios, private array $conceptos) {}

    public function headings(): array
    {
        return ['Fecha', 'Folio', 'No. julio', 'Máquina', 'Artículo', 'Rizo/Pie', 'Horario', 'Minutos', 'Kg neto',
            'Materia prima', ...array_values($this->conceptos), 'Conversión', 'Costo total', 'MP $/kg', 'Conversión $/kg', 'Total $/kg'];
    }

    public function array(): array
    {
        return $this->julios->map(function (array $j): array {
            $conversion = array_sum(array_map(fn ($col) => (float) $j[$col], array_keys($this->conceptos)));
            $total = (float) $j['mp'] + $conversion;
            $kg = (float) $j['kg'];

            return [$j['fecha'], $j['folio'], $j['julio'], $j['maquina'], $j['articulo'], $j['rizoPie'], $j['horario'],
                $j['minutos'], $j['kg'], $j['mp'], ...array_map(fn ($col) => $j[$col], array_keys($this->conceptos)),
                $conversion, $total,
                $kg > 0 ? (float) $j['mp'] / $kg : null, $kg > 0 ? $conversion / $kg : null, $kg > 0 ? $total / $kg : null];
        })->values()->all();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('C2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->getStyle('H2:'.$sheet->getHighestColumn().$sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0.00');

        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2563EB']]]];
    }
}
