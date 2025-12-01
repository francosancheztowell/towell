<?php

namespace App\Http\Controllers;

use App\Models\TejMarcas;
use App\Models\TejMarcasLine;
use App\Models\ReqProgramaTejido;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Helpers\TurnoHelper;

class MarcasController extends Controller
{
    public function index(Request $request)
    {
        try {
            if ($request->has('folio')) {
                // Permitir edición de folio específico
            } else {
                $folioEnProceso = TejMarcas::where('Status', 'En Proceso')
                    ->orderByDesc('Date')
                    ->value('Folio');

                if ($folioEnProceso) {
                    return redirect()->route('marcas.nuevo', ['folio' => $folioEnProceso])
                        ->with('warning', 'Hay un folio en proceso. Se ha redirigido automáticamente para continuar editándolo.');
                }
            }

            $telares = $this->obtenerSecuenciaTelares();

            return view('modulos.marcas-finales.nuevo-marcas', compact('telares'));
        } catch (\Exception $e) {
            return view('modulos.marcas-finales.nuevo-marcas', ['telares' => collect([])]);
        }
    }

    public function consultar()
    {
        try {
            $marcas = TejMarcas::select('Folio', 'Date', 'Turno', 'numero_empleado', 'Status')
                ->orderByRaw("CASE WHEN Status = 'En Proceso' THEN 0 ELSE 1 END")
                ->orderByDesc('Date')
                ->get();

            $ultimoFolio = $marcas->first();

            return view('modulos.marcas-finales.consultar-marcas-finales', compact('marcas', 'ultimoFolio'));
        } catch (\Exception $e) {
            return view('modulos.marcas-finales.consultar-marcas-finales', [
                'marcas' => collect([]),
                'ultimoFolio' => null
            ]);
        }
    }

    public function generarFolio()
    {
        try {
            $usuario = Auth::user();
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Validar que no exista otro folio "En Proceso"
            $folioEnProceso = TejMarcas::where('Status', 'En Proceso')->first();
            
            if ($folioEnProceso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un folio en proceso: ' . $folioEnProceso->Folio . '. Debe finalizarlo antes de crear uno nuevo.',
                    'folio_existente' => $folioEnProceso->Folio
                ], 400);
            }

            $ultimoFolio = TejMarcas::orderByDesc('Folio')->value('Folio');

            if ($ultimoFolio) {
                $soloDigitos = preg_replace('/\D/', '', $ultimoFolio);
                $numero = intval($soloDigitos) + 1;
                $nuevoFolio = 'FM' . str_pad($numero, 4, '0', STR_PAD_LEFT);
            } else {
                $nuevoFolio = 'FM0001';
            }

            return response()->json([
                'success' => true,
                'folio' => $nuevoFolio,
                'turno' => TurnoHelper::getTurnoActual(),
                'usuario' => $usuario->nombre ?? 'Usuario',
                'numero_empleado' => $usuario->numero_empleado ?? ''
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar folio'
            ], 500);
        }
    }

    public function obtenerDatosSTD()
    {
        try {
            $secuencia = $this->obtenerSecuenciaTelares();

            if ($secuencia->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'datos' => []
                ]);
            }

            $noTelares = $secuencia->pluck('NoTelarId')->toArray();

            $eficiencias = ReqProgramaTejido::select('NoTelarId', 'SalonTejidoId', 'EficienciaSTD')
                ->whereIn('NoTelarId', $noTelares)
                ->orderByDesc('FechaInicio')
                ->get()
                ->groupBy('NoTelarId')
                ->map(function ($group) {
                    return $group->first();
                });

            $datos = $secuencia->map(function ($row) use ($eficiencias) {
                $eficiencia = $eficiencias->get($row->NoTelarId);
                $porcentajeEfi = $eficiencia
                    ? (int)round(($eficiencia->EficienciaSTD ?? 0) * 100)
                    : 0;

                return [
                    'telar' => $row->NoTelarId,
                    'salon' => $eficiencia->SalonTejidoId ?? $row->SalonId ?? '-',
                    'porcentaje_efi' => $porcentajeEfi
                ];
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'datos' => $datos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos STD'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $usuario = Auth::user();
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $folio = $request->input('folio');
            $fecha = $request->input('fecha') ?: now()->toDateString();
            $turno = $request->input('turno') ?: TurnoHelper::getTurnoActual();
            $status = $request->input('status', 'En Proceso');
            $lineas = $request->input('lineas', []);

            DB::beginTransaction();

            $marca = TejMarcas::firstOrNew(['Folio' => $folio]);
            $marca->fill([
                'Date' => $fecha,
                'Turno' => $turno,
                'Status' => $status,
                'numero_empleado' => $usuario->numero_empleado ?? null,
                'nombreEmpl' => $usuario->nombre ?? null,
                'updated_at' => now()
            ]);

            if (!$marca->exists) {
                $marca->created_at = now();
            }

            $marca->save();

            TejMarcasLine::where('Folio', $folio)->delete();

            if (!empty($lineas)) {
                $noTelares = collect($lineas)->pluck('NoTelarId')->unique()->toArray();

                $eficienciasStd = ReqProgramaTejido::select('NoTelarId', 'SalonTejidoId', 'EficienciaSTD')
                    ->whereIn('NoTelarId', $noTelares)
                    ->orderByDesc('FechaInicio')
                    ->get()
                    ->groupBy('NoTelarId')
                    ->map(fn($group) => $group->first());

                // Mapa de salón por telar desde la secuencia (fallback si no hay STD)
                $secuencia = $this->obtenerSecuenciaTelares();
                $salonPorTelar = $secuencia->keyBy('NoTelarId');

                $lineasParaInsertar = [];
                foreach ($lineas as $linea) {
                    if (!is_array($linea) || !isset($linea['NoTelarId'])) {
                        continue;
                    }

                    $noTelar = $linea['NoTelarId'];
                    $std = $eficienciasStd->get($noTelar);

                    // Fallback salón si no hay STD
                    $stdSalon = $std->SalonTejidoId ?? optional($salonPorTelar->get($noTelar))->SalonId;

                    // STD eficiencia original viene como decimal (0-1), convertir a entero porcentaje
                    $stdEfiDecimal = $std->EficienciaSTD ?? null;
                    $stdEfiPercent = $stdEfiDecimal !== null ? (int)round($stdEfiDecimal * 100) : 0;

                    // Prioridad: valor capturado (PorcentajeEfi) si viene, si no STD convertido, si no 0
                    $efiPercent = isset($linea['PorcentajeEfi']) ? (int)$linea['PorcentajeEfi'] : $stdEfiPercent;
                    
                    // Asegurar rango 0-100
                    if ($efiPercent < 0) $efiPercent = 0;
                    if ($efiPercent > 100) $efiPercent = 100;

                    $lineasParaInsertar[] = [
                        'Folio' => $folio,
                        'Date' => $fecha,
                        'Turno' => $turno,
                        'SalonTejidoId' => $stdSalon,
                        'NoTelarId' => $noTelar,
                        'Eficiencia' => $efiPercent, // Guardar como ENTERO 0-100
                        'Marcas' => (int)($linea['Marcas'] ?? 0),
                        'Trama' => (int)($linea['Trama'] ?? 0),
                        'Pie' => (int)($linea['Pie'] ?? 0),
                        'Rizo' => (int)($linea['Rizo'] ?? 0),
                        'Otros' => (int)($linea['Otros'] ?? 0),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }

                TejMarcasLine::insert($lineasParaInsertar);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Datos guardados correctamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en MarcasController@store', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar datos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($folio)
    {
        try {
            $marca = TejMarcas::find($folio);

            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], 404);
            }

            $lineas = TejMarcasLine::where('Folio', $folio)
                ->orderBy('NoTelarId')
                ->get();

            return response()->json([
                'success' => true,
                'marca' => $marca,
                'lineas' => $lineas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener marca'
            ], 500);
        }
    }

    public function update(Request $request, $folio)
    {
        return $this->store($request);
    }

    public function finalizar($folio)
    {
        try {
            if (!Auth::check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $marca = TejMarcas::find($folio);
            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], 404);
            }

            $marca->update([
                'Status' => 'Finalizado',
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Marca finalizada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar marca'
            ], 500);
        }
    }

    public function visualizar($folio)
    {
        try {
            $marcaBase = TejMarcas::find($folio);
            if (!$marcaBase) {
                return redirect()->route('marcas.consultar')
                    ->with('error', 'Folio no encontrado');
            }

            $fecha = $marcaBase->Date; // Fecha común para los 3 turnos
            // Obtener todas las líneas de los tres turnos de esa fecha
            // Usar where simple para evitar problemas de firma con whereDate en algunos entornos
            $lineasFecha = TejMarcasLine::where('Date', $fecha)
                ->orderBy('NoTelarId')
                ->get();

            // Secuencia de telares para orden base (fallback si no hay líneas)
            $secuencia = $this->obtenerSecuenciaTelares();
            $telaresSecuencia = $secuencia->pluck('NoTelarId')->toArray();

            // Lista unificada de telares (secuencia + presentes en líneas)
            $telaresLineas = $lineasFecha->pluck('NoTelarId')->unique()->toArray();
            $telares = collect(array_unique(array_merge($telaresSecuencia, $telaresLineas)))->sort()->values();

            // Agrupar por telar y turno
            $porTelarTurno = [];
            foreach ($lineasFecha as $l) {
                $telar = $l->NoTelarId;
                $turno = (string)$l->Turno; // "1","2","3"
                if (!isset($porTelarTurno[$telar])) $porTelarTurno[$telar] = [];
                $porTelarTurno[$telar][$turno] = $l; // Última ocurrencia para ese turno/telar
            }

            // Preparar estructura para la vista
            $datos = $telares->map(function($telar) use ($porTelarTurno) {
                $t1 = $porTelarTurno[$telar]['1'] ?? null;
                $t2 = $porTelarTurno[$telar]['2'] ?? null;
                $t3 = $porTelarTurno[$telar]['3'] ?? null;
                return [
                    'telar' => $telar,
                    't1' => $t1,
                    't2' => $t2,
                    't3' => $t3,
                ];
            });

            return view('modulos.marcas-finales.visualizar-marcas', [
                'folio' => $folio,
                'fecha' => $fecha,
                'datos' => $datos,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('marcas.consultar')
                ->with('error', 'Error al visualizar: ' . $e->getMessage());
        }
    }

    private function obtenerSecuenciaTelares()
    {
        try {
            return DB::table('InvSecuenciaMarcas')
                ->orderBy('Orden', 'asc')
                ->select('NoTelarId', 'SalonId')
                ->get()
                ->map(function ($row) {
                    return (object)[
                        'NoTelarId' => $row->NoTelarId,
                        'SalonId' => $row->SalonId
                    ];
                });
        } catch (\Exception $e) {
            try {
                return DB::table('InvSecuenciaTelares')
                    ->orderBy('Secuencia', 'asc')
                    ->selectRaw('NoTelar as NoTelarId')
                    ->get()
                    ->map(function ($row) {
                        return (object)[
                            'NoTelarId' => $row->NoTelarId,
                            'SalonId' => null
                        ];
                    });
            } catch (\Exception $e2) {
                return collect([]);
            }
        }
    }
}
