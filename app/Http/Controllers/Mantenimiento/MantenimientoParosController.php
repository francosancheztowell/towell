<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Helpers\FolioHelper;
use App\Helpers\TurnoHelper;
use App\Http\Controllers\Controller;
use App\Models\Atadores\AtaMaquinasModel;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use App\Models\Sistema\SysDepartamento;
use App\Models\Sistema\SYSUsuario;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\Mantenimiento\ParoTelegramNotifier;
use App\Services\Mecanicos\CalificacionParoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MantenimientoParosController extends Controller
{
    /** Días de histórico que devuelve el listado cuando se piden paros finalizados. */
    private const DIAS_HISTORICO_DEFAULT = 30;

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
     * Todos los departamentos del catálogo (SysDepartamentos) para filtros en reportes de paros.
     * Sin restricción por usuario (p. ej. id 6): el combo Área debe listar todas las áreas aunque los datos vengan filtrados por backend.
     */
    public function departamentosCatalogoFiltros(): JsonResponse
    {
        try {
            $departamentos = SysDepartamento::query()
                ->orderBy('Depto')
                ->pluck('Depto')
                ->map(fn ($d) => trim((string) $d))
                ->filter(fn ($d) => $d !== '')
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'data' => $departamentos,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener catálogo de departamentos para filtros', [
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
     * Máquinas por departamento.
     *
     * - Para Urdido / Engomado: catálogo URDCatalogoMaquina (todas las máquinas del depto).
     * - Para Atadores: catálogo AtaMaquinasModel.
     * - Para Calidad: todos los telares disponibles, más máquinas de Urdido y Engomado.
     * - Para Tejedores / Trama / Desarrolladores / Supervisores: todos los telares asignados al usuario.
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
                return response()->json([
                    'success' => true,
                    'data' => $this->maquinasParaCalidad($departamento),
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

            $query = TelTelaresOperador::query()
                ->where('numero_empleado', $numeroEmpleado);

            // Los departamentos de tejido ven todos sus telares sin filtrar por salón.
            // El resto (Sistemas, Contabilidad, Directivos, Mantenimiento…) no tiene
            // telares asignados y recibe una lista vacía, que es la respuesta correcta:
            // no operan máquinas.
            if (! in_array($depUpper, ['TEJEDORES', 'TRAMA', 'DESARROLLADORES', 'SUPERVISORES'], true)) {
                $query->whereIn('SalonTejidoId', [$departamento]);
            }

            $maquinas = $query
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
     * @return Collection<int, array{
     *     MaquinaId: string,
     *     Nombre: string,
     *     Departamento: string,
     *     DepartamentoOrigen: string
     * }>
     */
    private function maquinasParaCalidad(string $departamento): Collection
    {
        $telares = TelTelaresOperador::query()
            ->select('NoTelarId as MaquinaId')
            ->whereNotNull('NoTelarId')
            ->distinct()
            ->get()
            ->map(function ($item) use ($departamento): array {
                $maquinaId = trim((string) $item->MaquinaId);

                return [
                    'MaquinaId' => $maquinaId,
                    'Nombre' => $maquinaId,
                    'Departamento' => $departamento,
                    'DepartamentoOrigen' => 'Tejido',
                ];
            });

        $urdidoEngomado = URDCatalogoMaquina::query()
            ->whereIn('Departamento', ['Urdido', 'Engomado'])
            ->get(['MaquinaId', 'Nombre', 'Departamento'])
            ->map(function (URDCatalogoMaquina $item) use ($departamento): array {
                $maquinaId = trim((string) $item->MaquinaId);
                $origen = strcasecmp(trim((string) $item->Departamento), 'Engomado') === 0
                    ? 'Engomado'
                    : 'Urdido';

                return [
                    'MaquinaId' => $maquinaId,
                    'Nombre' => trim((string) $item->Nombre) ?: $maquinaId,
                    'Departamento' => $departamento,
                    'DepartamentoOrigen' => $origen,
                ];
            });

        $ordenGrupos = ['Tejido' => 0, 'Urdido' => 1, 'Engomado' => 2];

        return $telares
            ->concat($urdidoEngomado)
            ->filter(fn (array $maquina): bool => $maquina['MaquinaId'] !== '')
            ->unique(fn (array $maquina): string => mb_strtoupper($maquina['MaquinaId']))
            ->sort(function (array $left, array $right) use ($ordenGrupos): int {
                $grupo = ($ordenGrupos[$left['DepartamentoOrigen']] ?? 99)
                    <=> ($ordenGrupos[$right['DepartamentoOrigen']] ?? 99);

                return $grupo !== 0
                    ? $grupo
                    : strnatcasecmp($left['MaquinaId'], $right['MaquinaId']);
            })
            ->values();
    }

    /**
     * Departamentos de CatParosFallas que aplican al departamento elegido.
     *
     * - Los departamentos de tejido reutilizan el catálogo "Tejido".
     * - Calidad consulta "Calidad" y también "Tejido" por compatibilidad con catálogos anteriores.
     *
     * @return list<string>
     */
    private function catalogoDepartamentos(string $departamento): array
    {
        $depUpper = strtoupper(trim($departamento));

        if (in_array($depUpper, ['JACQUARD', 'ITEMA', 'KARL MAYER', 'KARLMAYER', 'SMITH', 'TEJEDORES', 'TRMA', 'TRAMA', 'DESARROLLADORES', 'SUPERVISORES'], true)) {
            return ['Tejido'];
        }

        if ($depUpper === 'CALIDAD') {
            return ['Calidad', 'Tejido'];
        }

        return [$departamento];
    }

    /**
     * Tipos de falla disponibles para un departamento.
     *
     * Se derivan de CatParosFallas para no ofrecer tipos que dejarían el combo de
     * fallas vacío. "Calidad" se añade siempre: sus fallas se pueden reportar desde
     * cualquier departamento.
     */
    public function tiposFalla(string $departamento): JsonResponse
    {
        try {
            $departamentosConsulta = array_unique([
                ...$this->catalogoDepartamentos($departamento),
                'Calidad',
            ]);

            $tiposFalla = CatParosFallas::query()
                ->whereIn('Departamento', $departamentosConsulta)
                ->whereNotNull('TipoFallaId')
                ->distinct()
                ->orderBy('TipoFallaId')
                ->pluck('TipoFallaId');

            return response()->json([
                'success' => true,
                'data' => $tiposFalla,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener tipos de falla', [
                'departamento' => $departamento,
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
     * Fallas por departamento desde CatParosFallas.
     *
     * Si se proporciona tipoFallaId, se filtran las fallas por ese tipo.
     */
    public function fallas(string $departamento, ?string $tipoFallaId = null): JsonResponse
    {
        try {
            $departamentosConsulta = $this->catalogoDepartamentos($departamento);

            // Las fallas de tipo "Calidad" sólo existen bajo el departamento Calidad,
            // así que se agregan sin importar el departamento seleccionado.
            if (strtoupper(trim((string) $tipoFallaId)) === 'CALIDAD') {
                $departamentosConsulta[] = 'Calidad';
            }

            $query = CatParosFallas::query()
                ->whereIn('Departamento', array_unique($departamentosConsulta));

            if (! empty($tipoFallaId)) {
                $query->where('TipoFallaId', $tipoFallaId);
            }

            $items = $query
                ->orderByRaw('CASE WHEN Departamento = ? THEN 0 ELSE 1 END', ['Calidad'])
                ->orderBy('Falla')
                ->get(['Id', 'Falla', 'Descripcion', 'Abreviado', 'Seccion', 'TipoFallaId', 'Departamento'])
                ->unique(function ($item) {
                    return mb_strtoupper(trim((string) $item->Falla))
                        .'|'
                        .mb_strtoupper(trim((string) ($item->Descripcion ?? '')));
                })
                ->values();

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
     * - Urdido -> UrdProgramaUrdido (Status = 'En Proceso', por MaquinaId)
     * - Engomado -> EngProgramaEngomado (Status = 'En Proceso', por MaquinaEng)
     * - Resto -> ReqProgramaTejido (EnProceso = 1, por telar)
     *
     * El telar ya identifica su salón, así que no se filtra por SalonTejidoId.
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

            $rows = DB::table('ReqProgramaTejido')
                ->where('NoTelarId', $maquina)
                ->where('EnProceso', 1)
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
     *
     * La falla llega como Id de CatParosFallas: Falla, Descripcion y TipoFallaId se
     * leen del catálogo para que el par guardado siempre exista en él.
     * Fecha y hora las pone el servidor, no el formulario: la pantalla puede llevar
     * horas abierta.
     */
    public function store(Request $request, ParoTelegramNotifier $notifier): JsonResponse
    {
        try {
            $usuario = Auth::user();

            if (! $usuario) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado',
                ], 401);
            }

            // Largos alineados con las columnas de ManFallasParos.
            // Mensajes en español: la app corre con APP_LOCALE=en y el front muestra
            // este texto tal cual al operador.
            $datos = $request->validate([
                'depto' => 'required|string|max:15',
                'maquina' => 'required|string|max:15',
                'falla_id' => 'required|integer',
                'orden_trabajo' => 'nullable|string|max:50',
                'obs' => 'nullable|string|max:255',
            ], [
                'depto.required' => 'Selecciona un departamento.',
                'maquina.required' => 'Selecciona una máquina.',
                'falla_id.required' => 'Selecciona una falla.',
                'falla_id.integer' => 'La falla seleccionada no es válida.',
                'orden_trabajo.max' => 'La orden de trabajo no puede pasar de 50 caracteres.',
                'obs.max' => 'Las observaciones no pueden pasar de 255 caracteres.',
            ]);

            $falla = CatParosFallas::find($datos['falla_id']);

            if (! $falla) {
                return response()->json([
                    'success' => false,
                    'error' => 'La falla seleccionada ya no existe en el catálogo. Recarga la página.',
                ], 422);
            }

            $ahora = now();

            // Reportar un paro SIEMPRE notifica al supervisor. La vista tenía un
            // checkbox que se volvía a marcar solo si lo desmarcabas, o sea que la
            // opción nunca existió de verdad; la regla vive aquí y no en el formulario.
            $notificarSupervisor = true;

            $paro = DB::connection('sqlsrv')->transaction(function () use ($datos, $falla, $usuario, $ahora, $notificarSupervisor): ManFallasParos {
                // ponytail: el chequeo vive dentro de la transacción para acotar la
                // ventana, no para cerrarla. El candado real es un índice único
                // filtrado sobre (MaquinaId, TipoFallaId) WHERE Estatus='Activo'.
                if (ManFallasParos::hayActivoEnMaquina($datos['maquina'], (string) $falla->TipoFallaId)) {
                    throw ValidationException::withMessages([
                        'falla_id' => 'No se puede reportar: ya existe un paro activo con el mismo tipo de falla en este telar. Finalice el paro actual antes de reportar otro igual.',
                    ]);
                }

                $folio = FolioHelper::obtenerSiguienteFolio('ParosFallas', 5);

                if (trim($folio) === '') {
                    throw new RuntimeException('Error al generar folio');
                }

                return ManFallasParos::create([
                    'Folio' => $folio,
                    'Estatus' => 'Activo',
                    'Fecha' => $ahora->toDateString(),
                    'Hora' => $ahora->format('H:i:s'),
                    'Depto' => $datos['depto'],
                    'MaquinaId' => $datos['maquina'],
                    'TipoFallaId' => $falla->TipoFallaId,
                    'Falla' => $falla->Falla,
                    'Descripcion' => $falla->Descripcion,
                    'OrdenTrabajo' => $datos['orden_trabajo'] ?? null,
                    'Obs' => $datos['obs'] ?? null,
                    'CveEmpl' => $usuario->numero_empleado ?? null,
                    'NomEmpl' => $usuario->nombre ?? null,
                    'Turno' => TurnoHelper::resolverTurnoOperativo($usuario->turno ?? null),
                    'Enviado' => $notificarSupervisor,
                    'HoraFin' => null,
                    'CveAtendio' => null,
                    'NomAtendio' => null,
                    'TurnoAtendio' => null,
                ]);
            });

            if ($notificarSupervisor) {
                // Fuera del ciclo de respuesta: Telegram no debe hacer esperar al operador.
                defer(
                    static fn () => $notifier->notifyCreated($paro),
                    name: 'paro-telegram-alta-'.$paro->Id,
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Paro reportado correctamente'.($notificarSupervisor ? ' y notificación enviada a Telegram' : ''),
                'data' => [
                    'folio' => $paro->Folio,
                    'id' => $paro->Id,
                    'notificacion_enviada' => $notificarSupervisor,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => collect($e->errors())->flatten()->first() ?? 'Error de validación',
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
     * Área (departamento) del usuario en sesión; debe coincidir con ManFallasParos.Depto.
     */
    private function areaUsuarioAutenticado(): string
    {
        $usuario = Auth::user();
        if (! $usuario) {
            return '';
        }

        $area = trim((string) ($usuario->area ?? ''));
        if ($area !== '') {
            return $area;
        }

        if ($usuario->idusuario) {
            $desdeSys = SYSUsuario::where('idusuario', $usuario->idusuario)->value('area');

            return trim((string) ($desdeSys ?? ''));
        }

        return '';
    }

    /**
     * Usuario del área Tejedores: en reporte de paros solo deben verse solicitudes de sus telares (TelTelaresOperador).
     */
    private function usuarioEsAreaTejedores(): bool
    {
        return strcasecmp(trim($this->areaUsuarioAutenticado()), 'Tejedores') === 0;
    }

    /**
     * Telares (NoTelarId) asignados al usuario actual en TelTelaresOperador.
     *
     * @return list<string|int>
     */
    private function idsTelaresAsignadosOperadorActual(): array
    {
        $numeroEmpleado = Auth::user()?->numero_empleado;
        if ($numeroEmpleado === null || trim((string) $numeroEmpleado) === '') {
            return [];
        }

        return TelTelaresOperador::query()
            ->where('numero_empleado', $numeroEmpleado)
            ->whereNotNull('NoTelarId')
            ->distinct()
            ->pluck('NoTelarId')
            ->map(fn ($id) => is_string($id) ? trim($id) : $id)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Si el usuario es de Tejedores, limita ManFallasParos.MaquinaId a los NoTelarId asignados en TelTelaresOperador.
     *
     * No aplica cuando elige otro departamento en el filtro. Con alcance=todos solo acota filas con Depto Tejedores.
     */
    private function aplicarRestriccionTelaresOperadorSiCorresponde(
        $query,
        string $alcance,
        string $deptoReq
    ): void {
        if (! $this->usuarioEsAreaTejedores()) {
            return;
        }

        $telares = $this->idsTelaresAsignadosOperadorActual();

        if ($alcance === 'todos') {
            if ($telares === []) {
                $query->whereRaw('UPPER(LTRIM(RTRIM(Depto))) <> ?', ['TEJEDORES']);

                return;
            }

            $query->where(function ($q) use ($telares) {
                $q->whereRaw('UPPER(LTRIM(RTRIM(Depto))) <> ?', ['TEJEDORES'])
                    ->orWhereIn('MaquinaId', $telares);
            });

            return;
        }

        $consultaSoloTejedores = false;
        if ($deptoReq !== '') {
            $consultaSoloTejedores = strcasecmp(trim($deptoReq), 'Tejedores') === 0;
        } else {
            $consultaSoloTejedores = true;
        }

        if (! $consultaSoloTejedores) {
            return;
        }

        if ($telares === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('MaquinaId', $telares);
    }

    /**
     * Lista de paros para el reporte de solicitudes.
     *
     * Sin query: solo paros del departamento del usuario (`area`).
     * `alcance=todos`: todos los departamentos.
     * `depto={nombre}`: solo ese departamento (debe existir en SysDepartamentos).
     * `incluir_finalizados=1`: incluye paros cerrados de los últimos `dias` (7 por
     * defecto, máx. 365). Sin él solo se devuelven los `Activo`, que son pocos.
     * Usuarios con área Tejedores: además solo ven paros cuyo `MaquinaId` está en `TelTelaresOperador` para su `numero_empleado`
     * (salvo que filtren explícitamente otro departamento; con `alcance=todos` solo se acotan filas `Depto` Tejedores).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ManFallasParos::query()
                ->orderByDesc('Fecha')
                ->orderByDesc('Hora');

            $alcance = trim((string) $request->query('alcance', ''));
            $deptoReq = trim((string) $request->query('depto', ''));
            $incluirFinalizados = $request->boolean('incluir_finalizados');
            $dias = null;

            if ($incluirFinalizados) {
                // La tabla lleva miles de paros cerrados y la vista los pinta todos en
                // el DOM, así que el histórico se acota por fecha en vez de truncarse en
                // silencio; la ventana viaja en `meta` para que la UI pueda decirla.
                // Los Activo se muestran siempre: cerrarlos es el motivo de la pantalla
                // y un paro abierto desde hace meses no debe desaparecer del listado.
                $dias = max(1, min(365, (int) $request->query('dias', self::DIAS_HISTORICO_DEFAULT)));
                $desde = now()->subDays($dias)->toDateString();

                $query->where(function ($q) use ($desde) {
                    $q->where('Estatus', 'Activo')
                        ->orWhere('Fecha', '>=', $desde);
                });
            } else {
                $query->where('Estatus', 'Activo');
            }

            if ($alcance === 'todos') {
                // Sin filtro por departamento
            } elseif ($deptoReq !== '') {
                $departamentoValido = SysDepartamento::query()
                    ->where('Depto', $deptoReq)
                    ->exists();

                if ($departamentoValido) {
                    $query->where('Depto', $deptoReq);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } else {
                $areaUsuario = $this->areaUsuarioAutenticado();
                if ($areaUsuario !== '') {
                    $query->where('Depto', $areaUsuario);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            $this->aplicarRestriccionTelaresOperadorSiCorresponde($query, $alcance, $deptoReq);

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
                'meta' => [
                    'incluye_finalizados' => $incluirFinalizados,
                    'dias' => $dias,
                    'total' => $paros->count(),
                ],
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
    public function finalizar(
        Request $request,
        int $id,
        ParoTelegramNotifier $notifier,
        CalificacionParoService $calificaciones,
    ): JsonResponse {
        try {
            $paro = ManFallasParos::find($id);

            if (! $paro) {
                return response()->json([
                    'success' => false,
                    'error' => 'Paro no encontrado',
                ], 404);
            }

            // Solo se cierran paros activos: recerrar uno ya terminado pisaría
            // HoraFin, FechaFin, NomAtendio y Calidad, y se perdería el registro
            // real de cuándo y quién lo atendió. La columna es NVARCHAR, así que
            // se compara sin distinguir mayúsculas ni espacios sobrantes.
            if (strcasecmp(trim((string) $paro->Estatus), 'Activo') !== 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Este paro ya fue finalizado.',
                ], 422);
            }

            $datos = $request->validate([
                'atendio' => 'required|string|max:150',
                'turno' => 'nullable|integer|in:1,2,3,4',
                'calidad' => 'required|integer|min:1|max:5',
                'obs_cierre' => 'nullable|string|max:255',
            ], [
                'atendio.required' => 'Indica quién atendió el paro.',
                'calidad.required' => 'Califica la atención del paro.',
                'calidad.min' => 'La calificación debe ser de al menos 1.',
                'calidad.max' => 'La calificación no puede pasar de 5.',
                'obs_cierre.max' => 'Las observaciones de cierre no pueden pasar de 255 caracteres.',
            ]);

            $usuario = Auth::user();
            $ahora = now();

            $updateData = [
                'Estatus' => 'Terminado',
                'HoraFin' => $ahora->format('H:i:s'),
                'FechaFin' => $ahora->toDateString(),
                'NomAtendio' => $datos['atendio'],
                'CveAtendio' => $usuario->numero_empleado ?? null,
                'Calidad' => (int) $datos['calidad'],
            ];

            if ($request->filled('turno')) {
                $updateData['TurnoAtendio'] = (int) $datos['turno'];
            }

            if ($request->filled('obs_cierre')) {
                $updateData['ObsCierre'] = $datos['obs_cierre'];
            }

            // El cierre del paro y la calificación que hereda a sus órdenes de
            // trabajo van juntos: un paro cerrado cuya OT quedó sin calificar
            // deja al tejedor esperando una captura que ya nadie le va a pedir.
            $ordenesCalificadas = DB::connection('sqlsrv')->transaction(
                function () use ($paro, $updateData, $calificaciones): int {
                    $paro->update($updateData);
                    $paro->refresh();

                    return $calificaciones->propagarAOrdenesDelParo($paro);
                }
            );

            // Cerrar un paro también notifica siempre, igual que reportarlo: el
            // checkbox de la vista tampoco se podía desmarcar.
            $cerradoPor = $usuario->nombre ?? null;
            defer(
                static fn () => $notifier->notifyClosed($paro, $cerradoPor),
                name: 'paro-telegram-cierre-'.$paro->Id,
            );

            return response()->json([
                'success' => true,
                'message' => 'Paro finalizado correctamente y notificación enviada a Telegram'
                    .($ordenesCalificadas > 0
                        ? '. Se calificaron '.$ordenesCalificadas.($ordenesCalificadas === 1 ? ' orden de trabajo' : ' órdenes de trabajo').' con esta calificación'
                        : ''),
                'data' => [
                    'id' => $paro->Id,
                    'folio' => $paro->Folio,
                    'notificacion_enviada' => true,
                    'ordenes_calificadas' => $ordenesCalificadas,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => collect($e->errors())->flatten()->first() ?? 'Error de validación',
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
