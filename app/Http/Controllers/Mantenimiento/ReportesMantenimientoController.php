<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Http\Controllers\Controller;
use App\Exports\ReporteMantenimientoExport;
use App\Models\Mantenimiento\ManFallasParos;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportesMantenimientoController extends Controller
{
    /**
     * Selector de reportes (como urdido).
     * GET /mantenimiento/reportes
     */
    public function index()
    {
        $reportes = [
            [
                'nombre' => 'Fallas y Paros',
                'accion' => 'Pedir Rango de Fechas',
                'url' => route('mantenimiento.reportes.fallas-paros'),
                'disponible' => true,
            ],
        ];

        return view('modulos.mantenimiento.reportes-mantenimiento-index', [
            'reportes' => $reportes,
        ]);
    }

    /**
     * Reporte Fallas y Paros con filtro por fechas.
     * GET /mantenimiento/reportes/fallas-paros
     */
    public function reporteFallasParos(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        $query = ManFallasParos::query()
            ->orderBy('Fecha')
            ->orderBy('Hora');

        if ($fechaIni && $fechaFin) {
            $query->whereBetween('Fecha', [$fechaIni, $fechaFin]);
        }

        $registros = $query->get();

        return view('modulos.mantenimiento.reportes-mantenimiento-fallas-paros', [
            'registros' => $registros,
            'fechaIni' => $fechaIni ?? '',
            'fechaFin' => $fechaFin ?? '',
        ]);
    }

    /**
     * Exportar reporte a Excel.
     * GET /mantenimiento/reportes/fallas-paros/excel
     */
    public function exportarExcel(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        $query = ManFallasParos::query()
            ->orderBy('Fecha')
            ->orderBy('Hora');

        if ($fechaIni && $fechaFin) {
            $query->whereBetween('Fecha', [$fechaIni, $fechaFin]);
        }

        $registros = $query->get();

        return Excel::download(
            new ReporteMantenimientoExport($registros),
            'Reporte_Mantenimiento_' . now()->format('Y-m-d_His') . '.xlsx'
        );
    }
}
