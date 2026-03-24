<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Helpers\FolioHelper;
use App\Http\Controllers\Controller;
use App\Models\Atadores\AtaMaquinasModel;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\CatTipoFalla;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use App\Models\Sistema\SysDepartamento;
use App\Models\Sistema\SYSMensaje;
use App\Models\Sistema\SYSUsuario;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Urdido\URDCatalogoMaquina;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MantenimientoParosController extends Controller
{
    /**
     * Mostrar vista de nuevo paro con departamento pre-seleccionado del usuario.
     */
    public function nuevoParo()
    {
        $usuario = Auth::user();
        $areaUsuario = null;

        // Obtener área del usuario desde SYSUsuario.
        if ($usuario && $usuario->idusuario) {
            $sysUsuario = SYSUsuario::where('idusuario', $usuario->idusuario)->first();
            $areaUsuario = $sysUsuario->area ?? null;
        }

        return view('modulos.mantenimiento.nuevo-paro.index', [
            'areaUsuario' => $areaUsuario,
        ]);
    }

    /**
     * Departamentos disponibles para el módulo de mantenimiento.
     * Fuente: SysDepartamentos.Depto.
     * El usuario con id 6 solo recibe Urdido y Engomado.
     */
    public function departamentos(): JsonResponse
    {
        $usuario = Auth::user();
        $userId = $usuario ? ($usuario->id ?? $usuario->idusuario ?? null) : null;

        if ($userId === 6) {
            $departamentos = SysDepartamento::orderBy('Depto')
                ->whereIn('Depto', ['Urdido', 'Engomado'])
                ->pluck('Depto')
                ->toArray();
        } else {
            $departamentos = SysDepartamento::orderBy('Depto')
                ->pluck('Depto')
                ->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => $departamentos,
        ]);
    }

    /**
     * Máquinas por departamento.
     *
     * - Para Urdido / Engomado: catálogo URDCatalogoMaquina (todas las máquinas del depto).
     * - Para Atadores: catálogo AtaMaquinasModel.
     * - Para Calidad: todos los telares disponibles en TelTelaresOperador.
     * - Para Tejedores / Trama / Desarrolladores / Supervisores: todos los telares asignados al usuario.
     * - Para Jacquard / Smith / Itema / Karl Mayer: máquinas asignadas al usuario
     *   autenticado en TelTelaresOperador, filtradas por salón.
     */
    public function maquinas(string $departamento): JsonResponse
    {
        try {
            $depUpper = strtoupper(trim($departamento));

            // Para Urdido / Engomado usamos directamente el catálogo URDCatalogoMaquina.
            if (in_array($depUpper, ['URDIDO', 'ENGOMADO'], true)) {
                $maquinas = URDCatalogoMaquina::where('Departamento', $departamento)
                    ->orderBy('MaquinaId')
                    ->get(['MaquinaId', 'Nombre', 'Departamento']);

                return response()->json([
                    'success' => true,
                    'data' => $maquinas,
                ]);
            }

            // Para Atadores usamos el catálogo AtaMaquinasModel.
            if ($depUpper === 'ATADORES') {
                $maquinas = AtaMaquinasModel::orderBy('MaquinaId')
                    ->get()
                    ->map(function ($item) use ($departamento) {
                        return [
                            'MaquinaId' => $item->MaquinaId,
                            'Nombre' => $item->MaquinaId,
                            'Departamento' => $departamento,
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'data' => $maquinas,
                ]);
            }

            // Calidad debe ver todos los telares disponibles, sin filtrar por usuario.
            if ($depUpper === 'CALIDAD') {
                $maquinas = TelTelaresOperador::query()
                    ->select('NoTelarId as MaquinaId')
                    ->whereNotNull('NoTelarId')
                    ->distinct()
                    ->orderBy('NoTelarId')
                    ->get()
                    ->map(function ($item) use ($departamento) {
                        return [
                            'MaquinaId' => $item->MaquinaId,
                            'Nombre' => $item->MaquinaId,
                            'Departamento' => $departamento,
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'data' => $maquinas,
                ]);
            }

            $usuario = Auth::user();
            $numeroEmpleado = $usuario->numero_empleado ?? null;

            if (! $numeroEmpleado) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado o sin número de empleado',
                    'data' => [],
                ], 401);
            }

            // Para Tejedores, Trama, Desarrolladores y Supervisores:
            // obtener todos los telares del usuario sin filtrar por salón.
            if (in_array($depUpper, ['TEJEDORES', 'TRAMA', 'DESARROLLADORES', 'SUPERVISORES'], true)) {
                $maquinas = TelTelaresOperador::query()
                    ->where('numero_empleado', $numeroEmpleado)
                    ->select('NoTelarId as MaquinaId')
                    ->whereNotNull('NoTelarId')
                    ->distinct()
                    ->orderBy('NoTelarId')
                    ->get()
                    ->map(function ($item) use ($departamento) {
                        return [
                            'MaquinaId' => $item->MaquinaId,
                            'Nombre' => $item->MaquinaId,
                            'Departamento' => $departamento,
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'data' => $maquinas,
                ]);
            }

            // Mapear departamento a SalonTejidoId (en TelTelaresOperador está como 'Jacquard' y 'Smith').
            $salones = match ($depUpper) {
                'ITEMA' => ['Smith'],
                'JACQUARD' => ['Jacquard'],
                'SMITH' => ['Smith'],
                'KARLMAYER', 'KARL MAYER' => ['KARL MAYER', 'KarlMayer'],
                default => [$departamento],
            };

            $maquinas = TelTelaresOperador::query()
                ->where('numero_empleado', $numeroEmpleado)
                ->whereIn('SalonTejidoId', $salones)
                ->select('NoTelarId as MaquinaId')
                ->whereNotNull('NoTelarId')
                ->distinct()
                ->orderBy('NoTelarId')
                ->get()
                ->map(function ($item) use ($departamento) {
                    return [
                        'MaquinaId' => $item->MaquinaId,
                        'Nombre' => $item->MaquinaId,
                        'Departamento' => $departamento,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $maquinas,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Catálogo de tipos de falla (CatTipoFalla).
     */
    public function tiposFalla(): JsonResponse
    {
        $tiposFalla = CatTipoFalla::orderBy('TipoFallaId')
            ->pluck('TipoFallaId');

        return response()->json([
            'success' => true,
            'data' => $tiposFalla,
        ]);
    }

    /**
     * Fallas por departamento desde CatParosFallas.
     *
     * Nota: Para Jacquard, Itema, Karl Mayer y Smith, se usa "Tejido" como departamento
     * en CatParosFallas para obtener las fallas.
     *
     * Si se proporciona tipoFallaId, se filtran las fallas por ese tipo.
     */
    public function fallas(string $departamento, ?string $tipoFallaId = null): JsonResponse
    {
        try {
            $depUpper = strtoupper(trim($departamento));

            // Mapear departamentos de tejido a "Tejido" en CatParosFallas.
            $departamentoParaConsulta = $departamento;
            if (in_array($depUpper, ['JACQUARD', 'ITEMA', 'KARL MAYER', 'KARLMAYER', 'SMITH', 'TEJEDORES', 'TRMA', 'CALIDAD', 'DESARROLLADORES', 'SUPERVISORES'], true)) {
                $departamentoParaConsulta = 'Tejido';
            }

            $query = CatParosFallas::query()
                ->where('Departamento', $departamentoParaConsulta);

            if (! empty($tipoFallaId)) {
                $query->where('TipoFallaId', $tipoFallaId);
            }

            $items = $query->orderBy('Falla')
                ->get(['Falla', 'Descripcion', 'Abreviado', 'Seccion', 'TipoFallaId']);

            return response()->json([
                'success' => true,
                'data' => $items,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Orden de trabajo sugerida por departamento y máquina.
     *
     * - Jacquard / Smith / Itema / Karl Mayer -> ReqProgramaTejido (EnProceso = 1)
     * - Calidad -> ReqProgramaTejido (EnProceso = 1) sin filtrar por salón
     * - Urdido -> UrdProgramaUrdido (Status = 'En Proceso', por MaquinaId)
     * - Engomado -> EngProgramaEngomado (Status = 'En Proceso', por MaquinaEng)
     */
    public function ordenTrabajo(string $departamento, string $maquina): JsonResponse
    {
        try {
            $depUpper = strtoupper(trim($departamento));

            if ($depUpper === 'URDIDO') {
                $rows = DB::table('UrdProgramaUrdido')
                    ->where('MaquinaId', $maquina)
                    ->where('Status', 'En Proceso')
                    ->orderByDesc('FechaProg')
                    ->limit(5)
                    ->get([
                        'Folio as Orden_Prod',
                        'FechaProg as Fecha',
                        'MaquinaId',
                    ]);

                return response()->json([
                    'success' => true,
                    'data' => $rows,
                ]);
            }

            if ($depUpper === 'ENGOMADO') {
                $rows = DB::table('EngProgramaEngomado')
                    ->where('MaquinaEng', $maquina)
                    ->where('Status', 'En Proceso')
                    ->orderByDesc('FechaProg')
                    ->limit(5)
                    ->get([
                        'Folio as Orden_Prod',
                        'FechaProg as Fecha',
                        'MaquinaEng',
                        'SalonTejidoId',
                    ]);

                return response()->json([
                    'success' => true,
                    'data' => $rows,
                ]);
            }

            $salones = match ($depUpper) {
                'ITEMA' => ['SMIT'],
                'SMITH' => ['SMIT'],
                'JACQUARD' => ['JACQUARD'],
                'KARLMAYER', 'KARL MAYER' => ['KARL MAYER', 'KARLMAYER'],
                default => [$depUpper],
            };

            $query = DB::table('ReqProgramaTejido')
                ->where('NoTelarId', $maquina)
                ->where('EnProceso', 1);

            if ($depUpper !== 'CALIDAD') {
                $query->whereIn('SalonTejidoId', $salones);
            }

            $rows = $query
                ->orderByDesc('FechaInicio')
                ->limit(5)
                ->get(['NoProduccion as Orden_Prod', 'NombreProducto', 'FechaInicio', 'SalonTejidoId', 'NoTelarId']);

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar un nuevo paro/falla en ManFallasParos.
     */
    public function store(Request $request)
    {
        try {
            $usuario = Auth::user();

            if (! $usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                ], 401);
            }

            $request->validate([
                'fecha' => 'required|date',
                'hora' => 'required',
                'depto' => 'required|string|max:30',
                'maquina' => 'required|string|max:50',
                'tipo_falla' => 'required|string|max:20',
                'falla' => 'required|string|max:20',
                'descrip' => 'nullable|string|max:100',
                'orden_trabajo' => 'nullable|string|max:50',
                'obs' => 'nullable|string',
            ]);

            $folio = FolioHelper::obtenerSiguienteFolio('ParosFallas', 5);

            if (empty($folio)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Error al generar folio',
                ], 500);
            }

            $data = [
                'Folio' => $folio,
                'Estatus' => 'Activo',
                'Fecha' => $request->fecha,
                'Hora' => $request->hora,
                'Depto' => $request->depto,
                'MaquinaId' => $request->maquina,
                'TipoFallaId' => $request->tipo_falla,
                'Falla' => $request->falla,
                'Descripcion' => $request->descrip ?? null,
                'OrdenTrabajo' => $request->orden_trabajo ?? null,
                'Obs' => $request->obs ?? null,
                'CveEmpl' => $usuario->numero_empleado ?? null,
                'NomEmpl' => $usuario->nombre ?? null,
                'Turno' => (int) ($usuario->turno ?? 1),
                'Enviado' => $request->boolean('notificar_supervisor', false),
                'HoraFin' => null,
                'CveAtendio' => null,
                'NomAtendio' => null,
                'TurnoAtendio' => null,
            ];

            $paro = ManFallasParos::create($data);

            $notificarSupervisor = $request->boolean('notificar_supervisor', false);
            if ($notificarSupervisor) {
                $this->enviarNotificacionTelegram($paro, $usuario);
            }

            return response()->json([
                'success' => true,
                'message' => 'Paro reportado correctamente'.($notificarSupervisor ? ' y notificación enviada a Telegram' : ''),
                'data' => [
                    'folio' => $folio,
                    'id' => $paro->Id,
                    'notificacion_enviada' => $notificarSupervisor,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al guardar paro/falla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al guardar el paro: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mapea TipoFallaId del paro a la columna de SYSMensajes para Telegram.
     * ReporteElectrico, ReporteMecanico, ReporteTiempoMuerto.
     *
     * @return string|null Nombre de la columna del módulo o null si no hay coincidencia
     */
    private function moduloTelegramPorTipoFalla(?string $tipoFallaId): ?string
    {
        if ($tipoFallaId === null || $tipoFallaId === '') {
            return null;
        }

        $n = mb_strtoupper(trim($tipoFallaId));
        $n = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $n);

        if (str_contains($n, 'ELECTRIC') || $n === 'ELECTRICO') {
            return 'ReporteElectrico';
        }

        if (str_contains($n, 'MECANIC') || $n === 'MECANICO') {
            return 'ReporteMecanico';
        }

        if ((str_contains($n, 'TIEMPO') && str_contains($n, 'MUERTO')) || $n === 'TIEMPO MUERTO') {
            return 'ReporteTiempoMuerto';
        }

        return null;
    }

    /**
     * Enviar notificación a Telegram con los detalles del paro reportado.
     * Destinatarios: SYSMensajes con la columna según tipo de falla
     * (ReporteElectrico, ReporteMecanico, ReporteTiempoMuerto) y Activo=1.
     */
    private function enviarNotificacionTelegram($paro, $usuario): void
    {
        try {
            $botToken = config('services.telegram.bot_token');
            if (empty($botToken)) {
                Log::warning('No se pudo enviar notificación a Telegram: credenciales no configuradas');

                return;
            }

            $modulo = $this->moduloTelegramPorTipoFalla($paro->TipoFallaId ?? '');
            if ($modulo === null) {
                Log::info('Paro sin módulo Telegram para tipo de falla', ['TipoFallaId' => $paro->TipoFallaId ?? '']);

                return;
            }

            $chatIds = SYSMensaje::getChatIdsPorModulo($modulo);
            if (empty($chatIds)) {
                Log::warning("No hay destinatarios con {$modulo} activo en SYSMensajes para notificación de paro");

                return;
            }

            $fecha = Carbon::parse($paro->Fecha)->format('d/m/Y');
            $hora = $paro->Hora;

            $mensaje = "🚨 *NOTIFICACIÓN DE FALLA/PARO* 🚨\n\n";
            $mensaje .= "📋 *Folio:* {$paro->Folio}\n";
            $mensaje .= "👤 *Reportado por:* {$paro->NomEmpl}\n";
            $mensaje .= "📅 *Fecha:* {$fecha}\n";
            $mensaje .= "🕐 *Hora:* {$hora}\n";
            $mensaje .= "🏢 *Departamento:* {$paro->Depto}\n";
            $mensaje .= "🔧 *Máquina:* {$paro->MaquinaId}\n";
            $mensaje .= "⚠️ *Tipo de Falla:* {$paro->TipoFallaId}\n";
            $mensaje .= "❌ *Falla:* {$paro->Falla}\n";

            if (! empty($paro->Descripcion)) {
                $mensaje .= "📝 *Descripción:* {$paro->Descripcion}\n";
            }

            if (! empty($paro->OrdenTrabajo)) {
                $mensaje .= "📋 *Orden de Trabajo:* {$paro->OrdenTrabajo}\n";
            }

            if (! empty($paro->Obs)) {
                $mensaje .= "💬 *Observaciones:* {$paro->Obs}\n";
            }

            $mensaje .= "\n✅ *Estatus:* {$paro->Estatus}\n";
            $mensaje .= "🔄 *Turno:* {$paro->Turno}";

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            foreach ($chatIds as $chatId) {
                $response = Http::post($url, [
                    'chat_id' => $chatId,
                    'text' => $mensaje,
                    'parse_mode' => 'Markdown',
                ]);

                if (! $response->successful() || ! ($response->json()['ok'] ?? false)) {
                    Log::warning('Error al enviar notificación de paro a Telegram', [
                        'chat_id' => $chatId,
                        'folio' => $paro->Folio,
                        'response' => $response->json(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Excepción al enviar notificación de paro a Telegram', [
                'error' => $e->getMessage(),
                'folio' => $paro->Folio ?? null,
            ]);
        }
    }

    /**
     * Enviar notificación a Telegram al finalizar un paro/falla.
     * Destinatarios: según tipo de falla
     * (ReporteElectrico, ReporteMecanico, ReporteTiempoMuerto) en SYSMensajes.
     */
    private function enviarNotificacionTelegramCierre($paro, $usuario): void
    {
        try {
            $botToken = config('services.telegram.bot_token');
            if (empty($botToken)) {
                Log::warning('No se pudo enviar notificación de cierre a Telegram: credenciales no configuradas');

                return;
            }

            $modulo = $this->moduloTelegramPorTipoFalla($paro->TipoFallaId ?? '');
            if ($modulo === null) {
                Log::info('Paro finalizado sin módulo Telegram para tipo de falla', ['TipoFallaId' => $paro->TipoFallaId ?? '']);

                return;
            }

            $chatIds = SYSMensaje::getChatIdsPorModulo($modulo);
            if (empty($chatIds)) {
                Log::warning("No hay destinatarios con {$modulo} activo en SYSMensajes para cierre de paro");

                return;
            }

            $fecha = $paro->FechaFin ?? $paro->Fecha;
            $hora = $paro->HoraFin ?? now()->format('H:i:s');

            $mensaje = "✅ *PARO FINALIZADO* \n\n";
            $mensaje .= "📋 *Folio:* {$paro->Folio}\n";
            $mensaje .= "🏢 *Departamento:* {$paro->Depto}\n";
            $mensaje .= "🔧 *Máquina:* {$paro->MaquinaId}\n";
            $mensaje .= "⚠️ *Tipo de Falla:* {$paro->TipoFallaId}\n";
            $mensaje .= "❌ *Falla:* {$paro->Falla}\n";
            $mensaje .= '📅 *Fecha cierre:* '.Carbon::parse($fecha)->format('d/m/Y')."\n";
            $mensaje .= "🕐 *Hora cierre:* {$hora}\n";

            if (! empty($paro->NomAtendio)) {
                $mensaje .= "👤 *Atendió:* {$paro->NomAtendio}\n";
            }

            if (! empty($paro->TurnoAtendio)) {
                $mensaje .= "🔄 *Turno atención:* {$paro->TurnoAtendio}\n";
            }

            if (! empty($paro->Calidad)) {
                $mensaje .= "⭐ *Calidad:* {$paro->Calidad}/5\n";
            }

            if (! empty($paro->ObsCierre)) {
                $mensaje .= "💬 *Observaciones cierre:* {$paro->ObsCierre}\n";
            }

            $mensaje .= "\n👤 *Cerrado por:* ".($usuario->nombre ?? 'N/A');

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            foreach ($chatIds as $chatId) {
                $response = Http::post($url, [
                    'chat_id' => $chatId,
                    'text' => $mensaje,
                    'parse_mode' => 'Markdown',
                ]);

                if (! $response->successful() || ! ($response->json()['ok'] ?? false)) {
                    Log::warning('Error al enviar notificación de cierre de paro a Telegram', [
                        'chat_id' => $chatId,
                        'folio' => $paro->Folio,
                        'response' => $response->json(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Excepción al enviar notificación de cierre de paro a Telegram', [
                'error' => $e->getMessage(),
                'folio' => $paro->Folio ?? 'N/A',
            ]);
        }
    }

    /**
     * Obtener lista de paros/fallas para el reporte.
     * Muestra todos los paros sin filtros.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ManFallasParos::query()
                ->orderByDesc('Fecha')
                ->orderByDesc('Hora');

            $paros = $query->get([
                'Id',
                'Folio',
                'Estatus',
                'Fecha',
                'Hora',
                'Depto',
                'MaquinaId',
                'TipoFallaId',
                'Falla',
                'HoraFin',
                'NomAtendio',
                'NomEmpl',
                'CveEmpl',
            ]);

            return response()->json([
                'success' => true,
                'data' => $paros,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener paros/fallas', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Obtener un paro/falla específico por ID.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $paro = ManFallasParos::find($id);

            if (! $paro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Paro no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $paro,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener paro/falla', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Finalizar un paro/falla (actualizar con datos de cierre).
     */
    public function finalizar(Request $request, int $id): JsonResponse
    {
        try {
            $paro = ManFallasParos::find($id);

            if (! $paro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Paro no encontrado',
                ], 404);
            }

            $request->validate([
                'atendio' => 'nullable|string|max:100',
                'turno' => 'nullable|integer|in:1,2,3',
                'calidad' => 'nullable|integer|min:1|max:10',
                'obs_cierre' => 'nullable|string|max:255',
                'enviar_telegram' => 'nullable|boolean',
            ]);

            $usuario = Auth::user();

            $updateData = [
                'Estatus' => 'Terminado',
                'HoraFin' => now()->format('H:i:s'),
                'FechaFin' => now()->format('Y-m-d'),
            ];

            if ($request->filled('atendio')) {
                $updateData['NomAtendio'] = $request->atendio;
                $updateData['CveAtendio'] = $usuario->numero_empleado ?? null;
            }

            if ($request->filled('turno')) {
                $updateData['TurnoAtendio'] = (int) $request->turno;
            }

            if ($request->filled('calidad')) {
                $updateData['Calidad'] = (int) $request->calidad;
            }

            if ($request->filled('obs_cierre')) {
                $updateData['ObsCierre'] = $request->obs_cierre;
            }

            $paro->update($updateData);
            $paro->refresh();

            $enviarTelegram = $request->boolean('enviar_telegram', false);
            if ($enviarTelegram) {
                $this->enviarNotificacionTelegramCierre($paro, $usuario);
            }

            return response()->json([
                'success' => true,
                'message' => 'Paro finalizado correctamente'.($enviarTelegram ? ' y notificación enviada a Telegram' : ''),
                'data' => [
                    'id' => $paro->Id,
                    'folio' => $paro->Folio,
                    'notificacion_enviada' => $enviarTelegram,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al finalizar paro/falla', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al finalizar el paro: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener lista de operadores de mantenimiento para el select de "Atendio".
     */
    public function operadores(): JsonResponse
    {
        try {
            $operadores = ManOperadoresMantenimiento::select('Id', 'CveEmpl', 'NomEmpl', 'Turno', 'Depto')
                ->orderBy('NomEmpl')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $operadores,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener operadores de mantenimiento', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
