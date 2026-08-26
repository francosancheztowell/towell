<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export del reporte "Panel de Control — Telares KM".
 *
 * Réplica visual del dashboard original (daniel.xlsx): banner navy, KPIs en pares
 * combinados, detalle semanal a la izquierda con el análisis de observaciones al
 * costado (N:P), 4 gráficos nativos, hallazgos y nota. Sin fórmulas: los valores
 * llegan calculados en el array del contrato de PanelControlKmService::build().
 */
class PanelControlKmExport implements WithMultipleSheets
{
    public function __construct(private array $data) {}

    public function sheets(): array
    {
        return [
            new PanelControlKmDashboardSheet($this->data),
            new PanelControlKmDatosSheet($this->data),
        ];
    }
}

/**
 * Utilidades compartidas por las hojas del panel.
 */
trait PanelControlKmHelpers
{
    protected const COLOR_NAVY = '1F3864';

    protected const ESTADO_COLORES = [
        'En meta' => ['bg' => 'C6EFCE', 'txt' => '006100'],
        'Atención' => ['bg' => 'FFEB9C', 'txt' => '9C6500'],
        'Atencion' => ['bg' => 'FFEB9C', 'txt' => '9C6500'],
        'Crítico' => ['bg' => 'FFC7CE', 'txt' => '9C0006'],
        'Critico' => ['bg' => 'FFC7CE', 'txt' => '9C0006'],
        'Sin dato' => ['bg' => 'E7E6E6', 'txt' => '595959'],
    ];

    /**
     * Valor numérico o celda vacía (nunca 0 por un null).
     *
     * Devuelve null (no '') porque las hojas usan WithStrictNullComparison:
     * sólo null se omite, así que un 0 real sí se escribe como 0.
     */
    protected function num($valor): ?float
    {
        return ($valor === null || $valor === '') ? null : (float) $valor;
    }

    /** Fecha Y-m-d -> serial de Excel (celda vacía si null). */
    protected function fecha($valor): ?float
    {
        if (empty($valor)) {
            return null;
        }

        try {
            return (float) ExcelDate::PHPToExcel(new \DateTime((string) $valor));
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function estiloEncabezado(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
        ];
    }
}

/**
 * Hoja 1: Dashboard — misma geometría que el original.
 *
 * Filas fijas: 2 banner, 5-6 filtros/umbrales, 8-10 KPIs, 11 títulos de sección,
 * 12 encabezados. Desde la 13: detalle semanal en B:L y categorías en N:P en
 * paralelo. Después: 4 gráficos, hallazgos y nota.
 */
class PanelControlKmDashboardSheet implements FromArray, WithCharts, WithEvents, WithStrictNullComparison, WithTitle
{
    use PanelControlKmHelpers;

    private const NUM_COLS = 16; // A..P

    private const FILA_DATOS = 13;

    /** Colores de los valores KPI, tomados del original (B9..L9). */
    private const KPI_COLORES = ['1F3864', '2E75B6', 'C00000', '548235', '7030A0', 'BF8F00'];

    private array $filas = [];

    private int $numDetalle = 0;

    private int $numCategorias = 0;

    private int $filaChartTop = 0;

    private int $filaHallTitulo = 0;

    private int $filaHallIni = 0;

    private int $filaHallFin = 0;

    private int $filaNota = 0;

    /** Estados por fila del detalle, para pintar la columna L. */
    private array $estadosPorFila = [];

    /** Valores de dif por fila, para el rojo/verde. */
    private array $difPorFila = [];

    public function __construct(private array $data)
    {
        $this->construirFilas();
    }

    public function title(): string
    {
        return 'Dashboard';
    }

    public function array(): array
    {
        return $this->filas;
    }

    /** Escribe una fila colocando valores por letra de columna. */
    private function fila(array $celdas = []): int
    {
        $fila = array_fill(0, self::NUM_COLS, null);
        foreach ($celdas as $col => $valor) {
            $fila[ord($col) - ord('A')] = $valor;
        }
        $this->filas[] = $fila;

        return count($this->filas);
    }

    private function telarEtiqueta(): string
    {
        $t = (string) ($this->data['telar'] ?? 'ambos');

        return $t === 'ambos' ? 'Ambos' : $t;
    }

    private function construirFilas(): void
    {
        $d = $this->data;
        $kpis = $d['kpis'] ?? [];
        $brecha = $kpis['brecha'] ?? null;
        $detalle = $d['semanas_detalle'] ?? [];
        $categorias = $d['categorias'] ?? [];
        $this->numDetalle = count($detalle);
        $this->numCategorias = count($categorias);

        // 1 vacía, 2 banner
        $this->fila();
        $this->fila(['B' => 'PANEL DE CONTROL — TELARES KM '.($d['anio'] ?? ''), 'N' => 'Eficiencia · RPM · Eventos por semana']);
        $this->fila();
        $this->fila();
        // 5-6 filtros + umbrales (valores numéricos con formato 0%, como el original)
        $this->fila(['B' => 'Telar:', 'C' => $this->telarEtiqueta(), 'N' => 'Umbral verde (% del estándar)', 'O' => $this->num($d['umbral_verde'] ?? null)]);
        $this->fila(['N' => 'Umbral amarillo (% del estándar)', 'O' => $this->num($d['umbral_amarillo'] ?? null)]);
        $this->fila();

        // 8-10 KPIs en pares B:C D:E F:G H:I J:K L:M
        $this->fila(['B' => 'EFICIENCIA PROM.', 'D' => 'ESTÁNDAR PROM.', 'F' => 'BRECHA VS EST.', 'H' => 'RPM PROM.', 'J' => 'SEMANAS', 'L' => 'EVENTOS REGISTRADOS']);
        $this->fila([
            'B' => $this->num($kpis['eficiencia_prom'] ?? null),
            'D' => $this->num($kpis['estandar_prom'] ?? null),
            'F' => $this->num($brecha),
            'H' => $this->num($kpis['rpm_prom'] ?? null),
            'J' => (int) ($kpis['semanas'] ?? 0),
            'L' => (int) ($kpis['eventos'] ?? 0),
        ]);
        $this->fila([
            'B' => '% real de operación',
            'D' => 'meta de eficiencia',
            'F' => ($brecha !== null && (float) $brecha >= 0) ? 'puntos por encima' : 'puntos por debajo',
            'H' => 'revoluciones por minuto',
            'J' => 'semanas con registro',
            'L' => 'paros y observaciones',
        ]);

        // 11 títulos de sección, 12 encabezados
        $this->fila(['B' => 'DETALLE SEMANAL', 'N' => 'ANÁLISIS DE OBSERVACIONES']);
        $this->fila([
            'B' => 'Semana', 'C' => 'Efic. %', 'D' => 'Est. %', 'E' => 'RPM', 'F' => 'RPM Est.',
            'G' => 'Dif. (pp)', 'H' => 'Días', 'I' => 'Eventos', 'J' => 'Desde', 'K' => 'Hasta', 'L' => 'Estado',
            'N' => 'Categoría', 'O' => 'Menciones', 'P' => '% del total',
        ]);

        // 13.. detalle (B:L) y categorías (N:P) en paralelo
        $total = max($this->numDetalle, $this->numCategorias);
        for ($i = 0; $i < $total; $i++) {
            $celdas = [];
            if (isset($detalle[$i])) {
                $s = $detalle[$i];
                $celdas += [
                    'B' => $s['semana'] ?? '',
                    'C' => $this->num($s['efic'] ?? null),
                    'D' => $this->num($s['est'] ?? null),
                    'E' => $this->num($s['rpm'] ?? null),
                    'F' => $this->num($s['rpm_est'] ?? null),
                    'G' => $this->num($s['dif'] ?? null),
                    'H' => (int) ($s['dias'] ?? 0),
                    'I' => (int) ($s['eventos'] ?? 0),
                    'J' => $this->fecha($s['desde'] ?? null),
                    'K' => $this->fecha($s['hasta'] ?? null),
                    'L' => $s['estado'] ?? 'Sin dato',
                ];
            }
            if (isset($categorias[$i])) {
                $c = $categorias[$i];
                $celdas += [
                    'N' => $c['categoria'] ?? '',
                    'O' => (int) ($c['menciones'] ?? 0),
                    'P' => $this->num($c['porcentaje'] ?? 0),
                ];
            }
            $fila = $this->fila($celdas);
            if (isset($detalle[$i])) {
                $this->estadosPorFila[$fila] = $detalle[$i]['estado'] ?? 'Sin dato';
                $this->difPorFila[$fila] = $detalle[$i]['dif'] ?? null;
            }
        }

        // Espacio para los gráficos (18 filas), como en el original (B41:Q61)
        $this->filaChartTop = count($this->filas) + 2;
        $filaHall = $this->filaChartTop + 19;
        while (count($this->filas) < $filaHall - 1) {
            $this->fila();
        }

        // Hallazgos
        $this->filaHallTitulo = $this->fila(['B' => 'HALLAZGOS AUTOMÁTICOS']);
        $this->filaHallIni = count($this->filas) + 1;
        foreach ($d['hallazgos'] ?? [] as $h) {
            $this->fila(['B' => (string) $h]);
        }
        $this->filaHallFin = count($this->filas);
        if ($this->filaHallFin < $this->filaHallIni) {
            $this->filaHallIni = 0;
            $this->filaHallFin = 0;
        }
        $this->fila();

        $this->filaNota = $this->fila([
            'B' => 'Notas: los promedios de estándar (Est. % y RPM Est.) excluyen registros en cero. '
                ."'Eventos' cuenta una observación por cada turno con comentario capturado (ObsR1/R2/R3). "
                .'Todo el tablero responde al filtro de telar y a los umbrales seleccionados en la aplicación.',
        ]);
    }

    /** Los 4 gráficos del original, apuntando directo a las tablas de la hoja. */
    public function charts(): array
    {
        if ($this->numDetalle === 0) {
            return [];
        }

        $ini = self::FILA_DATOS;
        $fin = $ini + $this->numDetalle - 1;
        $n = $this->numDetalle;
        $catsSemana = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "Dashboard!\$B\${$ini}:\$B\${$fin}", null, $n)];

        $serie = function (string $col, string $nombre, string $color) use ($ini, $fin, $n) {
            $v = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "Dashboard!\${$col}\${$ini}:\${$col}\${$fin}", null, $n);
            $v->setFillColor($color);

            return [$v, new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, [$nombre])];
        };

        $chart = function (string $nombre, string $titulo, DataSeries $series, string $tl, string $br) {
            $c = new Chart($nombre, new Title($titulo), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]));
            $c->setTopLeftPosition($tl);
            $c->setBottomRightPosition($br);

            return $c;
        };

        $charts = [];
        $top = $this->filaChartTop;
        $bot = $top + 17;

        // Eficiencia real vs estándar (línea)
        [$vEfic, $lEfic] = $serie('C', 'Eficiencia real', self::COLOR_NAVY);
        [$vEst, $lEst] = $serie('D', 'Estándar', '2E75B6');
        $charts[] = $chart('chartEfic', 'Eficiencia real vs estándar por semana (%)', new DataSeries(
            DataSeries::TYPE_LINECHART, DataSeries::GROUPING_STANDARD, [0, 1],
            [$lEfic, $lEst], [$catsSemana[0], $catsSemana[0]], [$vEfic, $vEst]
        ), "B{$top}", "G{$bot}");

        // RPM real vs estándar (barras)
        [$vRpm, $lRpm] = $serie('E', 'RPM real', self::COLOR_NAVY);
        [$vRpmEst, $lRpmEst] = $serie('F', 'RPM estándar', 'A6B8D4');
        $charts[] = $chart('chartRpm', 'RPM real vs estándar por semana', new DataSeries(
            DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0, 1],
            [$lRpm, $lRpmEst], [$catsSemana[0], $catsSemana[0]], [$vRpm, $vRpmEst]
        ), "H{$top}", "L{$bot}");

        // Eventos por semana (barras)
        [$vEv, $lEv] = $serie('I', 'Eventos', 'BF8F00');
        $charts[] = $chart('chartEventos', 'Eventos registrados por semana', new DataSeries(
            DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0],
            [$lEv], $catsSemana, [$vEv]
        ), "N{$top}", "P{$bot}");

        // Menciones por tipo de observación (barras, debajo de la tabla de categorías)
        if ($this->numCategorias > 0) {
            $cIni = self::FILA_DATOS;
            $cFin = $cIni + $this->numCategorias - 1;
            $catsObs = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "Dashboard!\$N\${$cIni}:\$N\${$cFin}", null, $this->numCategorias);
            $vMen = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "Dashboard!\$O\${$cIni}:\$O\${$cFin}", null, $this->numCategorias);
            $vMen->setFillColor(self::COLOR_NAVY);
            $obsTop = self::FILA_DATOS + max($this->numCategorias, 10) + 1;
            $obsBot = min($obsTop + 15, $this->filaChartTop - 2);
            if ($obsBot > $obsTop + 4) {
                $charts[] = $chart('chartMenciones', 'Menciones por tipo de observación', new DataSeries(
                    DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0],
                    [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, ['Menciones'])],
                    [$catsObs], [$vMen]
                ), "N{$obsTop}", "P{$obsBot}");
            }
        }

        return $charts;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $navy = self::COLOR_NAVY;

                // Página: sin gridlines, pestaña navy, columna A de margen
                $sheet->setShowGridlines(false);
                $sheet->getTabColor()->setRGB($navy);
                $sheet->getColumnDimension('A')->setWidth(3.4);
                foreach (range('B', 'M') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(12.5);
                }
                $sheet->getColumnDimension('N')->setWidth(30);
                $sheet->getColumnDimension('O')->setWidth(14);
                $sheet->getColumnDimension('P')->setWidth(12);
                $sheet->getRowDimension(2)->setRowHeight(34);
                $sheet->getRowDimension(9)->setRowHeight(34);
                $sheet->getRowDimension(12)->setRowHeight(24);

                // Banner navy: título blanco 20pt, subtítulo a la derecha
                $sheet->mergeCells('B2:L2');
                $sheet->mergeCells('N2:P2');
                $sheet->getStyle('B2:P2')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $navy]],
                ]);
                $sheet->getStyle('B2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => 'FFFFFF']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getStyle('N2')->applyFromArray([
                    'font' => ['color' => ['rgb' => 'BFD3F2']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Filtros y umbrales (fila 5-6): selector resaltado, valores 0%
                $sheet->getStyle('B5')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $navy]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F7FB']],
                ]);
                $sheet->getStyle('C5')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '0000FF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('N5:N6')->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => $navy]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F7FB']],
                ]);
                $sheet->getStyle('O5:O6')->applyFromArray([
                    'font' => ['color' => ['rgb' => '0000FF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('O5:O6')->getNumberFormat()->setFormatCode('0%');

                // KPIs: pares combinados, etiqueta/leyenda gris 9pt, valor 20pt de color
                $paresKpi = [['B', 'C'], ['D', 'E'], ['F', 'G'], ['H', 'I'], ['J', 'K'], ['L', 'M']];
                foreach ($paresKpi as $i => [$c1, $c2]) {
                    foreach ([8, 9, 10] as $f) {
                        $sheet->mergeCells("{$c1}{$f}:{$c2}{$f}");
                    }
                    $sheet->getStyle("{$c1}8")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '808080']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sheet->getStyle("{$c1}9")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 20, 'color' => ['rgb' => self::KPI_COLORES[$i]]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getStyle("{$c1}10")->applyFromArray([
                        'font' => ['size' => 9, 'color' => ['rgb' => '808080']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }
                // Formatos de los valores KPI (como el original)
                $sheet->getStyle('B9')->getNumberFormat()->setFormatCode('0.0"%"');
                $sheet->getStyle('D9')->getNumberFormat()->setFormatCode('0.0"%"');
                $sheet->getStyle('F9')->getNumberFormat()->setFormatCode('+0.0;-0.0;0.0');
                $sheet->getStyle('H9')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('J9')->getNumberFormat()->setFormatCode('0');
                $sheet->getStyle('L9')->getNumberFormat()->setFormatCode('#,##0');
                // Brecha: verde si >= 0, rojo si < 0
                $brecha = $this->data['kpis']['brecha'] ?? null;
                if ($brecha !== null) {
                    $sheet->getStyle('F9')->getFont()->getColor()->setRGB((float) $brecha >= 0 ? '1E7145' : 'C00000');
                }

                // Títulos de sección
                foreach (['B11', 'N11', 'B'.$this->filaHallTitulo] as $celda) {
                    $sheet->getStyle($celda)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => $navy]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F7FB']],
                    ]);
                }
                // El de hallazgos lleva el acento ámbar del original
                $sheet->getStyle('B'.$this->filaHallTitulo)->getFill()->getStartColor()->setRGB('FFC000');
                $sheet->mergeCells("B{$this->filaHallTitulo}:L{$this->filaHallTitulo}");

                // Encabezados de tablas
                $sheet->getStyle('B12:L12')->applyFromArray($this->estiloEncabezado());
                $sheet->getStyle('N12:P12')->applyFromArray($this->estiloEncabezado());

                // Detalle semanal
                if ($this->numDetalle > 0) {
                    $ini = self::FILA_DATOS;
                    $fin = $ini + $this->numDetalle - 1;
                    $sheet->getStyle("B{$ini}:L{$fin}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sheet->getStyle("C{$ini}:D{$fin}")->getNumberFormat()->setFormatCode('0.0');
                    $sheet->getStyle("E{$ini}:F{$fin}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("G{$ini}:G{$fin}")->getNumberFormat()->setFormatCode('+0.0;-0.0;0.0');
                    $sheet->getStyle("H{$ini}:I{$fin}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("J{$ini}:K{$fin}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');

                    foreach ($this->estadosPorFila as $fila => $estado) {
                        $c = self::ESTADO_COLORES[$estado] ?? self::ESTADO_COLORES['Sin dato'];
                        $sheet->getStyle("L{$fila}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => $c['txt']]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $c['bg']]],
                        ]);
                    }
                    foreach ($this->difPorFila as $fila => $dif) {
                        if ($dif !== null) {
                            $sheet->getStyle("G{$fila}")->getFont()
                                ->setBold(true)->getColor()->setRGB((float) $dif >= 0 ? '1E7145' : 'C00000');
                        }
                    }

                    $sheet->freezePane('A'.self::FILA_DATOS);
                }

                // Análisis de observaciones (lateral N:P)
                if ($this->numCategorias > 0) {
                    $ini = self::FILA_DATOS;
                    $fin = $ini + $this->numCategorias - 1;
                    $sheet->getStyle("N{$ini}:P{$fin}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9D9D9']]],
                    ]);
                    $sheet->getStyle("O{$ini}:O{$fin}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("O{$ini}:O{$fin}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("P{$ini}:P{$fin}")->getNumberFormat()->setFormatCode('0.0%');
                    $sheet->getStyle("P{$ini}:P{$fin}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Hallazgos
                if ($this->filaHallIni > 0) {
                    for ($f = $this->filaHallIni; $f <= $this->filaHallFin; $f++) {
                        $sheet->mergeCells("B{$f}:L{$f}");
                        $sheet->getStyle("B{$f}")->applyFromArray([
                            'font' => ['size' => 11, 'color' => ['rgb' => '333333']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                        ]);
                    }
                }

                // Nota final
                $sheet->mergeCells("B{$this->filaNota}:L{$this->filaNota}");
                $sheet->getStyle("B{$this->filaNota}")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '808080']],
                    'alignment' => ['wrapText' => true],
                ]);
                $sheet->getRowDimension($this->filaNota)->setRowHeight(28);
            },
        ];
    }
}

/**
 * Hoja 2: Datos (detalle semanal plano, listo para pivotear).
 */
class PanelControlKmDatosSheet implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    use PanelControlKmHelpers;

    public function __construct(private array $data) {}

    public function title(): string
    {
        return 'Datos';
    }

    public function array(): array
    {
        $filas = [[
            'Telar', 'Año', 'Semana', 'Desde', 'Hasta', 'Días',
            'Efic. %', 'Est. %', 'Dif. (pp)', 'RPM', 'RPM Est.',
            'Eventos', 'Estado', 'Comentarios',
        ]];

        $telar = $this->data['telar'] ?? '';
        $anio = $this->data['anio'] ?? '';

        foreach ($this->data['semanas_detalle'] ?? [] as $s) {
            $comentarios = $s['comentarios'] ?? [];
            $filas[] = [
                $telar,
                $anio,
                $s['semana'] ?? '',
                $this->fecha($s['desde'] ?? null),
                $this->fecha($s['hasta'] ?? null),
                (int) ($s['dias'] ?? 0),
                $this->num($s['efic'] ?? null),
                $this->num($s['est'] ?? null),
                $this->num($s['dif'] ?? null),
                $this->num($s['rpm'] ?? null),
                $this->num($s['rpm_est'] ?? null),
                (int) ($s['eventos'] ?? 0),
                $s['estado'] ?? 'Sin dato',
                is_array($comentarios) ? implode(' | ', $comentarios) : (string) $comentarios,
            ];
        }

        return $filas;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $sheet->getHighestRow();

                $anchos = ['A' => 10, 'B' => 8, 'C' => 9, 'D' => 12, 'E' => 12, 'F' => 8,
                    'G' => 10, 'H' => 10, 'I' => 11, 'J' => 10, 'K' => 11,
                    'L' => 10, 'M' => 12, 'N' => 80];
                foreach ($anchos as $col => $ancho) {
                    $sheet->getColumnDimension($col)->setWidth($ancho);
                }

                $sheet->getStyle('A1:N1')->applyFromArray($this->estiloEncabezado());
                $sheet->freezePane('A2');

                if ($ultima > 1) {
                    $sheet->getStyle("D2:E{$ultima}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
                    $sheet->getStyle("G2:H{$ultima}")->getNumberFormat()->setFormatCode('0.0');
                    $sheet->getStyle("I2:I{$ultima}")->getNumberFormat()->setFormatCode('+0.0;-0.0;0.0');
                    $sheet->getStyle("J2:K{$ultima}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("F2:F{$ultima}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("L2:L{$ultima}")->getNumberFormat()->setFormatCode('#,##0');

                    for ($f = 2; $f <= $ultima; $f++) {
                        $estado = (string) $sheet->getCell("M{$f}")->getValue();
                        $c = self::ESTADO_COLORES[$estado] ?? self::ESTADO_COLORES['Sin dato'];
                        $sheet->getStyle("M{$f}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => $c['txt']]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $c['bg']]],
                        ]);
                    }
                }
            },
        ];
    }
}
