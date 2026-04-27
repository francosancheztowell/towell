<?php

namespace App\Http\Controllers\Tejido\Reportes;

use App\Exports\ReporteRpmSemanalExport;
use App\Http\Controllers\Controller;
use App\Services\Tejido\ReporteRpmSemanalService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteRpmSemanalController extends Controller
{
    public function __construct(
        private ReporteRpmSemanalService $reporteRpmSemanalService
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $semana = $request->query('semana');

        if (! $semana) {
            return view('modulos.tejido.reportes.rpm-semanal', [
                'secciones' => [],
                'filasOrdenTelar' => [],
                'totalGeneral' => null,
                'lunes' => null,
                'domingo' => null,
                'semanaParam' => null,
            ]);
        }

        try {
            $ref = Carbon::parse($semana, 'America/Mexico_City');
        } catch (\Throwable) {
            return redirect()
                ->route('tejido.reportes.inv-trama')
                ->with('error', 'Fecha de semana no válida.');
        }

        $lunes = $ref->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $domingo = $ref->copy()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        $data = $this->reporteRpmSemanalService->build($lunes, $domingo);

        return view('modulos.tejido.reportes.rpm-semanal', [
            'secciones' => $data['secciones'],
            'filasOrdenTelar' => $data['filas_orden_telar'],
            'totalGeneral' => $data['total_general'],
            'lunes' => $data['lunes'],
            'domingo' => $data['domingo'],
            'semanaParam' => $lunes,
        ]);
    }

    public function exportarExcel(Request $request): BinaryFileResponse|RedirectResponse
    {
        $semana = $request->input('semana') ?? $request->query('semana');

        if (! $semana) {
            return redirect()
                ->route('tejido.reportes.inv-trama')
                ->with('error', 'Indique la semana a exportar.');
        }

        try {
            $ref = Carbon::parse($semana, 'America/Mexico_City');
        } catch (\Throwable) {
            return redirect()
                ->route('tejido.reportes.inv-trama')
                ->with('error', 'Fecha de semana no válida.');
        }

        $lunes = $ref->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $domingo = $ref->copy()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        $data = $this->reporteRpmSemanalService->build($lunes, $domingo);
        $nombre = 'reporte-rpm-semanal_'.$lunes.'_'.$domingo.'.xlsx';

        return Excel::download(
            new ReporteRpmSemanalExport(
                $data['filas_orden_telar'],
                $data['total_general'],
                $lunes,
                $domingo
            ),
            $nombre
        );
    }
}
