<?php

namespace Tests\Unit\Calidad;

use App\Exports\BpmEngomadoExport;
use App\Exports\BpmUrdidoExport;
use App\Exports\ReporteResumenSemanalEngomadoExport;
use App\Exports\ReporteResumenSemanalUrdidoExport;
use Maatwebsite\Excel\Excel as ExcelFormato;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CAL-05: los exports BPM y Resumen Semanal de Urdido y Engomado eran copias que solo
 * cambiaban el proceso. Este snapshot (generado con las clases originales, antes de
 * deduplicarlas) fija el xlsx celda a celda: valor, formato numerico, fuente, relleno,
 * alineacion, anchos, celdas combinadas, tablas, panel congelado y graficas.
 *
 * Regenerar (solo si el cambio del Excel es intencional): ACTUALIZAR_SNAPSHOTS=1 php artisan test --filter=ExportsUrdidoEngomadoSnapshot
 */
class ExportsUrdidoEngomadoSnapshotTest extends TestCase
{
    /**
     * @return array<string, array{0: callable(): object}>
     */
    public static function exports(): array
    {
        return [
            'BpmUrdidoExport' => [fn () => new BpmUrdidoExport(self::filasBpm())],
            'BpmEngomadoExport' => [fn () => new BpmEngomadoExport(self::filasBpm())],
            'ReporteResumenSemanalUrdidoExport' => [fn () => new ReporteResumenSemanalUrdidoExport(self::semanas())],
            'ReporteResumenSemanalEngomadoExport' => [fn () => new ReporteResumenSemanalEngomadoExport(self::semanas())],
        ];
    }

    #[DataProvider('exports')]
    public function test_el_xlsx_no_cambia(callable $crear): void
    {
        $export = $crear();
        $nombre = class_basename($export);
        // Ida y vuelta por JSON: el fixture no distingue 11 de 11.0.
        $actual = json_decode((string) json_encode($this->snapshot($export)), true);
        $archivo = base_path("tests/fixtures/calidad/{$nombre}.json");

        if (getenv('ACTUALIZAR_SNAPSHOTS')) {
            @mkdir(dirname($archivo), 0777, true);
            file_put_contents($archivo, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        }

        $this->assertFileExists($archivo, 'Falta el snapshot: correr con ACTUALIZAR_SNAPSHOTS=1');
        $this->assertSame(json_decode((string) file_get_contents($archivo), true), $actual);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(object $export): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'snap').'.xlsx';
        file_put_contents($ruta, Excel::raw($export, ExcelFormato::XLSX));

        try {
            $reader = new XlsxReader;
            $reader->setIncludeCharts(true);
            $libro = $reader->load($ruta);
        } finally {
            @unlink($ruta);
        }

        return array_map(fn (Worksheet $hoja) => $this->hoja($hoja), $libro->getAllSheets());
    }

    /**
     * @return array<string, mixed>
     */
    private function hoja(Worksheet $hoja): array
    {
        $celdas = [];
        foreach ($hoja->getRowIterator() as $fila) {
            foreach ($fila->getCellIterator() as $celda) {
                $estilo = $celda->getStyle();
                $celdas[$celda->getCoordinate()] = [
                    'valor' => $celda->getValue(),
                    'formato' => $estilo->getNumberFormat()->getFormatCode(),
                    'negrita' => $estilo->getFont()->getBold(),
                    'tamano' => $estilo->getFont()->getSize(),
                    'color' => $estilo->getFont()->getColor()->getARGB(),
                    'relleno' => $estilo->getFill()->getFillType(),
                    'fondo' => $estilo->getFill()->getStartColor()->getARGB(),
                    'horizontal' => $estilo->getAlignment()->getHorizontal(),
                    'vertical' => $estilo->getAlignment()->getVertical(),
                    'ajuste' => $estilo->getAlignment()->getWrapText(),
                    'borde' => $estilo->getBorders()->getTop()->getBorderStyle(),
                ];
            }
        }

        $anchos = [];
        $ultima = Coordinate::columnIndexFromString($hoja->getHighestColumn());
        for ($i = 1; $i <= $ultima; $i++) {
            $col = Coordinate::stringFromColumnIndex($i);
            $anchos[$col] = round($hoja->getColumnDimension($col)->getWidth(), 2);
        }

        $graficas = [];
        foreach ($hoja->getChartCollection() as $grafica) {
            $graficas[] = [
                'nombre' => $grafica->getName(),
                'titulo' => $grafica->getTitle()?->getCaptionText(),
                'desde' => $grafica->getTopLeftCell(),
                'hasta' => $grafica->getBottomRightCell(),
                'series' => array_map(
                    fn ($serie) => [
                        'tipo' => $serie->getPlotType(),
                        'valores' => array_map(fn ($v) => $v->getDataSource(), $serie->getPlotValues()),
                    ],
                    $grafica->getPlotArea()?->getPlotGroup() ?? []
                ),
            ];
        }

        return [
            'titulo' => $hoja->getTitle(),
            'congelado' => $hoja->getFreezePane(),
            'combinadas' => array_values($hoja->getMergeCells()),
            'tablas' => array_map(fn ($t) => [$t->getName(), $t->getRange(), $t->getStyle()->getTheme()], $hoja->getTableCollection()->getArrayCopy()),
            'anchos' => $anchos,
            'celdas' => $celdas,
            'graficas' => $graficas,
        ];
    }

    /**
     * Una fila por cada variante de badge de Status y de Valor.
     */
    private static function filasBpm(): \Illuminate\Support\Collection
    {
        $base = [
            'InicioFolio' => 1, 'Folio' => 'BPM-001', 'Status' => 'Autorizado', 'Fecha' => '2026-09-01',
            'CveEmplEnt' => 101, 'NombreEmplEnt' => 'ANA PEREZ', 'TurnoEntrega' => '1',
            'CveEmplRec' => 102, 'NombreEmplRec' => 'LUIS DIAZ', 'TurnoRecibe' => '2',
            'CveEmplAutoriza' => 103, 'NombreEmplAutoriza' => 'SUPERVISOR UNO',
            'Orden' => 1, 'Actividad' => 'Limpieza de fileta', 'ValorTexto' => 'Correcto',
        ];

        return collect([
            (object) $base,
            (object) array_merge($base, ['InicioFolio' => 2, 'Status' => 'Terminado', 'Orden' => 2, 'ValorTexto' => 'Incorrecto', 'Fecha' => '15/09/2026']),
            (object) array_merge($base, ['InicioFolio' => 3, 'Status' => 'Creado', 'Orden' => 3, 'ValorTexto' => null, 'Fecha' => null]),
            (object) array_merge($base, ['InicioFolio' => 4, 'Status' => 'Otro', 'Orden' => 4, 'ValorTexto' => 'N/A']),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function semanas(): array
    {
        return [
            ['semana_label' => 'Sem 35', 'total_ordenes' => 12, 'total_julios' => 48, 'total_kg' => 10250.5, 'total_metros' => 152000.25, 'peso_promedio' => 213.55, 'metros_promedio' => 3166.67, 'cuenta_promedio' => 5120.4, 'eficiencia' => 87.5],
            ['semana_label' => 'Sem 36', 'total_ordenes' => 9, 'total_julios' => 36, 'total_kg' => 8100, 'total_metros' => 120500, 'peso_promedio' => 225, 'metros_promedio' => 3347.22, 'cuenta_promedio' => 4980, 'eficiencia' => null],
            ['semana_label' => 'Sem 37', 'total_ordenes' => 15, 'total_julios' => 60, 'total_kg' => 13020.75, 'total_metros' => 190300.8, 'peso_promedio' => 217.01, 'metros_promedio' => 3171.68, 'cuenta_promedio' => 5202.1, 'eficiencia' => 91.25],
        ];
    }
}
