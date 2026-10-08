<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Helpers\FolioHelper;
use App\Helpers\TurnoHelper;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Sistema\SysDepartamento;
use App\Models\Tejedores\TelTelaresOperador;
use App\Services\Mantenimiento\ParosCatalogoService;
use App\Services\Mantenimiento\ParoTelegramNotifier;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Alta y consulta de paros (ManFallasParos). Los catálogos de la cascada viven en
 * ParosCatalogoController y el cierre en ParosCierreController.
 */
class ParosController extends Controller
{
    use HandlesApiErrors;

    /** Días de histórico que devuelve el listado cuando se piden paros finalizados. */
    private const DIAS_HISTORICO_DEFAULT = 30;

    public function __construct(private readonly ParosCatalogoService $catalogo) {}

    /**
     * Vista de nuevo paro con el departamento del usuario preseleccionado.
     * La lista de departamentos viaja en la página: se ahorra una petición al abrirla.
     */
    public function nuevoParo()
    {
        return view('modulos.mantenimiento.nuevo-paro.index', [
            // El usuario autenticado ya es la fila de dbo.SYSUsuario: no se vuelve a consultar.
            'areaUsuario' => Auth::user()->area ?? null,
            'departamentos' => $this->catalogo->departamentos(),
        ]);
    }

    /**
     * Guardar un nuevo paro/falla en ManFallasParos.
     *
     * La falla llega como Id de CatParosFallas: Falla, Descripcion y TipoFallaId se
     * leen del catálogo para que el par guardado siempre exista en él. Departamento,
     * máquina y falla se validan entre sí con las mismas reglas que arman el combo en
     * cascada. Fecha y hora las pone el servidor, no el formulario: la pantalla puede
     * llevar horas abierta.
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

            [$datos, $falla] = $this->validarAlta($request, $usuario->numero_empleado ?? null);
            $ahora = now();

            $paro = DB::connection('sqlsrv')->transaction(function () use ($datos, $falla, $usuario, $ahora): ManFallasParos {
                // ponytail: el chequeo vive dentro de la transacción para acotar la
                // ventana (holdlock sobre IX_ManFallasParos_Estatus_MaquinaId), no para
                // cerrarla con una restricción única en la base.
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
                    'Enviado' => true,
                    'HoraFin' => null,
                    'CveAtendio' => null,
                    'NomAtendio' => null,
                    'TurnoAtendio' => null,
                ]);
            });

            // Encola el aviso (Telegram no debe hacer esperar al operador) y dice si de
            // verdad hay a quién avisar: sin token, sin canal para el tipo de falla o sin
            // destinatarios no sale nada, y el operador no debe creer que sí.
            $notificado = $notifier->notifyCreated($paro);

            if (! $notificado) {
                // Un fallo aquí no debe tirar la respuesta: el paro ya está guardado.
                rescue(fn () => $paro->update(['Enviado' => false]));
            }

            return response()->json([
                'success' => true,
                'message' => $notificado
                    ? 'Paro reportado correctamente y notificación enviada a Telegram'
                    : 'Paro reportado correctamente, pero no se pudo avisar por Telegram: avisa al supervisor directamente.',
                'data' => [
                    'folio' => $paro->Folio,
                    'id' => $paro->Id,
                    'notificacion_enviada' => $notificado,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => collect($e->errors())->flatten()->first() ?? 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al guardar paro/falla', 'No se pudo guardar el paro. Intenta de nuevo.');
        }
    }

    /**
     * Forma de la solicitud y coherencia entre departamento, máquina y falla.
     *
     * @return array{0: array{depto: string, maquina: string, falla_id: int|string, orden_trabajo?: string|null, obs?: string|null}, 1: CatParosFallas}
     */
    private function validarAlta(Request $request, ?string $numeroEmpleado): array
    {
        // Largos alineados con las columnas de ManFallasParos.
        // Mensajes en español: la app corre con APP_LOCALE=en y el front muestra
        // este texto tal cual al operador.
        $datos = $request->validate([
            // Closure y no Rule::in: la lista se consulta solo si el campo llegó, y no al armar las reglas.
            'depto' => ['bail', 'required', 'string', 'max:15', function (string $atributo, mixed $valor, \Closure $falla): void {
                if (! in_array($valor, $this->catalogo->departamentos(), true)) {
                    $falla('Ese departamento no puede reportar paros.');
                }
            }],
            'maquina' => 'required|string|max:15',
            'falla_id' => 'required|integer|exists:sqlsrv.CatParosFallas,Id',
            'orden_trabajo' => 'nullable|string|max:20|regex:/^\S+$/',
            'obs' => 'nullable|string|max:255',
        ], [
            'depto.required' => 'Selecciona un departamento.',
            'maquina.required' => 'Selecciona una máquina.',
            'falla_id.required' => 'Selecciona una falla.',
            'falla_id.integer' => 'La falla seleccionada no es válida.',
            'falla_id.exists' => 'La falla seleccionada ya no existe en el catálogo. Recarga la página.',
            'orden_trabajo.max' => 'La orden de trabajo no puede pasar de 20 caracteres.',
            'orden_trabajo.regex' => 'La orden de trabajo no puede llevar espacios.',
            'obs.max' => 'Las observaciones no pueden pasar de 255 caracteres.',
        ]);

        if (! $this->catalogo->esMaquinaDe($datos['depto'], $datos['maquina'], $numeroEmpleado)) {
            throw ValidationException::withMessages([
                'maquina' => 'Esa máquina no pertenece al departamento elegido.',
            ]);
        }

        $falla = CatParosFallas::findOrFail($datos['falla_id']);

        if (! $this->catalogo->fallaAplicaA($datos['depto'], $falla)) {
            throw ValidationException::withMessages([
                'falla_id' => 'Esa falla no corresponde al departamento elegido.',
            ]);
        }

        return [$datos, $falla];
    }

    /**
     * Lista de paros para el reporte de solicitudes.
     *
     * Sin query: solo paros del departamento del usuario (`area`).
     * `alcance=todos`: todos los departamentos.
     * `depto={nombre}`: solo ese departamento (debe existir en SysDepartamentos).
     * `incluir_finalizados=1`: incluye paros cerrados de los últimos `dias` (30 por
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
            return $this->apiErrorResponse($e, 'Error al obtener paros/fallas', 'No se pudieron cargar los paros.');
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
            return $this->apiErrorResponse($e, 'Error al obtener paro/falla', 'No se pudo cargar el paro.', context: ['id' => $id]);
        }
    }

    /**
     * Área (departamento) del usuario en sesión; debe coincidir con ManFallasParos.Depto.
     */
    private function areaUsuarioAutenticado(): string
    {
        // Usuario ya es dbo.SYSUsuario: releer `area` de la misma fila era una consulta extra.
        return trim((string) (Auth::user()->area ?? ''));
    }

    /**
     * Usuario del área Tejedores: en reporte de paros solo deben verse solicitudes de sus telares (TelTelaresOperador).
     */
    private function usuarioEsAreaTejedores(): bool
    {
        return strcasecmp($this->areaUsuarioAutenticado(), 'Tejedores') === 0;
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
    private function aplicarRestriccionTelaresOperadorSiCorresponde($query, string $alcance, string $deptoReq): void
    {
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

        $consultaSoloTejedores = $deptoReq === '' || strcasecmp($deptoReq, 'Tejedores') === 0;

        if (! $consultaSoloTejedores) {
            return;
        }

        if ($telares === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('MaquinaId', $telares);
    }
}
