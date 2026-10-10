<?php

namespace App\Http\Controllers\Engomado;

use App\Exports\BpmEngomadoExport;
use App\Exports\ControlMermaExport;
use App\Exports\ReporteResumenSemanalEngomadoExport;
use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProduccionEngomado;
use App\Services\Bpm\BpmReporteFilasService;
use App\Services\Engomado\ControlMermaReportService;
use App\Support\Bpm\AreaBpm;
use App\Support\Reportes\FechaReporte;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportesEngomadoController extends Controller
{
    /**
     * Selector de reportes: 03-OEE URD-ENG y Kaizen (usan el controlador de Urdido)
     */
    public function index()
    {
        $reportes = [
            [
                'nombre' => 'Produccion OEE-URD-ENG',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.03-oee'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Kaizen urd-eng (AX ENGOMADO / AX URDIDO)',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('urdido.reportes.urdido.kaizen'),
                'disponible' => true,
            ],
            [
                'nombre' => 'BPM Engomado',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('engomado.reportes.bpm'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Control Merma',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('engomado.reportes.control-merma'),
                'disponible' => true,
            ],
            [
                'nombre' => 'Resumen Engomado',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('engomado.reportes.resumen-engomado'),
                'disponible' => true,
            ],
        ];

        return view('modulos.engomado.reportes-engomado-index', ['reportes' => $reportes]);
    }

    public function reporteControlMerma(Request $request, ControlMermaReportService $service)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (! $fechaIni || ! $fechaFin) {
            return view('modulos.engomado.reportes-control-merma', [
                'filas' => collect(),
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
            ]);
        }

        $filas = $service->build($fechaIni, $fechaFin);

        return view('modulos.engomado.reportes-control-merma', [
            'filas' => $filas,
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
        ]);
    }

    public function exportarControlMermaExcel(Request $request, ControlMermaReportService $service)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (! $fechaIni || ! $fechaFin) {
            return redirect()->route('engomado.reportes.control-merma')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $filas = $service->build($fechaIni, $fechaFin);

        $fechaIniCarbon = FechaReporte::parse($fechaIni);
        $fechaFinCarbon = FechaReporte::parse($fechaFin);
        $fileName = 'control-merma-'.$fechaIniCarbon->format('Ymd').'-'.$fechaFinCarbon->format('Ymd').'.xlsx';

        return Excel::download(new ControlMermaExport($filas), $fileName);
    }

    public function reporteBpm(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (! $fechaIni || ! $fechaFin) {
            return view('modulos.engomado.reportes-bpm-engomado', [
                'filas' => [],
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
                'soloFinalizados' => $soloFinalizados,
            ]);
        }

        $filas = (new BpmReporteFilasService)->filas(AreaBpm::Engomado, $fechaIni, $fechaFin, $soloFinalizados);

        return view('modulos.engomado.reportes-bpm-engomado', [
            'filas' => $filas,
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
            'soloFinalizados' => $soloFinalizados,
        ]);
    }

    public function exportarBpmExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $soloFinalizados = $request->query('solo_finalizados', '1') === '1';

        if (! $fechaIni || ! $fechaFin) {
            return redirect()->route('engomado.reportes.bpm')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $filas = (new BpmReporteFilasService)->filas(AreaBpm::Engomado, $fechaIni, $fechaFin, $soloFinalizados);

        $fileName = 'bpm-engomado-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new BpmEngomadoExport($filas), $fileName);
    }

    public function reporteResumenEngomado(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (! $fechaIni || ! $fechaFin) {
            return view('modulos.engomado.reporte-resumen-engomado', [
                'datosSemanales' => [],
                'fechaIni' => $fechaIni ?? '',
                'fechaFin' => $fechaFin ?? '',
            ]);
        }

        $datosSemanales = $this->buildReporteSemanalData($fechaIni, $fechaFin);

        return view('modulos.engomado.reporte-resumen-engomado', [
            'datosSemanales' => $datosSemanales,
            'fechaIni' => $fechaIni,
            'fechaFin' => $fechaFin,
        ]);
    }

    public function exportarResumenEngomadoExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (! $fechaIni || ! $fechaFin) {
            return redirect()->route('engomado.reportes.resumen-engomado')
                ->with('error', 'Seleccione un rango de fechas para exportar.');
        }

        $datosSemanales = $this->buildReporteSemanalData($fechaIni, $fechaFin);

        $fechaIniCarbon = FechaReporte::parse($fechaIni);
        $fechaFinCarbon = FechaReporte::parse($fechaFin);
        $fileName = 'resumen-semanal-engomado-'.$fechaIniCarbon->format('Ymd').'-'.$fechaFinCarbon->format('Ymd').'.xlsx';

        return Excel::download(new ReporteResumenSemanalEngomadoExport($datosSemanales), $fileName);
    }

    private function buildReporteSemanalData(string $fechaIni, string $fechaFin): array
    {
        $fechaIniCarbon = FechaReporte::parse($fechaIni);
        $fechaFinCarbon = FechaReporte::parse($fechaFin)->endOfDay();

        $producciones = EngProduccionEngomado::query()
            ->with('programa') // Cargar la relación
            ->whereBetween('Fecha', [$fechaIniCarbon, $fechaFinCarbon])
            ->where(function ($query) {
                $query->where('Finalizar', 1)
                    ->orWhereNull('Finalizar');
            })
            ->orderBy('Fecha')
            ->get();

        $porSemana = [];
        $foliosPorSemana = [];

        foreach ($producciones as $prod) {
            $fecha = $prod->Fecha instanceof Carbon ? $prod->Fecha : Carbon::parse($prod->Fecha);
            $weekYear = $fecha->format('W-o');

            if (! isset($porSemana[$weekYear])) {
                $porSemana[$weekYear] = [
                    'semana_label' => 'SEM-'.$fecha->format('W-o'),
                    'total_ordenes' => 0,
                    'total_julios' => 0,
                    'total_kg' => 0,
                    'total_metros' => 0,
                    'total_cuenta' => 0, // Ensure this is initialized
                    'eficiencia' => 0,
                ];
                $foliosPorSemana[$weekYear] = [];
            }

            if (! in_array($prod->Folio, $foliosPorSemana[$weekYear])) {
                $foliosPorSemana[$weekYear][] = $prod->Folio;
                $porSemana[$weekYear]['total_ordenes']++;
            }

            $porSemana[$weekYear]['total_julios']++;
            $porSemana[$weekYear]['total_kg'] += (float) ($prod->KgNeto ?? 0);

            if ($prod->programa) {
                $porSemana[$weekYear]['total_cuenta'] += (float) ($prod->programa->Cuenta ?? 0);
            }

            $metros = 0;
            if ($prod->Metros1) {
                $metros += (float) $prod->Metros1;
            }
            if ($prod->Metros2) {
                $metros += (float) $prod->Metros2;
            }
            if ($prod->Metros3) {
                $metros += (float) $prod->Metros3;
            }
            $porSemana[$weekYear]['total_metros'] += $metros;
        }

        foreach ($porSemana as &$semana) {
            $semana['peso_promedio'] = ($semana['total_julios'] > 0) ? $semana['total_kg'] / $semana['total_julios'] : 0;
            $semana['metros_promedio'] = ($semana['total_julios'] > 0) ? $semana['total_metros'] / $semana['total_julios'] : 0;
            $semana['cuenta_promedio'] = ($semana['total_julios'] > 0) ? $semana['total_cuenta'] / $semana['total_julios'] : 0;
        }

        return array_values($porSemana);
    }
}
