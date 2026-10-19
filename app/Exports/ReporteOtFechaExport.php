<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\Mecanicos\ReporteOtFechaService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Excel del reporte "OT x Fecha": un bloque por mecánico (fila de subtotal + sus trabajos,
 * cada uno con su No. Empleado y nombre) y una fila de total al final.
 */
final class ReporteOtFechaExport implements FromArray, WithEvents, WithTitle
{
    public const ENCABEZADOS = ['No. Empleado', 'Nombre', 'Fecha', 'Folio', 'Telar', 'Tipo de falla', 'Falla', 'Trabajo realizado', 'Inicio', 'Fin', 'Duración (min)', 'Duración', 'Estatus', 'Calificación', 'Tejedor que calificó', 'Comentarios'];

    private const FILA_ENCABEZADOS = 5;

    /** @var list<int> */
    private array $filasMecanico = [];

    private int $filaTotal = 0;

    /**
     * @param  array<string, mixed>  $reporte
     */
    public function __construct(
        private readonly array $reporte,
    ) {}

    public function title(): string
    {
        return 'OT x Fecha';
    }

    public function array(): array
    {
        $mecanico = (string) ($this->reporte['mecanico_nombre'] ?? '');
        $filas = [
            ['ÓRDENES DE TRABAJO POR FECHA'],
            ['Periodo: '.$this->fecha($this->reporte['desde']).' al '.$this->fecha($this->reporte['hasta'])],
            ['Mecánico: '.($mecanico !== '' ? $mecanico : 'Todos los mecánicos')],
            [''],
            self::ENCABEZADOS,
        ];

        $this->filasMecanico = [];
        foreach ($this->reporte['mecanicos'] as $grupo) {
            $filas[] = [
                $grupo['nombre'].' ('.$grupo['cve'].') · '.$grupo['total_ots'].' OT · '.$grupo['total_trabajos'].' trabajos',
                '', '', '', '', '', '', '', '', '',
                $grupo['total_minutos'],
                ReporteOtFechaService::duracion($grupo['total_minutos']),
            ];
            $this->filasMecanico[] = count($filas);

            foreach ($grupo['renglones'] as $fila) {
                $filas[] = [
                    $grupo['cve'],
                    $grupo['nombre'],
                    $this->fecha($fila['fecha']),
                    $fila['folio'],
                    $fila['telar'],
                    $fila['tipo_falla'],
                    $fila['falla'],
                    $fila['trabajo'],
                    $fila['hora_inicio'],
                    $fila['hora_fin'].($fila['cruza_dia'] ? ' (+1 día)' : ''),
                    $fila['minutos'],
                    ReporteOtFechaService::duracion($fila['minutos']),
                    $fila['estatus'],
                    $fila['calificacion'],
                    $fila['tejedor'],
                    $fila['comentarios'],
                ];
            }
        }

        $filas[] = [
            'Total · '.count($this->reporte['mecanicos']).' mecánicos · '.$this->reporte['total_ots'].' OT · '.$this->reporte['total_trabajos'].' trabajos',
            '', '', '', '', '', '', '', '', '',
            $this->reporte['total_minutos'],
            ReporteOtFechaService::duracion($this->reporte['total_minutos']),
        ];
        $this->filaTotal = count($filas);

        return $filas;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $hoja = $event->sheet->getDelegate();
                $ultima = 'P';

                $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $hoja->getStyle('A'.self::FILA_ENCABEZADOS.':'.$ultima.self::FILA_ENCABEZADOS)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
                ]);
                foreach ($this->filasMecanico as $fila) {
                    $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                    ]);
                }
                $hoja->getStyle("A{$this->filaTotal}:{$ultima}{$this->filaTotal}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
                ]);
                $hoja->getStyle('A'.self::FILA_ENCABEZADOS.":{$ultima}{$this->filaTotal}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                foreach (['A' => 13, 'B' => 28, 'C' => 12, 'D' => 12, 'E' => 8, 'F' => 14, 'G' => 30, 'H' => 28, 'I' => 8, 'J' => 14, 'K' => 14, 'L' => 14, 'M' => 12, 'N' => 12, 'O' => 28, 'P' => 50] as $columna => $ancho) {
                    $hoja->getColumnDimension($columna)->setWidth($ancho);
                }
                $hoja->getStyle("P1:P{$this->filaTotal}")->getAlignment()->setWrapText(true);
                $hoja->freezePane('A'.(self::FILA_ENCABEZADOS + 1));
                $hoja->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
            },
        ];
    }

    private function fecha(string $fecha): string
    {
        return Carbon::parse($fecha)->format('d/m/Y');
    }
}
