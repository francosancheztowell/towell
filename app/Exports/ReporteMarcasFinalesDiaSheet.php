<?php

namespace App\Exports;

use Illuminate\Support\Collection;
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
    protected Collection $velocidadesPorTelar;

    private const TURNO_NAMES = [
        1 => 'PRIMER TURNO',
        2 => 'SEGUNDO TURNO',
        3 => 'TERCER TURNO',
        4 => 'CUARTO TURNO',
    ];

    private const TURNO_HORARIOS = [
        1 => '6:30-14:30',
        2 => '14:30-22:30',
        3 => '22:30-6:30',
        4 => '',
    ];

    private const DIAS_SEMANA = [
        'Monday' => 'LUNES',
        'Tuesday' => 'MARTES',
        'Wednesday' => 'MIERCOLES',
        'Thursday' => 'JUEVES',
        'Friday' => 'VIERNES',
        'Saturday' => 'SABADO',
        'Sunday' => 'DOMINGO',
    ];

    public function __construct(object $grupoDia, Collection $velocidadesPorTelar)
    {
        $this->grupoDia = $grupoDia;
        $this->velocidadesPorTelar = $velocidadesPorTelar;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function title(): string
    {
        $fecha = Carbon::parse($this->grupoDia->fecha);
        $diaSemana = self::DIAS_SEMANA[$fecha->format('l')] ?? '';
        return $fecha->format('d') . ' (' . $diaSemana . ')';
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
        $currentRow = 1;
        $fecha = Carbon::parse($this->grupoDia->fecha);
        $fechaFormateada = $fecha->format('d/m/Y');
        $diaSemana = self::DIAS_SEMANA[$fecha->format('l')] ?? '';

        foreach ($this->grupoDia->turnos as $grupoTurno) {
            $turno = $grupoTurno->turno;
            $turnoName = self::TURNO_NAMES[$turno] ?? "TURNO $turno";
            $horario = self::TURNO_HORARIOS[$turno] ?? '';

            $currentRow = $this->renderEncabezadoTurno($sheet, $currentRow, $turno, $turnoName, $horario, $diaSemana, $fechaFormateada);

            foreach ($grupoTurno->maquinas as $maquina) {
                $currentRow = $this->renderMaquina($sheet, $currentRow, $maquina);
            }

            $currentRow += 2;
        }

        $this->ajustarAnchoColumnas($sheet);
    }

    private function renderEncabezadoTurno(Worksheet $sheet, int $row, int $turno, string $turnoName, string $horario, string $diaSemana, string $fechaFormateada): int
    {
        // Fila 1: PRIMER TURNO (repetido 3 veces, fondo amarillo)
        $sheet->mergeCells("B{$row}:E{$row}");
        $sheet->setCellValue("B{$row}", $turnoName);
        $sheet->mergeCells("G{$row}:J{$row}");
        $sheet->setCellValue("G{$row}", $turnoName);
        $sheet->mergeCells("L{$row}:O{$row}");
        $sheet->setCellValue("L{$row}", $turnoName);
        $sheet->getStyle("B{$row}:O{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
        ]);
        $row++;

        // Fila 2: 1 TURNO | HORARIO: | 6:30-14:30 | SUPERVISOR: | ... | DIA: | LUNES | FECHA: | 20/04/2026
        $sheet->setCellValue("A{$row}", "{$turno} TURNO");
        $sheet->setCellValue("B{$row}", 'HORARIO:');
        $sheet->mergeCells("C{$row}:D{$row}");
        $sheet->setCellValue("C{$row}", $horario);
        $sheet->getStyle("C{$row}:D{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue("E{$row}", 'SUPERVISOR:');
        $sheet->setCellValue("I{$row}", 'DIA:');
        $sheet->setCellValue("J{$row}", $diaSemana);
        $sheet->setCellValue("L{$row}", 'FECHA:');
        $sheet->setCellValue("M{$row}", $fechaFormateada);
        $row++;

        // Fila 3: COMENTARIO DE PERSONAL (fondo amarillo)
        $sheet->mergeCells("C{$row}:L{$row}");
        $sheet->setCellValue("C{$row}", 'COMENTARIO  DE  PERSONAL');
        $sheet->getStyle("C{$row}:L{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'font' => ['bold' => true],
        ]);
        $row++;
        $row++; // Fila vacía

        // Fila 5: Leyenda
        $leyenda = 'F=FALTA,     P=PERMISO,     R=RETARDO,     C=CASTIGO,     I=INCAPACIDAD,     V=VACACIONES,     FL=FESTIVO LABORADO,     FD=FESTIVO DESCANSADO,     CL=CAMBIO DE LUGAR,     B=BAJA,     CT=CAMBIO DE TURNO';
        $sheet->mergeCells("A{$row}:P{$row}");
        $sheet->setCellValue("A{$row}", $leyenda);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['size' => 8, 'underline' => true],
        ]);
        $row++;
        $row++; // Fila vacía

        // Fila 7: Observaciones (azul)
        $sheet->setCellValue("A{$row}", 'Observaciones:');
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['color' => ['rgb' => '0000FF'], 'bold' => true],
        ]);
        $row++;
        $row++; // Fila vacía

        return $row;
    }

    private function renderMaquina(Worksheet $sheet, int $row, object $maquina): int
    {
        $nombreMaquina = strtoupper($maquina->maquina);
        $telares = $maquina->telares;
        $totalTelares = $telares->count();

        // Separar nombre de máquina en dos líneas si es necesario
        $nombrePartes = explode(' ', $nombreMaquina);
        $linea1 = $nombrePartes[0] ?? '';
        $linea2 = $nombrePartes[1] ?? '';

        // Fila encabezado máquina: JACQUARD | TEMPERATURA | 20 | 29 | ... | #¡DIV/0! | MAQUINAS TRABAJANDO | 8.0
        $sheet->setCellValue("A{$row}", $linea1);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
        ]);
        $sheet->setCellValue("B{$row}", 'TEMPERATURA');
        $sheet->setCellValue("C{$row}", 'Mínimo');
        $sheet->setCellValue("D{$row}", 'Máximo');
        // Celdas rojas vacías para marcadores
        $sheet->getStyle("F{$row}:G{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF0000']],
        ]);
        $sheet->getStyle("I{$row}:J{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF0000']],
        ]);
        $sheet->setCellValue("N{$row}", 'MAQUINAS TRABAJANDO');
        $sheet->setCellValue("O{$row}", $totalTelares);
        $sheet->getStyle("O{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
        ]);
        $row++;

        // Segunda línea del nombre (SULZER, SMIT, etc.)
        $sheet->setCellValue("A{$row}", $linea2);
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
        ]);
        $sheet->setCellValue("B{$row}", 'HUMEDAD');
        $row++;
        $row++; // Fila vacía

        // Fila encabezados de columnas
        $sheet->setCellValue("F{$row}", 'Marcador');
        $sheet->setCellValue("I{$row}", 'Marcador');
        $sheet->getStyle("F{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getStyle("I{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->setCellValue("K{$row}", 'Marcas');
        $sheet->getStyle("K{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->setCellValue("L{$row}", 'Horas');
        $sheet->getStyle("L{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '92D050']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->setCellValue("M{$row}", 'Eficiencia');
        $sheet->getStyle("M{$row}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $row++;

        // Segunda fila de encabezados
        $sheet->setCellValue("A{$row}", 'Observaciones');
        $sheet->setCellValue("B{$row}", 'R');
        $sheet->setCellValue("C{$row}", 'P');
        $sheet->setCellValue("D{$row}", 'L');
        $sheet->setCellValue("E{$row}", 'tiempo');
        $sheet->setCellValue("F{$row}", 'Inicial');
        $sheet->setCellValue("G{$row}", 'vel');
        $sheet->setCellValue("H{$row}", 'telar');
        $sheet->setCellValue("I{$row}", 'Final');
        // K, L, M ya tienen encabezados arriba
        $sheet->setCellValue("N{$row}", 'Temp.');

        // Estilos para encabezados
        $sheet->getStyle("F{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getStyle("G{$row}:H{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("I{$row}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $row++;

        // Datos de telares
        $primeraFilaDatos = $row;
        foreach ($telares as $registro) {
            $telar = $registro->telar;
            $marcas = $registro->marcas;
            $horas = $registro->horas;
            $velocidad = (float) ($this->velocidadesPorTelar[$telar] ?? 0);

            // Calcular eficiencia
            $eficiencia = ($velocidad > 0 && $horas > 0)
                ? round(($marcas / ($velocidad * 60 * $horas)) * 100000, 0)
                : 0;

            // Columna A-E: Observaciones, R, P, L, tiempo (vacías con fondo rojo para observaciones)
            $sheet->getStyle("A{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FF0000']],
            ]);

            // F: Marcador Inicial (vacío, fondo amarillo)
            $sheet->getStyle("F{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // G: vel (velocidad de ReqTelares, fondo amarillo)
            $sheet->setCellValue("G{$row}", $velocidad > 0 ? $velocidad : '');
            $sheet->getStyle("G{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // H: telar (fondo amarillo)
            $sheet->setCellValue("H{$row}", $telar);
            $sheet->getStyle("H{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // I: Marcador Final (vacío, fondo amarillo)
            $sheet->getStyle("I{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // K: Marcas
            $sheet->setCellValue("K{$row}", $marcas);
            $sheet->getStyle("K{$row}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // L: Horas (fondo verde)
            $sheet->setCellValue("L{$row}", $horas);
            $sheet->getStyle("L{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '92D050']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            // M: Eficiencia (fondo rojo si < 80, naranja si < 90, verde si >= 90)
            $sheet->setCellValue("M{$row}", $eficiencia > 0 ? $eficiencia : '');
            $eficienciaColor = 'FF0000'; // Rojo por defecto
            if ($eficiencia >= 90) {
                $eficienciaColor = '92D050'; // Verde
            } elseif ($eficiencia >= 80) {
                $eficienciaColor = 'FFC000'; // Naranja
            } elseif ($eficiencia >= 70) {
                $eficienciaColor = 'FFFF00'; // Amarillo
            }
            $sheet->getStyle("M{$row}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $eficienciaColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['bold' => true],
            ]);

            $row++;
        }
        $ultimaFilaDatos = $row - 1;

        // Fila de promedio de eficiencia
        $row++;
        if ($ultimaFilaDatos >= $primeraFilaDatos) {
            $sheet->setCellValue("L{$row}", 'Promedio:');
            $sheet->getStyle("L{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $sheet->setCellValue("M{$row}", "=IFERROR(AVERAGE(M{$primeraFilaDatos}:M{$ultimaFilaDatos}),\"\")");
            $sheet->getStyle("M{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
        }

        $row += 2; // Espacio entre máquinas

        return $row;
    }

    private function ajustarAnchoColumnas(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(8);
        $sheet->getColumnDimension('D')->setWidth(8);
        $sheet->getColumnDimension('E')->setWidth(8);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(6);
        $sheet->getColumnDimension('H')->setWidth(6);
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('J')->setWidth(10);
        $sheet->getColumnDimension('K')->setWidth(10);
        $sheet->getColumnDimension('L')->setWidth(8);
        $sheet->getColumnDimension('M')->setWidth(10);
        $sheet->getColumnDimension('N')->setWidth(22);
        $sheet->getColumnDimension('O')->setWidth(6);
    }
}
