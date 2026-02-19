<?php

namespace App\Http\Controllers\Engomado\ProgramaEngomado;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\Sistema\SYSRoles;
use App\Models\Sistema\SYSUsuariosRoles;

class ProgramarEngomadoController extends Controller
{
    private const MODULO_PROGRAMA_ENGOMADO = 'Programa Engomado';

    /**
     * Verifica si el usuario puede editar (mismo criterio que el botón Editar del navbar:
     * permiso "modificar" y acceso activo al módulo "Programa Engomado").
     */
    private function usuarioPuedeEditar(): bool
    {
        if (!Auth::id()) {
            return false;
        }

        if (!function_exists('userCan')) {
            return false;
        }

        $rol = SYSRoles::where('modulo', self::MODULO_PROGRAMA_ENGOMADO)->first();
        if (!$rol) {
            return false;
        }

        $tienePermisoModificar = userCan('modificar', self::MODULO_PROGRAMA_ENGOMADO);
        $permission = SYSUsuariosRoles::where('idusuario', Auth::id())
            ->where('idrol', $rol->idrol)
            ->first();
        $tieneAcceso = $permission && isset($permission->acceso) && (int) $permission->acceso === 1;

        return $tienePermisoModificar && $tieneAcceso;
    }

    /**
     * Mostrar la vista de programar engomado
     */
    public function index()
    {
        return view('modulos.engomado.programar-engomado');
    }

    /**
     * Mostrar ordenes para reimpresion y edicion (todos los status, con filtros)
     */
    public function reimpresionFinalizadas(Request $request)
    {
        $busqueda = trim((string) $request->query('q', ''));
        $folio = trim((string) $request->query('folio', ''));
        $maquina = trim((string) $request->query('maquina', ''));
        $tipo = trim((string) $request->query('tipo', ''));
        $status = trim((string) $request->query('status', ''));

        $query = EngProgramaEngomado::select([
            'Id',
            'Folio',
            'RizoPie',
            'Cuenta',
            'Calibre',
            'Metros',
            'MaquinaEng',
            'FechaProg',
            'Status',
        ]);

        if ($folio !== '') {
            $query->where('Folio', 'like', "%{$folio}%");
        }

        if ($maquina !== '') {
            $query->where('MaquinaEng', $maquina);
        }

        if ($tipo !== '') {
            $query->where('RizoPie', $tipo);
        }

        if ($status !== '') {
            $query->where('Status', $status);
        }

        if ($busqueda !== '' && $folio === '' && $maquina === '' && $tipo === '' && $status === '') {
            $query->where(function ($sub) use ($busqueda) {
                $sub->where('Folio', 'like', "%{$busqueda}%")
                    ->orWhere('Cuenta', 'like', "%{$busqueda}%")
                    ->orWhere('MaquinaEng', 'like', "%{$busqueda}%");
            });
        }

        $ordenes = $query
            ->orderBy('FechaProg', 'desc')
            ->orderBy('Id', 'desc')
            ->get();

        return view('modulos.engomado.reimpresion-engomado', [
            'ordenes' => $ordenes,
            'busqueda' => $busqueda,
        ]);
    }

    /**
     * Extraer número de tabla del campo MaquinaEng
     * Busca patrones como "WestPoint 2", "West Point 2", "Tabla 1", "Izquierda", "Derecha", "1", "2", etc.
     *
     * @param string|null $maquinaEng
     * @return int|null
     */
    private function extractTablaNumber(?string $maquinaEng): ?int
    {
        if (empty($maquinaEng)) {
            return null;
        }

        $maquinaEng = trim($maquinaEng);

        // Buscar "WestPoint 2" o "West Point 2" o "WestPoint2" (case insensitive)
        // WestPoint 2 -> tabla 1, WestPoint 3 -> tabla 2
        if (preg_match('/west\s*point\s*(\d+)/i', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            if ($numero === 2) return 1; // WestPoint 2 -> tabla 1
            if ($numero === 3) return 2; // WestPoint 3 -> tabla 2
            return null;
        }

        // Buscar "Tabla X" o "tabla X" (case insensitive)
        if (preg_match('/tabla\s*(\d+)/i', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            return ($numero >= 1 && $numero <= 2) ? $numero : null;
        }

        // Buscar "Izquierda" o "izquierda" -> tabla 1
        if (preg_match('/izquierda/i', $maquinaEng)) {
            return 1;
        }

        // Buscar "Derecha" o "derecha" -> tabla 2
        if (preg_match('/derecha/i', $maquinaEng)) {
            return 2;
        }

        // Buscar cualquier número al final del string (1 o 2)
        // Útil para casos como "Máquina 1", "Eng 2", etc.
        if (preg_match('/(\d+)\s*$/i', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            return ($numero >= 1 && $numero <= 2) ? $numero : null;
        }

        // Buscar solo números (1 o 2)
        if (preg_match('/^(\d+)$/', $maquinaEng, $matches)) {
            $numero = (int) $matches[1];
            return ($numero >= 1 && $numero <= 2) ? $numero : null;
        }

        return null;
    }

    /**
     * Obtener órdenes de engomado agrupadas por tabla (Izquierda/Derecha)
     * Extrae el número de tabla del campo MaquinaEng
     * Muestra todas las órdenes pero marca visualmente las que tienen status "Finalizado" en UrdProgramaUrdido
     * Ordena por Prioridad si existe, sino por FechaProg ascendente
     *
     * @return JsonResponse
     */
    public function getOrdenes(): JsonResponse
    {
        try {
            // Intentar cargar con Prioridad y Observaciones
            $tienePrioridad = false;
            try {
                $ordenes = EngProgramaEngomado::select([
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'MaquinaEng',
                    'Status',
                    'FechaProg',
                    'BomFormula',
                    'Prioridad',
                    'Observaciones',
                ])
                ->whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                ->whereNotNull('MaquinaEng')
                ->where('MaquinaEng', '!=', '')
                ->get();

                $tienePrioridad = true;
            } catch (\Exception $e) {
                // Si falla, cargar sin Prioridad
                $ordenes = EngProgramaEngomado::select([
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'MaquinaEng',
                    'Status',
                    'FechaProg',
                    'BomFormula',
                    'Observaciones',
                ])
                ->whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                ->whereNotNull('MaquinaEng')
                ->where('MaquinaEng', '!=', '')
                ->get();
            }

            // Cargar información de UrdProgramaUrdido para verificar status "Finalizado"
            $folios = $ordenes->pluck('Folio')->unique()->toArray();
            $urdidos = UrdProgramaUrdido::whereIn('Folio', $folios)
                ->select('Folio', 'Status')
                ->get()
                ->keyBy('Folio');

            // Inicializar Prioridad si no existe
            if ($tienePrioridad) {
                $ordenesSinPrioridad = $ordenes->filter(function ($orden) {
                    return empty($orden->Prioridad);
                });

                if ($ordenesSinPrioridad->count() > 0) {
                    try {
                        $maxPrioridad = EngProgramaEngomado::whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                            ->whereNotNull('MaquinaEng')
                            ->whereNotNull('Prioridad')
                            ->max('Prioridad') ?? 0;

                        foreach ($ordenesSinPrioridad as $orden) {
                            $maxPrioridad++;
                            DB::connection('sqlsrv')
                                ->table('EngProgramaEngomado')
                                ->where('Id', $orden->Id)
                                ->update(['Prioridad' => $maxPrioridad]);
                        }

                        // Recargar las órdenes con Prioridad
                        $ordenes = EngProgramaEngomado::select([
                            'Id',
                            'Folio',
                            'RizoPie as tipo',
                            'Cuenta',
                            'Calibre',
                            'Metros',
                            'MaquinaEng',
                            'Status',
                            'FechaProg',
                            'BomFormula',
                            'Prioridad',
                            'Observaciones',
                        ])
                        ->whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                        ->whereNotNull('MaquinaEng')
                        ->where('MaquinaEng', '!=', '')
                        ->get();
                    } catch (\Exception $e) {
                        $tienePrioridad = false;
                    }
                }
            }

            // Ordenar por Prioridad si existe, sino por FechaProg
            if ($tienePrioridad) {
                $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                    return isset($orden->Prioridad) && !empty($orden->Prioridad) ? $orden->Prioridad : 999999;
                })->values();
            } else {
                $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                    return $orden->FechaProg ? strtotime($orden->FechaProg) : 999999999;
                })->values();
            }

            // Agrupar por tabla (extraído de MaquinaEng)
            $ordenesPorTabla = [
                1 => [], // West Point 2
                2 => [], // West Point 3
            ];

            foreach ($ordenesOrdenadas as $orden) {
                $tabla = $this->extractTablaNumber($orden->MaquinaEng);

                // Solo incluir si la tabla es válida (1-2)
                if ($tabla !== null && isset($ordenesPorTabla[$tabla])) {
                    // Verificar si la orden de urdido está finalizada
                    $urdido = $urdidos->get($orden->Folio);
                    $urdidoFinalizado = $urdido && $urdido->Status === 'Finalizado';

                    $ordenesPorTabla[$tabla][] = [
                        'id' => $orden->Id,
                        'folio' => $orden->Folio,
                        'tipo' => $orden->tipo,
                        'cuenta' => $orden->Cuenta,
                        'calibre' => $orden->Calibre,
                        'metros' => $orden->Metros,
                        'maquina_eng' => $orden->MaquinaEng ?? null,
                        'tabla' => $tabla,
                        'status' => $orden->Status ?? null,
                        'formula' => $orden->BomFormula ?? null,
                        'observaciones' => $orden->Observaciones ?? '',
                        'prioridad' => ($tienePrioridad && isset($orden->Prioridad)) ? ($orden->Prioridad ?? 999999) : null,
                        'urdido_finalizado' => $urdidoFinalizado, // Flag para marcar visualmente
                    ];
                }
            }

            // Ordenar cada grupo por prioridad si existe, sino mantener el orden
            foreach ($ordenesPorTabla as $key => $grupo) {
                if ($tienePrioridad) {
                    usort($ordenesPorTabla[$key], function ($a, $b) {
                        $prioridadA = $a['prioridad'] ?? 999999;
                        $prioridadB = $b['prioridad'] ?? 999999;
                        return $prioridadA - $prioridadB;
                    });
                }
            }

            return response()->json([
                'success' => true,
                'data' => $ordenesPorTabla,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar si hay órdenes con status "En Proceso" por tabla (máquina)
     * Retorna true si hay 2 o más órdenes con status "En Proceso" en la misma tabla
     * (excluyendo la orden actual si se proporciona)
     * También verifica que la orden de urdido esté finalizada antes de permitir poner en proceso
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verificarOrdenEnProceso(Request $request): JsonResponse
    {
        try {
            $ordenIdExcluir = $request->query('excluir_id');
            $maquinaEng = $request->query('maquina_eng');
            $folio = $request->query('folio');

            // Verificar primero si la orden de urdido está finalizada
            if (!empty($folio)) {
                $urdido = UrdProgramaUrdido::where('Folio', $folio)->first();
                if ($urdido && $urdido->Status !== 'Finalizado') {
                    return response()->json([
                        'success' => true,
                        'tieneOrdenEnProceso' => true,
                        'cantidad' => 0,
                        'limite' => 2,
                        'tabla' => '',
                        'mensaje' => "No se puede cargar la orden. La orden de urdido debe tener status 'Finalizado' antes de poder ponerla en proceso en engomado.",
                        'urdidoNoFinalizado' => true,
                    ]);
                }
            }

            // Si no se proporciona maquina_eng, permitir (no bloquear)
            // Esto permite que funcione aunque no se pueda determinar la máquina
            if (empty($maquinaEng)) {
                return response()->json([
                    'success' => true,
                    'tieneOrdenEnProceso' => false,
                    'cantidad' => 0,
                    'mensaje' => 'No se proporcionó información de máquina. Se permite cargar la orden.',
                ]);
            }

            // Verificar por tabla (extraída de MaquinaEng)
            $tabla = $this->extractTablaNumber($maquinaEng);

            // Si no se puede determinar la tabla, permitir (no bloquear)
            if ($tabla === null) {
                return response()->json([
                    'success' => true,
                    'tieneOrdenEnProceso' => false,
                    'cantidad' => 0,
                    'mensaje' => 'No se pudo determinar la tabla de la máquina. Se permite cargar la orden.',
                ]);
            }

            // Obtener todas las órdenes en proceso y filtrar por tabla
            $ordenesEnProceso = EngProgramaEngomado::where('Status', 'En Proceso')
                ->whereNotNull('MaquinaEng')
                ->get()
                ->filter(function ($orden) use ($tabla, $ordenIdExcluir) {
                    $ordenTabla = $this->extractTablaNumber($orden->MaquinaEng);
                    if ($ordenIdExcluir && $orden->Id == $ordenIdExcluir) {
                        return false;
                    }
                    return $ordenTabla === $tabla;
                });

            $cantidadEnProceso = $ordenesEnProceso->count();

            $nombreTabla = $tabla == 1 ? 'West Point 2' : 'West Point 3';

            // Restricción eliminada - se permite cualquier cantidad de órdenes en proceso
            return response()->json([
                'success' => true,
                'tieneOrdenEnProceso' => false, // Siempre false para permitir cualquier cantidad
                'cantidad' => $cantidadEnProceso,
                'limite' => 0, // Sin límite
                'tabla' => $nombreTabla,
                'mensaje' => "Hay {$cantidadEnProceso} orden(es) en proceso en {$nombreTabla}.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al verificar órdenes en proceso: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Intercambiar prioridad entre dos órdenes mediante drag and drop
     * Intercambia el campo Prioridad (único globalmente, sin importar tabla)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function intercambiarPrioridad(Request $request): JsonResponse
    {
        try {
            // Habilitado para todos los usuarios
            $request->validate([
                'source_id' => 'required|integer|exists:EngProgramaEngomado,Id',
                'target_id' => 'required|integer|exists:EngProgramaEngomado,Id',
            ]);

            $ordenSource = EngProgramaEngomado::findOrFail($request->source_id);
            $ordenTarget = EngProgramaEngomado::findOrFail($request->target_id);

            // Asegurar que ambas órdenes tengan Prioridad
            if (empty($ordenSource->Prioridad)) {
                $maxPrioridad = EngProgramaEngomado::max('Prioridad') ?? 0;
                $ordenSource->Prioridad = $maxPrioridad + 1;
            }

            if (empty($ordenTarget->Prioridad)) {
                $maxPrioridad = EngProgramaEngomado::max('Prioridad') ?? 0;
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
                ], 403);
            }

            $request->validate([
                'id' => 'required|integer|exists:EngProgramaEngomado,Id',
                'observaciones' => 'nullable|string|max:60',
            ]);

            $orden = EngProgramaEngomado::findOrFail($request->id);
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
     * Recalcular prioridades consecutivas para todas las órdenes activas de engomado
     */
    private function recalcularPrioridadesEngomado(): void
    {
        try {
            $ordenes = EngProgramaEngomado::whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                ->whereNotNull('MaquinaEng')
                ->where('MaquinaEng', '!=', '')
                ->get();

            $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                $prioridad = isset($orden->Prioridad) && !empty($orden->Prioridad) ? $orden->Prioridad : 999999;
                $fecha = $orden->FechaProg ? (is_object($orden->FechaProg) ? $orden->FechaProg->format('Y-m-d H:i:s') : $orden->FechaProg) : '9999-12-31';
                return [$prioridad, $fecha];
            })->values();

            DB::beginTransaction();

            foreach ($ordenesOrdenadas as $index => $orden) {
                $nuevaPrioridad = $index + 1;
                DB::connection('sqlsrv')
                    ->table('EngProgramaEngomado')
                    ->where('Id', $orden->Id)
                    ->update(['Prioridad' => $nuevaPrioridad]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error al recalcular prioridades engomado: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar el status de una orden de engomado
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
                'id' => 'required|integer|exists:EngProgramaEngomado,Id',
                'status' => ['required', 'string', Rule::in(['Programado', 'En Proceso', 'Parcial', 'Cancelado'])],
            ]);

            $orden = EngProgramaEngomado::findOrFail($request->id);
            $nuevoStatus = $request->status;
            $statusAnterior = $orden->Status;

            if ($orden->Status === $nuevoStatus) {
                return response()->json([
                    'success' => true,
                    'message' => 'Status sin cambios',
                ]);
            }

            DB::beginTransaction();

            $orden->Status = $nuevoStatus;

            if ($nuevoStatus === 'Cancelado') {
                $orden->Prioridad = null;
                $orden->save();

                try {
                    EngProduccionEngomado::where('Folio', $orden->Folio)->delete();
                } catch (\Throwable $e) {
                    // No lanzar, solo registrar
                }

                $this->recalcularPrioridadesEngomado();
            } elseif ($statusAnterior === 'Cancelado' && in_array($nuevoStatus, ['Programado', 'En Proceso', 'Parcial'])) {
                $maxPrioridad = EngProgramaEngomado::whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                    ->whereNotNull('MaquinaEng')
                    ->where('MaquinaEng', '!=', '')
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
     * Obtener todas las órdenes sin agrupar por tabla
     * Solo órdenes con status "En Proceso", "Programado" o "Cancelado"
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
                $ordenes = EngProgramaEngomado::select([
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'MaquinaEng',
                    'Status',
                    'Prioridad',
                    'FechaProg',
                ])
                ->whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                ->whereNotNull('MaquinaEng')
                ->where('MaquinaEng', '!=', '')
                ->get();

                $tienePrioridad = true;
            } catch (\Exception $e) {
                // Si falla, cargar sin Prioridad
                $ordenes = EngProgramaEngomado::select([
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'MaquinaEng',
                    'Status',
                    'FechaProg',
                ])
                ->whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                ->whereNotNull('MaquinaEng')
                ->where('MaquinaEng', '!=', '')
                ->get();
            }

            // Inicializar prioridades si no existen
            if ($tienePrioridad) {
                $ordenesSinPrioridad = $ordenes->filter(function ($orden) {
                    return empty($orden->Prioridad);
                });

                if ($ordenesSinPrioridad->count() > 0) {
                    try {
                        $maxPrioridad = EngProgramaEngomado::whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                            ->whereNotNull('Prioridad')
                            ->max('Prioridad') ?? 0;

                        foreach ($ordenesSinPrioridad as $orden) {
                            $maxPrioridad++;
                            DB::connection('sqlsrv')
                                ->table('EngProgramaEngomado')
                                ->where('Id', $orden->Id)
                                ->update(['Prioridad' => $maxPrioridad]);
                        }

                        // Recargar las órdenes con Prioridad
                        $ordenes = EngProgramaEngomado::select([
                            'Id',
                            'Folio',
                            'RizoPie as tipo',
                            'Cuenta',
                            'Calibre',
                            'Metros',
                            'MaquinaEng',
                            'Status',
                            'Prioridad',
                            'FechaProg',
                        ])
                        ->whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
                        ->whereNotNull('MaquinaEng')
                        ->where('MaquinaEng', '!=', '')
                        ->get();
                    } catch (\Exception $e) {
                        $tienePrioridad = false;
                    }
                }
            }

            // Ordenar por Prioridad si existe, sino por FechaProg
            if ($tienePrioridad) {
                $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                    return isset($orden->Prioridad) && !empty($orden->Prioridad) ? $orden->Prioridad : 999999;
                })->values();
            } else {
                $ordenesOrdenadas = $ordenes->sortBy(function ($orden) {
                    return $orden->FechaProg ? strtotime($orden->FechaProg) : 999999999;
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
                    'tabla' => $orden->MaquinaEng ?? '',
                    'status' => $orden->Status ?? null,
                    'prioridad' => ($tienePrioridad && isset($orden->Prioridad)) ? ($orden->Prioridad ?? ($index + 1)) : ($index + 1),
                    'fecha_prog' => $orden->FechaProg ? $orden->FechaProg->format('Y-m-d') : null,
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
            // Habilitado para todos los usuarios
            $request->validate([
                'prioridades' => 'required|array',
                'prioridades.*.id' => 'required|integer|exists:EngProgramaEngomado,Id',
                'prioridades.*.prioridad' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();

            foreach ($request->prioridades as $item) {
                $orden = EngProgramaEngomado::findOrFail($item['id']);
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
