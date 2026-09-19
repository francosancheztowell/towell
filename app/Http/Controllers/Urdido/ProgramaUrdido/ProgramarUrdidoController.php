<?php

namespace App\Http\Controllers\Urdido\ProgramaUrdido;

use App\Http\Controllers\Controller;
use App\Jobs\Programas\SendUrdidoQualityNotification;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Services\Programas\ProgramaPrioridadService;
use App\Services\Programas\ProgramBoardActionService;
use App\Support\Programas\ProgramaConfig;
use App\Support\Programas\ProgramaModulo;
use App\Support\Programas\ProgramaRouteHelper;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramarUrdidoController extends Controller
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
     * Respuesta 403 si el usuario no puede modificar el programa; null si sí puede.
     */
    private function jsonSiNoPuedeModificarPrograma(): ?JsonResponse
    {
        if (function_exists('userCan') && userCan('modificar', 'Programa Urdido')) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => 'No autorizado',
        ], 403);
    }

    /**
     * Marca (1) o quita (0) la bandera de cuenta/calibre incorrecta.
     * Marcarla la puede hacer quien carga la orden; quitarla solo un supervisor.
     */
    public function marcarIncorrecto(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            'incorrecto' => 'required|boolean',
        ]);

        $incorrecto = $request->boolean('incorrecto');

        if (! $incorrecto && ! $this->usuarioPuedeEditar()) {
            return response()->json([
                'success' => false,
                'error' => 'Solo un supervisor puede quitar la marca de cuenta/calibre incorrecta.',
            ], 403);
        }

        UrdProgramaUrdido::where('Id', $request->integer('id'))
            ->update(['Incorrecto' => $incorrecto ? 1 : 0]);

        return response()->json(['success' => true]);
    }

    /**
     * Mostrar el programa de urdido con el diseño clásico de tablas por máquina.
     */
    public function index(): View
    {
        return view('modulos.urdido.programar-urdido', [
            'canEdit' => $this->usuarioPuedeEditar(),
            'programaRoutes' => ProgramaRouteHelper::urdido(),
            'observacionesMaxLength' => ProgramaConfig::OBSERVACIONES_MAX_LENGTH,
            'calidadComentarioMaxLength' => ProgramaConfig::CALIDAD_COMENTARIO_MAX_LENGTH,
            'calidadPuntos' => UrdProgramaUrdido::CALIDAD_PUNTOS,
        ]);
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
            'Fibra',
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
     * Ventana emergente que carga el PDF de la orden y fuerza el diálogo de impresión.
     */
    public function reimpresionVentanaImprimir(Request $request)
    {
        $ordenId = $request->query('orden_id');
        if (! $ordenId) {
            return response('<script>alert("Falta orden_id"); window.close();</script>', 400)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $pdfUrl = route('urdido.modulo.produccion.urdido.pdf', [
            'orden_id' => $ordenId,
            'tipo' => 'urdido',
            'reimpresion' => 1,
        ]);

        return view('modulos.urdido.reimpresion-urdido-popup', [
            'pdfUrl' => $pdfUrl,
            'ordenId' => $ordenId,
        ]);
    }

    /**
     * Extraer número de tarjeta (1-4) del campo MaquinaId.
     * Mc Coy 1 -> 1, Mc Coy 2 -> 2, Mc Coy 3 -> 3, Karl Mayer -> 4.
     */
    private function extractMcCoyNumber(?string $maquinaId): ?int
    {
        if (empty($maquinaId)) {
            return null;
        }

        $m = trim($maquinaId);

        // Karl Mayer -> tarjeta 4
        if (stripos($m, 'Karl Mayer') !== false) {
            return 4;
        }

        // Buscar patrón "Mc Coy X" (case insensitive, permite espacios variables)
        if (preg_match('/mc\s*coy\s*(\d+)/i', $m, $matches)) {
            $num = (int) $matches[1];

            return ($num >= 1 && $num <= 3) ? $num : null;
        }

        return null;
    }

    private function createdAtFallback(object $orden): int
    {
        return $orden->CreatedAt?->timestamp ?? PHP_INT_MAX;
    }

    /**
     * Obtener órdenes de urdido agrupadas por MC Coy
     * Extrae el MC Coy del campo MaquinaId (ej: "Mc Coy 1" -> 1)
     * Solo incluye órdenes con status "Programado" o "En Proceso" (excluye canceladas)
     * Ordena por Prioridad si existe, sino por CreatedAt ascendente
     */
    public function getOrdenes(): JsonResponse
    {
        try {
            $payload = $this->prioridadService->loadRecordsWithOptionalPriority(
                UrdProgramaUrdido::class,
                [
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Fibra',
                    'Metros',
                    'MaquinaId',
                    'Status',
                    'FechaProg',
                    'CreatedAt',
                    'Observaciones',
                    'InventSizeId',
                    'Calidad',
                    'CalidadComentario',
                    'AutorizaCalidad',
                    'FechaCalidad',
                    'Incorrecto',
                    ...array_keys(UrdProgramaUrdido::CALIDAD_PUNTOS),
                ],
                fn ($query) => $query
                    ->whereIn('Status', ProgramaConfig::ACTIVE_STATUSES)
                    ->whereNotNull('MaquinaId')
            );

            $ordenes = $payload['records'];
            $ordenesOrdenadas = $this->prioridadService->sortRecords(
                $ordenes,
                fn ($orden) => $this->createdAtFallback($orden)
            );
            $foliosConAx = array_fill_keys(
                UrdProduccionUrdido::foliosConAx($ordenesOrdenadas->pluck('Folio')->all()),
                true
            );

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
                        'cuenta_calibre' => $orden->InventSizeId ?? '',
                        'configuracion' => $orden->Fibra ?? '',
                        'metros' => $orden->Metros,
                        'mccoy' => $mcCoy,
                        'maquina_id' => $orden->MaquinaId ?? null,
                        'status' => $orden->Status ?? null,
                        'bloqueado_por_ax' => isset($foliosConAx[(string) $orden->Folio]),
                        'incorrecto' => (int) ($orden->Incorrecto ?? 0),
                        'observaciones' => $orden->Observaciones ?? '',
                        'prioridad' => $this->prioridadService->displayPriority($orden, $indexEnGrupo - 1),
                        'created_at' => $orden->CreatedAt ? $orden->CreatedAt->format('Y-m-d H:i:s') : null,
                        'calidad' => $orden->Calidad ?? null,
                        'calidadcomentario' => $orden->CalidadComentario ?? null,
                        'autoriza_calidad' => $orden->AutorizaCalidad ?? null,
                        'fecha_calidad' => $orden->FechaCalidad ? $orden->FechaCalidad->format('Y-m-d H:i:s') : null,
                        'calidad_puntos' => $this->puntosCalidad($orden),
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
                'error' => 'Error al obtener órdenes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Intercambiar prioridad entre dos órdenes mediante drag and drop
     * Intercambia el campo Prioridad (único globalmente, sin importar MC Coy)
     */
    public function intercambiarPrioridad(Request $request): JsonResponse
    {
        try {
            if ($noAutorizado = $this->jsonSiNoPuedeModificarPrograma()) {
                return $noAutorizado;
            }

            $request->validate([
                'source_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'target_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            ]);
            $this->boardActionService->swapPriorities(
                ProgramaModulo::Urdido,
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
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'observaciones' => 'nullable|string|max:'.ProgramaConfig::OBSERVACIONES_MAX_LENGTH,
            ]);

            $this->boardActionService->saveObservations(
                ProgramaModulo::Urdido,
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
     * Puntos del checklist de una orden, en el orden del catálogo.
     *
     * @return array<string, bool|null>
     */
    private function puntosCalidad(object $orden): array
    {
        $puntos = [];
        foreach (array_keys(UrdProgramaUrdido::CALIDAD_PUNTOS) as $campo) {
            $puntos[$campo] = $orden->{$campo} === null ? null : (bool) $orden->{$campo};
        }

        return $puntos;
    }

    public function actualizarCalidad(Request $request): JsonResponse
    {
        try {
            if (! function_exists('userCan') || ! userCan('registrar', 'Programa Urdido')) {
                return response()->json([
                    'success' => false,
                    'error' => 'No tiene permiso para evaluar la calidad de una orden.',
                ], 403);
            }

            $campos = array_keys(UrdProgramaUrdido::CALIDAD_PUNTOS);

            // Los puntos del checklist son obligatorios: el estado se deriva de ellos, no llega del front.
            $request->validate([
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'calidadcomentario' => 'nullable|string|max:'.ProgramaConfig::CALIDAD_COMENTARIO_MAX_LENGTH,
                ...array_fill_keys($campos, 'required|boolean'),
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->id);

            $algunoMalo = false;
            foreach ($campos as $campo) {
                $valor = $request->boolean($campo);
                $orden->{$campo} = $valor;
                $algunoMalo = $algunoMalo || ! $valor;
            }

            $orden->Calidad = $algunoMalo ? '0' : '1';
            $orden->CalidadComentario = $request->calidadcomentario;
            $orden->AutorizaCalidad = (string) (Auth::user()->nombre ?? '');
            $orden->FechaCalidad = now();
            $orden->save();

            SendUrdidoQualityNotification::dispatchAfterResponse([
                'folio' => (string) ($orden->Folio ?? ''),
                'date' => $orden->FechaCalidad->format('d/m/Y H:i'),
                'author' => $orden->AutorizaCalidad,
                'machine' => (string) ($orden->MaquinaId ?? ''),
                'supplier_lot' => (string) ($orden->LoteProveedor ?? ''),
                'fiber' => (string) ($orden->Fibra ?? ''),
                'size' => (string) ($orden->InventSizeId ?? ''),
                'quality' => (string) $orden->Calidad,
                'comment' => (string) ($orden->CalidadComentario ?? ''),
                'points' => $this->puntosCalidad($orden),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Calidad actualizada correctamente',
                'calidad' => $orden->Calidad,
                'calidadcomentario' => $orden->CalidadComentario,
                'autoriza_calidad' => $orden->AutorizaCalidad,
                'fecha_calidad' => $orden->FechaCalidad->format('Y-m-d H:i:s'),
                'calidad_puntos' => $this->puntosCalidad($orden),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al actualizar calidad: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar el status de una orden.
     * Delega a ProgramBoardActionService (tope 2× En Proceso + AX + cancelación en cascada).
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
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'status' => ['required', 'string', Rule::in(ProgramaConfig::STATUS_OPTIONS)],
            ]);

            $this->boardActionService->changeStatus(
                ProgramaModulo::Urdido,
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
     * Obtener todas las órdenes sin agrupar por MC Coy
     * Solo órdenes con status "En Proceso" o "Programado"
     * Si no tienen prioridad, se asignan automáticamente
     */
    public function getTodasOrdenes(): JsonResponse
    {
        try {
            $payload = $this->prioridadService->loadRecordsWithOptionalPriority(
                UrdProgramaUrdido::class,
                [
                    'Id',
                    'Folio',
                    'RizoPie as tipo',
                    'Cuenta',
                    'Calibre',
                    'Fibra',
                    'Metros',
                    'MaquinaId',
                    'Status',
                    'CreatedAt',
                    'InventSizeId',
                    'Incorrecto',
                ],
                fn ($query) => $query->whereIn('Status', ProgramaConfig::ACTIVE_STATUSES)
            );

            $ordenesOrdenadas = $this->prioridadService->sortRecords(
                $payload['records'],
                fn ($orden) => $this->createdAtFallback($orden)
            );

            // Convertir a array con formato para el frontend
            $ordenesArray = $ordenesOrdenadas->map(function ($orden, $index) {
                return [
                    'id' => $orden->Id,
                    'folio' => $orden->Folio,
                    'tipo' => $orden->tipo,
                    'cuenta_calibre' => $orden->InventSizeId ?? '',
                    'configuracion' => $orden->Fibra ?? '',
                    'metros' => $orden->Metros,
                    'maquina' => $orden->MaquinaId ?? '',
                    'status' => $orden->Status ?? null,
                    'incorrecto' => (int) ($orden->Incorrecto ?? 0),
                    'prioridad' => $this->prioridadService->displayPriority($orden, $index),
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
            // Habilitado para cualquier usuario con permiso de modificar el módulo (no solo supervisores)
            if ($noAutorizado = $this->jsonSiNoPuedeModificarPrograma()) {
                return $noAutorizado;
            }

            $request->validate([
                'prioridades' => 'required|array',
                // ponytail: sin exists: por fila (eran N SELECT); el UPDATE ignora ids inexistentes
                'prioridades.*.id' => 'required|integer',
                'prioridades.*.prioridad' => 'required|integer|min:1',
            ]);
            $this->prioridadService->bulkUpdatePriorities(
                UrdProgramaUrdido::class,
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
