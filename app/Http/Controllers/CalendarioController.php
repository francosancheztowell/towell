<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReqCalendarioTab;
use App\Models\ReqCalendarioLine;
use App\Models\ReqProgramaTejido;
use App\Models\ReqModelosCodificados;
use App\Observers\ReqProgramaTejidoObserver;
use App\Http\Controllers\ProgramaTejido\funciones\BalancearTejido;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ReqCalendarioLineImport;
use App\Imports\ReqCalendarioTabImport;

class CalendarioController extends Controller
{
    private const RECALC_TIMEOUT_SECONDS = 900; // 15 min
    private const RECALC_CHUNK_SIZE = 25;       // (ya no se usa en el recalc por telar, lo dejo por compat)
    private const RECALC_LOG_EVERY = 25;

    private function boostRuntimeLimits(): void
    {
        @ini_set('max_execution_time', (string) self::RECALC_TIMEOUT_SECONDS);
        @set_time_limit(self::RECALC_TIMEOUT_SECONDS);
        @ini_set('memory_limit', '1024M');

        // Evita que el query log reviente memoria en loops grandes
        try { DB::connection()->disableQueryLog(); } catch (\Throwable $e) {}
    }

    public function index(Request $request)
    {
        $calendarios = ReqCalendarioTab::orderBy('CalendarioId')->get();
        $lineas = ReqCalendarioLine::orderBy('CalendarioId')->orderBy('FechaInicio')->get();

        return view('catalagos.calendarios.index', [
            'calendarioTab'  => $calendarios,
            'calendarioLine' => $lineas
        ]);
    }

    public function getCalendariosJson(Request $request)
    {
        try {
            $calendarios = ReqCalendarioTab::orderBy('CalendarioId')->get(['CalendarioId', 'Nombre']);

            return response()->json([
                'success' => true,
                'data' => $calendarios
            ]);
        } catch (\Exception $e) {
            Log::error("Error al obtener calendarios: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener calendarios'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'CalendarioId' => 'required|string|max:20|unique:ReqCalendarioTab,CalendarioId',
                'Nombre'       => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos invalidos',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $turnos = $request->input('Turnos');
            $fechaInicial = $request->input('FechaInicial');
            $fechaFinal = $request->input('FechaFinal');

            if ($turnos !== null) {
                $extraValidator = Validator::make($request->all(), [
                    'FechaInicial' => 'required|date',
                    'FechaFinal'   => 'required|date',
                    'Turnos'       => 'required|array'
                ]);

                if ($extraValidator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Datos invalidos',
                        'errors'  => $extraValidator->errors()
                    ], 422);
                }
            }

            DB::beginTransaction();

            $calendario = ReqCalendarioTab::create([
                'CalendarioId' => $request->CalendarioId,
                'Nombre'       => $request->Nombre
            ]);

            $lineasCreadas = 0;
            if ($turnos !== null) {
                $lineasCreadas = $this->crearLineasDesdeTurnos(
                    $calendario->CalendarioId,
                    (string) $fechaInicial,
                    (string) $fechaFinal,
                    is_array($turnos) ? $turnos : []
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Calendario creado exitosamente',
                'data'    => $calendario,
                'lineas_creadas' => $lineasCreadas
            ]);
        } catch (\InvalidArgumentException $e) {
            if (DB::transactionLevel() > 0) {
                try { DB::rollBack(); } catch (\Exception $rollbackEx) {}
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                try { DB::rollBack(); } catch (\Exception $rollbackEx) {}
            }
            Log::error("Error al crear calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $calendario = ReqCalendarioTab::where('CalendarioId', $id)->first();
            if (!$calendario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Calendario no encontrado'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'Nombre' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $calendario->update(['Nombre' => $request->Nombre]);

            return response()->json([
                'success' => true,
                'message' => 'Calendario actualizado exitosamente',
                'data'    => $calendario
            ]);
        } catch (\Exception $e) {
            Log::error("Error al actualizar calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function getCalendarioDetalle(string $calendarioId)
    {
        try {
            $calendario = ReqCalendarioTab::where('CalendarioId', $calendarioId)->first();
            if (!$calendario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Calendario no encontrado'
                ], 404);
            }

            $lineas = ReqCalendarioLine::where('CalendarioId', $calendarioId)
                ->orderBy('FechaInicio', 'desc')
                ->get(['FechaInicio', 'FechaFin', 'HorasTurno', 'Turno']);

            $diasMap = [
                0 => 'domingo',
                1 => 'lunes',
                2 => 'martes',
                3 => 'miercoles',
                4 => 'jueves',
                5 => 'viernes',
                6 => 'sabado',
            ];

            $turnos = [1, 2, 3];
            $turnosData = [];

            foreach ($turnos as $turno) {
                foreach ($diasMap as $diaKey) {
                    $turnosData[$turno][$diaKey] = [
                        'horas' => 0,
                        'inicio' => '',
                        'fin' => '',
                        'activo' => false
                    ];
                }
            }

            $fechaInicial = null;
            $fechaFinal = null;

            $visitado = [];

            foreach ($lineas as $linea) {
                $inicio = Carbon::parse($linea->FechaInicio);
                $fin = Carbon::parse($linea->FechaFin);
                $diaKey = $diasMap[$inicio->dayOfWeek] ?? null;
                $turno = (int) $linea->Turno;

                if (!$diaKey || !in_array($turno, $turnos, true)) {
                    continue;
                }

                $visitKey = $turno . '|' . $diaKey;
                if (!isset($visitado[$visitKey])) {
                    $turnosData[$turno][$diaKey] = [
                        'horas' => (float) $linea->HorasTurno,
                        'inicio' => $inicio->format('H:i:s'),
                        'fin' => $fin->format('H:i:s'),
                        'activo' => true
                    ];
                    $visitado[$visitKey] = true;
                }

                $diaInicio = $inicio->copy()->startOfDay();
                if ($fechaInicial === null || $diaInicio->lt($fechaInicial)) {
                    $fechaInicial = $diaInicio;
                }

                if ($fechaFinal === null || $diaInicio->gt($fechaFinal)) {
                    $fechaFinal = $diaInicio;
                }
            }

            if ($fechaInicial === null) {
                $fechaInicial = Carbon::today();
            }
            if ($fechaFinal === null) {
                $fechaFinal = $fechaInicial->copy();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'calendarioId' => $calendario->CalendarioId,
                    'nombre' => $calendario->Nombre,
                    'fechaInicial' => $fechaInicial->format('Y-m-d'),
                    'fechaFinal' => $fechaFinal->format('Y-m-d'),
                    'turnos' => $turnosData
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Error al obtener detalle de calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function updateMasivo(Request $request, string $calendarioId)
    {
        try {
            $calendario = ReqCalendarioTab::where('CalendarioId', $calendarioId)->first();
            if (!$calendario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Calendario no encontrado'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'Nombre'       => 'required|string|max:255',
                'FechaInicial' => 'required|date',
                'FechaFinal'   => 'required|date',
                'Turnos'       => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos invalidos',
                    'errors'  => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $calendario->update(['Nombre' => $request->Nombre]);
            ReqCalendarioLine::where('CalendarioId', $calendarioId)->delete();

            $lineasCreadas = $this->crearLineasDesdeTurnos(
                $calendarioId,
                (string) $request->FechaInicial,
                (string) $request->FechaFinal,
                is_array($request->Turnos) ? $request->Turnos : []
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Calendario actualizado exitosamente',
                'lineas_creadas' => $lineasCreadas
            ]);
        } catch (\InvalidArgumentException $e) {
            if (DB::transactionLevel() > 0) {
                try { DB::rollBack(); } catch (\Exception $rollbackEx) {}
            }
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                try { DB::rollBack(); } catch (\Exception $rollbackEx) {}
            }
            Log::error("Error al actualizar calendario (masivo): " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $calendario = ReqCalendarioTab::where('CalendarioId', $id)->first();
            if (!$calendario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Calendario no encontrado'
                ], 404);
            }

            $programasUsando = ReqProgramaTejido::where('CalendarioId', $id)->count();
            if ($programasUsando > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede eliminar el calendario porque esta siendo utilizado por {$programasUsando} programa(s) de tejido."
                ], 422);
            }

            DB::beginTransaction();
            ReqCalendarioLine::where('CalendarioId', $calendario->CalendarioId)->delete();
            $calendario->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Calendario y sus lineas eliminados exitosamente'
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                try { DB::rollBack(); } catch (\Exception $rollbackEx) {}
            }
            Log::error("Error al eliminar calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function storeLine(Request $request)
    {
        try {
            $this->boostRuntimeLimits();

            $validator = Validator::make($request->all(), [
                'CalendarioId' => 'required|string|max:20',
                'FechaInicio'  => 'required|date',
                'FechaFin'     => 'required|date',
                'HorasTurno'   => 'required|numeric|min:0',
                'Turno'        => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $calendario = ReqCalendarioTab::where('CalendarioId', $request->CalendarioId)->first();
            if (!$calendario) {
                return response()->json([
                    'success' => false,
                    'message' => 'El calendario especificado no existe'
                ], 422);
            }

            $linea = ReqCalendarioLine::create([
                'CalendarioId' => $request->CalendarioId,
                'FechaInicio'  => $request->FechaInicio,
                'FechaFin'     => $request->FechaFin,
                'HorasTurno'   => $request->HorasTurno,
                'Turno'        => $request->Turno
            ]);

            // AUTO: solo fechas (no líneas) para evitar timeout
            $stats = $this->recalcularProgramasPorCalendario(
                $request->CalendarioId,
                Carbon::parse($request->FechaInicio),
                Carbon::parse($request->FechaFin),
                'storeLine',
                false
            );

            return response()->json([
                'success'   => true,
                'message'   => 'Línea de calendario creada exitosamente',
                'data'      => $linea,
                'recalculo' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error("Error al crear línea de calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function updateLine(Request $request, $id)
    {
        try {
            $this->boostRuntimeLimits();

            $linea = ReqCalendarioLine::find($id);
            if (!$linea) {
                return response()->json([
                    'success' => false,
                    'message' => 'Línea de calendario no encontrada'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'FechaInicio' => 'required|date',
                'FechaFin'    => 'required|date',
                'HorasTurno'  => 'required|numeric|min:0',
                'Turno'       => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors'  => $validator->errors()
                ], 422);
            }

            $calendarioId = $linea->CalendarioId;

            $oldIni = Carbon::parse($linea->FechaInicio);
            $oldFin = Carbon::parse($linea->FechaFin);
            $newIni = Carbon::parse($request->FechaInicio);
            $newFin = Carbon::parse($request->FechaFin);

            $linea->update([
                'FechaInicio' => $request->FechaInicio,
                'FechaFin'    => $request->FechaFin,
                'HorasTurno'  => $request->HorasTurno,
                'Turno'       => $request->Turno
            ]);

            $rangoIni = $oldIni->lt($newIni) ? $oldIni : $newIni;
            $rangoFin = $oldFin->gt($newFin) ? $oldFin : $newFin;

            $stats = $this->recalcularProgramasPorCalendario(
                $calendarioId,
                $rangoIni,
                $rangoFin,
                'updateLine',
                false
            );

            return response()->json([
                'success'   => true,
                'message'   => 'Línea de calendario actualizada exitosamente',
                'data'      => $linea->fresh(),
                'recalculo' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error("Error al actualizar línea de calendario: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function destroyLine($id)
    {
        try {
            $this->boostRuntimeLimits();

            $linea = ReqCalendarioLine::find($id);
            if (!$linea) {
                return response()->json([
                    'success' => false,
                    'message' => 'Línea de calendario no encontrada'
                ], 404);
            }

            $calendarioId = $linea->CalendarioId;
            $rangoIni = Carbon::parse($linea->FechaInicio);
            $rangoFin = Carbon::parse($linea->FechaFin);

            $linea->delete();

            $stats = $this->recalcularProgramasPorCalendario(
                $calendarioId,
                $rangoIni,
                $rangoFin,
                'destroyLine',
                false
            );

            return response()->json([
                'success'   => true,
                'message'   => 'Línea de calendario eliminada exitosamente',
                'recalculo' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error("Error al eliminar línea de calendario: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function procesarExcel(Request $request)
    {
        try {
            $this->boostRuntimeLimits();

            $tipo = $request->input('tipo', 'calendarios');

            $validator = Validator::make($request->all(), [
                'archivo_excel' => 'required|file|mimes:xlsx,xls|max:10240'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo inválido. Debe ser Excel (.xlsx/.xls) máx 10MB.',
                    'errors'  => $validator->errors()
                ], 400);
            }

            $archivo = $request->file('archivo_excel');

            DB::beginTransaction();
            try {
                try {
                    if ($tipo === 'lineas') {
                        $countBefore = ReqCalendarioLine::count();
                        ReqCalendarioLine::query()->delete();
                        Log::info("Datos de líneas de calendario eliminados antes del import ({$countBefore} registros)");
                    } else {
                        ReqCalendarioLine::query()->delete();
                        $countBefore = ReqCalendarioTab::count();
                        ReqCalendarioTab::query()->delete();
                        Log::info("Datos de calendarios eliminados antes del import ({$countBefore} registros)");
                    }
                } catch (\Exception $deleteEx) {
                    Log::warning("DELETE falló, intentando TRUNCATE: " . $deleteEx->getMessage());
                    if ($tipo === 'lineas') {
                        ReqCalendarioLine::truncate();
                    } else {
                        ReqCalendarioLine::truncate();
                        ReqCalendarioTab::truncate();
                    }
                }

                $importador = ($tipo === 'lineas')
                    ? new ReqCalendarioLineImport()
                    : new ReqCalendarioTabImport();

                Excel::import($importador, $archivo);

                $stats = $importador->getStats();
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Archivo procesado exitosamente',
                    'data' => [
                        'registros_procesados'   => $stats['procesados'] ?? 0,
                        'registros_creados'      => $stats['creados'] ?? 0,
                        'registros_actualizados' => $stats['actualizados'] ?? 0,
                        'total_errores'          => isset($stats['errores']) ? count($stats['errores']) : 0,
                        'errores'                => isset($stats['errores']) ? array_slice($stats['errores'], 0, 10) : []
                    ]
                ]);
            } catch (\Exception $e) {
                if (DB::transactionLevel() > 0) {
                    try { DB::rollBack(); } catch (\Exception $rollbackEx) {}
                }

                Log::error("Error al procesar Excel: {$e->getMessage()}", [
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar el Excel: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("EXCEPCIÓN GENERAL en procesarExcel: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error inesperado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function recalcularProgramas(Request $request, $calendarioId)
    {
        try {
            $this->boostRuntimeLimits();

            $calendario = ReqCalendarioTab::where('CalendarioId', $calendarioId)->first();
            if (!$calendario) {
                $todos = ReqCalendarioTab::select('CalendarioId')->get()->pluck('CalendarioId')->toArray();
                return response()->json([
                    'success' => false,
                    'message' => "Calendario '{$calendarioId}' no encontrado. Disponibles: " . implode(', ', $todos)
                ], 404);
            }

            $stats = null;
            if ($request->isMethod('post')) {
                $regenerarLineas = $request->boolean('regenerar_lineas', false);
                $stats = $this->recalcularProgramasPorCalendario($calendarioId, null, null, 'manual', $regenerarLineas);
            }

            return response()->json([
                'success' => true,
                'message' => 'Recálculo completado exitosamente',
                'data' => [
                    'calendario_id'     => $calendarioId,
                    'calendario_nombre' => $calendario->Nombre,
                    'recalculo'         => $stats,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error("Error en recálculo manual {$calendarioId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor durante el recálculo'
            ], 500);
        }
    }

    /**
     * Recalcular programas por calendario
     * FIX: Anterior por TELAR debe ser por orden real (FechaInicio, Id), no por Id<.
     * Velocidad: reduce N+1; recalc por telar.
     */
    private function recalcularProgramasPorCalendario(
        string $calendarioId,
        ?Carbon $rangoIni,
        ?Carbon $rangoFin,
        string $motivo = '',
        bool $regenerarLineas = false
    ): array {
        $this->boostRuntimeLimits();
        $t0 = microtime(true);

        $dispatcher = ReqProgramaTejido::getEventDispatcher();
        ReqProgramaTejido::unsetEventDispatcher();

        $procesados = 0;
        $actualizados = 0;
        $errores = 0;

        try {
            // Base: detectar telares afectados (solo los que usan este calendario)
            $base = ReqProgramaTejido::where('CalendarioId', $calendarioId)
                ->whereNotNull('FechaInicio');

            if ($rangoIni && $rangoFin) {
                $iniStr = $rangoIni->format('Y-m-d H:i:s');
                $finStr = $rangoFin->format('Y-m-d H:i:s');

                $base->whereRaw(
                    "FechaInicio < ? AND (
                        (FechaFinal IS NOT NULL AND FechaFinal > ?)
                        OR DATEADD(SECOND, CAST(ISNULL(HorasProd,0) * 3600 AS INT), FechaInicio) > ?
                    )",
                    [$finStr, $iniStr, $iniStr]
                );
            }

            $total = (clone $base)->count();

            $telares = (clone $base)
                ->select(['SalonTejidoId', 'NoTelarId'])
                ->distinct()
                ->get();

            Log::info('RECALC CALENDARIO: start', [
                'calendario_id' => $calendarioId,
                'motivo'        => $motivo,
                'total'         => $total,
                'telares'       => $telares->count(),
                'rango_ini'     => $rangoIni?->format('Y-m-d H:i:s'),
                'rango_fin'     => $rangoFin?->format('Y-m-d H:i:s'),
                'regenerar'     => $regenerarLineas ? 1 : 0,
            ]);

            $observer = $regenerarLineas ? new ReqProgramaTejidoObserver() : null;

            foreach ($telares as $t) {
                $salon = $t->SalonTejidoId;
                $telar = $t->NoTelarId;

                // Traer el telar completo (mismo calendario) para cascada correcta dentro del calendario
                $rows = ReqProgramaTejido::where('CalendarioId', $calendarioId)
                    ->where('SalonTejidoId', $salon)
                    ->where('NoTelarId', $telar)
                    ->whereNotNull('FechaInicio')
                    ->orderBy('FechaInicio', 'asc')
                    ->orderBy('Id', 'asc')
                    ->get([
                        'Id',
                        'CalendarioId',
                        'SalonTejidoId',
                        'NoTelarId',
                        'FechaInicio',
                        'FechaFinal',
                        'HorasProd',
                        'SaldoPedido',
                        'Produccion',
                        'TotalPedido',
                        'PesoCrudo',
                        'EntregaPT',
                        'EntregaCte',
                        'DiasEficiencia',
                        'StdHrsEfect',
                        'ProdKgDia2',
                        'DiasJornada',
                    ]);

                if ($rows->isEmpty()) continue;

                $prevFin = null;
                $prevId  = null;

                foreach ($rows as $p) {
                    try {
                        if (empty($p->FechaInicio)) { $errores++; continue; }

                        $inicioOriginal = Carbon::parse($p->FechaInicio);
                        $inicio = $inicioOriginal->copy();
                        $inicioAjustado = false;

                        // ✅ Anterior REAL por orden (loop)
                        if ($prevFin) {
                            if (!$prevFin->equalTo($inicioOriginal)) {
                                $inicio = $prevFin->copy();
                                $inicioAjustado = true;
                            }
                        }

                        // ✅ Snap al calendario
                        $snap = $this->snapInicioAlCalendario($calendarioId, $inicio);
                        if ($snap && !$snap->equalTo($inicio)) {
                            $inicio = $snap;
                            $inicioAjustado = true;
                        }

                        // HorasProd: usar la del registro; solo calcular si viene 0
                        $horas = (float) ($p->HorasProd ?? 0);
                        if ($horas <= 0) {
                            $horas = $this->calcularHorasProd($p);
                            if ($horas > 0) $p->HorasProd = $horas;
                        }
                        if ($horas <= 0) { $errores++; continue; }

                        $fin = BalancearTejido::calcularFechaFinalDesdeInicio($calendarioId, $inicio, $horas);
                        if (!$fin) {
                            $fin = $inicio->copy()->addSeconds((int) round($horas * 3600));
                        }
                        if ($fin->lt($inicio)) $fin = $inicio->copy();

                        $inicioStr = $inicio->format('Y-m-d H:i:s');
                        $finStr    = $fin->format('Y-m-d H:i:s');

                        $oldInicioStr = null;
                        try { $oldInicioStr = Carbon::parse($p->FechaInicio)->format('Y-m-d H:i:s'); } catch (\Throwable $e) {}
                        $oldFinStr = null;
                        if (!empty($p->FechaFinal)) {
                            try { $oldFinStr = Carbon::parse($p->FechaFinal)->format('Y-m-d H:i:s'); } catch (\Throwable $e) {}
                        }

                        $cambio = ($oldInicioStr !== $inicioStr) || ($oldFinStr !== $finStr);

                        // 🔎 DEBUG especial para tu caso
                        if ((int)$p->Id === 16) {
                            Log::info('RECALC DEBUG ID=16', [
                                'telar' => trim((string)$salon) . '|' . trim((string)$telar),
                                'prev_id' => $prevId,
                                'prev_fin' => $prevFin?->format('Y-m-d H:i:s'),
                                'inicio_original' => $inicioOriginal->format('Y-m-d H:i:s'),
                                'inicio_nuevo' => $inicioStr,
                                'inicio_ajustado' => $inicioAjustado ? 1 : 0,
                                'horas' => $horas,
                                'fin_old' => $oldFinStr,
                                'fin_new' => $finStr,
                            ]);
                        }

                        $p->FechaInicio = $inicioStr;
                        $p->FechaFinal  = $finStr;

                        // Solo dependientes de fechas
                        $deps = $this->calcularFormulasDependientesDeFechas($p, $inicio, $fin, $horas);
                        foreach ($deps as $campo => $valor) {
                            $p->{$campo} = $valor;
                        }

                        $p->saveQuietly();

                        $procesados++;
                        if ($cambio) $actualizados++;

                        if ($regenerarLineas && $observer) {
                            $observer->saved($p);
                        }

                        $prevFin = $fin->copy();
                        $prevId  = (int)$p->Id;

                        if ($procesados % self::RECALC_LOG_EVERY === 0) {
                            Log::info('RECALC CALENDARIO: progress', [
                                'calendario_id' => $calendarioId,
                                'procesados'    => $procesados,
                                'actualizados'  => $actualizados,
                                'errores'       => $errores,
                            ]);
                        }

                    } catch (\Throwable $e) {
                        $errores++;
                        Log::warning('RECALC CALENDARIO: programa con error', [
                            'programa_id' => $p->Id ?? null,
                            'error'       => $e->getMessage(),
                        ]);
                    }
                }
            }

            $secs = round(microtime(true) - $t0, 2);

            Log::info('RECALC CALENDARIO: done', [
                'calendario_id' => $calendarioId,
                'motivo'        => $motivo,
                'procesados'    => $procesados,
                'actualizados'  => $actualizados,
                'errores'       => $errores,
                'segundos'      => $secs,
            ]);

            return [
                'procesados'   => $procesados,
                'actualizados' => $actualizados,
                'errores'      => $errores,
                'segundos'     => $secs,
            ];
        } finally {
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
        }
    }

    // ======================
    // Helpers
    // ======================

    private function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
    {
        $linea = ReqCalendarioLine::where('CalendarioId', $calendarioId)
            ->where('FechaFin', '>', $fechaInicio->format('Y-m-d H:i:s'))
            ->orderBy('FechaInicio', 'asc')
            ->first(['FechaInicio', 'FechaFin']);

        if (!$linea) return null;

        $ini = Carbon::parse($linea->FechaInicio);
        $fin = Carbon::parse($linea->FechaFin);

        if ($fechaInicio->gte($ini) && $fechaInicio->lt($fin)) return $fechaInicio->copy();

        return $ini->copy();
    }

    private function calcularHorasProd(ReqProgramaTejido $p): float
    {
        $vel  = (float) ($p->VelocidadSTD ?? 0);
        $efic = (float) ($p->EficienciaSTD ?? 0);
        if ($efic > 1) $efic = $efic / 100;

        $cantidad = $this->sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
        $m = $this->getModeloParams($p->TamanoClave ?? null, $p);

        $stdToaHra = 0.0;
        if ($m['no_tiras'] > 0 && $m['total'] > 0 && $m['luchaje'] > 0 && $m['repeticiones'] > 0 && $vel > 0) {
            $parte1 = $m['total'];
            $parte2 = (($m['luchaje'] * 0.5) / 0.0254) / $m['repeticiones'];
            $den = ($parte1 + $parte2) / $vel;
            if ($den > 0) $stdToaHra = ($m['no_tiras'] * 60) / $den;
        }

        if ($stdToaHra > 0 && $efic > 0 && $cantidad > 0) {
            return $cantidad / ($stdToaHra * $efic);
        }

        return 0.0;
    }

    private function getModeloParams(?string $tamanoClave, ReqProgramaTejido $p): array
    {
        static $modeloCache = [];

        $noTiras = (float)($p->NoTiras ?? 0);
        $luchaje = (float)($p->Luchaje ?? 0);
        $rep     = (float)($p->Repeticiones ?? 0);

        $key = trim((string)$tamanoClave);
        if ($key === '') {
            return [
                'total' => 0.0,
                'no_tiras' => $noTiras,
                'luchaje' => $luchaje,
                'repeticiones' => $rep,
            ];
        }

        if (!isset($modeloCache[$key])) {
            $m = ReqModelosCodificados::where('TamanoClave', $key)->first();
            $modeloCache[$key] = $m ? [
                'total' => (float)($m->Total ?? 0),
                'no_tiras' => (float)($m->NoTiras ?? 0),
                'luchaje' => (float)($m->Luchaje ?? 0),
                'repeticiones' => (float)($m->Repeticiones ?? 0),
            ] : [
                'total' => 0.0,
                'no_tiras' => 0.0,
                'luchaje' => 0.0,
                'repeticiones' => 0.0,
            ];
        }

        $base = $modeloCache[$key];

        return [
            'total' => (float)($base['total'] ?? 0),
            'no_tiras' => $noTiras > 0 ? $noTiras : (float)($base['no_tiras'] ?? 0),
            'luchaje' => $luchaje > 0 ? $luchaje : (float)($base['luchaje'] ?? 0),
            'repeticiones' => $rep > 0 ? $rep : (float)($base['repeticiones'] ?? 0),
        ];
    }

    private function sanitizeNumber($value): float
    {
        if ($value === null) return 0.0;
        if (is_numeric($value)) return (float)$value;
        $clean = str_replace([',', ' '], '', (string)$value);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }

    private function normalizarHoraHms(string $hora): string
    {
        $hora = trim($hora);
        if ($hora === '') return '';
        if (preg_match('/^\d{1,2}:\d{2}$/', $hora)) return "{$hora}:00";
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $hora)) return $hora;
        return $hora;
    }

    private function crearLineasDesdeTurnos(
        string $calendarioId,
        string $fechaInicial,
        string $fechaFinal,
        array $turnos
    ): int {
        $inicio = Carbon::parse($fechaInicial)->startOfDay();
        $fin = Carbon::parse($fechaFinal)->startOfDay();
        if ($fin->lt($inicio)) {
            throw new \InvalidArgumentException('Fecha final menor a fecha inicial');
        }

        $diasMap = [
            0 => 'domingo',
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
        ];

        $horasPorDia = [];
        foreach ($diasMap as $key) {
            $horasPorDia[$key] = 0.0;
        }

        foreach ($turnos as $turno => $dias) {
            if (!is_array($dias)) continue;
            foreach ($dias as $diaKey => $info) {
                if (!is_array($info)) continue;
                if (isset($info['activo']) && $info['activo'] === false) continue;
                $horas = (float) ($info['horas'] ?? 0);
                $horasPorDia[$diaKey] = ($horasPorDia[$diaKey] ?? 0) + $horas;
            }
        }

        foreach ($horasPorDia as $diaKey => $total) {
            if ($total > 24.01) {
                throw new \InvalidArgumentException("La suma de horas para {$diaKey} no puede ser mayor a 24");
            }
        }

        $lineas = [];
        $fecha = $inicio->copy();

        while ($fecha->lte($fin)) {
            $diaKey = $diasMap[$fecha->dayOfWeek] ?? null;
            if ($diaKey && isset($horasPorDia[$diaKey]) && $horasPorDia[$diaKey] > 0) {
                foreach ($turnos as $turno => $dias) {
                    if (!is_array($dias) || !isset($dias[$diaKey])) continue;
                    $info = $dias[$diaKey];
                    if (!is_array($info)) continue;
                    if (isset($info['activo']) && $info['activo'] === false) continue;

                    $horas = (float) ($info['horas'] ?? 0);
                    if ($horas <= 0) continue;

                    $horaInicio = $this->normalizarHoraHms((string) ($info['inicio'] ?? ''));
                    if ($horaInicio === '') continue;

                    $inicioDt = Carbon::parse($fecha->format('Y-m-d') . ' ' . $horaInicio);
                    $finDt = $inicioDt->copy()->addSeconds((int) round($horas * 3600))->subSeconds(2);

                    $lineas[] = [
                        'CalendarioId' => $calendarioId,
                        'FechaInicio'  => $inicioDt->format('Y-m-d H:i:s'),
                        'FechaFin'     => $finDt->format('Y-m-d H:i:s'),
                        'HorasTurno'   => $horas,
                        'Turno'        => (int) $turno
                    ];
                }
            }

            $fecha->addDay();
        }

        if ($lineas) {
            $chunkSize = 400;
            foreach (array_chunk($lineas, $chunkSize) as $chunk) {
                ReqCalendarioLine::insert($chunk);
            }
        }

        return count($lineas);
    }

    /**
     * Solo campos que sí dependen de FechaInicio/FechaFinal (por cambios de calendario)
     * NO toca StdToaHra/StdDia/HorasProd (para que no "se pase por tiempo")
     */
    private function calcularFormulasDependientesDeFechas(ReqProgramaTejido $p, Carbon $inicio, Carbon $fin, float $horasProd): array
    {
        $out = [];

        $diffSeg = abs($fin->getTimestamp() - $inicio->getTimestamp());
        $diffDias = $diffSeg / 86400;

        if ($diffDias > 0) {
            $out['DiasEficiencia'] = (float) round($diffDias, 4);
        }

        $cantidad = $this->sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);
        if ($diffDias > 0 && $cantidad > 0) {
            $stdHrsEfect = ($cantidad / $diffDias) / 24;
            $out['StdHrsEfect'] = (float) round($stdHrsEfect, 4);

            $pesoCrudo = (float) ($p->PesoCrudo ?? 0);
            if ($pesoCrudo > 0) {
                $out['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 4);
            }
        }

        if ($horasProd > 0) {
            $out['DiasJornada'] = (float) round($horasProd / 24, 4);
        }

        $entregaCte = $fin->copy()->addDays(12);
        $out['EntregaCte'] = $entregaCte->format('Y-m-d H:i:s');

        if (!empty($p->EntregaPT)) {
            try {
                $entregaPT = Carbon::parse($p->EntregaPT);
                $out['PTvsCte'] = (float) round($entregaCte->diffInDays($entregaPT, false), 2);
            } catch (\Throwable $e) {}
        }

        return $out;
    }
}
