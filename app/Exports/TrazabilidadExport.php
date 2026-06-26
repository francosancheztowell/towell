<?php

namespace App\Exports;

use App\Models\Trazabilidad\TrazaProduccion;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
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
 * Exporta la matriz "Producción por día y área" de Trazabilidad a Excel:
 * logo de Towell, resumen de filtros aplicados y la tabla con colores por área
 * (mismo heatmap que la web). La matriz llega ya construida por
 * TrazabilidadMatrixService para no duplicar la lógica de negocio.
 */
class TrazabilidadExport implements WithColumnWidths, WithDrawings, WithEvents, WithTitle
{
    private const AZUL = '1E40AF';        // encabezado de la tabla

    private const AZUL_CLARO = 'DBEAFE';  // fila de totales

    private const GRIS_BORDE = 'D1D5DB';

    private array $fechas;

    private array $areas;

    private array $totales;

    private ?object $info;

    private string $metrica;

    private int $decimales;

    private int $numFechas;

    private int $ultimaCol;        // índice 1-based de la última columna (Total)

    private string $ultimaColLetra;

    public function __construct(array $matriz, private array $filtros)
    {
        $this->fechas = $matriz['fechas'];
        $this->areas = $matriz['areas'];
        $this->totales = $matriz['totales'];
        $this->info = $matriz['info'];
        $this->metrica = $matriz['metrica'];
        $this->decimales = $matriz['decimales'];

        $this->numFechas = count($this->fechas);
        $this->ultimaCol = 2 + $this->numFechas; // A = Área, fechas, última = Total
        $this->ultimaColLetra = Coordinate::stringFromColumnIndex($this->ultimaCol);
    }

    public function title(): string
    {
        return 'Trazabilidad';
    }

    /**
     * Logo de Towell flotando arriba a la izquierda.
     */
    public function drawings()
    {
        $ruta = public_path('images/fondosTowell/logo_towell.png');
        if (! is_file($ruta)) {
            return [];
        }

        $logo = new Drawing;
        $logo->setName('Towell');
        $logo->setPath($ruta);
        $logo->setHeight(46);
        $logo->setCoordinates('A1');
        $logo->setOffsetX(8);
        $logo->setOffsetY(8);

        return $logo;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 30]; // Área
        for ($i = 2; $i <= $this->ultimaCol - 1; $i++) {
            $widths[Coordinate::stringFromColumnIndex($i)] = 9; // fechas
        }
        $widths[$this->ultimaColLetra] = 12; // Total

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->construirHoja($sheet);
            },
        ];
    }

    private function construirHoja(Worksheet $sheet): void
    {
        $last = $this->ultimaColLetra;

        // ===== Banner de título (fila 1) =====
        $sheet->mergeCells("A1:{$last}1");
        $sheet->setCellValue('A1', '          TRAZABILIDAD');
        $sheet->getRowDimension(1)->setRowHeight(40);
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Subtítulo (fila 2)
        $metricaTxt = $this->metrica === 'peso' ? 'Kilos (Peso)' : 'Cantidad (Material)';
        $sheet->mergeCells("A2:{$last}2");
        $sheet->setCellValue('A2', 'Producción por día y área  ·  Métrica: ' . $metricaTxt . '  ·  Generado: ' . now()->format('d/m/Y H:i'));
        $sheet->getStyle("A2:{$last}2")->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // ===== Resumen de filtros =====
        $fila = 4;
        $sheet->setCellValue("A{$fila}", 'FILTROS APLICADOS');
        $sheet->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::AZUL);
        $fila++;

        foreach ($this->resumenFiltros() as $etiqueta => $valor) {
            $sheet->setCellValue("A{$fila}", $etiqueta . ':');
            $sheet->mergeCells('B' . $fila . ':' . $last . $fila);
            $sheet->setCellValue("B{$fila}", $valor);
            $sheet->getStyle("A{$fila}")->getFont()->setBold(true)->getColor()->setRGB('334155');
            $sheet->getStyle("A{$fila}:{$last}{$fila}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $fila++;
        }

        // ===== Tabla =====
        $filaHeader = $fila + 1;
        $this->escribirTabla($sheet, $filaHeader);

        // Congelar la primera columna (Área) y el encabezado de la tabla.
        $sheet->freezePane('B' . ($filaHeader + 1));
    }

    private function escribirTabla(Worksheet $sheet, int $filaHeader): void
    {
        $last = $this->ultimaColLetra;

        // --- Encabezado: Área | fechas | Total ---
        $sheet->setCellValue("A{$filaHeader}", 'Área');
        $col = 2;
        foreach ($this->fechas as $fecha) {
            $letra = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$letra}{$filaHeader}", $fecha['label']);
            $col++;
        }
        $sheet->setCellValue("{$this->ultimaColLetra}{$filaHeader}", 'Total');

        $sheet->getStyle("A{$filaHeader}:{$last}{$filaHeader}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$filaHeader}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($filaHeader)->setRowHeight(22);

        $formato = $this->decimales > 0 ? '#,##0.0' : '#,##0';

        // --- Filas de áreas ---
        $fila = $filaHeader + 1;
        foreach ($this->areas as $area) {
            $textHex = ltrim($area['text'], '#');
            $tintHex = ltrim($area['tint'], '#');

            // Celda del nombre de área.
            $sheet->setCellValue("A{$fila}", $area['label'] ?? $area['nombre']);
            $sheet->getStyle("A{$fila}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $textHex]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $tintHex]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Valores por fecha (con heatmap).
            $col = 2;
            foreach ($this->fechas as $i => $fecha) {
                $letra = Coordinate::stringFromColumnIndex($col);
                $valor = $area['valores'][$i] ?? null;
                if ($valor !== null) {
                    $sheet->setCellValue("{$letra}{$fila}", $valor);
                    $sheet->getStyle("{$letra}{$fila}")->getNumberFormat()->setFormatCode($formato);
                    $bg = $this->rgbaAHex($area['bgs'][$i] ?? null) ?? $tintHex;
                    $sheet->getStyle("{$letra}{$fila}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $textHex]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                    ]);
                }
                $col++;
            }

            // Total de la fila.
            $totalArea = array_sum(array_map(fn ($v) => (float) ($v ?? 0), $area['valores']));
            $sheet->setCellValue("{$this->ultimaColLetra}{$fila}", round($totalArea, $this->decimales));
            $sheet->getStyle("{$this->ultimaColLetra}{$fila}")->getNumberFormat()->setFormatCode($formato);
            $sheet->getStyle("{$this->ultimaColLetra}{$fila}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $textHex]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
            ]);

            $fila++;
        }

        // --- Pie: totales por columna ---
        $sheet->setCellValue("A{$fila}", 'Total');
        $col = 2;
        foreach ($this->fechas as $i => $fecha) {
            $letra = Coordinate::stringFromColumnIndex($col);
            if (! is_null($this->totales[$i] ?? null)) {
                $sheet->setCellValue("{$letra}{$fila}", $this->totales[$i]);
                $sheet->getStyle("{$letra}{$fila}")->getNumberFormat()->setFormatCode($formato);
            }
            $col++;
        }
        $granTotal = array_sum(array_map(fn ($v) => (float) ($v ?? 0), $this->totales));
        $sheet->setCellValue("{$this->ultimaColLetra}{$fila}", round($granTotal, $this->decimales));
        $sheet->getStyle("{$this->ultimaColLetra}{$fila}")->getNumberFormat()->setFormatCode($formato);

        $sheet->getStyle("A{$fila}:{$last}{$fila}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::AZUL]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL_CLARO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Alineación centrada para todas las celdas numéricas del cuerpo.
        $sheet->getStyle('B' . ($filaHeader + 1) . ":{$last}{$fila}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Bordes finos a toda la tabla.
        $sheet->getStyle("A{$filaHeader}:{$last}{$fila}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::GRIS_BORDE]],
            ],
        ]);
    }

    /**
     * Resumen legible de los filtros aplicados (etiqueta => valor).
     */
    private function resumenFiltros(): array
    {
        $r = [];

        if (filled($this->filtros['flog'])) {
            $r['Flog'] = $this->filtros['flog'];
        }
        if (filled($this->filtros['articulo'])) {
            $r['Artículo'] = $this->etiquetaCombo('Articulo', 'NombreArticulo', $this->filtros['articulo']);
        }
        if (filled($this->filtros['tamano'])) {
            $r['Tamaño'] = $this->filtros['tamano'];
        }
        if (filled($this->filtros['color'])) {
            $r['Color'] = $this->etiquetaCombo('Color', 'NombreColor', $this->filtros['color']);
        }
        if (filled($this->filtros['mes'])) {
            $r['Meses'] = $this->nombresMeses($this->filtros['mes']);
        }

        // Tipo / Cliente / Agente (solo con un Flog específico).
        if ($this->info) {
            if (filled($this->info->Tipo ?? null)) {
                $r['Tipo'] = $this->info->Tipo;
            }
            if (filled($this->info->Cliente ?? null)) {
                $r['Cliente'] = $this->info->Cliente;
            }
            if (filled($this->info->Agente ?? null)) {
                $r['Agente'] = $this->info->Agente;
            }
        }

        if (empty($r)) {
            $r['Filtro'] = 'Sin filtros (todos los registros)';
        }

        return $r;
    }

    /**
     * "código / nombre" para Artículo o Color a partir del código.
     */
    private function etiquetaCombo(string $colCod, string $colNom, string $codigo): string
    {
        $nombre = TrazaProduccion::query()
            ->where($colCod, $codigo)
            ->whereNotNull($colNom)->where($colNom, '<>', '')
            ->value($colNom);

        return trim($codigo . (filled($nombre) ? ' / ' . $nombre : ''));
    }

    /**
     * Nombres de los meses a partir del CSV "5,6".
     */
    private function nombresMeses(string $csv): string
    {
        $nombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return collect(explode(',', $csv))
            ->map(fn ($m) => (int) trim($m))
            ->filter()
            ->map(fn ($m) => $nombres[$m] ?? (string) $m)
            ->implode(', ');
    }

    /**
     * Convierte "rgba(r,g,b,a)" en HEX mezclando sobre blanco (Excel no soporta alpha).
     */
    private function rgbaAHex(?string $rgba): ?string
    {
        if (! $rgba || ! preg_match('/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,?\s*([\d.]+)?\s*\)/', $rgba, $m)) {
            return null;
        }
        $r = (int) $m[1];
        $g = (int) $m[2];
        $b = (int) $m[3];
        $a = isset($m[4]) ? (float) $m[4] : 1.0;

        $mezcla = fn ($c) => (int) round($c * $a + 255 * (1 - $a));

        return sprintf('%02X%02X%02X', $mezcla($r), $mezcla($g), $mezcla($b));
    }
}
