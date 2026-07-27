<?php

namespace App\Http\Controllers\mecanicos;

use App\Helpers\FolioHelper;
use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use App\Models\Mecanicos\MecOrdenTrabajoLineModel;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Models\Sistema\SSYSFoliosSecuencia;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrdenesTrabajoMecaController extends Controller
{
    private const MODULO_FOLIOS = 'Mecanicos';

    private const PREFIJO_FOLIOS = 'MEC';

    private const LONGITUD_CONSECUTIVO_FOLIOS = 5;

    public function index(): View
    {
        return view('modulos.mecanicos.index', [
            'fechaInicial' => now('America/Mexico_City')->toDateString(),
            'operadores' => $this->operadoresMecanicos(),
        ]);
    }

    public function captura(string $folio): View
    {
        $orden = MecOrdenTrabajoModel::query()
            ->with(['lineas' => fn ($query) => $query->orderBy('Id')])
            ->find($folio);

        abort_unless($orden, 404);

        return view('modulos.mecanicos.captura', [
            'orden' => $orden,
            'operadores' => $this->operadoresMecanicos(),
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

        return response()->json([
            'success' => true,
            'data' => $orden,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
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
        $orden = MecOrdenTrabajoModel::find($folio);

        if (! $orden) {
            return $this->ordenNoEncontrada();
        }

        try {
            $datos = $this->normalizarCabecera($request->validate($this->reglasCabecera()));
            $this->validarFolioParoDisponible($datos['FolioParo'] ?? null, $folio);

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
        $orden = MecOrdenTrabajoModel::find($folio);

        if (! $orden) {
            return $this->ordenNoEncontrada();
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
        if (! MecOrdenTrabajoModel::whereKey($folio)->exists()) {
            return $this->ordenNoEncontrada();
        }

        try {
            $linea = MecOrdenTrabajoLineModel::create([
                'Folio' => $folio,
                ...$this->normalizarLinea($request->validate($this->reglasLinea())),
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
            $registro->update($this->normalizarLinea($request->validate($this->reglasLinea())));

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
        $registro = MecOrdenTrabajoLineModel::query()
            ->where('Folio', $folio)
            ->find($linea);

        if (! $registro) {
            return response()->json([
                'success' => false,
                'error' => 'Renglón de orden no encontrado.',
            ], 404);
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
            'Calificacion' => ['nullable', 'integer', 'min:0'],
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
