<?php

namespace App\Http\Controllers\Urdido\ProgramaUrdido;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Services\Programas\ProgramaPrioridadService;
use App\Support\Programas\ProgramaConfig;
use App\Support\Programas\ProgramaRouteHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProgramarUrdidoController extends Controller
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
     * Mostrar la vista de programar urdido
     */
    public function index()
    {
        return view('modulos.urdido.programar-urdido', [
            'canEdit' => $this->usuarioPuedeEditar(),
            'programaRoutes' => ProgramaRouteHelper::urdido(),
            'observacionesMaxLength' => ProgramaConfig::OBSERVACIONES_MAX_LENGTH,
            'calidadComentarioMaxLength' => ProgramaConfig::CALIDAD_COMENTARIO_MAX_LENGTH,
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

    private function activeOrdersQuery()
    {
        return UrdProgramaUrdido::query()
            ->whereIn('Status', ProgramaConfig::ACTIVE_STATUSES)
            ->whereNotNull('MaquinaId');
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
                        'observaciones' => $orden->Observaciones ?? '',
                        'prioridad' => $this->prioridadService->displayPriority($orden, $indexEnGrupo - 1),
                        'created_at' => $orden->CreatedAt ? $orden->CreatedAt->format('Y-m-d H:i:s') : null,
                        'calidad' => $orden->Calidad ?? null,
                        'calidadcomentario' => $orden->CalidadComentario ?? null,
                        'autoriza_calidad' => $orden->AutorizaCalidad ?? null,
                        'fecha_calidad' => $orden->FechaCalidad ? $orden->FechaCalidad->format('Y-m-d H:i:s') : null,
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
     * Subir prioridad de una orden
     * Intercambia CreatedAt con la orden inmediatamente anterior en el mismo MC Coy
     * (la orden con CreatedAt más reciente se mueve antes)
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
            $ordenesMcCoy = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
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

            if (! $ordenAnterior) {
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
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => 'Error al subir prioridad: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar si hay órdenes con status "En Proceso" por máquina (MC Coy)
     * Retorna true si hay al menos una orden con status "En Proceso" en la misma máquina
     * (excluyendo la orden actual si se proporciona)
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
                'error' => 'Error al verificar órdenes en proceso: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bajar prioridad de una orden
     * Intercambia CreatedAt con la orden inmediatamente posterior en el mismo MC Coy
     * (la orden con CreatedAt más antiguo se mueve después)
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
            $ordenesMcCoy = UrdProgramaUrdido::whereIn('Status', ['Programado', 'En Proceso', 'Parcial'])
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

            if (! $ordenPosterior) {
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
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => 'Error al bajar prioridad: '.$e->getMessage(),
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
            // Habilitado para todos los usuarios con acceso al módulo
            $request->validate([
                'source_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'target_id' => 'required|integer|exists:UrdProgramaUrdido,Id',
            ]);
            $this->prioridadService->swapPriorities(
                UrdProgramaUrdido::class,
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
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'observaciones' => 'nullable|string|max:'.ProgramaConfig::OBSERVACIONES_MAX_LENGTH,
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
                'error' => 'Error de validación: '.$e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al guardar observaciones: '.$e->getMessage(),
            ], 500);
        }
    }

    public function actualizarCalidad(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'calidad' => ['required', 'string', Rule::in(['A', 'R', 'O'])],
                'calidadcomentario' => 'nullable|string|max:'.ProgramaConfig::CALIDAD_COMENTARIO_MAX_LENGTH,
            ]);

            $orden = UrdProgramaUrdido::findOrFail($request->id);
            $orden->Calidad = $request->calidad;
            $orden->CalidadComentario = $request->calidadcomentario;
            $orden->AutorizaCalidad = Auth::user()->nombre;
            $orden->FechaCalidad = now();
            $orden->save();

            $estadoTexto = match ($request->calidad) {
                'A' => '✅ Aprobado',
                'R' => '❌ Rechazado',
                'O' => '⚠️ Con observaciones',
            };

            $mensaje = "🏭 *CALIDAD URDIDO*\n\n";
            $mensaje .= "📋 Folio: {$orden->Folio}\n";
            $mensaje .= "📅 Fecha: {$orden->FechaCalidad->format('d/m/Y H:i')}\n";
            $mensaje .= "👷‍♂️ Realizó: {$orden->AutorizaCalidad}\n";
            $mensaje .= "🏭 Maquina: {$orden->MaquinaId}\n";
            $mensaje .= "🏭 Lote Prov: {$orden->LoteProveedor}\n";
            $mensaje .= "⚙️ Fibra: {$orden->Fibra}\n";
            $mensaje .= "📐 Cuenta: {$orden->InventSizeId}\n\n";
            $mensaje .= "Status: {$estadoTexto}\n";
            if ($request->calidadcomentario) {
                $mensaje .= "💬 Obs: {$request->calidadcomentario}";
            }

            $botToken = config('services.telegram.bot_token');
            $chatIds = \App\Models\Sistema\SYSMensaje::getChatIdsPorModulo('UrdidoCalidad');

            if (! empty($botToken) && ! empty($chatIds)) {
                $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
                foreach ($chatIds as $chatId) {
                    try {
                        Http::timeout(10)->post($url, [
                            'chat_id' => $chatId,
                            'text' => $mensaje,
                            'parse_mode' => 'Markdown',
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Error al enviar telegram calidad urdido: '.$e->getMessage());
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Calidad actualizada correctamente',
                'calidad' => $orden->Calidad,
                'calidadcomentario' => $orden->CalidadComentario,
                'autoriza_calidad' => $orden->AutorizaCalidad,
                'fecha_calidad' => $orden->FechaCalidad,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
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
     * Recalcular prioridades consecutivas para todas las órdenes activas
     * Excluye órdenes canceladas
     */
    private function recalcularPrioridades(): void
    {
        try {
            $this->prioridadService->recalculatePriorities(
                $this->activeOrdersQuery(),
                fn ($orden) => $this->createdAtFallback($orden)
            );
        } catch (\Throwable $e) {
            Log::error('Error al recalcular prioridades: '.$e->getMessage());
        }
    }

    /**
     * Actualizar el status de una orden
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
                'id' => 'required|integer|exists:UrdProgramaUrdido,Id',
                'status' => ['required', 'string', Rule::in(ProgramaConfig::STATUS_OPTIONS)],
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
                    if ($ordenEngomado && ! in_array($ordenEngomado->Status, ['Cancelado', 'Finalizado'])) {
                        $ordenEngomado->Status = 'Cancelado';
                        $ordenEngomado->Prioridad = null;
                        $ordenEngomado->save();

                        // Eliminar registros de producción de engomado cuando se cancela
                        try {

                            $registrosEliminados = EngProduccionEngomado::where('Folio', $orden->Folio)->delete();
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
            elseif ($statusAnterior === 'Cancelado' && in_array($nuevoStatus, ProgramaConfig::ACTIVE_STATUSES, true)) {
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
            // Habilitado para todos los usuarios con acceso al módulo
            $request->validate([
                'prioridades' => 'required|array',
                'prioridades.*.id' => 'required|integer|exists:UrdProgramaUrdido,Id',
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
