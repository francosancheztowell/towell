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
            ->get(['Folio', 'Date', 'Turno', 'NoTelarId', 'Marcas', 'Horas', 'Trama', 'Pie', 'Rizo', 'Otros']);

        if ($registros->isEmpty()) {
            return collect();
        }

        $registrosNormalizados = $registros->map(function ($registro) {
            $telar = (int) $registro->NoTelarId;
            $maquina = $this->resolverMaquinaPorTelar($telar);

            return (object) [
                'maquina' => $maquina,
                'fecha' => $registro->Date,
                'turno' => (int) $registro->Turno,
                'folio' => $registro->Folio,
                'telar' => $telar,
                'marcas' => (int) ($registro->Marcas ?? 0),
                'horas' => (float) ($registro->Horas ?? 0),
                'trama' => (int) ($registro->Trama ?? 0),
                'pie' => (int) ($registro->Pie ?? 0),
                'rizo' => (int) ($registro->Rizo ?? 0),
                'otros' => (int) ($registro->Otros ?? 0),
            ];
        });

        $promediosPorTelar = $registrosNormalizados
            ->filter(function ($registro) {
                return in_array((int) $registro->turno, [1, 2, 3], true);
            })
            ->groupBy(function ($registro) {
                return $registro->maquina . '|' . $registro->telar;
            })
            ->map(function (Collection $lineas) {
                $sumaTurnos = collect([1, 2, 3])->sum(function (int $turno) use ($lineas) {
                    return (float) $lineas->where('turno', $turno)->sum('marcas');
                });

                return $sumaTurnos / 3;
            });

        return $registrosNormalizados
            ->groupBy('turno')
            ->map(function (Collection $itemsTurno, int|string $turno) use ($promediosPorTelar) {
                $maquinas = $itemsTurno
                    ->groupBy('maquina')
                    ->map(function (Collection $itemsMaquina, string $maquina) use ($promediosPorTelar) {
                        $telares = $itemsMaquina
                            ->groupBy('telar')
                            ->map(function (Collection $lineas, int|string $telar) use ($maquina, $promediosPorTelar) {
                                $promedioKey = $maquina . '|' . (int) $telar;

                                return (object) [
                                    'telar' => (int) $telar,
                                    'marcas' => (int) $lineas->sum('marcas'),
                                    'horas' => (float) $lineas->sum('horas'),
                                    'trama' => (int) $lineas->sum('trama'),
                                    'pie' => (int) $lineas->sum('pie'),
                                    'rizo' => (int) $lineas->sum('rizo'),
                                    'otros' => (int) $lineas->sum('otros'),
                                    'promedio_gral_telar' => (float) ($promediosPorTelar->get($promedioKey, 0.0)),
                                ];
                            })
                            ->sortBy('telar')
                            ->values();

                        return (object) [
                            'maquina' => $maquina,
                            'total_telares' => $telares->count(),
                            'total_marcas' => (int) round((float) $telares->sum('marcas')),
                            'telares' => $telares,
                        ];
                    })
                    ->sortBy(function ($grupoMaquina) {
                        return $this->ordenMaquina($grupoMaquina->maquina);
                    })
                    ->values();

                return (object) [
                    'turno' => (int) $turno,
                    'maquinas' => $maquinas,
                ];
            })
            ->sortBy(function ($grupoTurno) {
                return $grupoTurno->turno;
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
