<?php

namespace App\Http\Controllers\Engomado\ProgramaEngomado;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Services\Programas\ProgramaPrioridadService;
use App\Services\Programas\ProgramBoardActionService;
use App\Support\Programas\ProgramaConfig;
use App\Support\Programas\ProgramaModulo;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramarEngomadoController extends Controller
{
    public function __construct(
        private readonly ProgramaPrioridadService $prioridadService,
        private readonly ProgramBoardActionService $boardActionService,
    ) {}

    /**
     * Verifica si el usuario puede editar: solo usuarios con puesto de Supervisor.
     */
    private function usuarioPuedeEditar(): bool
    {
        $usuario = Auth::user();
        if (! $usuario) {
            return false;
        }

        $puesto = trim($usuario->puesto ?? '');

        return $puesto !== '' && stripos($puesto, 'supervisor') !== false;
    }

    /**
     * Mostrar el programa de engomado con el diseño clásico de tablas por máquina.
     */
    public function index(): View
    {
        return view('modulos.engomado.programar-engomado-livewire');
    }

    /**
     * Mostrar ordenes para reimpresion y edicion (todos los status, con filtros)
     */
    public function reimpresionFinalizadas(): View
    {
        return view('modulos.engomado.reimpresion-engomado');
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
            $foliosConAx = array_fill_keys(
                EngProduccionEngomado::foliosConAx($ordenesOrdenadas->pluck('Folio')->all()),
                true
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
                        'bloqueado_por_ax' => isset($foliosConAx[(string) $orden->Folio]),
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
     * Intercambiar prioridad entre dos órdenes mediante drag and drop.
     */
    public function intercambiarPrioridad(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'source_id' => 'required|integer|exists:EngProgramaEngomado,Id',
                'target_id' => 'required|integer|exists:EngProgramaEngomado,Id',
            ]);

            $this->boardActionService->swapPriorities(
                ProgramaModulo::Engomado,
                (int) $request->source_id,
                (int) $request->target_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Prioridad actualizada correctamente',
            ]);
        } catch (DomainException $e) {
            $status = str_contains($e->getMessage(), 'permiso') ? 403 : 422;

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
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

            $this->boardActionService->saveObservations(
                ProgramaModulo::Engomado,
                (int) $request->id,
                trim((string) ($request->observaciones ?? ''))
            );

            return response()->json([
                'success' => true,
                'message' => 'Observaciones guardadas correctamente',
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (ValidationException $e) {
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
     * Actualizar el status de una orden de engomado.
     * Delega a ProgramBoardActionService (Urdido Finalizado + tope 2× En Proceso + AX).
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

            $this->boardActionService->changeStatus(
                ProgramaModulo::Engomado,
                (int) $request->id,
                (string) $request->status
            );

            return response()->json([
                'success' => true,
                'message' => 'Status actualizado correctamente',
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
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
                // ponytail: sin exists: por fila (eran N SELECT); el UPDATE ignora ids inexistentes
                'prioridades.*.id' => 'required|integer',
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
        } catch (ValidationException $e) {
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
