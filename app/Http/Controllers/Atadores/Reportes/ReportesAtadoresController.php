<?php

namespace App\Http\Controllers\Atadores\Reportes;

use App\Exports\ProgramaAtadoresExport;
use App\Exports\Reporte00EAtadoresRangoExport;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportesAtadoresController extends Controller
{
    /**
     * Selector de reportes: muestra los reportes disponibles
     */
    public function index()
    {
        $reportes = [
            [
                'nombre' => 'Reporte de Programa Atadores',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('atadores.reportes.programa'),
                'disponible' => true,
            ],
            [
                'nombre' => '00E Atadores',
                'accion' => 'Seleccionar Fechas',
                'url' => route('atadores.reportes.atadores'),
                'disponible' => true,
            ],
        ];

        return view('modulos.atadores.reportes.index', ['reportes' => $reportes]);
    }

    /**
     * Vista del reporte de Programa Atadores con filtros de fecha
     */
    public function reportePrograma(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (!$fechaIni || !$fechaFin) {
            return view('modulos.atadores.reportes.programa', [
                'fechaIni' => null,
                'fechaFin' => null,
                'datos' => collect(),
            ]);
        }

        $fechaIniFormateada = Carbon::parse($fechaIni)->format('Y-m-d');
        $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');

        return view('modulos.atadores.reportes.programa', [
            'fechaIni' => $fechaIniFormateada,
            'fechaFin' => $fechaFinFormateada,
        ]);
    }

    /**
     * Exportar Excel del Programa Atadores
     */
    public function exportarExcel(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio') ?? $request->query('fecha_ini');
        $fechaFin = $request->input('fecha_fin') ?? $request->query('fecha_fin');

        if (!$fechaInicio || !$fechaFin) {
            return redirect()->back()->with('error', 'Debe seleccionar fecha inicio y fecha fin para exportar.');
        }

        $fechaInicioFormateada = Carbon::parse($fechaInicio)->format('Y-m-d');
        $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');

        if ($fechaInicioFormateada > $fechaFinFormateada) {
            return redirect()->back()->with('error', 'La fecha inicio no puede ser mayor que la fecha fin.');
        }

        $nombreArchivo = 'atadores_' . Carbon::parse($fechaInicioFormateada)->format('d-m-Y') . '_a_' . Carbon::parse($fechaFinFormateada)->format('d-m-Y') . '.xlsx';

        return Excel::download(
            new ProgramaAtadoresExport($fechaInicioFormateada, $fechaFinFormateada),
            $nombreArchivo
        );
    }

    public function reporteAtadores(Request $request)
    {
        [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin] = $this->resolverRangoFechasAtadoresDesdeRequest($request);
        $domingoFin = $lunesFin?->addDays(6);

        return view('modulos.atadores.reportes.atadores', [
            'fechaIni' => $fechaInicio?->toDateString(),
            'fechaFin' => $fechaFin?->toDateString(),
            'lunesIni' => $lunesInicio?->toDateString(),
            'domingoFin' => $domingoFin?->toDateString(),
        ]);
    }

    public function exportarReporteAtadoresExcel(Request $request)
    {
        @set_time_limit(300);

        [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin] = $this->resolverRangoFechasAtadoresDesdeRequest($request);

        if (!$fechaInicio || !$fechaFin || !$lunesInicio || !$lunesFin) {
            return redirect()
                ->route('atadores.reportes.atadores')
                ->with('error', 'Debe seleccionar una fecha inicial y final validas para exportar el 00E Atadores.');
        }

        $nombreArchivo = '00E_atadores_' . $fechaInicio->format('d-m-Y') . '_a_' . $fechaFin->format('d-m-Y') . '.xlsx';

        return Excel::download(
            new Reporte00EAtadoresRangoExport($lunesInicio, $lunesFin),
            $nombreArchivo
        );
    }

    private function resolverRangoFechasAtadoresDesdeRequest(Request $request): array
    {
        $fechaIni = trim((string) $request->query('fecha_ini', $request->input('fecha_ini', '')));
        $fechaFin = trim((string) $request->query('fecha_fin', $request->input('fecha_fin', '')));

        if ($fechaIni === '' && $fechaFin === '') {
            [$lunesInicio, $lunesFin] = $this->resolverRangoSemanasDesdeRequest($request);

            if (!$lunesInicio || !$lunesFin) {
                return [null, null, null, null];
            }

            return [$lunesInicio, $lunesFin->addDays(6), $lunesInicio, $lunesFin];
        }

        $fechaInicio = $this->resolverFecha($fechaIni);
        $fechaFin = $this->resolverFecha($fechaFin);

        if (!$fechaInicio || !$fechaFin) {
            return [null, null, null, null];
        }

        if ($fechaInicio->greaterThan($fechaFin)) {
            return [null, null, null, null];
        }

        $lunesInicio = $fechaInicio->startOfWeek(Carbon::MONDAY);
        $lunesFin = $fechaFin->startOfWeek(Carbon::MONDAY);

        return [$fechaInicio, $fechaFin, $lunesInicio, $lunesFin];
    }

    private function resolverRangoSemanasDesdeRequest(Request $request): array
    {
        $semana = trim((string) $request->query('semana', $request->input('semana', '')));
        $semanaIni = trim((string) $request->query('semana_ini', $request->input('semana_ini', '')));
        $semanaFin = trim((string) $request->query('semana_fin', $request->input('semana_fin', '')));

        if ($semana !== '') {
            $semanaIni = $semana;
            $semanaFin = $semana;
        }

        $lunesInicio = $this->resolverSemanaIso($semanaIni);
        $lunesFin = $this->resolverSemanaIso($semanaFin);

        if (!$lunesInicio || !$lunesFin) {
            return [null, null];
        }

        if ($lunesInicio->greaterThan($lunesFin)) {
            return [null, null];
        }

        return [$lunesInicio, $lunesFin];
    }

    private function resolverFecha(?string $fecha): ?CarbonImmutable
    {
        $fecha = trim((string) ($fecha ?? ''));
        if ($fecha === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($fecha)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolverSemanaIso(?string $semana): ?CarbonImmutable
    {
        $semana = trim((string) ($semana ?? ''));
        if ($semana === '') {
            return null;
        }

        if (!preg_match('/^(\d{4})-W(\d{2})$/', $semana, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $week = (int) $matches[2];

        if ($week < 1 || $week > 53) {
            return null;
        }

        return CarbonImmutable::now(config('app.timezone'))
            ->setISODate($year, $week)
            ->startOfDay();
    }
}