<?php

namespace App\Http\Controllers\Tejido\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Tejido\TejMarcas;
use App\Models\Tejido\TejMarcasLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReporteMarcasFinalesController extends Controller
{
    public function index(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (empty($fechaIni) || empty($fechaFin)) {
            return view('modulos.tejido.reportes.reporte-marcas-finales', [
                'fechaIni' => null,
                'fechaFin' => null,
                'preview' => collect(),
            ]);
        }

        try {
            $fechaIniFormateada = Carbon::parse($fechaIni)->format('Y-m-d');
            $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');
        } catch (\Throwable $th) {
            return redirect()->route('tejido.reportes.marcas-finales')
                ->with('error', 'Rango de fechas inválido.');
        }

        if ($fechaIniFormateada > $fechaFinFormateada) {
            return redirect()->route('tejido.reportes.marcas-finales')
                ->with('error', 'La fecha inicial no puede ser mayor que la final.');
        }

        $preview = $this->obtenerPreview($fechaIniFormateada, $fechaFinFormateada);

        return view('modulos.tejido.reportes.reporte-marcas-finales', [
            'fechaIni' => $fechaIniFormateada,
            'fechaFin' => $fechaFinFormateada,
            'preview' => $preview,
        ]);
    }

    private function obtenerPreview(string $fechaIni, string $fechaFin): Collection
    {
        $registros = TejMarcas::query()
            ->whereBetween('Date', [$fechaIni, $fechaFin])
            ->orderBy('Date')
            ->orderBy('Turno')
            ->get(['Folio', 'Date', 'Turno', 'Status']);

        if ($registros->isEmpty()) {
            return collect();
        }

        $totalesPorFolio = TejMarcasLine::query()
            ->whereIn('Folio', $registros->pluck('Folio')->all())
            ->selectRaw('Folio, COUNT(*) as TotalTelares, SUM(COALESCE(Marcas, 0)) as TotalMarcas')
            ->groupBy('Folio')
            ->get()
            ->keyBy('Folio');

        return $registros->map(function ($registro) use ($totalesPorFolio) {
            $totales = $totalesPorFolio->get($registro->Folio);

            return (object) [
                'fecha' => $registro->Date,
                'turno' => $registro->Turno,
                'folio' => $registro->Folio,
                'status' => $registro->Status,
                'total_telares' => (int) ($totales->TotalTelares ?? 0),
                'total_marcas' => (int) round((float) ($totales->TotalMarcas ?? 0)),
            ];
        });
    }
}
