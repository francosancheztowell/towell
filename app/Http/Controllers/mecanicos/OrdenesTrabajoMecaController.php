<?php

namespace App\Http\Controllers\mecanicos;

use App\Helpers\FolioHelper;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Models\Sistema\SSYSFoliosSecuencia;
use App\Models\Tejedores\TelTelaresOperador;
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

    private const ESTATUS_AUTORIZADO = 'Autorizado';

    private const ESTATUS_CANCELADO = 'Cancelado';

    public function index(): View
    {
        $modoTejedor = $this->esModoTejedorSoloCalificacion();
        $permisos = $this->permisosVista();

        return view('modulos.mecanicos.ordenes-trabajo.index', [
            'fechaInicial' => now('America/Mexico_City')->toDateString(),
            'operadores' => $this->operadoresMecanicos(),
            'esTejedor' => $this->esTejedor(),
            'modoTejedor' => $modoTejedor,
            // Alias: supervisor = permiso registrar (autorizar).
            'esSupervisor' => $permisos['puedeRegistrar'],
            'puedeCrear' => $permisos['puedeCrear'] && ! $modoTejedor,
            'puedeEditar' => $permisos['puedeModificar'] && ! $modoTejedor,
            'puedeEliminar' => $permisos['puedeEliminar'] && ! $modoTejedor,
            'puedeRegistrar' => $permisos['puedeRegistrar'],
            'puedeCalificar' => $this->puedeCalificar(),
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

        return view('modulos.mecanicos.ordenes-trabajo.captura', [
            'orden' => $orden,
            'operadores' => $this->operadoresMecanicos(),
            'esTejedor' => $this->esTejedor(),
            'modoTejedor' => $modoTejedor,
            'esSupervisor' => $permisos['puedeRegistrar'],
            'puedeCrear' => $permisos['puedeCrear'] && ! $modoTejedor,
            'puedeEditar' => $permisos['puedeModificar'] && ! $modoTejedor,
            'puedeEliminar' => $permisos['puedeEliminar'] && ! $modoTejedor,
            'puedeRegistrar' => $permisos['puedeRegistrar'],
            'puedeCalificar' => $this->puedeCalificar(),
            'bloqueada' => $orden->Estatus === self::ESTATUS_AUTORIZADO,
            'tejedorCve' => trim((string) ($usuario->numero_empleado ?? '')),
            'tejedorNombre' => trim((string) ($usuario->nombre ?? '')),
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
                'OrdenTrabajo',
                'Turno',
                'Depto',
            ]);

        return response()->json([
            'success' => true,
            'data' => $paros,
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

            $this->validarFolioParoDisponible($datos['FolioParo'] ?? null);

            $orden = DB::transaction(function () use ($datos): MecOrdenTrabajoModel {
                $folio = $this->siguienteFolio();

                $orden = MecOrdenTrabajoModel::create([
                    ...$datos,
                    'Folio' => $folio,
                    'Estatus' => $datos['Estatus'] ?? 'Activo',
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
            $this->asegurarNoAutorizada($orden);

            $datos = $this->normalizarCabecera($request->validate($this->reglasCabecera()));
            $this->validarFolioParoDisponible($datos['FolioParo'] ?? null, $folio);

            // La autorización solo se hace con el botón Autorizar (gating de supervisor),
            // no cambiando el estatus desde la edición normal de la cabecera.
            if (($datos['Estatus'] ?? null) === self::ESTATUS_AUTORIZADO) {
                throw ValidationException::withMessages([
                    'Estatus' => ['Usa el botón Autorizar para autorizar la orden.'],
                ]);
            }

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

        if ($orden->Estatus === self::ESTATUS_AUTORIZADO) {
            return response()->json([
                'success' => false,
                'error' => 'La orden está autorizada y no puede eliminarse.',
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
            $this->asegurarNoAutorizada($orden);

            $linea = MecOrdenTrabajoLineModel::create([
                'Folio' => $folio,
                ...$this->filtrarCamposCalificacion(
                    $this->normalizarLinea($request->validate($this->reglasLinea()))
                ),
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
            if ($orden) {
                $this->asegurarNoAutorizada($orden);
            }

            if ($orden && ! $this->tejedorPuedeVerOrden($orden)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No tienes acceso a esta orden: el telar no está asignado a tu usuario.',
                ], 403);
            }

            // Tejedor o usuario con "registrar" sin modificar: solo calificación.
            if ($this->debeUsarRutaSoloCalificacion()) {
                if (! $this->puedeCalificar()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'No tienes permiso para calificar intervenciones.',
                    ], 403);
                }

                $validated = $request->validate([
                    'Calificacion' => ['required', 'integer', 'between:1,10'],
                    'CveTejedor' => ['nullable', 'string', 'max:30'],
                    'NomTejedor' => ['nullable', 'string', 'max:150'],
                ]);

                if ($this->esTejedor() && ! $this->puedeRegistrar()) {
                    [$cve, $nombre] = $this->datosTejedorSesion();
                } else {
                    $cve = trim((string) ($validated['CveTejedor'] ?? ''));
                    $nombre = trim((string) ($validated['NomTejedor'] ?? ''));

                    if ($cve === '' || $nombre === '') {
                        [$cve, $nombre] = $this->datosTejedorSesion();
                    }
                }

                $registro->update([
                    'Calificacion' => (int) $validated['Calificacion'],
                    'CveTejedor' => $cve,
                    'NomTejedor' => $nombre,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Calificación guardada correctamente.',
                    'data' => $registro->fresh(),
                ]);
            }

            if ($respuesta = $this->respuestaSinPermiso('modificar', 'No tienes permiso para modificar renglones.')) {
                return $respuesta;
            }

            $registro->update($this->filtrarCamposCalificacion(
                $this->normalizarLinea($request->validate($this->reglasLinea()))
            ));

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
        if ($orden && $orden->Estatus === self::ESTATUS_AUTORIZADO) {
            return response()->json([
                'success' => false,
                'error' => 'La orden está autorizada y quedó en solo lectura.',
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
     * Autoriza una orden (permiso "registrar" del módulo = acceso supervisor).
     * Deja el registro en estatus Autorizado (solo lectura).
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
            'Fecha' => ['required', 'date'],
            'TelarId' => ['required', 'string', 'max:10'],
            'FolioParo' => ['nullable', 'string', 'max:30'],
            'Falla' => ['nullable', 'string', 'max:150'],
            'FechaParo' => ['nullable', 'date'],
            'HoraParo' => ['nullable', 'date_format:H:i'],
            'Estatus' => ['nullable', 'string', 'max:15'],
            'Orden' => ['nullable', 'string', 'max:20'],
            'Turno' => ['nullable', 'integer', 'between:1,3'],
        ];
    }

    private function operadoresMecanicos(): Collection
    {
        return ManOperadoresMantenimiento::query()
            ->orderBy('NomEmpl')
            ->get(['CveEmpl', 'NomEmpl', 'Turno', 'Depto']);
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
            'Calificacion' => ['nullable', 'integer', 'between:1,10'],
            'CveTejedor' => ['nullable', 'string', 'max:30'],
            'NomTejedor' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function normalizarCabecera(array $datos): array
    {
        foreach (['TelarId', 'FolioParo', 'Falla', 'Estatus', 'Orden'] as $campo) {
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
        foreach (['CveOperador', 'NomOperador', 'CveTejedor', 'NomTejedor'] as $campo) {
            $valor = trim((string) ($datos[$campo] ?? ''));
            $datos[$campo] = $valor !== '' ? $valor : null;
        }

        foreach (['Ajusto', 'Reparo', 'Cambio', 'Lubrico', 'FaltaRefacc'] as $campo) {
            $datos[$campo] = filter_var($datos[$campo] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

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

    private function siguienteFolio(): string
    {
        try {
            $this->asegurarSecuenciaFolios();

            $folio = trim(FolioHelper::obtenerSiguienteFolio(
                self::MODULO_FOLIOS,
                self::LONGITUD_CONSECUTIVO_FOLIOS,
            ));
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
        if (! $this->esModoTejedorSoloCalificacion()) {
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
        if (! $this->esModoTejedorSoloCalificacion()) {
            return true;
        }

        $telar = trim((string) ($orden->TelarId ?? ''));
        if ($telar === '') {
            return false;
        }

        return in_array($telar, $this->idsTelaresAsignadosOperadorActual(), true);
    }

    /**
     * Tejedor (área) o usuario con "registrar" pueden capturar calificación / firma.
     */
    private function puedeCalificar(): bool
    {
        return $this->esTejedor() || $this->puedeRegistrar();
    }

    /**
     * Ruta restringida a calificación: tejedor sin registrar, o registrar sin modificar.
     */
    private function debeUsarRutaSoloCalificacion(): bool
    {
        if ($this->esModoTejedorSoloCalificacion()) {
            return true;
        }

        return $this->puedeRegistrar() && ! userCan('modificar', self::MODULO_PERMISO);
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
        if (! $this->esModoTejedorSoloCalificacion()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'error' => $mensaje,
        ], 403);
    }

    /**
     * Impide mutar una orden ya autorizada (queda en solo lectura).
     */
    private function asegurarNoAutorizada(MecOrdenTrabajoModel $orden): void
    {
        if ($orden->Estatus === self::ESTATUS_AUTORIZADO) {
            throw ValidationException::withMessages([
                'Estatus' => ['La orden está autorizada y quedó en solo lectura.'],
            ]);
        }
    }

    /**
     * Calificación / CVE / nombre tejedor: tejedor o permiso registrar.
     * Cualquier otro rol no puede sobrescribir esos campos.
     */
    private function filtrarCamposCalificacion(array $datos): array
    {
        if ($this->puedeCalificar()) {
            return $datos;
        }

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
