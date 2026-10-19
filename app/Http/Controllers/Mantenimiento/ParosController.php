<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Helpers\FolioHelper;
use App\Helpers\TurnoHelper;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManFallasParos;
use App\Services\Mantenimiento\ParosCatalogoService;
use App\Services\Mantenimiento\ParoTelegramNotifier;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
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
     * La lista (departamentos de sus máquinas asignadas) viaja en la página: se ahorra una petición.
     */
    public function nuevoParo()
    {
        return view('modulos.mantenimiento.nuevo-paro.index', [
            // El usuario autenticado ya es la fila de dbo.SYSUsuario: no se vuelve a consultar.
            'areaUsuario' => Auth::user()->area ?? null,
            'departamentos' => $this->catalogo->departamentos(Auth::user()),
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

            [$datos, $falla] = $this->validarAlta($request, $usuario);
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
    private function validarAlta(Request $request, Authenticatable $usuario): array
    {
        // Largos alineados con las columnas de ManFallasParos.
        // Mensajes en español: la app corre con APP_LOCALE=en y el front muestra
        // este texto tal cual al operador.
        $datos = $request->validate([
            // Closure y no Rule::in: la lista se consulta solo si el campo llegó, y no al armar las reglas.
            'depto' => ['bail', 'required', 'string', 'max:15', function (string $atributo, mixed $valor, \Closure $falla) use ($usuario): void {
                if (! $this->catalogo->puedeReportarEn((string) $valor, $usuario)) {
                    $falla('No tienes ese departamento asignado.');
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

        if (! $this->catalogo->esMaquinaDe($datos['depto'], $datos['maquina'], $usuario)) {
            throw ValidationException::withMessages([
                'maquina' => 'Esa máquina no es del departamento elegido.',
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
     * Sin query: los paros de sus máquinas asignadas (de sus departamentos en SYSDepartamentosAsignacion); sin asignación,
     * o en Atadores/Calidad, los del departamento del usuario (`area`).
     * `alcance=todos`: todos los departamentos.
     * `depto={nombre}`: solo ese departamento.
     * Quien tiene máquinas asignadas nunca ve paros de otras, en ningún modo: por máquina y no
     * por `Depto`, así salen también los históricos guardados como "Tejedores".
     * `incluir_finalizados=1`: incluye paros cerrados de los últimos `dias` (30 por
     * defecto, máx. 365). Sin él solo se devuelven los `Activo`, que son pocos.
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

            $this->filtrarPorAlcance($query, $alcance, $deptoReq);

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

    /** Máquinas asignadas siempre; luego `depto` pedido o, sin asignación ni `alcance=todos`, el área del usuario. */
    private function filtrarPorAlcance(Builder $query, string $alcance, string $deptoReq): void
    {
        $usuario = Auth::user();
        $asignadas = $this->catalogo->esPorArea($usuario) ? [] : $this->catalogo->maquinasAsignadas($usuario);

        if ($asignadas !== []) {
            $query->whereIn('MaquinaId', $asignadas);
        }

        if ($deptoReq !== '' && $alcance !== 'todos') {
            $query->where('Depto', $deptoReq);
        } elseif ($alcance !== 'todos' && $asignadas === []) {
            $areaUsuario = trim((string) ($usuario->area ?? ''));
            $areaUsuario !== '' ? $query->where('Depto', $areaUsuario) : $query->whereRaw('1 = 0');
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

            if (! $this->catalogo->puedeVerParo((string) $paro->MaquinaId, Auth::user())) {
                return response()->json([
                    'success' => false,
                    'error' => 'Ese paro es de una máquina que no tienes asignada.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => $paro,
            ]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al obtener paro/falla', 'No se pudo cargar el paro.', context: ['id' => $id]);
        }
    }
}
