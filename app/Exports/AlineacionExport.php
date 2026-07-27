<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export de Alineación (programa de tejido en proceso).
 * Encabezado de 2 filas (grupos combinados: Crudo, Hilo, Cenefa Trama, Peso, Muestra),
 * columnas de Telar a Tolerancia en azul y negritas, logo Towell arriba.
 *
 * @param  array<int, array<string, mixed>>  $items
 * @param  array<int, string>  $columnas
 * @param  array<string, string>  $columnLabels
 * @param  array<string, string>  $subColumnLabels
 * @param  array<string, array<int, string>>  $headerGroups
 */
class AlineacionExport implements FromArray, WithDrawings, WithEvents, WithTitle
{
    private const COLOR_HEADER = 'C6E0B4';
    private const COLOR_COLUMNA_AZUL = 'CCECFF';
    private const COLOR_COLUMNA_BLANCA = 'FFFFFF';

    /**
     * Anchos explícitos (unidades de caracter de Excel). No se usa autosize: con
     * encabezados combinados (merge de 2 filas / colspan) el autosize de PhpSpreadsheet
     * ignora el contenido de celdas fusionadas y calcula anchos incorrectos (Modelo
     * queda angosto, columnas de un dígito quedan anchas).
     */
    private const ANCHO_MODELO = 60;
    private const ANCHO_MUY_ANGOSTO = 6;
    private const ANCHO_ANGOSTO = 8;
    private const ANCHO_NORMAL = 14;

    private const COLUMNAS_MUY_ANGOSTAS = ['Tolerancia', 'RazSN'];
    private const COLUMNAS_ANGOSTAS = [
        'NoTelarId', 'CalibreRizo', 'Ancho', 'LargoCrudo', 'PesoCrudo',
        'Luchaje', 'MedidaPlano', 'NoTiras', 'PasadasComb1', 'PasadasComb2', 'PasadasComb3', 'PasadasComb4',
        'AnchoToalla', 'PesoGRM2', 'PesoMin', 'PesoMax', 'MuestraMin', 'MuestraMax',
        'Produccion', 'SaldoPedido', 'DiasEficiencia',
    ];

    /** Fila donde empieza el encabezado de la tabla (deja espacio arriba para el logo). */
    private const FILA_ENCABEZADO_1 = 4;
    private const FILA_ENCABEZADO_2 = 5;
    private const FILA_DATOS = 6;

    public function __construct(
        private array $items,
        private array $columnas,
        private array $columnLabels,
        private array $subColumnLabels,
        private array $headerGroups,
        private string $ultimaColumnaDestacada,
        private ?string $rutaLogo = null,
    ) {}

    public function array(): array
    {
        // Placeholder: los datos reales se escriben en registerEvents() (AfterSheet),
        // ya que el encabezado combinado de 2 filas y el logo no encajan en FromArray.
        return [['']];
    }

    public function title(): string
    {
        return 'Alineación';
    }

    public function drawings()
    {
        if ($this->rutaLogo === null) {
            return [];
        }

        $drawing = new Drawing();
        $drawing->setPath($this->rutaLogo);
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->escribirEncabezados($sheet);
                $this->escribirDatos($sheet);
                $this->aplicarEstilos($sheet);
            },
        ];
    }

    private function colGroupInfo(): array
    {
        $groupFirstCol = [];
        $colInGroup = [];
        foreach ($this->headerGroups as $parent => $cols) {
            $groupFirstCol[$cols[0]] = ['parent' => $parent, 'colspan' => count($cols)];
            foreach ($cols as $c) {
                $colInGroup[$c] = true;
            }
        }

        return [$groupFirstCol, $colInGroup];
    }

    private function escribirEncabezados(Worksheet $sheet): void
    {
        [$groupFirstCol, $colInGroup] = $this->colGroupInfo();

        $fila1 = self::FILA_ENCABEZADO_1;
        $fila2 = self::FILA_ENCABEZADO_2;

        $col = 1;
        foreach ($this->columnas as $columna) {
            $letra = Coordinate::stringFromColumnIndex($col);

            if (isset($groupFirstCol[$columna])) {
                $colspan = $groupFirstCol[$columna]['colspan'];
                $letraFin = Coordinate::stringFromColumnIndex($col + $colspan - 1);
                $sheet->setCellValue("{$letra}{$fila1}", $groupFirstCol[$columna]['parent']);
                $sheet->mergeCells("{$letra}{$fila1}:{$letraFin}{$fila1}");
            } elseif (empty($colInGroup[$columna])) {
                $sheet->setCellValue("{$letra}{$fila1}", $this->columnLabels[$columna] ?? $columna);
                $sheet->mergeCells("{$letra}{$fila1}:{$letra}{$fila2}");
            }

            if (!empty($colInGroup[$columna])) {
                $sheet->setCellValue("{$letra}{$fila2}", $this->subColumnLabels[$columna] ?? '');
            }

            $col++;
        }
    }

    private function escribirDatos(Worksheet $sheet): void
    {
        $filaActual = self::FILA_DATOS;
        foreach ($this->items as $item) {
            $col = 1;
            foreach ($this->columnas as $columna) {
                $letra = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$letra}{$filaActual}", $item[$columna] ?? '');
                $col++;
            }
            $filaActual++;
        }
    }

    private function aplicarEstilos(Worksheet $sheet): void
    {
        $fila1 = self::FILA_ENCABEZADO_1;
        $fila2 = self::FILA_ENCABEZADO_2;
        $ultimaFilaDatos = self::FILA_DATOS + count($this->items) - 1;
        $ultimaFila = max($ultimaFilaDatos, $fila2);
        $ultimaColumna = Coordinate::stringFromColumnIndex(count($this->columnas));

        // Encabezado: verde, negritas, centrado
        $sheet->getStyle("A{$fila1}:{$ultimaColumna}{$fila2}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Bordes de toda la tabla (encabezado + datos)
        $sheet->getStyle("A{$fila1}:{$ultimaColumna}{$ultimaFila}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        // Columnas de Telar a Tolerancia: azul y negritas (encabezado y datos)
        $indiceUltimaAzul = array_search($this->ultimaColumnaDestacada, $this->columnas, true);
        $columnaLimite = $indiceUltimaAzul !== false ? $indiceUltimaAzul + 1 : 0;

        for ($col = 1; $col <= count($this->columnas); $col++) {
            $letra = Coordinate::stringFromColumnIndex($col);
            $esDestacada = $col <= $columnaLimite;
            $color = $esDestacada ? self::COLOR_COLUMNA_AZUL : self::COLOR_COLUMNA_BLANCA;

            $sheet->getStyle("{$letra}{$fila2}:{$letra}{$ultimaFilaDatos}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
            ]);

            if ($esDestacada) {
                $sheet->getStyle("{$letra}{$fila2}:{$letra}{$ultimaFilaDatos}")->applyFromArray([
                    'font' => ['bold' => true],
                ]);
            }
        }

        for ($col = 1; $col <= count($this->columnas); $col++) {
            $columna = $this->columnas[$col - 1];
            $letra = Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($letra)->setWidth($this->anchoColumna($columna));
        }

        $indiceModelo = array_search('NombreProducto', $this->columnas, true);
        if ($indiceModelo !== false) {
            $letraModelo = Coordinate::stringFromColumnIndex($indiceModelo + 1);
            $sheet->getStyle("{$letraModelo}{$fila2}:{$letraModelo}{$ultimaFilaDatos}")->applyFromArray([
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
        }

        $sheet->freezePane('A'.self::FILA_DATOS);
    }

    private function anchoColumna(string $columna): int
    {
        if ($columna === 'NombreProducto') {
            return self::ANCHO_MODELO;
        }
        if (in_array($columna, self::COLUMNAS_MUY_ANGOSTAS, true)) {
            return self::ANCHO_MUY_ANGOSTO;
        }
        if (in_array($columna, self::COLUMNAS_ANGOSTAS, true)) {
            return self::ANCHO_ANGOSTO;
        }

        return self::ANCHO_NORMAL;
    }
}
