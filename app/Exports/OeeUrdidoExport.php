<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * OEE Urdido: mismas filas que la pantalla (julios + subtotales máquina / mes / total).
 * Anchos fijos en vez de ShouldAutoSize: medir cada celda costaba 1.5 s de 4 s por mes.
 */
class OeeUrdidoExport implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    private const ENCABEZADO = ['Mes', 'Máquina', 'Fecha', 'Día', 'Hora', 'Orden', 'No. julio', 'Número de L.MAT', 'Código de artículo',
        'Configuración', 'Tamaño', 'Color', 'Almacén', 'Kg', 'Minutos prod', 'Min paro necesario', 'Min oportunidad',
        'Defecto', 'Penalización (pts)', 'Julios', 'Oficial'];

    /** Ancho por columna, en el orden de ENCABEZADO. */
    private const ANCHOS = [14, 11, 11, 16, 7, 8, 9, 24, 15, 14, 12, 7, 11, 13, 12, 18, 15, 9, 17, 8, 45];

    /** @var list<int> filas (1-based) de subtotales, en negritas */
    private array $subtotales = [];

    public function __construct(private readonly array $reporte) {}

    public function columnWidths(): array
    {
        $anchos = [];
        foreach (self::ANCHOS as $i => $ancho) {
            $anchos[chr(65 + $i)] = $ancho;
        }

        return $anchos;
    }

    public function title(): string
    {
        return 'OEE Urdido';
    }

    public function array(): array
    {
        $filas = [self::ENCABEZADO];
        foreach ($this->reporte['meses'] as $mes) {
            foreach ($mes['maquinas'] as $maq) {
                foreach ($maq['julios'] as $j) {
                    $filas[] = [$mes['etiqueta'], $maq['nombre'], $j['fecha'], $j['dia'], $j['hora'], $j['orden'], $j['julio'],
                        $j['lmat'], 'JULIO-URDIDO', $j['config'], $j['tamano'], '1000', 'A-JUL/URD',
                        ...$this->medidas($j), $j['defecto'], $j['penalizacion'], null, $j['oficiales']];
                }
                $filas[] = $this->subtotal($filas, [$mes['etiqueta'], 'Total '.$maq['nombre']], $maq['tot']);
            }
            $filas[] = $this->subtotal($filas, ['Total '.$mes['etiqueta'], ''], $mes['tot']);
        }
        $filas[] = $this->subtotal($filas, ['TOTAL GENERAL', ''], $this->reporte['tot']);

        return $filas;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]] + array_fill_keys($this->subtotales, ['font' => ['bold' => true]]);
    }

    private function subtotal(array $filas, array $etiquetas, array $t): array
    {
        $this->subtotales[] = count($filas) + 1;

        return [...$etiquetas, ...array_fill(0, 11, null), ...$this->medidas($t), null, null, $t['julios'], null];
    }

    /** Cantidad, Minutos prod, Min paro necesario, Min oportunidad. Sin porcentajes por ahora: los calcula quien analiza. */
    private function medidas(array $t): array
    {
        return [round($t['kg'], 1), round($t['minutos']), round($t['paro_nec'], 1), round($t['paro_oport'], 1)];
    }
}
