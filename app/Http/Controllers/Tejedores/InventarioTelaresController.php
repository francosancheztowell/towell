<?php

namespace App\Http\Controllers\Tejedores;

use App\Http\Controllers\Controller;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Inventario\InvTelasReservadas;
use App\Models\Urdido\UrdProgramaUrdido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class InventarioTelaresController extends Controller
{
    /**
     * Obtener todos los registros de inventario de telares
     * Solo muestra registros con status 'Activo'
     */
    public function index(): JsonResponse
    {
        try {
            $inventario = TejInventarioTelares::where('status', 'Activo')
                ->orderBy('fecha', 'asc')
                ->orderBy('no_telar', 'asc')
                ->orderBy('turno', 'asc')
                ->get();

            // Entregar fecha como YYYY-MM-DD para evitar desfases por zona horaria en frontend.
            $inventarioNormalizado = $inventario->map(function (TejInventarioTelares $row) {
                $data = $row->toArray();

                $rawFecha = $row->getRawOriginal('fecha');
                if ($rawFecha) {
                    try {
                        $data['fecha'] = Carbon::parse($rawFecha)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $data['fecha'] = is_scalar($rawFecha) ? (string) $rawFecha : null;
                    }
                } elseif (!empty($row->fecha)) {
                    try {
                        $data['fecha'] = Carbon::parse($row->fecha)->format('Y-m-d');
                    } catch (\Throwable $e) {
                        $data['fecha'] = null;
                    }
                } else {
                    $data['fecha'] = null;
                }

                return $data;
            })->values();

            return response()->json([
                'success' => true,
                'data' => $inventarioNormalizado
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener inventario de telares', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inventario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar un registro de inventario de telares
     * Permite múltiples registros por telar (uno por fecha/turno/tipo)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'no_telar' => 'required|string|max:20',
                'tipo' => 'required|string|max:20',
                'cuenta' => 'required|string|max:20',
                'calibre' => 'nullable',
                'fecha' => 'required|date',
                'turno' => 'required|integer|min:1|max:3',
                'salon' => 'required|string|max:50',
                'hilo' => 'nullable|string|max:50',
                'no_orden' => 'nullable|string|max:50',
            ]);

            // Buscar registro existente por telar+tipo+fecha+turno (combinación única)
            $existente = TejInventarioTelares::where('no_telar', $validated['no_telar'])
                ->where('tipo', $validated['tipo'])
                ->where('fecha', $validated['fecha'])
                ->where('turno', $validated['turno'])
                ->where('status', 'Activo')
                ->first();

            if ($existente) {
                // Actualizar registro existente
                $datosUpdate = [
                    'cuenta' => $validated['cuenta'],
                    'calibre' => $validated['calibre'],
                    'salon' => $validated['salon'],
                    'hilo' => $validated['hilo'] ?? $existente->hilo,
                    'no_orden' => null, // FORZAR null - no se guarda no_orden
                ];

                $existente->update($datosUpdate);
                $registro = $existente;
            } else {
                // Crear nuevo registro
                $datosCreate = [
                    'no_telar' => $validated['no_telar'],
                    'status' => 'Activo',
                    'tipo' => $validated['tipo'],
                    'cuenta' => $validated['cuenta'],
                    'calibre' => $validated['calibre'],
                    'fecha' => $validated['fecha'],
                    'turno' => $validated['turno'],
                    'tipo_atado' => 'Normal',
                    'salon' => $validated['salon'],
                    'hilo' => $validated['hilo'] ?? null,
                    'no_orden' => null, // FORZAR null - no se guarda no_orden
                ];

                $registro = TejInventarioTelares::create($datosCreate);
            }

            return response()->json([
                'success' => true,
                'message' => 'Guardado con éxito',
                'data' => $registro,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al guardar inventario de telares', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar estado del telar antes de eliminar
     */
    public function verificarEstado(Request $request): JsonResponse
    {
        try {
            $noTelar = $request->input('no_telar') ?? $request->query('no_telar');
            $tipo = $request->input('tipo') ?? $request->query('tipo');
            $fecha = $request->input('fecha') ?? $request->query('fecha');
            $turno = $request->input('turno') ?? $request->query('turno');

            // Validar y normalizar parámetros
            $noTelar = $noTelar ? trim(strval($noTelar)) : null;
            $tipo = $tipo ? trim(strval($tipo)) : null;
            $fecha = $fecha ? trim(strval($fecha)) : null;
            $turno = $turno !== null ? (is_numeric($turno) ? intval($turno) : trim(strval($turno))) : null;

            if (!$noTelar || !$tipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan parámetros requeridos: no_telar, tipo'
                ], 422);
            }

            // Si se proporcionan fecha y turno, buscar el registro específico
            if ($fecha && $turno) {
                // Normalizar tipo para búsqueda
                $tipoNormalizado = $tipo;
                if ($tipo) {
                    $tipoUpper = strtoupper(trim($tipo));
                    if ($tipoUpper === 'RIZO') {
                        $tipoNormalizado = 'Rizo';
                    } elseif ($tipoUpper === 'PIE') {
                        $tipoNormalizado = 'Pie';
                    }
                }

                // Convertir fecha a formato correcto si viene como string
                $fechaFormato = $fecha;
                if (is_string($fecha)) {
                    try {
                        $fechaCarbon = \Carbon\Carbon::parse($fecha);
                        $fechaFormato = $fechaCarbon->format('Y-m-d');
                    } catch (\Exception $e) {
                        // Si no se puede parsear, usar la fecha tal cual
                        $fechaFormato = $fecha;
                    }
                }

                // Convertir turno a entero si viene como string
                $turnoInt = is_numeric($turno) ? (int)$turno : $turno;

                // Buscar el registro específico con múltiples intentos para asegurar que lo encontramos
                // Primero intentar con fecha exacta
                $registro = TejInventarioTelares::where('no_telar', $noTelar)
                    ->where('tipo', $tipoNormalizado)
                    ->where('fecha', $fechaFormato)
                    ->where('turno', $turnoInt)
                    ->where('status', 'Activo')
                    ->first();

                // Si no se encuentra, intentar con diferentes formatos de fecha (por si hay problemas de formato)
                if (!$registro) {
                    // Intentar parsear la fecha de diferentes maneras
                    try {
                        $fechaAlternativa = \Carbon\Carbon::parse($fechaFormato)->format('Y-m-d');
                        $registro = TejInventarioTelares::where('no_telar', $noTelar)
                            ->where('tipo', $tipoNormalizado)
                            ->whereRaw("CONVERT(DATE, fecha) = ?", [$fechaAlternativa])
                            ->where('turno', $turnoInt)
                            ->where('status', 'Activo')
                            ->first();
                    } catch (\Exception $e) {
                        // Ignorar error de parsing
                    }
                }

                // Si aún no se encuentra, NO usar un registro diferente como fallback
                // porque podría ser un registro diferente con el mismo telar/tipo/turno
                // Es mejor devolver error que usar el registro incorrecto

                if (!$registro) {
                    // Log detallado para debugging
                    $registrosSimilares = TejInventarioTelares::where('no_telar', $noTelar)
                        ->where('tipo', $tipoNormalizado)
                        ->where('status', 'Activo')
                        ->get(['id', 'fecha', 'turno', 'Reservado', 'no_julio', 'no_orden', 'metros']);

                    Log::warning('Registro no encontrado en verificarEstado', [
                        'no_telar' => $noTelar,
                        'tipo' => $tipoNormalizado,
                        'fecha_buscada' => $fechaFormato,
                        'turno_buscado' => $turnoInt,
                        'registros_similares' => $registrosSimilares->toArray()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Registro no encontrado',
                        'debug' => [
                            'fecha_buscada' => $fechaFormato,
                            'turno_buscado' => $turnoInt,
                            'registros_disponibles' => $registrosSimilares->count()
                        ]
                    ], 404);
                }

                // Verificar el estado del registro específico
                // IMPORTANTE: Verificar SIEMPRE si hay reservas activas que correspondan a este registro específico
                // usando TejInventarioTelaresId (más preciso), luego Fecha/Turno, y finalmente ProdDate como respaldo
                // Esto asegura que solo se considere reservado si realmente hay una reserva activa para ESTE registro específico

                $tieneReservasActivas = false;
                $metodoUsado = 'ninguno';

                // PRIORIDAD 1: Buscar por TejInventarioTelaresId (más preciso - identificación única)
                $reservasPorId = InvTelasReservadas::where('TejInventarioTelaresId', $registro->id)
                    ->where('Status', 'Reservado')
                    ->exists();

                if ($reservasPorId) {
                    $tieneReservasActivas = true;
                    $metodoUsado = 'TejInventarioTelaresId';
                } else {
                    // PRIORIDAD 2: Buscar por Fecha y Turno (campos específicos del registro)
                    try {
                        $fechaFormatoDB = \Carbon\Carbon::parse($fechaFormato)->format('Y-m-d');
                        $turnoIntDB = is_numeric($turnoInt) ? (int)$turnoInt : null;

                        if ($fechaFormatoDB && $turnoIntDB) {
                            $reservasPorFechaTurno = InvTelasReservadas::where('NoTelarId', $noTelar)
                                ->where('Status', 'Reservado')
                                ->where('Fecha', $fechaFormatoDB)
                                ->where('Turno', $turnoIntDB);

                            if ($tipoNormalizado) {
                                $reservasPorFechaTurno->where('Tipo', $tipoNormalizado);
                            }

                            $tieneReservasActivas = $reservasPorFechaTurno->exists();
                            if ($tieneReservasActivas) {
                                $metodoUsado = 'Fecha/Turno';
                            }
                        }
                    } catch (\Exception $e) {
                        // Si no se puede parsear la fecha/turno, continuar con siguiente método
                    }

                    // PRIORIDAD 3: Buscar por ProdDate (comportamiento legacy - menos preciso)
                    if (!$tieneReservasActivas) {
                        try {
                            $fechaProdDate = \Carbon\Carbon::parse($fechaFormato)->format('Y-m-d');
                            $reservasPorProdDate = InvTelasReservadas::where('NoTelarId', $noTelar)
                                ->where('Status', 'Reservado')
                                ->whereRaw("CONVERT(DATE, ProdDate) = ?", [$fechaProdDate]);

                            if ($tipoNormalizado) {
                                $reservasPorProdDate->where('Tipo', $tipoNormalizado);
                            }

                            $tieneReservasActivas = $reservasPorProdDate->exists();
                            if ($tieneReservasActivas) {
                                $metodoUsado = 'ProdDate';
                            }
                        } catch (\Exception $e) {
                            // Si no se puede parsear la fecha, ignorar esta verificación
                        }
                    }
                }

                // REGLA CRÍTICA: El registro está reservado si:
                // 1. Tiene Reservado = 1 (campo del registro) - ESTA ES LA FUENTE DE VERDAD PRINCIPAL
                // 2. O hay reservas activas que correspondan a este registro específico (verificado arriba)
                // IMPORTANTE: Si Reservado = 1, SIEMPRE considerar reservado, incluso si no se encuentran reservas activas
                // (puede ser una reserva antigua sin TejInventarioTelaresId o con datos inconsistentes)
                $campoReservado = (bool)($registro->Reservado ?? false);

                // Si el campo Reservado = 1, SIEMPRE considerar reservado
                // Esto es crítico porque el campo Reservado es la fuente de verdad más confiable
                if ($campoReservado) {
                    $estaReservado = true;
                } else {
                    // Si Reservado = 0, solo considerar reservado si hay reservas activas verificadas
                    $estaReservado = $tieneReservasActivas;
                }

                // Verificación adicional usando indicadores de reserva para distinguir registros:
                // - no_julio: Si tiene valor (no NULL), indica reserva
                // - no_orden: Si tiene valor (no NULL), indica reserva
                // - metros: Si tiene valor (no NULL), puede indicar reserva
                // Estos campos ayudan a distinguir registros reservados de no reservados
                // cuando hay múltiples fechas/turnos del mismo telar/tipo
                $tieneNoJulio = !empty($registro->no_julio);
                $tieneNoOrden = !empty($registro->no_orden);
                $tieneMetros = !empty($registro->metros);

                // REGLA CRÍTICA: Si Reservado = 0 y todos los indicadores son NULL (no_julio, no_orden, metros),
                // el registro NO está reservado, aunque otros registros del mismo telar/tipo puedan estarlo.
                // Esto evita conflictos cuando hay múltiples fechas/turnos del mismo telar/tipo.
                // Ejemplo: ID 112 (Reservado=0, no_julio=NULL, no_orden=NULL, metros=NULL) NO está reservado
                //          ID 115 (Reservado=1, no_julio=S462, no_orden=3869, metros=1000) SÍ está reservado

                // Si está programado, obtener el Status en UrdProgramaUrdido (solo informativo)
                // IMPORTANTE: Usar no_orden (= Folio en UrdProgramaUrdido) como identificador primario
                // para evitar confusiones cuando NoTelarId tiene múltiples telares (ej: "211,214,210")
                $estaProgramado = (bool)($registro->Programado ?? false);
                $statusUrdido = null;

                if ($estaProgramado) {
                    $noOrdenRegistro = $registro->no_orden ?? null;

                    if ($noOrdenRegistro && trim($noOrdenRegistro) !== '') {
                        // Búsqueda por Folio (identificación exacta 1:1)
                        $programaUrdido = UrdProgramaUrdido::where('Folio', trim($noOrdenRegistro))->first();

                        if ($programaUrdido) {
                            $statusUrdido = $programaUrdido->Status;
                        }
                    }
                }

                return response()->json([
                    'success' => true,
                    'reservado' => $estaReservado,
                    'programado' => $estaProgramado,
                    'registro_id' => $registro->id,
                    'status_urdido' => $statusUrdido, // Informativo: Status en UrdProgramaUrdido
                    'puede_eliminar' => true // Siempre se puede eliminar/editar registros programados
                ]);
            }

            // Si no se proporcionan fecha y turno, buscar cualquier registro del telar/tipo
            // (comportamiento legacy para compatibilidad)
            $telar = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('tipo', $tipo)
                ->where('status', 'Activo')
                ->first();

            if (!$telar) {
                return response()->json([
                    'success' => false,
                    'message' => 'Telar no encontrado'
                ], 404);
            }

            // Verificar si hay reservas activas en InvTelasReservadas para este telar y tipo
            // Normalizar tipo para búsqueda
            $tipoNormalizado = null;
            if ($tipo) {
                $tipoUpper = strtoupper(trim($tipo));
                if ($tipoUpper === 'RIZO') {
                    $tipoNormalizado = 'Rizo';
                } elseif ($tipoUpper === 'PIE') {
                    $tipoNormalizado = 'Pie';
                } else {
                    $tipoNormalizado = $tipo;
                }
            }

            // Verificar si hay reservas activas en InvTelasReservadas
            $tieneReservasActivas = InvTelasReservadas::where('NoTelarId', $noTelar)
                ->where('Status', 'Reservado');

            if ($tipoNormalizado) {
                $tieneReservasActivas->where('Tipo', $tipoNormalizado);
            }

            $tieneReservasActivas = $tieneReservasActivas->exists();

            // El telar está reservado si:
            // 1. El campo Reservado está en true, O
            // 2. Hay reservas activas en InvTelasReservadas
            $estaReservado = (bool)($telar->Reservado ?? false) || $tieneReservasActivas;

            return response()->json([
                'success' => true,
                'reservado' => $estaReservado,
                'programado' => (bool)($telar->Programado ?? false)
            ]);
        } catch (\Exception $e) {
            Log::error('Error al verificar estado del telar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un registro de inventario de telares
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            // Para DELETE, los datos pueden venir en el body o como query params
            $noTelar = $request->input('no_telar') ?? $request->query('no_telar');
            $tipo = $request->input('tipo') ?? $request->query('tipo');
            $fecha = $request->input('fecha') ?? $request->query('fecha');
            $turno = $request->input('turno') ?? $request->query('turno');

            if (!$noTelar || !$tipo || !$fecha || !$turno) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan parámetros requeridos: no_telar, tipo, fecha, turno'
                ], 422);
            }

            // Buscar registro por telar+tipo+fecha+turno
            $registro = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('tipo', $tipo)
                ->where('fecha', $fecha)
                ->where('turno', $turno)
                ->where('status', 'Activo')
                ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            // Normalizar tipo para búsqueda
            $tipoNormalizado = null;
            if ($tipo) {
                $tipoUpper = strtoupper(trim($tipo));
                if ($tipoUpper === 'RIZO') {
                    $tipoNormalizado = 'Rizo';
                } elseif ($tipoUpper === 'PIE') {
                    $tipoNormalizado = 'Pie';
                } else {
                    $tipoNormalizado = $tipo;
                }
            }

            // Registros programados: se permite eliminar sin importar el Status en UrdProgramaUrdido
            // (antes se bloqueaba si el Status era diferente a "Programado", pero ya no hay restricción)

            // 1) Eliminar reservas activas en InvTelasReservadas para este registro específico
            // IMPORTANTE: Usar TejInventarioTelaresId para identificar el registro específico
            $reservasEliminadas = 0;
            try {
                // PRIORIDAD 1: Buscar por TejInventarioTelaresId (más preciso - identificación única)
                $reservasPorId = InvTelasReservadas::where('TejInventarioTelaresId', $registro->id)
                    ->where('Status', 'Reservado')
                    ->get();

                foreach ($reservasPorId as $reserva) {
                    $reserva->delete();
                    $reservasEliminadas++;
                }

                // PRIORIDAD 2: Si no se encontraron por ID, buscar por Fecha y Turno (campos específicos)
                if ($reservasEliminadas === 0) {
                    try {
                        $fechaFormatoDB = Carbon::parse($fecha)->format('Y-m-d');
                        $turnoIntDB = is_numeric($turno) ? (int)$turno : null;

                        if ($fechaFormatoDB && $turnoIntDB) {
                            $reservasPorFechaTurno = InvTelasReservadas::where('NoTelarId', $noTelar)
                                ->where('Status', 'Reservado')
                                ->where('Fecha', $fechaFormatoDB)
                                ->where('Turno', $turnoIntDB);

                            if ($tipoNormalizado) {
                                $reservasPorFechaTurno->where('Tipo', $tipoNormalizado);
                            }

                            $reservasEncontradas = $reservasPorFechaTurno->get();
                            foreach ($reservasEncontradas as $reserva) {
                                $reserva->delete();
                                $reservasEliminadas++;
                            }
                        }
                    } catch (\Exception $e) {
                        // Si no se puede parsear la fecha/turno, continuar con siguiente método
                    }
                }

                // PRIORIDAD 3: Si aún no se encontraron, buscar por ProdDate (comportamiento legacy)
                if ($reservasEliminadas === 0) {
                    try {
                        $fechaProdDate = \Carbon\Carbon::parse($fecha)->format('Y-m-d');
                        $reservasPorProdDate = InvTelasReservadas::where('NoTelarId', $noTelar)
                            ->where('Status', 'Reservado')
                            ->whereRaw("CONVERT(DATE, ProdDate) = ?", [$fechaProdDate]);

                        if ($tipoNormalizado) {
                            $reservasPorProdDate->where('Tipo', $tipoNormalizado);
                        }

                        $reservasEncontradas = $reservasPorProdDate->get();
                        foreach ($reservasEncontradas as $reserva) {
                            $reserva->delete();
                            $reservasEliminadas++;
                        }
                    } catch (\Exception $e) {
                        // Si no se puede parsear la fecha, ignorar
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error al eliminar reservas en InvTelasReservadas', [
                    'registro_id' => $registro->id ?? null,
                    'no_telar' => $noTelar,
                    'tipo' => $tipoNormalizado,
                    'error' => $e->getMessage()
                ]);
            }

            // 2) Eliminar el registro en tej_inventario_telares
            $registro->delete();

            // 3) Actualizar el campo Reservado del registro específico eliminado a false
            // (aunque ya fue eliminado, esto es para consistencia si se restaura)
            // También verificar si quedan más reservas activas para este telar y tipo
            try {
                // Actualizar el campo Reservado del registro específico a false
                // (aunque el registro ya fue eliminado, esto es para consistencia)
                // Nota: El registro ya fue eliminado, así que esto no tiene efecto práctico,
                // pero es bueno para mantener la lógica consistente

                // Verificar si quedan más reservas activas para este telar y tipo
                // IMPORTANTE: Verificar por TejInventarioTelaresId para otros registros del mismo telar/tipo
                $tieneOtrasReservas = InvTelasReservadas::where('NoTelarId', $noTelar)
                    ->where('Status', 'Reservado')
                    ->exists();

                if (!$tieneOtrasReservas) {
                    // No quedan reservas, actualizar todos los telares de este número y tipo
                    // que aún estén activos y tengan Reservado = 1
                    $telares = TejInventarioTelares::where('no_telar', $noTelar)
                        ->where('status', 'Activo')
                        ->where('Reservado', true);

                    if ($tipoNormalizado) {
                        $telares->where('tipo', $tipoNormalizado);
                    }

                    $telares->update(['Reservado' => false]);
                }
            } catch (\Exception $e) {
                Log::warning('Error al actualizar campo Reservado después de eliminar', [
                    'no_telar' => $noTelar,
                    'tipo' => $tipoNormalizado,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado con éxito',
                'reservas_eliminadas' => $reservasEliminadas
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar inventario de telares', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar fecha de un registro de inventario de telares
     */
    /**
     * Verificar qué turnos están ocupados para una fecha específica
     */
    public function verificarTurnosOcupados(Request $request): JsonResponse
    {
        try {
            $noTelar = $request->input('no_telar');
            $tipo = $request->input('tipo');
            $fecha = $request->input('fecha');
            $registroIdExcluir = $request->input('registro_id_excluir'); // ID del registro que se está actualizando (excluir de la verificación)

            if (!$noTelar || !$tipo || !$fecha) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan parámetros requeridos: no_telar, tipo, fecha'
                ], 422);
            }

            // Normalizar tipo
            $tipoNormalizado = null;
            if ($tipo) {
                $tipoUpper = strtoupper(trim($tipo));
                if ($tipoUpper === 'RIZO') {
                    $tipoNormalizado = 'Rizo';
                } elseif ($tipoUpper === 'PIE') {
                    $tipoNormalizado = 'Pie';
                } else {
                    $tipoNormalizado = $tipo;
                }
            }

            // Buscar registros activos para esta fecha, telar y tipo
            $query = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('tipo', $tipoNormalizado)
                ->where('fecha', $fecha)
                ->where('status', 'Activo');

            // Excluir el registro que se está actualizando (si se proporciona)
            if ($registroIdExcluir) {
                $query->where('id', '!=', $registroIdExcluir);
            }

            $registros = $query->get(['id', 'turno', 'Reservado', 'Programado']);

            // Obtener los turnos ocupados (1, 2, 3)
            // Cualquier registro existente en esa fecha y turno se considera ocupado
            $turnosOcupados = [];
            foreach ($registros as $registro) {
                $turno = $registro->turno;
                if ($turno) {
                    $turnosOcupados[] = (int)$turno;
                }
            }

            // Eliminar duplicados y ordenar
            $turnosOcupados = array_unique($turnosOcupados);
            sort($turnosOcupados);

            return response()->json([
                'success' => true,
                'turnos_ocupados' => $turnosOcupados,
                'turnos_disponibles' => array_values(array_diff([1, 2, 3], $turnosOcupados))
            ]);
        } catch (\Exception $e) {
            Log::error('Error al verificar turnos ocupados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar turnos ocupados: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateFecha(Request $request): JsonResponse
    {
        try {
            $noTelar = $request->input('no_telar');
            $tipo = $request->input('tipo');
            $fechaOriginal = $request->input('fecha_original');
            $turnoOriginal = $request->input('turno');
            $fechaNueva = $request->input('fecha_nueva');
            $turnoNuevo = $request->input('turno_nuevo'); // Nuevo turno seleccionado

            if (!$noTelar || !$tipo || !$fechaOriginal || !$turnoOriginal || !$fechaNueva) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan parámetros requeridos: no_telar, tipo, fecha_original, turno, fecha_nueva'
                ], 422);
            }

            // Buscar registro por telar+tipo+fecha+turno
            $registro = TejInventarioTelares::where('no_telar', $noTelar)
                ->where('tipo', $tipo)
                ->where('fecha', $fechaOriginal)
                ->where('turno', $turnoOriginal)
                ->where('status', 'Activo')
                ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            // Normalizar tipo para búsqueda
            $tipoNormalizado = null;
            if ($tipo) {
                $tipoUpper = strtoupper(trim($tipo));
                if ($tipoUpper === 'RIZO') {
                    $tipoNormalizado = 'Rizo';
                } elseif ($tipoUpper === 'PIE') {
                    $tipoNormalizado = 'Pie';
                } else {
                    $tipoNormalizado = $tipo;
                }
            }

            // Registros programados: se permite actualizar sin importar el Status en UrdProgramaUrdido
            // (antes se bloqueaba si el Status era diferente a "Programado", pero ya no hay restricción)

            $calibreRegistro = $registro->calibre ?? null;
            $fechaOriginalFormato = \Carbon\Carbon::parse($fechaOriginal)->format('Y-m-d');
            $noTelarNormalizado = (string)trim($noTelar);

            // Si se proporciona turno_nuevo, validar que no esté ocupado
            // Excluir el registro actual de la verificación
            if ($turnoNuevo) {
                $turnosOcupados = $this->verificarTurnosOcupadosInterno($noTelar, $tipoNormalizado, $fechaNueva, $registro->id);
                if (in_array((int)$turnoNuevo, $turnosOcupados)) {
                    return response()->json([
                        'success' => false,
                        'message' => "El turno {$turnoNuevo} ya está ocupado para esta fecha"
                    ], 400);
                }
            }

            // 1) Actualizar la fecha y turno (si se proporciona) en el registro de tej_inventario_telares
            $registro->fecha = $fechaNueva;
            if ($turnoNuevo) {
                $registro->turno = (int)$turnoNuevo;
            }
            $registro->save();

            // 2) Actualizar FechaReq en UrdProgramaUrdido si existe registro
            // IMPORTANTE: Usar no_orden (= Folio en UrdProgramaUrdido) como identificador primario
            // para evitar conflictos cuando NoTelarId tiene múltiples telares (ej: "211,214,210")
            $noOrdenRegistro = $registro->no_orden ?? null;
            $fechaNuevaFormato = Carbon::parse($fechaNueva)->format('Y-m-d');

            if ($noOrdenRegistro && trim($noOrdenRegistro) !== '') {
                // Buscar por Folio (identificación exacta 1:1)
                $programaUrdido = UrdProgramaUrdido::where('Folio', trim($noOrdenRegistro))->first();

                if ($programaUrdido) {
                    $noTelarIdStr = (string)trim($programaUrdido->NoTelarId ?? '');
                    $telaresEnPrograma = array_map('trim', explode(',', $noTelarIdStr));

                    // Solo actualizar FechaReq si NoTelarId tiene UN SOLO telar
                    // Si tiene múltiples telares, NO actualizar porque afectaría a los demás
                    if (count($telaresEnPrograma) <= 1) {
                        $programaUrdido->FechaReq = $fechaNuevaFormato;
                        $programaUrdido->save();

                        Log::info('FechaReq actualizada en UrdProgramaUrdido (telar único)', [
                            'Folio' => $programaUrdido->Folio,
                            'NoTelarId' => $noTelarIdStr,
                            'FechaReq_anterior' => $fechaOriginalFormato,
                            'FechaReq_nueva' => $fechaNuevaFormato
                        ]);
                    } else {
                        Log::info('FechaReq NO actualizada en UrdProgramaUrdido: múltiples telares comparten el registro', [
                            'Folio' => $programaUrdido->Folio,
                            'NoTelarId' => $noTelarIdStr,
                            'telares_count' => count($telaresEnPrograma),
                            'telar_editado' => $noTelarNormalizado,
                            'motivo' => 'Actualizar FechaReq afectaría a todos los telares del grupo'
                        ]);
                    }
                }
            }

            // 2) Actualizar ProdDate, Fecha y Turno en InvTelasReservadas para este registro específico
            // Convertir fecha nueva a formato datetime para ProdDate
            try {
                $fechaProdDate = Carbon::parse($fechaNueva)->format('Y-m-d H:i:s');
                $fechaFormato = Carbon::parse($fechaNueva)->format('Y-m-d');

                // Buscar reservas por TejInventarioTelaresId (más preciso)
                $reservas = InvTelasReservadas::where('TejInventarioTelaresId', $registro->id)
                    ->where('Status', 'Reservado');

                // Si no se encuentran por ID, buscar por fecha original y turno original
                if ($reservas->count() === 0) {
                    $reservas = InvTelasReservadas::where('NoTelarId', $noTelar)
                        ->where('Status', 'Reservado')
                        ->where('Fecha', $fechaOriginal)
                        ->where('Turno', $turnoOriginal);

                    if ($tipoNormalizado) {
                        $reservas->where('Tipo', $tipoNormalizado);
                    }
                }

                // Actualizar ProdDate, Fecha y Turno en las reservas encontradas
                $updateData = [
                    'ProdDate' => $fechaProdDate,
                    'Fecha' => $fechaFormato
                ];

                if ($turnoNuevo) {
                    $updateData['Turno'] = (int)$turnoNuevo;
                }

                $reservasEncontradas = $reservas->get();
                foreach ($reservasEncontradas as $reserva) {
                    $reserva->ProdDate = $fechaProdDate;
                    $reserva->Fecha = $fechaFormato;
                    if ($turnoNuevo) {
                        $reserva->Turno = (int)$turnoNuevo;
                    }
                    $reserva->save();
                }
            } catch (\Exception $e) {
                Log::warning('Error al actualizar ProdDate en InvTelasReservadas', [
                    'no_telar' => $noTelar,
                    'tipo' => $tipoNormalizado,
                    'fecha_nueva' => $fechaNueva,
                    'error' => $e->getMessage()
                ]);
                // No fallar si no se puede actualizar InvTelasReservadas
            }

            return response()->json([
                'success' => true,
                'message' => 'Fecha y turno actualizados con éxito',
                'registro' => $registro
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar fecha de inventario de telares', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar fecha: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método interno para verificar turnos ocupados (reutilizable)
     */
    private function verificarTurnosOcupadosInterno($noTelar, $tipo, $fecha, $registroIdExcluir = null): array
    {
        // Normalizar tipo
        $tipoNormalizado = null;
        if ($tipo) {
            $tipoUpper = strtoupper(trim($tipo));
            if ($tipoUpper === 'RIZO') {
                $tipoNormalizado = 'Rizo';
            } elseif ($tipoUpper === 'PIE') {
                $tipoNormalizado = 'Pie';
            } else {
                $tipoNormalizado = $tipo;
            }
        }

        // Buscar registros activos para esta fecha, telar y tipo
        $query = TejInventarioTelares::where('no_telar', $noTelar)
            ->where('tipo', $tipoNormalizado)
            ->where('fecha', $fecha)
            ->where('status', 'Activo');

        // Excluir el registro que se está actualizando (si se proporciona)
        if ($registroIdExcluir) {
            $query->where('id', '!=', $registroIdExcluir);
        }

        $registros = $query->get(['id', 'turno', 'Reservado', 'Programado']);

        // Obtener los turnos ocupados (1, 2, 3)
        // Cualquier registro existente en esa fecha y turno se considera ocupado
        $turnosOcupados = [];
        foreach ($registros as $registro) {
            $turno = $registro->turno;
            if ($turno) {
                $turnosOcupados[] = (int)$turno;
            }
        }

        // Eliminar duplicados y ordenar
        $turnosOcupados = array_unique($turnosOcupados);
        sort($turnosOcupados);

        return $turnosOcupados;
    }
}
