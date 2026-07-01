<?php

namespace App\Exports;

use App\Models\Trazabilidad\TrazaProduccion;
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
 * Exporta el reporte de Trazabilidad de producción a Excel (pensado para enviar
 * a contabilidad). Incluye:
 *   - Encabezado de marca en BLANCO para que el logo azul de Towell sea visible.
 *   - Resumen de los filtros aplicados.
 *   - DOS tablas con el mismo heatmap que la web: una en Cantidad (piezas /
 *     material) y otra en Kilos (peso).
 *
 * Las matrices llegan ya construidas por TrazabilidadMatrixService para no
 * duplicar la lógica de negocio.
 */
class TrazabilidadExport implements WithColumnWidths, WithDrawings, WithEvents, WithTitle
{
    private const AZUL = '4F86C6';        // azul de marca Towell (acentos / encabezados)

    private const AZUL_TEXTO = '2F5C9E';  // azul más oscuro para títulos sobre blanco

    private const AZUL_CLARO = 'DBEAFE';  // fila de totales

    private const GRIS_BORDE = 'D1D5DB';

    private array $matrizCantidad;

    private array $matrizPeso;

    private ?object $info;

    private int $numFechas;        // nº de columnas de fecha (máximo entre ambas tablas)

    private int $ultimaCol;        // índice 1-based de la última columna global

    private string $ultimaColLetra;

    /**
     * @param  array  $matrices  ['cantidad' => matriz, 'peso' => matriz]
     * @param  array  $filtros  filtros activos para el resumen
     */
    public function __construct(array $matrices, private array $filtros)
    {
        $this->matrizCantidad = $matrices['cantidad'];
        $this->matrizPeso = $matrices['peso'];

        // Tipo/Cliente/Agente es igual en ambas (mismo Flog) → tomamos el de Cantidad.
        $this->info = $this->matrizCantidad['info'] ?? null;

        $this->numFechas = max(
            count($this->matrizCantidad['fechas']),
            count($this->matrizPeso['fechas'])
        );
        $this->ultimaCol = 2 + $this->numFechas; // A = Área, fechas, última = Total
        $this->ultimaColLetra = Coordinate::stringFromColumnIndex(max($this->ultimaCol, 2));
    }

    public function title(): string
    {
        return 'Trazabilidad';
    }

    /**
     * Logo de Towell arriba a la izquierda. El encabezado va en blanco para que
     * el logo (azul) no se pierda.
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
        $logo->setHeight(50);
        $logo->setCoordinates('A1');
        $logo->setOffsetX(10);
        $logo->setOffsetY(10);

        return $logo;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 30]; // Área
        for ($i = 2; $i <= $this->ultimaCol - 1; $i++) {
            $widths[Coordinate::stringFromColumnIndex($i)] = 9; // fechas
        }
        $widths[Coordinate::stringFromColumnIndex($this->ultimaCol)] = 12; // Total

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->construirHoja($event->sheet->getDelegate());
            },
        ];
    }

    private function construirHoja(Worksheet $sheet): void
    {
        $last = $this->ultimaColLetra;

        // ===== Encabezado de marca (filas 1-2) — fondo BLANCO para el logo azul =====
        $sheet->mergeCells("A1:{$last}1");
        $sheet->setCellValue('A1', '                    Reporte de Trazabilidad de Producción');
        $sheet->getRowDimension(1)->setRowHeight(54);
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => self::AZUL_TEXTO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Subtítulo (fila 2) — también sobre blanco, con una línea azul de separación.
        $sheet->mergeCells("A2:{$last}2");
        $sheet->setCellValue('A2', '                    Producción por día y área  ·  Generado: '.now()->format('d/m/Y H:i'));
        $sheet->getStyle("A2:{$last}2")->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '64748B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => self::AZUL]],
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // ===== Resumen de filtros =====
        $fila = 4;
        $sheet->setCellValue("A{$fila}", 'FILTROS APLICADOS');
        $sheet->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB(self::AZUL_TEXTO);
        $fila++;

        foreach ($this->resumenFiltros() as $etiqueta => $valor) {
            $sheet->setCellValue("A{$fila}", $etiqueta.':');
            $sheet->mergeCells('B'.$fila.':'.$last.$fila);
            $sheet->setCellValue("B{$fila}", $valor);
            $sheet->getStyle("A{$fila}")->getFont()->setBold(true)->getColor()->setRGB('334155');
            $sheet->getStyle("A{$fila}:{$last}{$fila}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $fila++;
        }

        // ===== Tabla 1: Cantidad (piezas / material) =====
        $fila++; // espacio
        $fila = $this->bandaSeccion($sheet, $fila, 'CANTIDAD  ·  Piezas / Material', $this->matrizCantidad);
        $fila = $this->escribirTabla($sheet, $this->matrizCantidad, $fila) + 2; // espacio

        // ===== Tabla 2: Kilos (peso) =====
        $fila = $this->bandaSeccion($sheet, $fila, 'KILOS  ·  Peso', $this->matrizPeso);
        $this->escribirTabla($sheet, $this->matrizPeso, $fila);
    }

    /**
     * Banda de sección azul (título de cada tabla). Devuelve la fila siguiente.
     */
    private function bandaSeccion(Worksheet $sheet, int $fila, string $texto, array $matriz): int
    {
        $last = $this->lastCol($matriz);

        $sheet->mergeCells("A{$fila}:{$last}{$fila}");
        $sheet->setCellValue("A{$fila}", '  '.$texto);
        $sheet->getRowDimension($fila)->setRowHeight(24);
        $sheet->getStyle("A{$fila}:{$last}{$fila}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        return $fila + 1;
    }

    /**
     * Escribe una tabla (Área | fechas | Total) a partir de su matriz.
     * Devuelve el índice de la última fila escrita.
     */
    private function escribirTabla(Worksheet $sheet, array $matriz, int $filaHeader): int
    {
        $fechas = $matriz['fechas'];
        $areas = $matriz['areas'];
        $totales = $matriz['totales'];
        $decimales = $matriz['decimales'];

        $numFechas = count($fechas);
        $ultimaCol = 2 + $numFechas;
        $ultimaColLetra = Coordinate::stringFromColumnIndex($ultimaCol);
        $last = $ultimaColLetra;

        // Sin datos para esta métrica con los filtros aplicados.
        if ($numFechas === 0 || empty($areas)) {
            $sheet->setCellValue("A{$filaHeader}", 'Sin datos para esta métrica con los filtros aplicados.');
            $sheet->getStyle("A{$filaHeader}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');

            return $filaHeader;
        }

        // --- Encabezado: Área | fechas | Total ---
        $sheet->setCellValue("A{$filaHeader}", 'Área');
        $col = 2;
        foreach ($fechas as $fecha) {
            $letra = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$letra}{$filaHeader}", $fecha['label']);
            $col++;
        }
        $sheet->setCellValue("{$ultimaColLetra}{$filaHeader}", 'Total');

        $sheet->getStyle("A{$filaHeader}:{$last}{$filaHeader}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL_TEXTO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$filaHeader}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($filaHeader)->setRowHeight(22);

        $formato = $decimales > 0 ? '#,##0.0' : '#,##0';

        // --- Filas de áreas ---
        $fila = $filaHeader + 1;
        foreach ($areas as $area) {
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
            foreach ($fechas as $i => $fecha) {
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
            $sheet->setCellValue("{$ultimaColLetra}{$fila}", round($totalArea, $decimales));
            $sheet->getStyle("{$ultimaColLetra}{$fila}")->getNumberFormat()->setFormatCode($formato);
            $sheet->getStyle("{$ultimaColLetra}{$fila}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $textHex]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
            ]);

            $fila++;
        }

        // --- Pie: totales por columna ---
        $sheet->setCellValue("A{$fila}", 'Total');
        $col = 2;
        foreach ($fechas as $i => $fecha) {
            $letra = Coordinate::stringFromColumnIndex($col);
            if (! is_null($totales[$i] ?? null)) {
                $sheet->setCellValue("{$letra}{$fila}", $totales[$i]);
                $sheet->getStyle("{$letra}{$fila}")->getNumberFormat()->setFormatCode($formato);
            }
            $col++;
        }
        $granTotal = array_sum(array_map(fn ($v) => (float) ($v ?? 0), $totales));
        $sheet->setCellValue("{$ultimaColLetra}{$fila}", round($granTotal, $decimales));
        $sheet->getStyle("{$ultimaColLetra}{$fila}")->getNumberFormat()->setFormatCode($formato);

        $sheet->getStyle("A{$fila}:{$last}{$fila}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::AZUL_TEXTO]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::AZUL_CLARO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Alineación centrada para las celdas numéricas del cuerpo.
        $sheet->getStyle('B'.($filaHeader + 1).":{$last}{$fila}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Bordes finos a toda la tabla.
        $sheet->getStyle("A{$filaHeader}:{$last}{$fila}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::GRIS_BORDE]],
            ],
        ]);

        return $fila;
    }

    /**
     * Letra de la última columna (Total) para una matriz concreta.
     */
    private function lastCol(array $matriz): string
    {
        $num = count($matriz['fechas']);

        return Coordinate::stringFromColumnIndex(max(2 + $num, 2));
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

        return trim($codigo.(filled($nombre) ? ' / '.$nombre : ''));
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
