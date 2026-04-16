<?php

namespace App\Http\Controllers\Tejido\Reportes;

use App\Http\Controllers\Controller;
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
        $registros = TejMarcasLine::query()
            ->whereBetween('Date', [$fechaIni, $fechaFin])
            ->orderBy('Date')
            ->orderBy('Turno')
            ->get(['Folio', 'Date', 'Turno', 'NoTelarId', 'Marcas', 'Trama', 'Pie', 'Rizo', 'Otros']);

        if ($registros->isEmpty()) {
            return collect();
        }

        return $registros
            ->map(function ($registro) {
                $telar = (int) $registro->NoTelarId;
                $maquina = $this->resolverMaquinaPorTelar($telar);

                return (object) [
                    'maquina' => $maquina,
                    'fecha' => $registro->Date,
                    'turno' => (int) $registro->Turno,
                    'folio' => $registro->Folio,
                    'telar' => $telar,
                    'marcas' => (int) ($registro->Marcas ?? 0),
                    'trama' => (int) ($registro->Trama ?? 0),
                    'pie' => (int) ($registro->Pie ?? 0),
                    'rizo' => (int) ($registro->Rizo ?? 0),
                    'otros' => (int) ($registro->Otros ?? 0),
                ];
            })
            ->groupBy('maquina')
            ->map(function (Collection $items, string $maquina) {
                $ordenados = $items->sortBy([
                    ['fecha', 'asc'],
                    ['turno', 'asc'],
                    ['telar', 'asc'],
                ])->values();

                return (object) [
                    'maquina' => $maquina,
                    'total_telares' => $items->pluck('telar')->unique()->count(),
                    'total_marcas' => (int) round((float) $items->sum('marcas')),
                    'registros' => $ordenados,
                ];
            })
            ->sortBy(function ($grupo) {
                return $this->ordenMaquina($grupo->maquina);
            })
            ->values();
    }

    private function resolverMaquinaPorTelar(int $telar): string
    {
        if ($telar >= 207 && $telar <= 211) {
            return 'Jacquard Sulzer';
        }

        if (($telar >= 201 && $telar <= 206) || ($telar >= 212 && $telar <= 215)) {
            return 'Jacquard Smith';
        }

        if ($telar >= 299 && $telar <= 320) {
            return 'Smith Liso';
        }

        if (in_array($telar, [401, 402], true)) {
            return 'Karl Mayer';
        }

        return 'Sin clasificación';
    }

    private function ordenMaquina(string $maquina): int
    {
        return match ($maquina) {
            'Jacquard Sulzer' => 1,
            'Jacquard Smith' => 2,
            'Smith Liso' => 3,
            'Karl Mayer' => 4,
            default => 99,
        };
    }
}
