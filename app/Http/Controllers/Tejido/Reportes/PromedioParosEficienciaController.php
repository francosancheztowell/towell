<?php

namespace App\Http\Controllers\Tejido\Reportes;

use App\Exports\PromedioParosEficienciaExport;
use App\Http\Controllers\Controller;
use App\Services\Tejido\PromedioParosEficienciaReportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class PromedioParosEficienciaController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $resolved = $this->resolveDateRange($request);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        return view('modulos.tejido.reportes.promedio-paros-eficiencia', [
            'fechaIni' => $resolved['fecha_ini'],
            'fechaFin' => $resolved['fecha_fin'],
        ]);
    }

    public function exportarExcel(
        Request $request,
        PromedioParosEficienciaReportService $service
    ) {
        $resolved = $this->resolveDateRange($request, requireRange: true);
        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        // Generar el XLSX recorre miles de celdas con PhpSpreadsheet; el límite por defecto (60s) no alcanza en producción.
        set_time_limit(300);

        $report = $service->build($resolved['fecha_ini'], $resolved['fecha_fin']);

        $fileName = 'promedio_paros_eficiencia_'
            . Carbon::parse($resolved['fecha_ini'])->format('d-m-Y')
            . '_a_'
            . Carbon::parse($resolved['fecha_fin'])->format('d-m-Y')
            . '.xlsx';

        return Excel::download(
            new PromedioParosEficienciaExport($report),
            $fileName
        );
    }

    private function resolveDateRange(Request $request, bool $requireRange = false): array|RedirectResponse
    {
        $fechaIni = $request->query('fecha_ini', $request->input('fecha_ini'));
        $fechaFin = $request->query('fecha_fin', $request->input('fecha_fin'));

        if (!$fechaIni && !$fechaFin && !$requireRange) {
            return [
                'fecha_ini' => null,
                'fecha_fin' => null,
            ];
        }

        if (!$fechaIni || !$fechaFin) {
            return redirect()
                ->route('tejido.reportes.promedio-paros-eficiencia')
                ->with('error', 'Debe seleccionar fecha inicial y fecha final.');
        }

        try {
            $fechaIni = Carbon::parse($fechaIni)->format('Y-m-d');
            $fechaFin = Carbon::parse($fechaFin)->format('Y-m-d');
        } catch (\Throwable $th) {
            return redirect()
                ->route('tejido.reportes.promedio-paros-eficiencia')
                ->with('error', 'Las fechas seleccionadas no son validas.');
        }

        if ($fechaIni > $fechaFin) {
            return redirect()
                ->route('tejido.reportes.promedio-paros-eficiencia')
                ->with('error', 'La fecha inicial no puede ser mayor que la final.');
        }

        return [
            'fecha_ini' => $fechaIni,
            'fecha_fin' => $fechaFin,
        ];
    }
}
