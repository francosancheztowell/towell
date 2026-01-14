<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Carbon\Carbon;
use App\Models\Tejido\TejMarcas;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MarcasFinalesExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents, WithColumnFormatting
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

        // Mapa Telar -> Salón (si viene en la secuencia)
        $salonPorTelar = collect($this->tablas)
            ->flatMap(function ($t) {
                return collect($t['telares'])->mapWithKeys(function ($obj) {
                    // los elementos de 'telares' pueden ser enteros o stdClass {NoTelarId, SalonId}
                    if (is_object($obj) && isset($obj->NoTelarId)) {
                        return [$obj->NoTelarId => ($obj->SalonId ?? null)];
                    }
                    return [$obj => null];
                });
            });

        // Orden: Turno 1, 2, 3; dentro de cada turno, telar en el orden provisto
        foreach ([1, 2, 3] as $turno) {
            $tabla = $porTurno->get($turno);
            if (!$tabla) continue;

            $folio = $tabla['folio'];
            $infoFolio = [
                'Status' => '',
                'numero_empleado' => '',
                'nombreEmpl' => ''
            ];
            if ($folio) {
                $m = TejMarcas::find($folio);
                if ($m) {
                    $infoFolio['Status'] = $m->Status ?? '';
                    $infoFolio['numero_empleado'] = $m->numero_empleado ?? '';
                    $infoFolio['nombreEmpl'] = $m->nombreEmpl ?? '';
                }
            }

            // Lista de telares en el orden recibido
            $telares = collect($tabla['telares'])->map(function ($obj) {
                return is_object($obj) && isset($obj->NoTelarId) ? $obj->NoTelarId : $obj;
            })->values();

            foreach ($telares as $telar) {
                $linea = optional($tabla['lineas'])->get($telar);

                // Fecha: usar la de la línea o la recibida en el constructor
                $fecha = null;
                try {
                    if ($linea && $linea->Date) {
                        $fecha = Carbon::parse($linea->Date);
                    } else {
                        $fecha = Carbon::parse($this->fecha);
                    }
                } catch (\Throwable $th) {
                    $fecha = null;
                }

                // Eficiencia como entero (0-100)
                $ef = null;
                if ($linea) {
                    $e = $linea->Eficiencia ?? $linea->EficienciaSTD ?? $linea->EficienciaStd ?? null;
                    if ($e !== null && $e !== '') {
                        if (is_numeric($e) && $e <= 1) $e = $e * 100; // si viene 0-1
                        $ef = intval(round($e));
                    }
                }

                $data->push([
                    'Folio' => $folio ?? '',
                    'Fecha' => $fecha, // Carbon para poder darle formato de fecha
                    'Turno' => $turno,
                    'Cve Empleado' => $infoFolio['numero_empleado'],
                    'Nombre Empleado' => $infoFolio['nombreEmpl'],
                    'Status' => $infoFolio['Status'],
                    'Telar' => $telar,
                    'Salon' => $salonPorTelar->get($telar) ?? ($linea->SalonTejidoId ?? ''),
                    'Eficiencia' => $ef,
                    'Marcas' => $linea->Marcas ?? null,
                    'Trama' => $linea->Trama ?? null,
                    'Pie' => $linea->Pie ?? null,
                    'Rizo' => $linea->Rizo ?? null,
                    'Otros' => $linea->Otros ?? null,
                ]);
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Folio',
            'Fecha',
            'Turno',
            'Cve Empleado',
            'Nombre Empleado',
            'Status',
            'Telar',
            'Salon',
            'Eficiencia',
            'Marcas',
            'Trama',
            'Pie',
            'Rizo',
            'Otros',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Encabezado: azul con letras blancas y centrado
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
            'A' => 12, // Folio
            'B' => 12, // Fecha
            'C' => 8,  // Turno
            'D' => 14, // Cve Empleado
            'E' => 22, // Nombre
            'F' => 14, // Status
            'G' => 10, // Telar
            'H' => 10, // Salon
            'I' => 12, // Eficiencia
            'J' => 10, // Marcas
            'K' => 10, // Trama
            'L' => 8,  // Pie
            'M' => 8,  // Rizo
            'N' => 10, // Otros
        ];
    }

    public function title(): string
    {
        // Nombre de la hoja: Reporte Marcas finales "fecha"
        try {
            $f = Carbon::parse($this->fecha)->format('d/m/Y');
        } catch (\Throwable $th) {
            $f = (string) $this->fecha;
        }
        return 'Reporte Marcas finales ' . $f;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $range = 'A1:' . $highestColumn . $highestRow;

                // Bordes negros para toda la tabla
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('000000'));

                // Altura del encabezado
                $sheet->getRowDimension(1)->setRowHeight(20);
            }
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_DATE_DDMMYYYY, // Fecha
            'C' => NumberFormat::FORMAT_NUMBER,        // Turno
            'I' => NumberFormat::FORMAT_NUMBER,        // Eficiencia (entero)
            'J' => NumberFormat::FORMAT_NUMBER,
            'K' => NumberFormat::FORMAT_NUMBER,
            'L' => NumberFormat::FORMAT_NUMBER,
            'M' => NumberFormat::FORMAT_NUMBER,
            'N' => NumberFormat::FORMAT_NUMBER,
        ];
    }
}
