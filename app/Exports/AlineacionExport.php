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
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export de Alineación (programa de tejido en proceso).
 * Encabezado de 2 filas (grupos combinados: Crudo, Hilo, Cenefa Trama, Peso, Muestra),
 * columnas de Telar a Tolerancia en azul y negritas, logo Towell arriba.
 * Impresión: oficio horizontal, ajustado a una hoja.
 *
 * @param  array<int, array<string, mixed>>  $items
 * @param  array<int, string>  $columnas
 * @param  array<string, string>  $columnLabels
 * @param  array<string, string>  $subColumnLabels
 * @param  array<string, array<int, string>>  $headerGroups
 */
class AlineacionExport implements FromArray, WithDrawings, WithEvents, WithTitle
{
    private const COLOR_HEADER = 'D9EAD3';
    private const COLOR_COLUMNA_AZUL = 'CCFFFF';
    private const COLOR_COLUMNA_BLANCA = 'FFFFFF';

    /** Margen (en caracteres) que se suma al contenido más largo de cada columna. */
    private const ANCHO_MARGEN = 6;
    private const ANCHO_MINIMO = 8;

    private const FUENTE = 'Arial';
    private const TAMANO_FUENTE_DEFAULT = 23;
    private const TAMANOS_FUENTE = [
        'NoTelarId' => 25,
        'NoProduccion' => 25,
        'FechaCambio' => 22,
        'FechaCompromiso' => 22,
        'ItemId' => 25,
        'NombreProducto' => 27,
        'Tolerancia' => 25,
    ];

    /**
     * Tamaños "de referencia" (los que ya daban el ancho de columna que gustó) usados
     * solo para calcular el ancho, para que subir TAMANOS_FUENTE no ensanche columnas.
     */
    private const TAMANO_FUENTE_REFERENCIA_DEFAULT = 18;
    private const TAMANOS_FUENTE_REFERENCIA = [
        'NoTelarId' => 20,
        'NoProduccion' => 20,
        'FechaCambio' => 17,
        'FechaCompromiso' => 17,
        'ItemId' => 20,
        'NombreProducto' => 22,
        'Tolerancia' => 20,
    ];

    /** Alto de fila para datos: filas altas para que la tabla rellene más la hoja al imprimir. */
    private const ALTURA_FILA_DATOS = 95.0;

    /** Fila donde empieza el encabezado de la tabla (deja espacio arriba para el logo). */
    private const FILA_ENCABEZADO_1 = 11;
    private const FILA_ENCABEZADO_2 = 12;
    private const FILA_DATOS = 13;

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
        $drawing->setHeight(150);
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

        // Bordes de toda la tabla (encabezado + datos), negros como los de Excel por defecto
        $sheet->getStyle("A{$fila1}:{$ultimaColumna}{$ultimaFila}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
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
            $tamano = self::TAMANOS_FUENTE[$columna] ?? self::TAMANO_FUENTE_DEFAULT;
            $tamanoReferencia = self::TAMANOS_FUENTE_REFERENCIA[$columna] ?? self::TAMANO_FUENTE_REFERENCIA_DEFAULT;

            $sheet->getColumnDimension($letra)->setWidth($this->anchoColumna($columna, $tamanoReferencia));
            $sheet->getStyle("{$letra}{$fila1}:{$letra}{$ultimaFilaDatos}")->applyFromArray([
                'font' => ['name' => self::FUENTE, 'size' => $tamano],
            ]);
        }

        for ($fila = self::FILA_DATOS; $fila <= $ultimaFilaDatos; $fila++) {
            $sheet->getRowDimension($fila)->setRowHeight(self::ALTURA_FILA_DATOS);
        }

        $sheet->freezePane('A'.self::FILA_DATOS);

        $this->configurarImpresion($sheet, $ultimaColumna, $ultimaFila);
    }

    /**
     * Ancho = longitud del valor más largo de esa columna (encabezado y datos) + margen,
     * escalado al tamaño de fuente de la columna (la unidad de ancho de Excel se basa en
     * la fuente por defecto de 11pt; columnas con fuente más grande necesitan más ancho
     * por caracter). No se usa autosize de PhpSpreadsheet: con encabezados combinados
     * (merge de 2 filas / colspan) ignora el contenido de celdas fusionadas y calcula
     * anchos incorrectos.
     */
    private function anchoColumna(string $columna, int $tamanoFuente): int
    {
        $etiqueta = $this->subColumnLabels[$columna] ?? $this->columnLabels[$columna] ?? $columna;
        $maxLargo = mb_strlen((string) $etiqueta);

        foreach ($this->items as $item) {
            $valor = (string) ($item[$columna] ?? '');
            $maxLargo = max($maxLargo, mb_strlen($valor));
        }

        $factorFuente = $tamanoFuente / 11;

        return max(self::ANCHO_MINIMO, (int) ceil(($maxLargo + self::ANCHO_MARGEN) * $factorFuente));
    }

    /**
     * Legal horizontal, ancho ajustado a 1 hoja (Excel calcula la escala automáticamente);
     * el alto queda libre, así que si hay muchas filas continúa en más hojas hacia abajo
     * en vez de encogerse también verticalmente.
     */
    private function configurarImpresion(Worksheet $sheet, string $ultimaColumna, int $ultimaFila): void
    {
        $pageSetup = $sheet->getPageSetup();
        $pageSetup->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pageSetup->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
        $pageSetup->setFitToPage(true);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setPrintArea("A1:{$ultimaColumna}{$ultimaFila}");

        $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.4)->setRight(0.3);
    }
}
