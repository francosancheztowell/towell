<?php

namespace App\Http\Controllers\Tejido\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Tejido\TejMarcasLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteMarcasFinalesController extends Controller
{
    public function index(Request $request)
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');
        $diaSeleccionado = $request->query('dia');

        if (empty($fechaIni) || empty($fechaFin)) {
            return view('modulos.tejido.reportes.reporte-marcas-finales', [
                'fechaIni' => null,
                'fechaFin' => null,
                'preview' => collect(),
                'diasDisponibles' => collect(),
                'diaActual' => null,
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

        $diasDisponibles = $this->obtenerDiasConDatos($fechaIniFormateada, $fechaFinFormateada);

        if ($diasDisponibles->isEmpty()) {
            return view('modulos.tejido.reportes.reporte-marcas-finales', [
                'fechaIni' => $fechaIniFormateada,
                'fechaFin' => $fechaFinFormateada,
                'preview' => collect(),
                'diasDisponibles' => collect(),
                'diaActual' => null,
            ]);
        }

        $diaActual = $diaSeleccionado && $diasDisponibles->contains($diaSeleccionado)
            ? $diaSeleccionado
            : $diasDisponibles->first();

        $preview = $this->obtenerPreviewPorDia($diaActual);

        return view('modulos.tejido.reportes.reporte-marcas-finales', [
            'fechaIni' => $fechaIniFormateada,
            'fechaFin' => $fechaFinFormateada,
            'preview' => $preview,
            'diasDisponibles' => $diasDisponibles,
            'diaActual' => $diaActual,
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
                    'horas' => (float) ($registro->Horas ?? 0),
                    'trama' => (int) ($registro->Trama ?? 0),
                    'pie' => (int) ($registro->Pie ?? 0),
                    'rizo' => (int) ($registro->Rizo ?? 0),
                    'otros' => (int) ($registro->Otros ?? 0),
                ];
            })
            ->groupBy('turno')
            ->map(function (Collection $itemsTurno, int|string $turno) {
                $maquinas = $itemsTurno
                    ->groupBy('maquina')
                    ->map(function (Collection $itemsMaquina, string $maquina) {
                        $telares = $itemsMaquina
                            ->groupBy('telar')
                            ->map(function (Collection $lineas, int|string $telar) {
                                return (object) [
                                    'telar' => (int) $telar,
                                    'marcas' => (int) $lineas->sum('marcas'),
                                    'horas' => (float) $lineas->sum('horas'),
                                    'trama' => (int) $lineas->sum('trama'),
                                    'pie' => (int) $lineas->sum('pie'),
                                    'rizo' => (int) $lineas->sum('rizo'),
                                    'otros' => (int) $lineas->sum('otros'),
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

    private function obtenerDiasConDatos(string $fechaIni, string $fechaFin): Collection
    {
        return TejMarcasLine::query()
            ->whereBetween('Date', [$fechaIni, $fechaFin])
            ->distinct()
            ->orderBy('Date')
            ->pluck('Date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->unique()
            ->values();
    }

    private function obtenerPreviewPorDia(string $fecha): Collection
    {
        $registros = TejMarcasLine::query()
            ->whereDate('Date', $fecha)
            ->orderBy('Turno')
            ->get(['Folio', 'Date', 'Turno', 'NoTelarId', 'Marcas', 'Horas', 'Trama', 'Pie', 'Rizo', 'Otros']);

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
                    'horas' => (float) ($registro->Horas ?? 0),
                    'trama' => (int) ($registro->Trama ?? 0),
                    'pie' => (int) ($registro->Pie ?? 0),
                    'rizo' => (int) ($registro->Rizo ?? 0),
                    'otros' => (int) ($registro->Otros ?? 0),
                ];
            })
            ->groupBy('turno')
            ->map(function (Collection $itemsTurno, int|string $turno) {
                $maquinas = $itemsTurno
                    ->groupBy('maquina')
                    ->map(function (Collection $itemsMaquina, string $maquina) {
                        $telares = $itemsMaquina
                            ->groupBy('telar')
                            ->map(function (Collection $lineas, int|string $telar) {
                                return (object) [
                                    'telar' => (int) $telar,
                                    'marcas' => (int) $lineas->sum('marcas'),
                                    'horas' => (float) $lineas->sum('horas'),
                                    'trama' => (int) $lineas->sum('trama'),
                                    'pie' => (int) $lineas->sum('pie'),
                                    'rizo' => (int) $lineas->sum('rizo'),
                                    'otros' => (int) $lineas->sum('otros'),
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

    private function calcularPromediosPorTelar(string $fechaIni, string $fechaFin): Collection
    {
        $maquinasConPromedio = ['Jacquard Sulzer', 'Jacquard Smith', 'Smith Liso'];

        $registros = TejMarcasLine::query()
            ->whereBetween('Date', [$fechaIni, $fechaFin])
            ->whereIn('Turno', [1, 2, 3])
            ->get(['Turno', 'NoTelarId', 'Marcas']);

        if ($registros->isEmpty()) {
            return collect();
        }

        return $registros
            ->map(function ($registro) {
                $telar = (int) $registro->NoTelarId;
                return (object) [
                    'telar' => $telar,
                    'turno' => (int) $registro->Turno,
                    'marcas' => (int) ($registro->Marcas ?? 0),
                    'maquina' => $this->resolverMaquinaPorTelar($telar),
                ];
            })
            ->filter(fn($item) => in_array($item->maquina, $maquinasConPromedio, true))
            ->groupBy('telar')
            ->map(function (Collection $lineas, int|string $telar) {
                $marcasPorTurno = $lineas->groupBy('turno')->map(fn($t) => $t->sum('marcas'));

                $marcasT1 = (int) ($marcasPorTurno->get(1) ?? 0);
                $marcasT2 = (int) ($marcasPorTurno->get(2) ?? 0);
                $marcasT3 = (int) ($marcasPorTurno->get(3) ?? 0);

                $promedio = ($marcasT1 + $marcasT2 + $marcasT3) / 3;

                return (object) [
                    'telar' => (int) $telar,
                    'marcas_t1' => $marcasT1,
                    'marcas_t2' => $marcasT2,
                    'marcas_t3' => $marcasT3,
                    'promedio_gral' => round($promedio, 2),
                ];
            });
    }

    public function exportarExcel(Request $request): BinaryFileResponse
    {
        $fechaIni = $request->query('fecha_ini');
        $fechaFin = $request->query('fecha_fin');

        if (empty($fechaIni) || empty($fechaFin)) {
            abort(400, 'Fechas requeridas');
        }

        $fechaIniFormateada = Carbon::parse($fechaIni)->format('Y-m-d');
        $fechaFinFormateada = Carbon::parse($fechaFin)->format('Y-m-d');

        $datosPorDia = $this->obtenerDatosPorDia($fechaIniFormateada, $fechaFinFormateada);

        $export = new \App\Exports\ReporteMarcasFinalesExport($datosPorDia);

        $filename = 'marcas_finales_' . $fechaIniFormateada . '_' . $fechaFinFormateada . '.xlsx';

        return Excel::download($export, $filename);
    }

    private function obtenerDatosPorDia(string $fechaIni, string $fechaFin): Collection
    {
        $registros = TejMarcasLine::query()
            ->whereBetween('Date', [$fechaIni, $fechaFin])
            ->orderBy('Date')
            ->orderBy('Turno')
            ->orderBy('NoTelarId')
            ->get(['Folio', 'Date', 'Turno', 'NoTelarId', 'Marcas', 'Horas', 'Trama', 'Pie', 'Rizo', 'Otros']);

        if ($registros->isEmpty()) {
            return collect();
        }

        return $registros
            ->map(function ($registro) {
                $telar = (int) $registro->NoTelarId;
                return (object) [
                    'fecha' => Carbon::parse($registro->Date)->format('Y-m-d'),
                    'turno' => (int) $registro->Turno,
                    'telar' => $telar,
                    'maquina' => $this->resolverMaquinaPorTelar($telar),
                    'marcas' => (int) ($registro->Marcas ?? 0),
                    'horas' => (float) ($registro->Horas ?? 0),
                ];
            })
            ->groupBy('fecha')
            ->map(function (Collection $itemsDia, string $fecha) {
                $turnos = $itemsDia
                    ->groupBy('turno')
                    ->map(function (Collection $itemsTurno, int|string $turno) {
                        $maquinas = $itemsTurno
                            ->groupBy('maquina')
                            ->map(function (Collection $itemsMaquina, string $maquina) {
                                $telares = $itemsMaquina
                                    ->sortBy('telar')
                                    ->values()
                                    ->map(fn($item) => (object) [
                                        'telar' => $item->telar,
                                        'marcas' => $item->marcas,
                                        'horas' => $item->horas,
                                    ]);

                                return (object) [
                                    'maquina' => $maquina,
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
                    ->sortBy('turno')
                    ->values();

                return (object) [
                    'fecha' => $fecha,
                    'turnos' => $turnos,
                ];
            })
            ->sortBy('fecha')
            ->values();
    }
}
