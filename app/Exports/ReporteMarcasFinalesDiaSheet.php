<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;

class ReporteMarcasFinalesDiaSheet implements WithStyles, WithTitle, WithEvents
{
    protected object $grupoDia;

    public function __construct(object $grupoDia)
    {
        $this->grupoDia = $grupoDia;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function title(): string
    {
        return Carbon::parse($this->grupoDia->fecha)->format('d-m-Y');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->buildSheet($sheet);
            }
        ];
    }

    private function buildSheet(Worksheet $sheet): void
    {
        $turnoNames = [1 => 'PRIMER TURNO', 2 => 'SEGUNDO TURNO', 3 => 'TERCER TURNO', 4 => 'CUARTO TURNO'];
        $turnoHorarios = [1 => '6:30-14:30', 2 => '14:30-22:30', 3 => '22:30-6:30', 4 => ''];

        $currentRow = 1;
        $fecha = $this->grupoDia->fecha;
        $fechaFormateada = Carbon::parse($fecha)->format('d/m/Y');

        foreach ($this->grupoDia->turnos as $grupoTurno) {
            $turno = $grupoTurno->turno;
            $turnoName = $turnoNames[$turno] ?? "TURNO $turno";
            $horario = $turnoHorarios[$turno] ?? '';

            // === ENCABEZADO DEL TURNO ===
            $sheet->mergeCells("B{$currentRow}:D{$currentRow}");
            $sheet->setCellValue("B{$currentRow}", $turnoName);
            $sheet->mergeCells("F{$currentRow}:H{$currentRow}");
            $sheet->setCellValue("F{$currentRow}", $turnoName);
            $sheet->mergeCells("J{$currentRow}:L{$currentRow}");
            $sheet->setCellValue("J{$currentRow}", $turnoName);
            $sheet->getStyle("B{$currentRow}:L{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            ]);
            $currentRow++;

            // Fila info turno
            $sheet->setCellValue("A{$currentRow}", "{$turno} TURNO");
            $sheet->setCellValue("B{$currentRow}", 'HORARIO:');
            $sheet->setCellValue("C{$currentRow}", $horario);
            $sheet->getStyle("C{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            ]);
            $sheet->setCellValue("D{$currentRow}", 'SUPERVISOR:');
            $sheet->setCellValue("F{$currentRow}", 'DIA:');
            $sheet->setCellValue("H{$currentRow}", 'FECHA:');
            $sheet->setCellValue("I{$currentRow}", $fechaFormateada);
            $currentRow++;

            // Comentario de personal
            $sheet->mergeCells("C{$currentRow}:H{$currentRow}");
            $sheet->setCellValue("C{$currentRow}", 'COMENTARIO DE PERSONAL');
            $sheet->getStyle("C{$currentRow}:H{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['bold' => true],
            ]);
            $currentRow++;
            $currentRow++;

            // Leyenda
            $leyenda = 'F=FALTA,  P=PERMISO,  R=RETARDO,  C=CASTIGO,  I=INCAPACIDAD,  V=VACACIONES,  FL=FESTIVO LABORADO,  FD=FESTIVO DESCANSADO,  CL=CAMBIO DE LUGAR,  B=BAJA,  CT=CAMBIO DE TURNO';
            $sheet->mergeCells("A{$currentRow}:L{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $leyenda);
            $sheet->getStyle("A{$currentRow}")->applyFromArray([
                'font' => ['size' => 8, 'underline' => true],
            ]);
            $currentRow++;

            // Observaciones
            $sheet->setCellValue("A{$currentRow}", 'Observaciones:');
            $sheet->getStyle("A{$currentRow}")->applyFromArray([
                'font' => ['color' => ['rgb' => '0000FF']],
            ]);
            $currentRow++;
            $currentRow++;

            // === MÁQUINAS ===
            foreach ($grupoTurno->maquinas as $maquina) {
                // Encabezado de máquina
                $sheet->setCellValue("A{$currentRow}", strtoupper($maquina->maquina));
                $sheet->getStyle("A{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                ]);
                $sheet->setCellValue("C{$currentRow}", 'Mínimo Máximo');
                $sheet->getStyle("C{$currentRow}")->getFont()->setSize(8);
                $currentRow++;

                // Encabezados de columnas
                // A=Observaciones, B=R, C=P, D=L, E=tiempo, F=vel, G=telar, H=Marcador Inicial, I=Marcador Final, J=Marcas, K=Horas, L=Eficiencia, M=Temp
                $headers = ['Observaciones', 'R', 'P', 'L', 'tiempo', 'vel', 'telar', 'Marcador Inicial', 'Marcador Final', 'Marcas', 'Horas', 'Eficiencia', 'Temp.'];
                $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'];
                foreach ($headers as $idx => $header) {
                    $sheet->setCellValue($cols[$idx] . $currentRow, $header);
                }
                $sheet->getStyle("A{$currentRow}:M{$currentRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
                $currentRow++;

                // Datos de telares
                foreach ($maquina->telares as $registro) {
                    $sheet->setCellValue("A{$currentRow}", ''); // Observaciones
                    $sheet->setCellValue("B{$currentRow}", ''); // R
                    $sheet->setCellValue("C{$currentRow}", ''); // P
                    $sheet->setCellValue("D{$currentRow}", ''); // L
                    $sheet->setCellValue("E{$currentRow}", ''); // tiempo
                    $sheet->setCellValue("F{$currentRow}", ''); // vel (vacío, el usuario lo llena)
                    $sheet->setCellValue("G{$currentRow}", $registro->telar); // telar
                    $sheet->setCellValue("H{$currentRow}", ''); // Marcador Inicial (vacío)
                    $sheet->setCellValue("I{$currentRow}", ''); // Marcador Final (vacío)
                    $sheet->setCellValue("J{$currentRow}", $registro->marcas); // Marcas de TejMarcasLine
                    $sheet->setCellValue("K{$currentRow}", $registro->horas); // Horas de TejMarcasLine

                    // Fórmula de Eficiencia: =J{row}/(F{row}*60*K{row})*100000
                    // Si vel (F) es 0 o vacío, dará error, así que usamos IFERROR
                    $sheet->setCellValue("L{$currentRow}", "=IFERROR(J{$currentRow}/(F{$currentRow}*60*K{$currentRow})*100000,\"\")");

                    $sheet->setCellValue("M{$currentRow}", ''); // Temp

                    // Color amarillo para vel y telar
                    $sheet->getStyle("F{$currentRow}:G{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
                    ]);

                    // Color para Marcador Inicial y Final (rojo si hay valores altos)
                    $sheet->getStyle("H{$currentRow}:I{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF0000']],
                    ]);

                    // Horas en verde
                    $sheet->getStyle("K{$currentRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '92D050']],
                    ]);

                    $currentRow++;
                }

                $currentRow++; // Espacio entre máquinas
            }

            $currentRow += 2; // Espacio entre turnos
        }

        // Ajustar anchos de columna
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(5);
        $sheet->getColumnDimension('D')->setWidth(5);
        $sheet->getColumnDimension('E')->setWidth(8);
        $sheet->getColumnDimension('F')->setWidth(6);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(14);
        $sheet->getColumnDimension('J')->setWidth(10);
        $sheet->getColumnDimension('K')->setWidth(8);
        $sheet->getColumnDimension('L')->setWidth(10);
        $sheet->getColumnDimension('M')->setWidth(8);
    }
}
