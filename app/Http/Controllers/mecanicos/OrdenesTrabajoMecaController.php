<?php

namespace App\Http\Controllers\mecanicos;

use App\Helpers\FolioHelper;
use App\Helpers\TurnoHelper;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Models\Sistema\SSYSFoliosSecuencia;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Urdido\URDCatalogoMaquina;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrdenesTrabajoMecaController extends Controller
{
    private const MODULO_PERMISO = 'Ordenes de Trabajo';

    private const MODULO_FOLIOS = 'Mecanicos';

    private const PREFIJO_FOLIOS = 'MEC';

    private const LONGITUD_CONSECUTIVO_FOLIOS = 5;

    private const ESTATUS_ACTIVO = 'Activo';

    private const ESTATUS_TERMINADO = 'Terminado';

    private const ESTATUS_CALIFICADO = 'Calificado';

    private const ESTATUS_AUTORIZADO = 'Autorizado';

    private const ESTATUS_CANCELADO = 'Cancelado';

    private const HORAS_HISTORIAL_PAROS = 12;

    public function index(): View
    {
        $modoTejedor = $this->esModoTejedorSoloCalificacion();
        $permisos = $this->permisosVista();

        return view('modulos.mecanicos.ordenes-trabajo.index', [
            'telares' => $this->catalogoTelares(),
            'operadores' => $this->operadoresMecanicos(),
            'esTejedor' => $this->esTejedor(),
            'modoTejedor' => $modoTejedor,
            'esSupervisor' => $permisos['puedeRegistrar'],
            'puedeCrear' => $permisos['puedeCrear'] && ! $modoTejedor,
            'puedeEditar' => $permisos['puedeModificar'] && ! $modoTejedor,
            'puedeEliminar' => $permisos['puedeEliminar'] && ! $modoTejedor,
            'puedeRegistrar' => $permisos['puedeRegistrar'],
            'puedeFinalizar' => $this->puedeFinalizarComoMecanico(),
            'puedeCalificar' => $this->puedeCalificarComoTejedor(),
            'turnoSugerido' => (int) TurnoHelper::getTurnoActual(),
            'fechaSugerida' => $this->ahoraPlanta()->toDateString(),
            'horaSugerida' => $this->ahoraPlanta()->format('H:i'),
        ]);
    }

    public function captura(string $folio): View
    {
        $orden = MecOrdenTrabajoModel::query()
            ->with(['lineas' => fn ($query) => $query->orderBy('Id')])
            ->find($folio);

        abort_unless($orden, 404);
        abort_unless($this->tejedorPuedeVerOrden($orden), 403);

        $usuario = Auth::user();
        $modoTejedor = $this->esModoTejedorSoloCalificacion();
        $permisos = $this->permisosVista();
        $estatus = (string) ($orden->Estatus ?: self::ESTATUS_ACTIVO);
        $bloqueadaEdicion = $this->estatusBloqueaEdicionMecanico($estatus);
        $bloqueadaTotal = $estatus === self::ESTATUS_AUTORIZADO;

        return view('modulos.mecanicos.ordenes-trabajo.captura', [
            'orden' => $orden,
            'operadores' => $this->operadoresMecanicos(),
            'esTejedor' => $this->esTejedor(),
            'modoTejedor' => $modoTejedor,
            'esSupervisor' => $permisos['puedeRegistrar'],
            'puedeCrear' => $permisos['puedeCrear'] && ! $modoTejedor && ! $bloqueadaEdicion,
            'puedeEditar' => $permisos['puedeModificar'] && ! $modoTejedor && ! $bloqueadaEdicion,
            'puedeEliminar' => $permisos['puedeEliminar'] && ! $modoTejedor && ! $bloqueadaEdicion,
            'puedeRegistrar' => $permisos['puedeRegistrar'],
            'puedeFinalizar' => $this->puedeFinalizarComoMecanico() && $estatus === self::ESTATUS_ACTIVO,
            'puedeCalificar' => $this->puedeCalificarComoTejedor() && $estatus === self::ESTATUS_TERMINADO,
            'puedeAutorizar' => $permisos['puedeRegistrar'] && $estatus === self::ESTATUS_CALIFICADO,
            'bloqueada' => $bloqueadaTotal,
            'bloqueadaEdicion' => $bloqueadaEdicion,
            'tejedorCve' => trim((string) ($usuario->numero_empleado ?? '')),
            'tejedorNombre' => trim((string) ($usuario->nombre ?? '')),
            'usuarioCapturaCve' => trim((string) ($usuario->numero_empleado ?? '')),
            'usuarioCapturaNombre' => trim((string) ($usuario->nombre ?? '')),
            'turnoSugerido' => (int) TurnoHelper::getTurnoActual(),
            'fechaSugerida' => $this->ahoraPlanta()->toDateString(),
        ]);
    }

    public function registros(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'fecha' => ['nullable', 'date'],
            'estatus' => ['nullable', 'string', 'max:15'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $buscar = trim((string) ($datos['buscar'] ?? ''));

        $ordenes = MecOrdenTrabajoModel::query()
            ->with(['lineas' => fn ($query) => $query->orderBy('Id')])
            ->withCount('lineas')
            ->when($datos['fecha'] ?? null, fn ($query, $fecha) => $query->whereDate('Fecha', $fecha))
            ->when($datos['estatus'] ?? null, fn ($query, $estatus) => $query->where('Estatus', $estatus))
            ->when($buscar !== '', function ($query) use ($buscar) {
                $query->where(function ($subquery) use ($buscar) {
                    $subquery->where('Folio', 'like', "%{$buscar}%")
                        ->orWhere('TelarId', 'like', "%{$buscar}%")
                        ->orWhere('FolioParo', 'like', "%{$buscar}%")
                        ->orWhere('Falla', 'like', "%{$buscar}%")
                        ->orWhere('Orden', 'like', "%{$buscar}%");
                });
            })
            ->tap(fn (Builder $query) => $this->aplicarFiltroTelaresTejedor($query))
            ->orderByDesc('Fecha')
            ->orderByDesc('Folio')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ordenes,
        ]);
    }

    public function parosActivos(): JsonResponse
    {
        $foliosYaUsados = MecOrdenTrabajoModel::query()
            ->whereNotNull('FolioParo')
            ->where('FolioParo', '<>', '')
            ->pluck('FolioParo');

        $paros = ManFallasParos::query()
            ->where('Estatus', 'Activo')
            ->whereNotIn('Folio', $foliosYaUsados)
            ->orderByDesc('Fecha')
            ->orderByDesc('Hora')
            ->get([
                'Id',
                'Folio',
                'Fecha',
                'Hora',
                'MaquinaId',
                'Falla',
                'Descripcion',
                'OrdenTrabajo',
                'Turno',
                'Depto',
            ])
            ->map(function ($paro) {
                $paro->FallaTexto = $this->textoFalla($paro->Falla, $paro->Descripcion);

                return $paro;
            });

        return response()->json([
            'success' => true,
            'data' => $paros,
        ]);
    }

    /**
     * Historial de paros elegibles de un telar para crear una OT.
     *
     * Solo incluye paros de las últimas 12 horas. Se excluyen los folios ya
     * vinculados a otra orden para que la UI no ofrezca una selección que el
     * backend necesariamente rechazaría.
     */
    public function parosHistorial(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'TelarId' => ['required', 'string', 'max:50'],
        ]);

        $telarId = trim((string) $datos['TelarId']);
        $foliosYaUsados = MecOrdenTrabajoModel::query()
            ->whereNotNull('FolioParo')
            ->where('FolioParo', '<>', '')
            ->pluck('FolioParo');

        $paros = ManFallasParos::query()
            ->where('MaquinaId', $telarId)
            ->whereNotNull('Folio')
            ->where('Folio', '<>', '')
            ->whereNotIn('Folio', $foliosYaUsados)
            ->tap(fn (Builder $query) => $this->aplicarFiltroParosUltimasHoras($query))
            ->orderByDesc('Fecha')
            ->orderByDesc('Hora')
            ->get([
                'Id',
                'Folio',
                'Fecha',
                'Hora',
                'MaquinaId',
                'Falla',
                'Descripcion',
                'OrdenTrabajo',
                'Turno',
                'Estatus',
                'Obs',
                'ObsCierre',
            ])
            ->map(function (ManFallasParos $paro): ManFallasParos {
                $paro->FallaTexto = $this->textoFalla($paro->Falla, $paro->Descripcion);
                $paro->ComentariosTexto = $this->textoComentariosParo($paro);

                return $paro;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $paros,
            'captura_manual_permitida' => $paros->isEmpty(),
        ]);
    }

    public function show(string $folio): JsonResponse
    {
        $orden = MecOrdenTrabajoModel::query()
            ->with(['lineas' => fn ($query) => $query->orderBy('Id')])
            ->find($folio);

        if (! $orden) {
            return $this->ordenNoEncontrada();
        }

        if (! $this->tejedorPuedeVerOrden($orden)) {
            return response()->json([
                'success' => false,
                'error' => 'No tienes acceso a esta orden: el telar no está asignado a tu usuario.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $orden,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($respuesta = $this->respuestaSinPermiso('crear', 'No tienes permiso para crear órdenes de trabajo.')) {
            return $respuesta;
        }

        if ($respuesta = $this->respuestaSiTejedorNoPuedeMutar('Los tejedores no pueden crear órdenes de trabajo.')) {
            return $respuesta;
        }

        try {
            $datos = $this->normalizarCabecera($request->validate($this->reglasCabecera()));
            $datos = $this->resolverOrigenCabecera($datos, $request->boolean('CapturaManual'));
            $datos['Fecha'] = $this->fechaCreacionFolio();

            $this->validarOrdenNoVacia($datos);
            $this->validarFolioParoDisponible($datos['FolioParo'] ?? null);

            $orden = DB::transaction(function () use ($datos): MecOrdenTrabajoModel {
                $folio = $this->siguienteFolio();

                $orden = MecOrdenTrabajoModel::create([
                    ...$datos,
                    'Folio' => $folio,
                    'Fecha' => $datos['Fecha'],
                    // Toda orden nace Activa; el estatus solo avanza por el flujo.
                    'Estatus' => self::ESTATUS_ACTIVO,
                ]);

                $this->asegurarLineaInicial($orden);

                return $orden;
            });

            $orden->load(['lineas' => fn ($query) => $query->orderBy('Id')]);

            return response()->json([
                'success' => true,
                'message' => 'Orden de trabajo creada correctamente.',
                'data' => $orden,
            ], 201);
        } catch (ValidationException $exception) {
            return $this->respuestaValidacion($exception);
        } catch (\Throwable $exception) {
            Log::error('Error al crear orden de trabajo mecánica', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo crear la orden de trabajo.',
            ], 500);
        }
    }

    public function update(Request $request, string $folio): JsonResponse
    {
        if ($respuesta = $this->respuestaSinPermiso('modificar', 'No tienes permiso para modificar órdenes de trabajo.')) {
            return $respuesta;
        }

        if ($respuesta = $this->respuestaSiTejedorNoPuedeMutar('Los tejedores no pueden modificar la cabecera de la orden.')) {
            return $respuesta;
        }

        $orden = MecOrdenTrabajoModel::find($folio);

        if (! $orden) {
            return $this->ordenNoEncontrada();
        }

        try {
            $this->asegurarEditablePorMecanico($orden);

            $datos = $this->normalizarCabecera($request->validate($this->reglasCabecera()));
            $this->validarFolioParoDisponible($datos['FolioParo'] ?? null, $folio);

            // Fecha de creación del folio y estatus de flujo no se editan aquí.
            unset($datos['Fecha'], $datos['Estatus']);

            $orden->update($datos);
            $orden->load(['lineas' => fn ($query) => $query->orderBy('Id')]);

            return response()->json([
                'success' => true,
                'message' => 'Cabecera de la orden actualizada.',
                'data' => $orden,
            ]);
        } catch (ValidationException $exception) {
            return $this->respuestaValidacion($exception);
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar orden de trabajo mecánica', [
                'folio' => $folio,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo actualizar la orden de trabajo.',
            ], 500);
        }
    }

    public function destroy(string $folio): JsonResponse
    {
        if ($respuesta = $this->respuestaSinPermiso('eliminar', 'No tienes permiso para eliminar órdenes de trabajo.')) {
            return $respuesta;
        }

        if ($respuesta = $this->respuestaSiTejedorNoPuedeMutar('Los tejedores no pueden eliminar órdenes de trabajo.')) {
            return $respuesta;
        }

        $orden = MecOrdenTrabajoModel::find($folio);

        if (! $orden) {
            return $this->ordenNoEncontrada();
        }

        if ($this->estatusBloqueaEdicionMecanico((string) ($orden->Estatus ?: self::ESTATUS_ACTIVO))) {
            return response()->json([
                'success' => false,
                'error' => 'La orden ya no se puede eliminar (finalizada, calificada o autorizada).',
            ], 422);
        }

        try {
            $orden->delete();

            return response()->json([
                'success' => true,
                'message' => 'Orden de trabajo eliminada.',
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error al eliminar orden de trabajo mecánica', [
                'folio' => $folio,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo eliminar la orden de trabajo.',
            ], 500);
        }
    }

    public function storeLinea(Request $request, string $folio): JsonResponse
    {
        if ($respuesta = $this->respuestaSinPermiso('crear', 'No tienes permiso para agregar renglones a la orden.')) {
            return $respuesta;
        }

        if ($respuesta = $this->respuestaSiTejedorNoPuedeMutar('Los tejedores no pueden agregar renglones; solo pueden calificar.')) {
            return $respuesta;
        }

        $orden = MecOrdenTrabajoModel::find($folio);

        if (! $orden) {
            return $this->ordenNoEncontrada();
        }

        try {
            $this->asegurarEditablePorMecanico($orden);

            $datosLinea = $this->filtrarCamposCalificacion(
                $this->normalizarLinea($request->validate($this->reglasLinea()))
            );
            $this->validarLineaCompleta($datosLinea);

            $linea = MecOrdenTrabajoLineModel::create([
                'Folio' => $folio,
                ...$datosLinea,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Renglón agregado correctamente.',
                'data' => $linea,
            ], 201);
        } catch (ValidationException $exception) {
            return $this->respuestaValidacion($exception);
        } catch (\Throwable $exception) {
            Log::error('Error al agregar renglón a orden de trabajo mecánica', [
                'folio' => $folio,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo agregar el renglón.',
            ], 500);
        }
    }

    public function updateLinea(Request $request, string $folio, int $linea): JsonResponse
    {
        $registro = MecOrdenTrabajoLineModel::query()
            ->where('Folio', $folio)
            ->find($linea);

        if (! $registro) {
            return response()->json([
                'success' => false,
                'error' => 'Renglón de orden no encontrado.',
            ], 404);
        }

        try {
            $orden = MecOrdenTrabajoModel::find($folio);
            if ($orden && ! $this->tejedorPuedeVerOrden($orden)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes acceso a esta orden: el telar no está asignado a tu usuario.',
                ], 403);
            }

            // Tejedor o supervisor: calificación cuando la orden ya está Finalizada (Terminado).
            if (
                $orden
                && (string) ($orden->Estatus ?: self::ESTATUS_ACTIVO) === self::ESTATUS_TERMINADO
                && $this->puedeCalificarComoTejedor()
            ) {
                $validated = $request->validate([
                    'Calificacion' => ['required', 'integer', 'between:1,10'],
                ]);

                [$cve, $nombre] = $this->datosTejedorSesion();

                $registro->update([
                    'Calificacion' => (int) $validated['Calificacion'],
                    'CveTejedor' => $cve,
                    'NomTejedor' => $nombre,
                ]);

                $orden->load(['lineas' => fn ($query) => $query->orderBy('Id')]);
                $pasoACalificado = false;

                if ($this->todasLasLineasCalificadas($orden)) {
                    $orden->update(['Estatus' => self::ESTATUS_CALIFICADO]);
                    $orden->refresh();
                    $pasoACalificado = true;
                }

                $orden->load(['lineas' => fn ($query) => $query->orderBy('Id')]);

                return response()->json([
                    'success' => true,
                    'message' => $pasoACalificado
                        ? 'Calificación guardada. La orden pasó a Calificado.'
                        : 'Calificación guardada correctamente.',
                    'data' => $registro->fresh(),
                    'orden' => $orden,
                ]);
            }

            if ($orden) {
                $this->asegurarEditablePorMecanico($orden);
            }

            if ($respuesta = $this->respuestaSinPermiso('modificar', 'No tienes permiso para modificar renglones.')) {
                return $respuesta;
            }

            $datosLinea = $this->filtrarCamposCalificacion(
                $this->normalizarLinea($request->validate($this->reglasLinea()))
            );
            $this->validarLineaCompleta($datosLinea);

            $registro->update($datosLinea);

            return response()->json([
                'success' => true,
                'message' => 'Renglón actualizado correctamente.',
                'data' => $registro->fresh(),
            ]);
        } catch (ValidationException $exception) {
            return $this->respuestaValidacion($exception);
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar renglón de orden de trabajo mecánica', [
                'folio' => $folio,
                'linea_id' => $linea,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo actualizar el renglón.',
            ], 500);
        }
    }

    public function destroyLinea(string $folio, int $linea): JsonResponse
    {
        if ($respuesta = $this->respuestaSinPermiso('eliminar', 'No tienes permiso para eliminar renglones.')) {
            return $respuesta;
        }

        if ($respuesta = $this->respuestaSiTejedorNoPuedeMutar('Los tejedores no pueden eliminar renglones.')) {
            return $respuesta;
        }

        $registro = MecOrdenTrabajoLineModel::query()
            ->where('Folio', $folio)
            ->find($linea);

        if (! $registro) {
            return response()->json([
                'success' => false,
                'error' => 'Renglón de orden no encontrado.',
            ], 404);
        }

        $orden = MecOrdenTrabajoModel::find($folio);
        if ($orden && $this->estatusBloqueaEdicionMecanico((string) $orden->Estatus)) {
            return response()->json([
                'success' => false,
                'error' => 'La orden ya no admite cambios en renglones (finalizada, calificada o autorizada).',
            ], 422);
        }

        if (MecOrdenTrabajoLineModel::where('Folio', $folio)->count() <= 1) {
            return response()->json([
                'success' => false,
                'error' => 'La orden debe conservar al menos un renglón. Elimina la orden completa si fue creada por error.',
            ], 422);
        }

        $registro->delete();

        return response()->json([
            'success' => true,
            'message' => 'Renglón eliminado correctamente.',
        ]);
    }

    /**
     * Mecánico finaliza la captura: pasa a Terminado y bloquea edición.
     * Después solo el tejedor puede calificar.
     */
    public function finalizar(string $folio): JsonResponse
    {
        if (! $this->puedeFinalizarComoMecanico()) {
            return response()->json([
                'success' => false,
                'error' => 'No tienes permiso para finalizar la orden. Se requiere modificar (mecánico) o registrar (supervisor).',
            ], 403);
        }

        $orden = MecOrdenTrabajoModel::query()
            ->with(['lineas' => fn ($query) => $query->orderBy('Id')])
            ->find($folio);

        if (! $orden) {
            return $this->ordenNoEncontrada();
        }

        if ((string) ($orden->Estatus ?: self::ESTATUS_ACTIVO) !== self::ESTATUS_ACTIVO) {
            return response()->json([
                'success' => false,
                'error' => 'Solo se pueden finalizar órdenes en estatus Activo.',
            ], 422);
        }

        if ($orden->lineas->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'La orden no tiene renglones para finalizar.',
            ], 422);
        }

        $lineasSinCaptura = $orden->lineas->filter(fn ($linea) => $this->lineaSinCaptura($linea));
        if ($lineasSinCaptura->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'Hay renglones sin captura. Completa o elimina los vacíos antes de finalizar.',
            ], 422);
        }

        try {
            $orden->update(['Estatus' => self::ESTATUS_TERMINADO]);
            $orden->load(['lineas' => fn ($query) => $query->orderBy('Id')]);

            return response()->json([
                'success' => true,
                'message' => 'Orden finalizada. Ya no se puede editar; el tejedor puede calificarla.',
                'data' => $orden,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error al finalizar orden de trabajo mecánica', [
                'folio' => $folio,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo finalizar la orden.',
            ], 500);
        }
    }

    /**
     * Autoriza una orden (permiso "registrar"). Solo si ya está Calificada.
     */
    public function autorizar(string $folio): JsonResponse
    {
        $orden = MecOrdenTrabajoModel::query()
            ->with(['lineas' => fn ($query) => $query->orderBy('Id')])
            ->find($folio);

        if (! $orden) {
            return $this->ordenNoEncontrada();
        }

        if (! $this->puedeRegistrar()) {
            return response()->json([
                'success' => false,
                'error' => 'No tienes permiso para autorizar órdenes de trabajo (se requiere Registrar).',
            ], 403);
        }

        if ($orden->Estatus === self::ESTATUS_AUTORIZADO) {
            return response()->json([
                'success' => false,
                'error' => 'La orden ya está autorizada.',
            ], 422);
        }

        if ($orden->Estatus === self::ESTATUS_CANCELADO) {
            return response()->json([
                'success' => false,
                'error' => 'No se puede autorizar una orden cancelada.',
            ], 422);
        }

        if ($orden->Estatus !== self::ESTATUS_CALIFICADO) {
            return response()->json([
                'success' => false,
                'error' => 'Solo se pueden autorizar órdenes en estatus Calificado (el tejedor debe calificar todos los renglones).',
            ], 422);
        }

        try {
            $orden->update(['Estatus' => self::ESTATUS_AUTORIZADO]);
            $orden->load(['lineas' => fn ($query) => $query->orderBy('Id')]);

            return response()->json([
                'success' => true,
                'message' => 'Orden autorizada correctamente.',
                'data' => $orden,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error al autorizar orden de trabajo mecánica', [
                'folio' => $folio,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo autorizar la orden.',
            ], 500);
        }
    }

    private function reglasCabecera(): array
    {
        return [
            'TelarId' => ['required', 'string', 'max:50'],
            'FolioParo' => ['nullable', 'string', 'max:30'],
            'Falla' => ['required', 'string', 'max:150'],
            'Comentarios' => ['nullable', 'string', 'max:500'],
            'FechaParo' => ['nullable', 'date'],
            'HoraParo' => ['nullable', 'date_format:H:i'],
            'Orden' => ['nullable', 'string', 'max:20'],
            'Turno' => ['nullable', 'integer', 'between:1,3'],
        ];
    }

    /**
     * Restringe paros a los ocurridos dentro de las últimas N horas (Fecha + Hora).
     */
    private function aplicarFiltroParosUltimasHoras(Builder $query, int $horas = self::HORAS_HISTORIAL_PAROS): void
    {
        $desde = Carbon::now('America/Mexico_City')->subHours($horas);

        $query->where(function (Builder $subquery) use ($desde): void {
            $subquery->where('Fecha', '>', $desde->toDateString())
                ->orWhere(function (Builder $mismoDia) use ($desde): void {
                    $mismoDia->where('Fecha', $desde->toDateString())
                        ->where('Hora', '>=', $desde->format('H:i:s'));
                });
        });
    }

    private function operadoresMecanicos(): Collection
    {
        return ManOperadoresMantenimiento::query()
            ->orderBy('NomEmpl')
            ->get(['CveEmpl', 'NomEmpl', 'Turno', 'Depto']);
    }

    /**
     * Catálogo completo de máquinas (dbo.URDCatalogoMaquinas), sin filtrar
     * por departamento ni asignación del usuario.
     *
     * @return list<array{id: string, label: string}>
     */
    private function catalogoTelares(): array
    {
        return URDCatalogoMaquina::query()
            ->select('MaquinaId', 'Nombre', 'Departamento')
            ->whereNotNull('MaquinaId')
            ->where('MaquinaId', '!=', '')
            ->orderBy('Departamento')
            ->orderBy('MaquinaId')
            ->get()
            ->map(function (URDCatalogoMaquina $maquina): array {
                $maquinaId = trim((string) $maquina->MaquinaId);
                $nombre = trim((string) $maquina->Nombre);
                $departamento = trim((string) $maquina->Departamento);
                $label = $nombre !== '' ? "{$maquinaId} — {$nombre}" : $maquinaId;

                return [
                    'id' => $maquinaId,
                    'label' => $departamento !== '' ? "{$departamento} · {$label}" : $label,
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] !== '')
            ->unique('id')
            ->values()
            ->all();
    }

    private function reglasLinea(): array
    {
        return [
            'CveOperador' => ['nullable', 'string', 'max:30'],
            'NomOperador' => ['nullable', 'string', 'max:150'],
            'Ajusto' => ['nullable', 'boolean'],
            'Reparo' => ['nullable', 'boolean'],
            'Cambio' => ['nullable', 'boolean'],
            'Lubrico' => ['nullable', 'boolean'],
            'FaltaRefacc' => ['nullable', 'boolean'],
            'HoraInicial' => ['nullable', 'date_format:H:i'],
            'HoraFinal' => ['nullable', 'date_format:H:i'],
            // Turno 4 es el comodín que cubre descansos (ver TurnoHelper).
            'Turno' => ['required', 'integer', 'between:1,4'],
            'Fecha' => ['required', 'date_format:Y-m-d'],
            'comentarios' => ['nullable', 'string', 'max:500'],
            'Calificacion' => ['nullable', 'integer', 'between:1,10'],
            'CveTejedor' => ['nullable', 'string', 'max:30'],
            'NomTejedor' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function normalizarCabecera(array $datos): array
    {
        foreach (['TelarId', 'FolioParo', 'Falla', 'Comentarios', 'Orden'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $valor = trim((string) ($datos[$campo] ?? ''));
                $datos[$campo] = $valor !== '' ? $valor : null;
            }
        }

        if (isset($datos['HoraParo'])) {
            $datos['HoraParo'] = $this->normalizarHora($datos['HoraParo']);
        }

        return $datos;
    }

    private function normalizarLinea(array $datos): array
    {
        foreach (['CveOperador', 'NomOperador', 'CveTejedor', 'NomTejedor', 'comentarios'] as $campo) {
            $valor = trim((string) ($datos[$campo] ?? ''));
            $datos[$campo] = $valor !== '' ? $valor : null;
        }

        foreach (['Ajusto', 'Reparo', 'Cambio', 'Lubrico', 'FaltaRefacc'] as $campo) {
            $datos[$campo] = filter_var($datos[$campo] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        $datos['Turno'] = isset($datos['Turno']) ? (int) $datos['Turno'] : null;
        $datos['HoraInicial'] = $this->normalizarHora($datos['HoraInicial'] ?? null);
        $datos['HoraFinal'] = $this->normalizarHora($datos['HoraFinal'] ?? null);
        $datos['TotalMinutos'] = $this->calcularTotalMinutos($datos['HoraInicial'], $datos['HoraFinal']);

        return $datos;
    }

    private function normalizarHora(?string $hora): ?string
    {
        if ($hora === null || trim($hora) === '') {
            return null;
        }

        return Carbon::createFromFormat('H:i', $hora)->format('H:i:s');
    }

    private function normalizarHoraHistorica(mixed $hora): ?string
    {
        $valor = trim((string) $hora);
        if ($valor === '') {
            return null;
        }

        return $this->normalizarHora(substr($valor, 0, 5));
    }

    private function calcularTotalMinutos(?string $horaInicial, ?string $horaFinal): ?int
    {
        if ($horaInicial === null || $horaFinal === null) {
            return null;
        }

        $inicio = Carbon::createFromFormat('H:i:s', $horaInicial);
        $fin = Carbon::createFromFormat('H:i:s', $horaFinal);

        if ($fin->lessThan($inicio)) {
            $fin->addDay();
        }

        return $inicio->diffInMinutes($fin);
    }

    /**
     * Reloj de planta. Único origen de los valores sugeridos en la UI para que
     * no dependan de la zona horaria del navegador del mecánico.
     */
    private function ahoraPlanta(): Carbon
    {
        return now('America/Mexico_City');
    }

    /**
     * Fecha de la orden = día en que se genera el folio, no la fecha del paro.
     */
    private function fechaCreacionFolio(): string
    {
        return $this->ahoraPlanta()->toDateString();
    }

    private function siguienteFolio(): string
    {
        try {
            $this->asegurarSecuenciaFolios();

            $folio = trim(FolioHelper::obtenerSiguienteFolio(
                self::MODULO_FOLIOS,
                self::LONGITUD_CONSECUTIVO_FOLIOS,
            ));
        } catch (\Throwable $exception) {
            Log::error('No fue posible generar folio para orden de trabajo mecánica', [
                'modulo_folios' => self::MODULO_FOLIOS,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'Folio' => ['Configura la secuencia de folios del módulo Mecanicos antes de crear órdenes.'],
            ]);
        }

        if ($folio === '') {
            throw ValidationException::withMessages([
                'Folio' => ['Configura la secuencia de folios del módulo Mecanicos antes de crear órdenes.'],
            ]);
        }

        return $folio;
    }

    /**
     * Crea la secuencia la primera vez que se use el módulo y la alinea con
     * los folios MEC existentes para no reutilizar un folio anterior.
     */
    private function asegurarSecuenciaFolios(): void
    {
        DB::transaction(function (): void {
            $secuencia = SSYSFoliosSecuencia::query()
                ->where('modulo', self::MODULO_FOLIOS)
                ->lockForUpdate()
                ->first();

            if ($secuencia !== null) {
                return;
            }

            $ultimoFolio = (string) MecOrdenTrabajoModel::query()
                ->where('Folio', 'like', self::PREFIJO_FOLIOS.'%')
                ->orderByDesc('Folio')
                ->value('Folio');

            $consecutivoInicial = (int) substr($ultimoFolio, strlen(self::PREFIJO_FOLIOS));

            SSYSFoliosSecuencia::create([
                'modulo' => self::MODULO_FOLIOS,
                'prefijo' => self::PREFIJO_FOLIOS,
                'consecutivo' => $consecutivoInicial,
            ]);
        });
    }

    /**
     * Garantiza el primer renglón vacío al crear la cabecera.
     * Si el trigger de BD ya insertó la línea, no duplica.
     */
    private function asegurarLineaInicial(MecOrdenTrabajoModel $orden): void
    {
        if ($orden->lineas()->exists()) {
            return;
        }

        MecOrdenTrabajoLineModel::create([
            'Folio' => $orden->Folio,
        ]);
    }

    private function validarFolioParoDisponible(?string $folioParo, ?string $folioActual = null): void
    {
        if ($folioParo === null || $folioParo === '') {
            return;
        }

        $existe = MecOrdenTrabajoModel::query()
            ->where('FolioParo', $folioParo)
            ->when($folioActual !== null, fn ($query) => $query->where('Folio', '<>', $folioActual))
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'FolioParo' => ['Ese folio de paro ya tiene una orden de trabajo asociada.'],
            ]);
        }
    }

    /**
     * La cabecera de una OT nueva procede de un paro elegible o de una captura
     * manual explícita. Los datos de un paro se vuelven
     * a leer aquí para no confiar en campos bloqueados solamente en el cliente.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function resolverOrigenCabecera(array $datos, bool $capturaManual): array
    {
        if ($capturaManual) {
            // El valor visible "Sin Folio de Paro." es solo informativo; nunca
            // debe guardarse ni debe prevalecer un folio que quedó en el request.
            $datos['FolioParo'] = null;

            return $datos;
        }

        $folioParo = trim((string) ($datos['FolioParo'] ?? ''));

        if ($folioParo !== '') {
            $paro = ManFallasParos::query()
                ->where('Folio', $folioParo)
                ->first();

            if ($paro === null) {
                throw ValidationException::withMessages([
                    'FolioParo' => ['El folio de paro seleccionado ya no existe.'],
                ]);
            }

            $datos = [
                ...$datos,
                'TelarId' => trim((string) $paro->MaquinaId),
                'FolioParo' => trim((string) $paro->Folio),
                'Falla' => $this->textoFalla($paro->Falla, $paro->Descripcion),
                'Comentarios' => $this->textoComentariosParo($paro),
                'FechaParo' => optional($paro->Fecha)->toDateString(),
                'HoraParo' => $this->normalizarHoraHistorica($paro->Hora),
                'Orden' => trim((string) ($paro->OrdenTrabajo ?? '')) ?: null,
                'Turno' => $paro->Turno !== null ? (int) $paro->Turno : null,
                'Estatus' => self::ESTATUS_ACTIVO,
            ];

            return $datos;
        }

        throw ValidationException::withMessages([
            'CapturaManual' => ['Selecciona un folio de paro para la máquina o habilita la captura manual.'],
        ]);
    }

    /**
     * Determina si el usuario autenticado es tejedor por su área
     * (convención de la app: area = TEJEDORES / TEJEDOR).
     */
    private function esTejedor(): bool
    {
        $area = strtoupper(trim((string) (Auth::user()->area ?? '')));

        return in_array($area, ['TEJEDORES', 'TEJEDOR'], true);
    }

    /**
     * Permiso "registrar" del módulo = rol supervisor (calificar / autorizar).
     */
    private function puedeRegistrar(): bool
    {
        return userCan('registrar', self::MODULO_PERMISO);
    }

    /**
     * Tejedor de área sin permiso registrar: solo califica, no captura intervenciones.
     */
    private function esModoTejedorSoloCalificacion(): bool
    {
        return $this->esTejedor() && ! $this->puedeRegistrar();
    }

    /**
     * @return array{puedeCrear: bool, puedeModificar: bool, puedeEliminar: bool, puedeRegistrar: bool}
     */
    private function permisosVista(): array
    {
        return [
            'puedeCrear' => userCan('crear', self::MODULO_PERMISO),
            'puedeModificar' => userCan('modificar', self::MODULO_PERMISO),
            'puedeEliminar' => userCan('eliminar', self::MODULO_PERMISO),
            'puedeRegistrar' => $this->puedeRegistrar(),
        ];
    }

    /**
     * Telares (NoTelarId) asignados al usuario en TelTelaresOperador.
     *
     * @return list<string>
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
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn (string $id) => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Tejedor (sin registrar): solo ve órdenes de sus telares en TelTelaresOperador.
     */
    private function aplicarFiltroTelaresTejedor(Builder $query): void
    {
        if (! $this->esTejedor()) {
            return;
        }

        $telares = $this->idsTelaresAsignadosOperadorActual();
        if ($telares === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('TelarId', $telares);
    }

    private function tejedorPuedeVerOrden(MecOrdenTrabajoModel $orden): bool
    {
        if (! $this->esTejedor()) {
            return true;
        }

        $telar = trim((string) ($orden->TelarId ?? ''));
        if ($telar === '') {
            return false;
        }

        return in_array($telar, $this->idsTelaresAsignadosOperadorActual(), true);
    }

    /**
     * Tejedor (área) o supervisor (registrar) pueden calificar después de Finalizar.
     */
    private function puedeCalificarComoTejedor(): bool
    {
        return $this->esTejedor() || $this->puedeRegistrar();
    }

    /**
     * Mecánico con modificar, o supervisor con registrar, pueden finalizar.
     * El tejedor en modo solo-calificación no finaliza.
     */
    private function puedeFinalizarComoMecanico(): bool
    {
        if ($this->esModoTejedorSoloCalificacion()) {
            return false;
        }

        return userCan('modificar', self::MODULO_PERMISO) || $this->puedeRegistrar();
    }

    /**
     * Texto de falla para UI: código + descripción (no solo el número/clave).
     */
    private function textoFalla(?string $falla, ?string $descripcion): string
    {
        $falla = trim((string) $falla);
        $descripcion = trim((string) $descripcion);

        if ($falla !== '' && $descripcion !== '') {
            if (strcasecmp($falla, $descripcion) === 0) {
                return mb_substr($descripcion, 0, 150);
            }

            return mb_substr("{$falla} — {$descripcion}", 0, 150);
        }

        return mb_substr($descripcion !== '' ? $descripcion : $falla, 0, 150);
    }

    private function textoComentariosParo(ManFallasParos $paro): ?string
    {
        $comentarios = array_filter([
            trim((string) ($paro->Obs ?? '')),
            trim((string) ($paro->ObsCierre ?? '')),
        ], fn (string $valor): bool => $valor !== '');

        if ($comentarios === []) {
            return null;
        }

        return mb_substr(implode("\n", array_unique($comentarios)), 0, 500);
    }

    /**
     * Evita crear órdenes de trabajo “vacías” (sin telar ni descripción de falla).
     *
     * @param  array<string, mixed>  $datos
     */
    private function validarOrdenNoVacia(array $datos): void
    {
        $telar = trim((string) ($datos['TelarId'] ?? ''));
        $falla = trim((string) ($datos['Falla'] ?? ''));

        if ($telar === '' || $falla === '') {
            throw ValidationException::withMessages([
                'Falla' => ['La orden de trabajo no puede quedar vacía: captura el telar y la descripción de la falla.'],
            ]);
        }
    }

    private function estatusBloqueaEdicionMecanico(string $estatus): bool
    {
        $estatus = $estatus !== '' ? $estatus : self::ESTATUS_ACTIVO;

        return in_array($estatus, [
            self::ESTATUS_TERMINADO,
            self::ESTATUS_CALIFICADO,
            self::ESTATUS_AUTORIZADO,
        ], true);
    }

    private function asegurarEditablePorMecanico(MecOrdenTrabajoModel $orden): void
    {
        if ($this->estatusBloqueaEdicionMecanico((string) ($orden->Estatus ?: self::ESTATUS_ACTIVO))) {
            throw ValidationException::withMessages([
                'Estatus' => ['La orden ya no admite edición (finalizada, calificada o autorizada).'],
            ]);
        }
    }

    private function todasLasLineasCalificadas(MecOrdenTrabajoModel $orden): bool
    {
        $lineas = $orden->lineas;
        if ($lineas->isEmpty()) {
            return false;
        }

        return $lineas->every(function ($linea): bool {
            $calificacion = $linea->Calificacion;

            return $calificacion !== null && (int) $calificacion >= 1 && (int) $calificacion <= 10;
        });
    }

    /**
     * Renglón vacío (el placeholder al crear la orden).
     */

    /**
     * Un renglón solo se guarda si tiene mecánico, al menos un trabajo y ambas horas.
     * Clave/mecánico solos no bastan.
     *
     * @param  array<string, mixed>  $datos
     */
    private function validarLineaCompleta(array $datos): void
    {
        $cve = trim((string) ($datos['CveOperador'] ?? ''));
        $nom = trim((string) ($datos['NomOperador'] ?? ''));
        $horaInicial = trim((string) ($datos['HoraInicial'] ?? ''));
        $horaFinal = trim((string) ($datos['HoraFinal'] ?? ''));
        $tieneTrabajo = (bool) ($datos['Ajusto'] ?? false)
            || (bool) ($datos['Reparo'] ?? false)
            || (bool) ($datos['Cambio'] ?? false)
            || (bool) ($datos['Lubrico'] ?? false)
            || (bool) ($datos['FaltaRefacc'] ?? false);

        $errors = [];

        if ($cve === '' || $nom === '') {
            $errors['CveOperador'] = ['Selecciona la clave y el mecánico que está capturando.'];
        }

        if (! $tieneTrabajo) {
            $errors['Ajusto'] = ['Marca al menos un trabajo realizado antes de guardar el renglón.'];
        }

        if ($horaInicial === '' || $horaFinal === '') {
            $errors['HoraInicial'] = ['Captura hora inicial y hora final para guardar el renglón.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function lineaSinCaptura(MecOrdenTrabajoLineModel $linea): bool
    {
        return trim((string) ($linea->CveOperador ?? '')) === ''
            && trim((string) ($linea->NomOperador ?? '')) === ''
            && ! (bool) $linea->Ajusto
            && ! (bool) $linea->Reparo
            && ! (bool) $linea->Cambio
            && ! (bool) $linea->Lubrico
            && ! (bool) $linea->FaltaRefacc
            && empty($linea->HoraInicial)
            && empty($linea->HoraFinal);
    }

    /**
     * Ruta restringida a calificación: tejedor (con o sin registrar).
     */
    private function debeUsarRutaSoloCalificacion(): bool
    {
        return $this->esTejedor();
    }

    private function respuestaSinPermiso(string $accion, string $mensaje): ?JsonResponse
    {
        if (userCan($accion, self::MODULO_PERMISO)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => $mensaje,
        ], 403);
    }

    /**
     * @return array{0: string, 1: string} [cve, nombre]
     */
    private function datosTejedorSesion(): array
    {
        $usuario = Auth::user();
        $cve = trim((string) ($usuario->numero_empleado ?? ''));
        $nombre = trim((string) ($usuario->nombre ?? ''));

        if ($cve === '' || $nombre === '') {
            throw ValidationException::withMessages([
                'CveTejedor' => ['Tu usuario no tiene número de empleado o nombre configurados.'],
            ]);
        }

        return [$cve, $nombre];
    }

    private function respuestaSiTejedorNoPuedeMutar(string $mensaje): ?JsonResponse
    {
        if (! $this->esTejedor()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => $mensaje,
        ], 403);
    }

    /**
     * Calificación solo la captura el tejedor; se descarta del payload de mecánicos.
     */
    private function filtrarCamposCalificacion(array $datos): array
    {
        unset($datos['Calificacion'], $datos['CveTejedor'], $datos['NomTejedor']);

        return $datos;
    }

    private function ordenNoEncontrada(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Orden de trabajo no encontrada.',
        ], 404);
    }

    private function respuestaValidacion(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Corrige los datos marcados.',
            'errors' => $exception->errors(),
        ], 422);
    }
}
