<?php

namespace App\Http\Controllers\Tejedores\Reportes;

use App\Exports\DesarrolladoresReporteExport;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportesDesarrolladoresController extends Controller
{
    /**
     * Selector de reportes: muestra los reportes disponibles
     */
    public function index()
    {
        $reportes = [
            [
                'nombre' => 'Reporte de Desarrolladores',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('tejedores.reportes-desarrolladores.programa'),
                'disponible' => true,
            ],
        ];

        return view('modulos.desarrolladores.reportes.index', ['reportes' => $reportes]);
    }

    /**
     * Vista del reporte de Desarrolladores con filtros de fecha
     */
    public function reportePrograma(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (!$fechaIni || !$fechaFin) {
            return view('modulos.desarrolladores.reportes.programa', [
                'fechaIni' => null,
                'fechaFin' => null,
                'datos' => collect(),
            ]);
        }

        $fechaIniFormateada = Carbon::parse($fechaIni)->format('Y-m-d');
        $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');

        return view('modulos.desarrolladores.reportes.programa', [
            'fechaIni' => $fechaIniFormateada,
            'fechaFin' => $fechaFinFormateada,
        ]);
    }

    /**
     * Exportar Excel del Reporte de Desarrolladores
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

        $nombreArchivo = 'desarrolladores_' . Carbon::parse($fechaInicioFormateada)->format('d-m-Y') . '_a_' . Carbon::parse($fechaFinFormateada)->format('d-m-Y') . '.xlsx';

        return Excel::download(
            new DesarrolladoresReporteExport($fechaInicioFormateada, $fechaFinFormateada),
            $nombreArchivo
        );
    }
}
