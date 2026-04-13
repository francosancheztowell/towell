<?php

namespace App\Http\Controllers\Engomado\ProgramaEngomado;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Services\Programas\ProgramaPrioridadService;
use App\Support\Programas\ProgramaConfig;
use App\Support\Programas\ProgramaRouteHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProgramarEngomadoController extends Controller
{
    public function __construct(private readonly ProgramaPrioridadService $prioridadService) {}

    /**
     * Verifica si el usuario puede editar: solo usuarios del área Supervisores.
     */
    private function usuarioPuedeEditar(): bool
    {
        $usuario = Auth::user();
        if (! $usuario) {
            return false;
        }

        $area = trim($usuario->area ?? '');

        return strcasecmp($area, 'Supervisores') === 0;
    }

    /**
     * Mostrar la vista de programar engomado
     */
    public function index()
    {
        return view('modulos.engomado.programar-engomado', [
            'canEdit' => $this->usuarioPuedeEditar(),
            'programaRoutes' => ProgramaRouteHelper::engomado(),
            'observacionesMaxLength' => ProgramaConfig::OBSERVACIONES_MAX_LENGTH,
        ]);
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
            'Fibra',
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
            if ($numero === 2) {
                return 1;
            } // WestPoint 2 -> tabla 1
            if ($numero === 3) {
                return 2;
            } // WestPoint 3 -> tabla 2

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

    private function activeOrdersQuery()
    {
        return EngProgramaEngomado::query()
            ->whereIn('Status', ProgramaConfig::ACTIVE_STATUSES)
            ->whereNotNull('MaquinaEng')
            ->where('MaquinaEng', '!=', '');
    }

    private function fechaProgFallback(object $orden): int
    {
        if ($orden->FechaProg instanceof \DateTimeInterface) {
            return $orden->FechaProg->getTimestamp();
        }

        return $orden->FechaProg ? strtotime((string) $orden->FechaProg) : PHP_INT_MAX;
    }

    /**
     * Obtener órdenes de engomado agrupadas por tabla (Izquierda/Derecha)
     * Extrae el número de tabla del campo MaquinaEng
     * Muestra todas las órdenes pero marca visualmente las que tienen status "Finalizado" en UrdProgramaUrdido
     * Ordena por Prioridad si existe, sino por FechaProg ascendente
     */
    public function getOrdenes(): JsonResponse
    {
        try {
            $payload = $this->prioridadService->loadRecordsWithOptionalPriority(
                EngProgramaEngomado::class,
                [
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
                    'InventSizeId',
                    'Fibra',
                ],
                fn ($query) => $query
                    ->whereIn('Status', ProgramaConfig::ACTIVE_STATUSES)
                    ->whereNotNull('MaquinaEng')
                    ->where('MaquinaEng', '!=', '')
            );

            $ordenes = $payload['records'];

            // Cargar información de UrdProgramaUrdido para verificar status "Finalizado"
            $folios = $ordenes->pluck('Folio')->unique()->toArray();
            $urdidos = UrdProgramaUrdido::whereIn('Folio', $folios)
                ->select('Folio', 'Status')
                ->get()
                ->keyBy('Folio');

            $ordenesOrdenadas = $this->prioridadService->sortRecords(
                $ordenes,
                fn ($orden) => $this->fechaProgFallback($orden)
            );

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
                        'cuenta_calibre' => $orden->InventSizeId ?? '',
                        'configuracion' => $orden->Fibra ?? '',
                        'metros' => $orden->Metros,
                        'maquina_eng' => $orden->MaquinaEng ?? null,
                        'tabla' => $tabla,
                        'status' => $orden->Status ?? null,
                        'formula' => $orden->BomFormula ?? null,
                        'observaciones' => $orden->Observaciones ?? '',
                        'prioridad' => $this->prioridadService->displayPriority($orden, count($ordenesPorTabla[$tabla])),
                        'urdido_finalizado' => $urdidoFinalizado,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $ordenesPorTabla,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener órdenes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar si hay órdenes con status "En Proceso" por tabla (máquina)
     * Retorna true si hay 2 o más órdenes con status "En Proceso" en la misma tabla
     * (excluyendo la orden actual si se proporciona)
     * También verifica que la orden de urdido esté finalizada antes de permitir poner en proceso
     */
    public function verificarOrdenEnProceso(Request $request): JsonResponse
    {
        try {
            $ordenIdExcluir = $request->query('excluir_id');
            $maquinaEng = $request->query('maquina_eng');
            $folio = $request->query('folio');

            // Verificar primero si la orden de urdido está finalizada
            if (! empty($folio)) {
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
                'error' => 'Error al verificar órdenes en proceso: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Intercambiar prioridad entre dos órdenes mediante drag and drop
     * Intercambia el campo Prioridad (único globalmente, sin importar tabla)
     */
    public function intercambiarPrioridad(Request $request): JsonResponse
    {
        try {
            // Habilitado para todos los usuarios
            $request->validate([
                'source_id' => 'required|integer|exists:EngProgramaEngomado,Id',
                'target_id' => 'required|integer|exists:EngProgramaEngomado,Id',
            ]);
            $this->prioridadService->swapPriorities(
                EngProgramaEngomado::class,
                (int) $request->source_id,
                (int) $request->target_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Prioridad actualizada correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => 'Error al intercambiar prioridad: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar observaciones de una orden
     */
    public function guardarObservaciones(Request $request): JsonResponse
    {
        try {
            if (! $this->usuarioPuedeEditar()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado',
                ], 403);
            }

            $request->validate([
                'id' => 'required|integer|exists:EngProgramaEngomado,Id',
                'observaciones' => 'nullable|string|max:'.ProgramaConfig::OBSERVACIONES_MAX_LENGTH,
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
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al guardar observaciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recalcular prioridades consecutivas para todas las órdenes activas de engomado
     */
    private function recalcularPrioridadesEngomado(): void
    {
        try {
            $this->prioridadService->recalculatePriorities(
                $this->activeOrdersQuery(),
                fn ($orden) => $this->fechaProgFallback($orden)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error al recalcular prioridades engomado: '.$e->getMessage());
        }
    }

    /**
     * Actualizar el status de una orden de engomado
     * Si se cancela, se elimina la prioridad y se recalculan todas las demás
     */
    public function actualizarStatus(Request $request): JsonResponse
    {
        try {
            if (! $this->usuarioPuedeEditar()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No autorizado',
                ], 403);
            }

            $request->validate([
                'id' => 'required|integer|exists:EngProgramaEngomado,Id',
                'status' => ['required', 'string', Rule::in(ProgramaConfig::STATUS_OPTIONS)],
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
            } elseif ($statusAnterior === 'Cancelado' && in_array($nuevoStatus, ProgramaConfig::ACTIVE_STATUSES, true)) {
                $orden->Prioridad = $this->prioridadService->nextPriority($this->activeOrdersQuery());
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
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar status: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todas las órdenes sin agrupar por tabla
     * Solo órdenes con status "En Proceso", "Programado" o "Cancelado"
     * Si no tienen prioridad, se asignan automáticamente
     */
    public function getTodasOrdenes(): JsonResponse
    {
        try {
            $payload = $this->prioridadService->loadRecordsWithOptionalPriority(
                EngProgramaEngomado::class,
                [
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Metros',
                    'MaquinaEng',
                    'Status',
                    'FechaProg',
                    'InventSizeId',
                    'Fibra',
                ],
                fn ($query) => $query
                    ->whereIn('Status', ProgramaConfig::ACTIVE_STATUSES)
                    ->whereNotNull('MaquinaEng')
                    ->where('MaquinaEng', '!=', '')
            );

            $ordenesOrdenadas = $this->prioridadService->sortRecords(
                $payload['records'],
                fn ($orden) => $this->fechaProgFallback($orden)
            );

            // Convertir a array con formato para el frontend
            $ordenesArray = $ordenesOrdenadas->map(function ($orden, $index) {
                return [
                    'id' => $orden->Id,
                    'folio' => $orden->Folio,
                    'tipo' => $orden->tipo,
                    'cuenta' => $orden->Cuenta !== null && $orden->Cuenta !== '' ? (string) $orden->Cuenta : '',
                    'calibre' => $orden->Calibre !== null && $orden->Calibre !== '' ? (string) $orden->Calibre : '',
                    'configuracion' => $orden->Fibra ?? '',
                    'metros' => $orden->Metros,
                    'status' => $orden->Status ?? null,
                    'prioridad' => $this->prioridadService->displayPriority($orden, $index),
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
                'error' => 'Error al obtener órdenes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar prioridades en lote
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
            $this->prioridadService->bulkUpdatePriorities(
                EngProgramaEngomado::class,
                $request->prioridades
            );

            return response()->json([
                'success' => true,
                'message' => 'Prioridades actualizadas correctamente',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar prioridades: '.$e->getMessage(),
            ], 500);
        }
    }
}
