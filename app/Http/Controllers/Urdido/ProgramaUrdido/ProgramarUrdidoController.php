<?php

namespace App\Http\Controllers\Urdido\ProgramaUrdido;

use App\Http\Controllers\Controller;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ProgramarUrdidoController extends Controller
{
    private function usuarioPuedeEditar(): bool
    {
        $usuario = Auth::user();
        if (!$usuario) {
            return false;
        }

        $area = strtolower($usuario->area ?? '');
        $puesto = strtolower($usuario->puesto ?? '');

        return $area === 'urdido' && str_contains($puesto, 'supervisor');
    }
    /**
     * Mostrar la vista de programar urdido
     */
    public function index()
    {
        return view('modulos.urdido.programar-urdido');
    }

    /**
     * Mostrar todas las ordenes para reimpresion
     * Ordenadas por las más recientes primero
     */
    public function reimpresionFinalizadas(Request $request)
    {
        $busqueda = trim((string) $request->query('q', ''));
        $folio = trim((string) $request->query('folio', ''));
        $maquina = trim((string) $request->query('maquina', ''));
        $tipo = trim((string) $request->query('tipo', ''));
        $status = trim((string) $request->query('status', ''));

        $query = UrdProgramaUrdido::select([
            'Id',
            'Folio',
            'RizoPie',
            'Cuenta',
            'Calibre',
            'Metros',
            'MaquinaId',
            'FechaProg',
            'Status',
        ]);

        // Filtro por folio
        if ($folio !== '') {
            $query->where('Folio', 'like', "%{$folio}%");
        }

        // Filtro por máquina
        if ($maquina !== '') {
            $query->where('MaquinaId', $maquina);
        }

        // Filtro por tipo
        if ($tipo !== '') {
            $query->where('RizoPie', $tipo);
        }

        // Filtro por status
        if ($status !== '') {
            $query->where('Status', $status);
        }

        // Búsqueda general (si no hay filtros específicos)
        if ($busqueda !== '' && $folio === '' && $maquina === '' && $tipo === '' && $status === '') {
            $query->where(function ($sub) use ($busqueda) {
                $sub->where('Folio', 'like', "%{$busqueda}%")
                    ->orWhere('Cuenta', 'like', "%{$busqueda}%")
                    ->orWhere('MaquinaId', 'like', "%{$busqueda}%");
            });
        }

        $ordenes = $query
            ->orderBy('FechaProg', 'desc') // Más recientes primero
            ->orderBy('Id', 'desc') // Si hay misma fecha, más reciente por ID
            ->get(); // Sin límite para mostrar todas

        return view('modulos.urdido.reimpresion-urdido', [
            'ordenes' => $ordenes,
            'busqueda' => $busqueda,
        ]);
    }

    /**
     * Extraer número de MC Coy del campo MaquinaId
     *
     * @param string|null $maquinaId
     * @return int|null
     */
    private function extractMcCoyNumber(?string $maquinaId): ?int
    {
        if (empty($maquinaId)) {
            return null;
        }

        // Buscar patrón "Mc Coy X" (case insensitive, permite espacios variables)
        if (preg_match('/mc\s*coy\s*(\d+)/i', $maquinaId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Obtener órdenes de urdido agrupadas por MC Coy
     * Extrae el MC Coy del campo MaquinaId (ej: "Mc Coy 1" -> 1)
     * Solo incluye órdenes con status "Programado" o "En Proceso" (excluye canceladas)
     * Ordena por Prioridad si existe, sino por CreatedAt ascendente
     *
     * @return JsonResponse
     */
    public function getOrdenes(): JsonResponse
    {
        try {
            // Observaciones siempre existe, verificar si Prioridad existe
            $tienePrioridad = false;

            try {
                // Intentar cargar con Prioridad
                $ordenes = UrdProgramaUrdido::select([
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'MaquinaId',
                    'Status',
                    'FechaProg',
                    'Prioridad',
                    'CreatedAt',
                    'Observaciones',
                ])
                ->whereIn('Status', ['Programado', 'En Proceso'])
                ->whereNotNull('MaquinaId')
                ->get();

                $tienePrioridad = true;
            } catch (\Exception $e) {
                // Si falla, cargar sin Prioridad (Observaciones siempre existe)
                $ordenes = UrdProgramaUrdido::select([
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'MaquinaId',
                    'Status',
                    'FechaProg',
                    'CreatedAt',
                    'Observaciones',
                ])
                ->whereIn('Status', ['Programado', 'En Proceso'])
                ->whereNotNull('MaquinaId')
                ->get();
            }

            // Si existe el campo Prioridad, ordenar por él y verificar si necesita inicialización
            if ($tienePrioridad) {
                // Inicializar Prioridad si no existe para algunas órdenes
                $ordenesSinPrioridad = $ordenes->filter(function ($orden) {
                    return empty($orden->Prioridad);
                });

                if ($ordenesSinPrioridad->count() > 0) {
                    try {
                        // Obtener la máxima prioridad existente
                        $maxPrioridad = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso'])
                            ->whereNotNull('MaquinaId')
                            ->whereNotNull('Prioridad')
                            ->max('Prioridad') ?? 0;

                        // Asignar prioridades a las que no tienen
                        foreach ($ordenesSinPrioridad as $orden) {
                            $maxPrioridad++;
                            DB::connection('sqlsrv')
                                ->table('UrdProgramaUrdido')
                                ->where('Id', $orden->Id)
                                ->update(['Prioridad' => $maxPrioridad]);
                        }

                        // Recargar las órdenes con Prioridad y Observaciones
                        $ordenes = UrdProgramaUrdido::select([
                            'Id',
                            'Folio',
                            'RizoPie as tipo',
                            'Cuenta',
                            'Calibre',
                            'Metros',
                            'MaquinaId',
                            'Status',
                            'FechaProg',
                            'Prioridad',
                            'CreatedAt',
                            'Observaciones',
                        ])
                        ->whereIn('Status', ['Programado', 'En Proceso'])
                        ->whereNotNull('MaquinaId')
                        ->get();
                    } catch (\Exception $e) {
                        // Si falla al actualizar Prioridad, continuar sin inicialización
                        $tienePrioridad = false;
                    }
                }

                // Ordenar todas las órdenes por Prioridad (global)
                $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                    return isset($orden->Prioridad) && !empty($orden->Prioridad) ? $orden->Prioridad : 999999;
                })->values();
            } else {
                // Si no existe Prioridad, ordenar por CreatedAt
                $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                    return $orden->CreatedAt ? $orden->CreatedAt->timestamp : 999999999;
                })->values();
            }

            // Agrupar por MC Coy (extraído de MaquinaId)
            $ordenesPorMcCoy = [
                1 => [],
                2 => [],
                3 => [],
                4 => [],
            ];

            foreach ($ordenesOrdenadas as $orden) {
                $mcCoy = $this->extractMcCoyNumber($orden->MaquinaId);

                // Solo incluir si el MC Coy es válido (1-4)
                if ($mcCoy !== null && isset($ordenesPorMcCoy[$mcCoy])) {
                    // Obtener índice dentro del grupo para mostrar prioridad relativa
                    $indexEnGrupo = count($ordenesPorMcCoy[$mcCoy]) + 1;

                    $ordenesPorMcCoy[$mcCoy][] = [
                        'id' => $orden->Id,
                        'folio' => $orden->Folio,
                        'tipo' => $orden->tipo,
                        'cuenta' => $orden->Cuenta,
                        'calibre' => $orden->Calibre,
                        'metros' => $orden->Metros,
                        'mccoy' => $mcCoy,
                        'maquina_id' => $orden->MaquinaId ?? null,
                        'status' => $orden->Status ?? null,
                        'observaciones' => $orden->Observaciones ?? '',
                        'prioridad' => ($tienePrioridad && isset($orden->Prioridad)) ? ($orden->Prioridad ?? 999999) : $indexEnGrupo,
                        'created_at' => $orden->CreatedAt ? $orden->CreatedAt->format('Y-m-d H:i:s') : null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $ordenesPorMcCoy,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subir prioridad de una orden
     * Intercambia CreatedAt con la orden inmediatamente anterior en el mismo MC Coy
     * (la orden con CreatedAt más reciente se mueve antes)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function subirPrioridad(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->id);
            $mcCoy = $this->extractMcCoyNumber($orden->MaquinaId);

            if ($mcCoy === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo determinar el MC Coy de la orden',
                ], 400);
            }

            // Obtener todas las órdenes del mismo MC Coy ordenadas por CreatedAt
            $ordenesMcCoy = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso'])
                ->whereNotNull('MaquinaId')
                ->get()
                ->filter(function ($item) use ($mcCoy) {
                    return $this->extractMcCoyNumber($item->MaquinaId) === $mcCoy;
                })
                ->sortBy('CreatedAt')
                ->values();

            // Encontrar la posición actual de la orden
            $posicionActual = $ordenesMcCoy->search(function ($item) use ($orden) {
                return $item->Id === $orden->Id;
            });

            if ($posicionActual === false || $posicionActual === 0) {
                // Ya está en la primera posición o no se encontró
                return response()->json([
                    'success' => false,
                    'error' => 'La orden ya está en la primera posición',
                ], 400);
            }

            // Obtener la orden anterior
            $ordenAnterior = $ordenesMcCoy->get($posicionActual - 1);

            if (!$ordenAnterior) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontró la orden anterior',
                ], 400);
            }

            DB::beginTransaction();

            // Intercambiar CreatedAt (aumentar la prioridad = CreatedAt más antiguo)
            $createdAtTemp = $orden->CreatedAt;
            $orden->CreatedAt = $ordenAnterior->CreatedAt;
            $ordenAnterior->CreatedAt = $createdAtTemp;

            $orden->save();
            $ordenAnterior->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prioridad actualizada correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: ' . $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error al subir prioridad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar si hay órdenes con status "En Proceso" por máquina (MC Coy)
     * Retorna true si hay al menos una orden con status "En Proceso" en la misma máquina
     * (excluyendo la orden actual si se proporciona)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verificarOrdenEnProceso(Request $request): JsonResponse
    {
        try {
            $ordenIdExcluir = $request->query('excluir_id');
            $maquinaId = $request->query('maquina_id');

            // Si no se proporciona maquina_id, permitir (no bloquear)
            // Esto permite que funcione aunque no se pueda determinar la máquina
            if (empty($maquinaId)) {
                return response()->json([
                    'success' => true,
                    'tieneOrdenEnProceso' => false,
                    'cantidad' => 0,
                    'mensaje' => 'No se proporcionó información de máquina. Se permite cargar la orden.',
                ]);
            }

            // Verificar por máquina (MC Coy)
            $mcCoy = $this->extractMcCoyNumber($maquinaId);

            // Si no se puede determinar el MC Coy, permitir (no bloquear)
            if ($mcCoy === null) {
                return response()->json([
                    'success' => true,
                    'tieneOrdenEnProceso' => false,
                    'cantidad' => 0,
                    'mensaje' => 'No se pudo determinar el MC Coy de la máquina. Se permite cargar la orden.',
                ]);
            }

            // Obtener todas las órdenes en proceso y filtrar por MC Coy
            $ordenesEnProceso = UrdProgramaUrdido::where('Status', 'En Proceso')
                ->whereNotNull('MaquinaId')
                ->get()
                ->filter(function ($orden) use ($mcCoy, $ordenIdExcluir) {
                    $ordenMcCoy = $this->extractMcCoyNumber($orden->MaquinaId);
                    if ($ordenIdExcluir && $orden->Id == $ordenIdExcluir) {
                        return false;
                    }
                    return $ordenMcCoy === $mcCoy;
                });

            $cantidadEnProceso = $ordenesEnProceso->count();

            $nombreMaquina = $mcCoy == 4 ? 'Karl Mayer' : "MC Coy {$mcCoy}";

            // Permitir hasta 2 órdenes en proceso por máquina
            // Solo bloquear si ya hay 2 o más órdenes en proceso
            $limitePorMaquina = 2;
            $tieneOrdenEnProceso = $cantidadEnProceso >= $limitePorMaquina;

            return response()->json([
                'success' => true,
                'tieneOrdenEnProceso' => $tieneOrdenEnProceso,
                'cantidad' => $cantidadEnProceso,
                'limite' => $limitePorMaquina,
                'maquina' => $nombreMaquina,
                'mensaje' => $tieneOrdenEnProceso
                    ? "Ya existen {$limitePorMaquina} órdenes con status 'En Proceso' en {$nombreMaquina}. No se puede cargar otra orden en esta máquina hasta finalizar alguna de las actuales."
                    : "Hay {$cantidadEnProceso} orden(es) en proceso en {$nombreMaquina}. Puede cargar hasta {$limitePorMaquina} órdenes en proceso por máquina.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al verificar órdenes en proceso: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bajar prioridad de una orden
     * Intercambia CreatedAt con la orden inmediatamente posterior en el mismo MC Coy
     * (la orden con CreatedAt más antiguo se mueve después)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bajarPrioridad(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->id);
            $mcCoy = $this->extractMcCoyNumber($orden->MaquinaId);

            if ($mcCoy === null) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo determinar el MC Coy de la orden',
                ], 400);
            }

            // Obtener todas las órdenes del mismo MC Coy ordenadas por CreatedAt
            $ordenesMcCoy = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso'])
                ->whereNotNull('MaquinaId')
                ->get()
                ->filter(function ($item) use ($mcCoy) {
                    return $this->extractMcCoyNumber($item->MaquinaId) === $mcCoy;
                })
                ->sortBy('CreatedAt')
                ->values();

            // Encontrar la posición actual de la orden
            $posicionActual = $ordenesMcCoy->search(function ($item) use ($orden) {
                return $item->Id === $orden->Id;
            });

            if ($posicionActual === false || $posicionActual === $ordenesMcCoy->count() - 1) {
                // Ya está en la última posición o no se encontró
                return response()->json([
                    'success' => false,
                    'error' => 'La orden ya está en la última posición',
                ], 400);
            }

            // Obtener la orden posterior
            $ordenPosterior = $ordenesMcCoy->get($posicionActual + 1);

            if (!$ordenPosterior) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontró la orden posterior',
                ], 400);
            }

            DB::beginTransaction();

            // Intercambiar CreatedAt (disminuir la prioridad = CreatedAt más reciente)
            $createdAtTemp = $orden->CreatedAt;
            $orden->CreatedAt = $ordenPosterior->CreatedAt;
            $ordenPosterior->CreatedAt = $createdAtTemp;

            $orden->save();
            $ordenPosterior->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prioridad actualizada correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: ' . $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error al bajar prioridad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Intercambiar prioridad entre dos órdenes mediante drag and drop
     * Intercambia el campo Prioridad (único globalmente, sin importar MC Coy)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function intercambiarPrioridad(Request $request): JsonResponse
    {
        try {
            if (!$this->usuarioPuedeEditar()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado',
                ], 200);
            }

            $request->validate([
                'source_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'target_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            ]);

            $ordenSource = UrdProgramaUrdido::findOrFail($request->source_id);
            $ordenTarget = UrdProgramaUrdido::findOrFail($request->target_id);

            // Asegurar que ambas órdenes tengan Prioridad
            if (empty($ordenSource->Prioridad)) {
                $maxPrioridad = UrdProgramaUrdido::max('Prioridad') ?? 0;
                $ordenSource->Prioridad = $maxPrioridad + 1;
            }

            if (empty($ordenTarget->Prioridad)) {
                $maxPrioridad = UrdProgramaUrdido::max('Prioridad') ?? 0;
                $ordenTarget->Prioridad = $maxPrioridad + 1;
            }

            DB::beginTransaction();

            // Intercambiar Prioridad (único global)
            $prioridadTemp = $ordenSource->Prioridad;
            $ordenSource->Prioridad = $ordenTarget->Prioridad;
            $ordenTarget->Prioridad = $prioridadTemp;

            $ordenSource->save();
            $ordenTarget->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prioridad actualizada correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: ' . $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error al intercambiar prioridad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar observaciones de una orden
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function guardarObservaciones(Request $request): JsonResponse
    {
        try {
            if (!$this->usuarioPuedeEditar()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado',
                ], 200);
            }

            $request->validate([
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'observaciones' => 'nullable|string|max:1000',
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->id);
            $orden->Observaciones = $request->observaciones ?? '';
            $orden->save();

            return response()->json([
                'success' => true,
                'message' => 'Observaciones guardadas correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: ' . $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al guardar observaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recalcular prioridades consecutivas para todas las órdenes activas
     * Excluye órdenes canceladas
     */
    private function recalcularPrioridades(): void
    {
        try {
            // Obtener todas las órdenes activas
            $ordenes = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso'])
                ->whereNotNull('MaquinaId')
                ->get();

            // Ordenar: primero por prioridad (las que tienen), luego por CreatedAt
            $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                $prioridad = isset($orden->Prioridad) && !empty($orden->Prioridad) ? $orden->Prioridad : 999999;
                $fecha = $orden->CreatedAt ? $orden->CreatedAt->timestamp : 999999999;
                return [$prioridad, $fecha];
            })->values();

            DB::beginTransaction();

            // Asignar prioridades consecutivas empezando desde 1
            foreach ($ordenesOrdenadas as $index => $orden) {
                $nuevaPrioridad = $index + 1;
                DB::connection('sqlsrv')
                    ->table('UrdProgramaUrdido')
                    ->where('Id', $orden->Id)
                    ->update(['Prioridad' => $nuevaPrioridad]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al recalcular prioridades: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar el status de una orden
     * Si se cancela, se elimina la prioridad y se recalculan todas las demás
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarStatus(Request $request): JsonResponse
    {
        try {
            if (!$this->usuarioPuedeEditar()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado',
                ], 403);
            }

            $request->validate([
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'status' => ['required', 'string', Rule::in(['Programado', 'En Proceso', 'Cancelado'])],
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->id);
            $nuevoStatus = $request->status;
            $statusAnterior = $orden->Status;

            if ($orden->Status === $nuevoStatus) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status sin cambios',
                ]);
            }

            if ($nuevoStatus === 'En Proceso') {
                $mcCoy = $this->extractMcCoyNumber($orden->MaquinaId);
                $limitePorMaquina = 2;

                if ($mcCoy !== null) {
                    $cantidadEnProceso = UrdProgramaUrdido::where('Status', 'En Proceso')
                        ->whereNotNull('MaquinaId')
                        ->where('Id', '!=', $orden->Id)
                        ->get()
                        ->filter(function ($item) use ($mcCoy) {
                            return $this->extractMcCoyNumber($item->MaquinaId) === $mcCoy;
                        })
                        ->count();

                    if ($cantidadEnProceso >= $limitePorMaquina) {
                        $nombreMaquina = $mcCoy === 4 ? 'Karl Mayer' : "MC Coy {$mcCoy}";

                        return response()->json([
                            'success' => false,
                            'error' => "Ya existen {$limitePorMaquina} ordenes en proceso en {$nombreMaquina}.",
                        ], 422);
                    }
                }
            }

            DB::beginTransaction();

            $orden->Status = $nuevoStatus;

            // Si se cancela, eliminar prioridad y recalcular todas las demás
            if ($nuevoStatus === 'Cancelado') {
                $orden->Prioridad = null;
                $orden->save();

                // Eliminar registros de producción de urdido cuando se cancela
                try {
                    $registrosEliminados = UrdProduccionUrdido::where('Folio', $orden->Folio)->delete();

                } catch (\Throwable $e) {
                    // No lanzar excepción, solo registrar el error
                }

                // También cancelar la orden correspondiente en engomado si existe
                try {
                    $ordenEngomado = EngProgramaEngomado::where('Folio', $orden->Folio)->first();
                    if ($ordenEngomado && !in_array($ordenEngomado->Status, ['Cancelado', 'Finalizado'])) {
                        $ordenEngomado->Status = 'Cancelado';
                        $ordenEngomado->Prioridad = null;
                        $ordenEngomado->save();

                        // Eliminar registros de producción de engomado cuando se cancela
                        try {

                            $registrosEliminados = \App\Models\Engomado\EngProduccionEngomado::where('Folio', $orden->Folio)->delete();
                        } catch (\Throwable $e) {
                            // No lanzar excepción, solo registrar el error
                        }

                    }
                } catch (\Throwable $e) {
                    // No lanzar excepción, solo registrar el error
                }

                // Recalcular prioridades de todas las órdenes activas
                $this->recalcularPrioridades();
            }
            // Si se reactiva desde cancelado, asignar nueva prioridad al final
            elseif ($statusAnterior === 'Cancelado' && in_array($nuevoStatus, ['Programado', 'En Proceso'])) {
                $maxPrioridad = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso'])
                    ->whereNotNull('MaquinaId')
                    ->whereNotNull('Prioridad')
                    ->max('Prioridad') ?? 0;

                $orden->Prioridad = $maxPrioridad + 1;
                $orden->save();
            } else {
                $orden->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Status actualizado correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: ' . $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todas las órdenes sin agrupar por MC Coy
     * Solo órdenes con status "En Proceso" o "Programado"
     * Si no tienen prioridad, se asignan automáticamente
     *
     * @return JsonResponse
     */
    public function getTodasOrdenes(): JsonResponse
    {
        try {
            // Intentar cargar con Prioridad
            $tienePrioridad = false;
            try {
                $ordenes = UrdProgramaUrdido::select([
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'MaquinaId',
                    'Status',
                    'Prioridad',
                    'CreatedAt',
                ])
                ->whereIn('Status', ['Programado', 'En Proceso'])
                ->get();

                $tienePrioridad = true;
            } catch (\Exception $e) {
                // Si falla, cargar sin Prioridad
                $ordenes = UrdProgramaUrdido::select([
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'MaquinaId',
                    'Status',
                    'CreatedAt',
                ])
                ->whereIn('Status', ['Programado', 'En Proceso'])
                ->get();
            }

            // Inicializar prioridades si no existen
            if ($tienePrioridad) {
                $ordenesSinPrioridad = $ordenes->filter(function ($orden) {
                    return empty($orden->Prioridad);
                });

                if ($ordenesSinPrioridad->count() > 0) {
                    try {
                        $maxPrioridad = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso'])
                            ->whereNotNull('Prioridad')
                            ->max('Prioridad') ?? 0;

                        foreach ($ordenesSinPrioridad as $orden) {
                            $maxPrioridad++;
                            DB::connection('sqlsrv')
                                ->table('UrdProgramaUrdido')
                                ->where('Id', $orden->Id)
                                ->update(['Prioridad' => $maxPrioridad]);
                        }

                        // Recargar las órdenes con Prioridad
                        $ordenes = UrdProgramaUrdido::select([
                            'Id',
                            'Folio',
                            'RizoPie as tipo',
                            'Cuenta',
                            'Calibre',
                            'Metros',
                            'MaquinaId',
                            'Status',
                            'Prioridad',
                            'CreatedAt',
                        ])
                        ->whereIn('Status', ['Programado', 'En Proceso'])
                        ->get();
                    } catch (\Exception $e) {
                        $tienePrioridad = false;
                    }
                }
            }

            // Ordenar por Prioridad si existe, sino por CreatedAt
            if ($tienePrioridad) {
                $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                    return isset($orden->Prioridad) && !empty($orden->Prioridad) ? $orden->Prioridad : 999999;
                })->values();
            } else {
                $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                    return $orden->CreatedAt ? $orden->CreatedAt->timestamp : 999999999;
                })->values();
            }

            // Convertir a array con formato para el frontend
            $ordenesArray = $ordenesOrdenadas->map(function ($orden, $index) use ($tienePrioridad) {
                return [
                    'id' => $orden->Id,
                    'folio' => $orden->Folio,
                    'tipo' => $orden->tipo,
                    'cuenta' => $orden->Cuenta,
                    'calibre' => $orden->Calibre,
                    'metros' => $orden->Metros,
                    'maquina' => $orden->MaquinaId ?? '',
                    'status' => $orden->Status ?? null,
                    'prioridad' => ($tienePrioridad && isset($orden->Prioridad)) ? ($orden->Prioridad ?? ($index + 1)) : ($index + 1),
                    'created_at' => $orden->CreatedAt ? $orden->CreatedAt->format('Y-m-d H:i:s') : null,
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => $ordenesArray,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar prioridades en lote
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function actualizarPrioridades(Request $request): JsonResponse
    {
        try {
            if (!$this->usuarioPuedeEditar()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado',
                ], 200);
            }

            $request->validate([
                'prioridades' => 'required|array',
                'prioridades.*.id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'prioridades.*.prioridad' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();

            foreach ($request->prioridades as $item) {
                $orden = UrdProgramaUrdido::findOrFail($item['id']);
                $orden->Prioridad = $item['prioridad'];
                $orden->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prioridades actualizadas correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: ' . $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar prioridades: ' . $e->getMessage(),
            ], 500);
        }
    }
}
