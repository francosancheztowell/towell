<?php

namespace App\Http\Controllers\Urdido\ProgramaUrdido;

use App\Http\Controllers\Controller;
use App\Models\UrdProgramaUrdido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class ProgramarUrdidoController extends Controller
{
    /**
     * Mostrar la vista de programar urdido
     */
    public function index()
    {
        return view('modulos.urdido.programar-urdido');
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
     * Ordena por CreatedAt ascendente (más antiguas primero) dentro de cada grupo
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
     * Verificar si hay órdenes con status "En Proceso"
     * Retorna true si hay al menos una orden con status "En Proceso" (excluyendo la orden actual si se proporciona)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verificarOrdenEnProceso(Request $request): JsonResponse
    {
        try {
            $ordenIdExcluir = $request->query('excluir_id');

            $query = UrdProgramaUrdido::where('Status', 'En Proceso');

            if ($ordenIdExcluir) {
                $query->where('Id', '!=', $ordenIdExcluir);
            }

            $cantidadEnProceso = $query->count();

            return response()->json([
                'success' => true,
                'tieneOrdenEnProceso' => $cantidadEnProceso > 0,
                'cantidad' => $cantidadEnProceso,
                'mensaje' => $cantidadEnProceso > 0
                    ? "Ya existe una orden con status 'En Proceso'. No se puede cargar otra orden hasta finalizar la actual."
                    : 'No hay órdenes en proceso',
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

