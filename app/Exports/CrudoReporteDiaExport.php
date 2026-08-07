<?php

declare(strict_types=1);

namespace App\Exports;

use App\DTOs\Crudo\CrudoDashboardData;
use App\DTOs\Crudo\CrudoMachineMetrics;
use App\Enums\Crudo\CrudoMachineState;
use App\Services\Crudo\CrudoProductionTargetService;
use DateTimeImmutable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reporte diario de telares (día de producción 06:30 → 06:30) agrupado por
 * estado del semáforo. Una sola hoja: logo, tarjetas de KPI, tabla por estado,
 * y resumen por salón.
 */
final class CrudoReporteDiaExport implements FromArray, WithDrawings, WithEvents, WithTitle
{
    private const HEADERS = [
        'Salón', 'Telar', 'Estado', 'Falla / Paro', 'Orden', 'Modelo',
        'Piezas', 'Segundas', '% Segundas', '% Calidad',
        'Kg', 'Kg Meta',
    ];

    private const LAST_COLUMN = 'L';

    private const SALON_HEADERS = [
        'Salón', 'Telares', 'Paro', 'Mala calidad', 'Bajos kg', 'En operación', 'Sin datos',
        'Kg', 'Kg Meta', '% Cumpl.',
    ];

    /**
     * Mismo semáforo que el tablero web: fondo `--status-bg-soft` y texto
     * `--status-ink` de resources/css/crudo/dashboard.css.
     *
     * value => [fondo, texto]
     */
    private const STATE_COLORS = [
        'paro' => ['FFFFE0DD', 'FFD10000'],
        'bad_quality' => ['FFE4EAFF', 'FF0000D6'],
        'low_kilos' => ['FFFFF9D1', 'FF96700A'],
        'no_data' => ['FFECECEC', 'FF000000'],
        'operating' => ['FFDDFBE9', 'FF009E2B'],
    ];

    /** Orden del reporte: primero lo que urge atender. */
    private const GROUP_ORDER = ['paro', 'bad_quality', 'low_kilos', 'no_data', 'operating'];

    /** Tarjetas de KPI: 5 por fila, cada una ocupa dos columnas. */
    private const TILES_PER_ROW = 5;

    /** @var list<list<mixed>> */
    private array $rows = [];

    /** @var array<int, string> fila (1-based) => estado */
    private array $dataRows = [];

    /** @var array<int, string> fila (1-based) => estado del encabezado de grupo */
    private array $groupRows = [];

    /**
     * Tarjetas colocadas: [fila de la etiqueta, columna inicial, estado|null, formato].
     *
     * @var list<array{int, int, ?string, string}>
     */
    private array $tiles = [];

    private int $headerRow = 0;

    private int $salonTitleRow = 0;

    private int $salonHeaderRow = 0;

    private int $salonTotalRow = 0;

    public function __construct(
        private readonly CrudoDashboardData $data,
        private readonly DateTimeImmutable $day,
        private readonly ?string $rutaLogo = null,
    ) {
        $this->build();
    }

    public function title(): string
    {
        return 'Telares '.$this->day->format('d-m-Y');
    }

    public function array(): array
    {
        return $this->rows;
    }

    /**
     * Resumen del tablero, para que el correo muestre los KPIs sin volver a
     * consultar producción.
     *
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        return $this->data->summary;
    }

    public function drawings()
    {
        if ($this->rutaLogo === null) {
            return [];
        }

        $drawing = new Drawing;
        $drawing->setPath($this->rutaLogo);
        $drawing->setHeight(46);
        $drawing->setOffsetX(8);
        $drawing->setOffsetY(6);
        $drawing->setCoordinates('A1');

        return $drawing;
    }

    private function build(): void
    {
        $summary = $this->data->summary;

        $this->rows[] = ['']; // fila del logo
        $this->rows[] = ['REPORTE DIARIO DE TELARES'];
        $this->rows[] = [sprintf(
            'Día de producción %s (06:30 a 06:30 del día siguiente) · Generado %s',
            $this->day->format('d/m/Y'),
            now(config('app.timezone'))->format('d/m/Y H:i'),
        )];
        $this->rows[] = [''];

        $this->buildTiles([
            ['Telares', (float) ($summary['total'] ?? 0), null, '#,##0'],
            ['Paro', (float) ($summary['paro'] ?? 0), 'paro', '#,##0'],
            ['Mala calidad', (float) ($summary['bad_quality'] ?? 0), 'bad_quality', '#,##0'],
            ['Bajos kg', (float) ($summary['low_kilos'] ?? 0), 'low_kilos', '#,##0'],
            ['Sin datos', (float) ($summary['no_data'] ?? 0), 'no_data', '#,##0'],
            ['En operación', (float) ($summary['operating'] ?? 0), 'operating', '#,##0'],
            ['Kg producidos', (float) ($summary['kilos'] ?? 0), null, '#,##0.0'],
            ['Kg meta', (float) ($summary['expectedKilos'] ?? 0), null, '#,##0.0'],
            ['% Cumpl. kg', (float) ($summary['efficiencyPercent'] ?? 0), null, '0.0"%"'],
            ['% Calidad', (float) ($summary['qualityPercent'] ?? 0), null, '0.0"%"'],
        ]);

        $this->rows[] = [''];

        $this->headerRow = count($this->rows) + 1;
        $this->rows[] = self::HEADERS;

        foreach (self::GROUP_ORDER as $state) {
            $machines = array_values(array_filter(
                $this->data->machines,
                static fn (CrudoMachineMetrics $machine): bool => $machine->state->value === $state,
            ));

            if ($machines === []) {
                continue;
            }

            $label = CrudoMachineState::from($state)->label();
            $this->rows[] = [mb_strtoupper($label).' — '.count($machines).' telar(es)'];
            $this->groupRows[count($this->rows)] = $state;

            foreach ($machines as $machine) {
                $this->rows[] = $this->machineRow($machine, $label);
                $this->dataRows[count($this->rows)] = $state;
            }
        }

        if ($this->dataRows === []) {
            $this->rows[] = ['Sin telares para la fecha seleccionada.'];
        }

        $this->buildSalonBlock();
    }

    /**
     * @param  list<array{string, float, ?string, string}>  $kpis
     */
    private function buildTiles(array $kpis): void
    {
        foreach (array_chunk($kpis, self::TILES_PER_ROW) as $fila) {
            $labels = [];
            $values = [];

            foreach ($fila as $index => [$label, $value, $state, $format]) {
                $column = $index * 2;
                $labels[$column] = $label;
                $labels[$column + 1] = '';
                $values[$column] = round($value, 1);
                $values[$column + 1] = '';
                $this->tiles[] = [count($this->rows) + 1, $column + 1, $state, $format];
            }

            $this->rows[] = $labels;
            $this->rows[] = $values;
        }
    }

    /**
     * @return list<mixed>
     */
    private function machineRow(CrudoMachineMetrics $machine, string $label): array
    {
        $conMeta = $machine->productionStandardStatus === CrudoProductionTargetService::COMPLETE
            && $machine->expectedKilos > 0;

        return [
            $machine->salon,
            $machine->telar,
            $label,
            $machine->paro['falla'] ?? '',
            $machine->programa['orden'] ?? '',
            $machine->programa['claveModelo'] ?? ($machine->programa['nombreProducto'] ?? ''),
            round($machine->pieces),
            round($machine->seconds),
            round($machine->secondsPercent, 1),
            round($machine->qualityPercent, 1),
            round($machine->kilos, 1),
            $conMeta ? round($machine->expectedKilos, 1) : null,
        ];
    }

    /** Resumen por salón al pie de la tabla. */
    private function buildSalonBlock(): void
    {
        $this->rows[] = [''];
        $this->salonTitleRow = count($this->rows) + 1;
        $this->rows[] = ['RESUMEN POR SALÓN'];
        $this->salonHeaderRow = count($this->rows) + 1;
        $this->rows[] = self::SALON_HEADERS;

        $vacio = ['telares' => 0, 'kilos' => 0.0, 'meta' => 0.0] + array_fill_keys(self::GROUP_ORDER, 0);
        $salones = [];
        $totales = $vacio;

        foreach ($this->data->machines as $machine) {
            $salones[$machine->salon] ??= $vacio;

            $salones[$machine->salon]['telares']++;
            $salones[$machine->salon][$machine->state->value]++;
            $salones[$machine->salon]['kilos'] += $machine->kilos;
            $totales['telares']++;
            $totales[$machine->state->value]++;
            $totales['kilos'] += $machine->kilos;

            if ($machine->productionStandardStatus === CrudoProductionTargetService::COMPLETE) {
                $salones[$machine->salon]['meta'] += $machine->expectedKilos;
                $totales['meta'] += $machine->expectedKilos;
            }
        }

        foreach ($salones as $nombre => $datos) {
            $this->rows[] = $this->salonRow((string) $nombre, $datos);
        }

        $this->rows[] = $this->salonRow('TOTAL', $totales);
        $this->salonTotalRow = count($this->rows);
    }

    /**
     * @param  array<string, int|float>  $datos
     * @return list<mixed>
     */
    private function salonRow(string $nombre, array $datos): array
    {
        return [
            $nombre,
            $datos['telares'],
            $datos['paro'],
            $datos['bad_quality'],
            $datos['low_kilos'],
            $datos['operating'],
            $datos['no_data'],
            round($datos['kilos'], 1),
            round($datos['meta'], 1),
            $datos['meta'] > 0 ? round(($datos['kilos'] / $datos['meta']) * 100, 1) : null,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $last = self::LAST_COLUMN;

                // Encabezado: franja blanca para el logo + banda con el título.
                $sheet->getRowDimension(1)->setRowHeight(46);
                $sheet->mergeCells("A1:{$last}1");
                $sheet->mergeCells("A2:{$last}2");
                $sheet->mergeCells("A3:{$last}3");
                $sheet->getStyle("A1:{$last}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFFF');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A2:{$last}2")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E293B');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension(2)->setRowHeight(26);
                $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setARGB('FF475569');
                $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $this->styleTiles($sheet);
                $this->styleTable($sheet);
                $this->styleSalonBlock($sheet);

                foreach (range('A', $last) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($this->headerRow, $this->headerRow);
            },
        ];
    }

    private function styleTiles(Worksheet $sheet): void
    {
        foreach ($this->tiles as [$row, $columnIndex, $state, $format]) {
            $from = Coordinate::stringFromColumnIndex($columnIndex);
            $to = Coordinate::stringFromColumnIndex($columnIndex + 1);
            [$fill, $ink] = $state !== null
                ? self::STATE_COLORS[$state]
                : ['FFF1F5F9', 'FF1E293B'];
            $tile = "{$from}{$row}:{$to}".($row + 1);

            $sheet->mergeCells("{$from}{$row}:{$to}{$row}");
            $sheet->mergeCells("{$from}".($row + 1).":{$to}".($row + 1));

            $sheet->getStyle($tile)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
            $sheet->getStyle($tile)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($tile)->getBorders()->getOutline()
                ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setARGB($ink);

            $sheet->getStyle("{$from}{$row}")->getFont()
                ->setBold(true)->setSize(9)->getColor()->setARGB($ink);
            $sheet->getStyle("{$from}".($row + 1))->getFont()
                ->setBold(true)->setSize(20)->getColor()->setARGB($ink);
            $sheet->getStyle("{$from}".($row + 1))->getNumberFormat()->setFormatCode($format);

            $sheet->getRowDimension($row)->setRowHeight(16);
            $sheet->getRowDimension($row + 1)->setRowHeight(30);
        }
    }

    private function styleTable(Worksheet $sheet): void
    {
        $last = self::LAST_COLUMN;
        $header = $this->headerRow;

        $sheet->getStyle("A{$header}:{$last}{$header}")->getFont()
            ->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$header}:{$last}{$header}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF334155');
        $sheet->getStyle("A{$header}:{$last}{$header}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($header)->setRowHeight(30);
        $sheet->freezePane('C'.($header + 1));
        $sheet->setAutoFilter("A{$header}:{$last}{$header}");

        foreach ($this->groupRows as $row => $state) {
            [, $ink] = self::STATE_COLORS[$state];
            $sheet->mergeCells("A{$row}:{$last}{$row}");
            $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($ink);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setARGB('FFFFFFFF');
            $sheet->getRowDimension($row)->setRowHeight(20);
        }

        foreach ($this->dataRows as $row => $state) {
            [$fill, $ink] = self::STATE_COLORS[$state];
            $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
            $sheet->getStyle("C{$row}")->getFont()->setBold(true)->getColor()->setARGB($ink);
        }

        if ($this->dataRows === []) {
            return;
        }

        $first = (int) array_key_first($this->dataRows);
        $lastRow = (int) array_key_last($this->dataRows);

        $sheet->getStyle("A{$first}:{$last}{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');
        $sheet->getStyle("G{$first}:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("I{$first}:J{$lastRow}")->getNumberFormat()->setFormatCode('0.0"%"');
        $sheet->getStyle("K{$first}:L{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle("B{$first}:B{$lastRow}")->getFont()->setBold(true);
        $sheet->getStyle("G{$first}:{$last}{$lastRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $this->applyConditionalFormats($sheet, $first, $lastRow);
    }

    /**
     * Reglas nativas de Excel: siguen vivas si el usuario filtra u ordena la
     * tabla, cosa que un color escrito a mano no hace.
     */
    private function applyConditionalFormats(Worksheet $sheet, int $first, int $lastRow): void
    {
        $umbral = (float) config('crudo.bad_quality_percent', 7);

        $segundas = new Conditional;
        $segundas->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType(Conditional::OPERATOR_GREATERTHANOREQUAL)
            ->addCondition((string) $umbral);
        $segundas->getStyle()->getFont()->setBold(true)->getColor()->setARGB('FFD10000');
        $sheet->getStyle("I{$first}:I{$lastRow}")->setConditionalStyles([$segundas]);

        // Kg por debajo de la meta prorrateada del programa EnProceso del telar.
        $bajoMeta = new Conditional;
        $bajoMeta->setConditionType(Conditional::CONDITION_EXPRESSION)
            ->addCondition("AND(\$L{$first}<>\"\",\$K{$first}<\$L{$first})");
        $bajoMeta->getStyle()->getFont()->setBold(true)->getColor()->setARGB('FF96700A');
        $sheet->getStyle("K{$first}:K{$lastRow}")->setConditionalStyles([$bajoMeta]);
    }

    private function styleSalonBlock(Worksheet $sheet): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count(self::SALON_HEADERS));
        $header = $this->salonHeaderRow;
        $total = $this->salonTotalRow;

        $sheet->mergeCells("A{$this->salonTitleRow}:{$lastColumn}{$this->salonTitleRow}");
        $sheet->getStyle("A{$this->salonTitleRow}")->getFont()
            ->setBold(true)->setSize(12)->getColor()->setARGB('FF1E293B');

        $sheet->getStyle("A{$header}:{$lastColumn}{$header}")->getFont()
            ->setBold(true)->setSize(10)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$header}:{$lastColumn}{$header}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF334155');
        $sheet->getStyle("A{$header}:{$lastColumn}{$header}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);

        $sheet->getStyle("A{$header}:{$lastColumn}{$total}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');
        $sheet->getStyle('B'.($header + 1).":{$lastColumn}{$total}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H'.($header + 1).":I{$total}")->getNumberFormat()->setFormatCode('#,##0.0');
        $sheet->getStyle('J'.($header + 1).":J{$total}")->getNumberFormat()->setFormatCode('0.0"%"');
        $sheet->getStyle("A{$total}:{$lastColumn}{$total}")->getFont()->setBold(true);
        $sheet->getStyle("A{$total}:{$lastColumn}{$total}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
    }
}
