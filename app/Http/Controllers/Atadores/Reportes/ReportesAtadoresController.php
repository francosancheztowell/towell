<?php

namespace App\Http\Controllers\Atadores\Reportes;

use App\Exports\ProgramaAtadoresExport;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
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
}
