<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatMatrizCalibres;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\Catalogos\CatMatrizCalibres;
use App\Services\Planeacion\MatrizCalibresService;
use App\ValueObjects\Planeacion\MatrizCalibreClave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class MatrizCalibresController extends Controller
{
    public function __construct(
        private readonly MatrizCalibresService $matrizCalibres,
    ) {}

    public function index(): View
    {
        $registros = CatMatrizCalibres::query()
            ->orderBy('Tipo')
            ->orderBy('Calibre')
            ->orderBy('Id')
            ->get();

        $tipos = $registros
            ->pluck('Tipo')
            ->filter(fn ($tipo) => filled($tipo))
            ->unique()
            ->sort()
            ->values();

        return view('catalagos.matriz-calibres', [
            'registros' => $registros,
            'tipos' => $tipos,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validatePayload($request);
            $registro = $this->matrizCalibres->guardarRegistroCompleto($validated);

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
                'data' => $registro,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al crear CatMatrizCalibres', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear registro: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $registro = CatMatrizCalibres::find($id);

        if (! $registro) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $registro,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $registro = CatMatrizCalibres::find($id);

            if (! $registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado',
                ], 404);
            }

            $validated = $this->validatePayload($request);
            $registro = $this->matrizCalibres->guardarRegistroCompleto($validated, $registro);

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado exitosamente',
                'data' => $registro,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al actualizar CatMatrizCalibres', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar registro: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $registro = CatMatrizCalibres::find($id);

            if (! $registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado',
                ], 404);
            }

            $registro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente',
                'deleted_id' => $id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar CatMatrizCalibres', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar registro: '.$e->getMessage(),
            ], 500);
        }
    }

    public function lookup(Request $request): JsonResponse
    {
        $tipo = mb_strtoupper(trim((string) $request->input('tipo', '')), 'UTF-8');
        $request->merge([
            'tipo' => $tipo,
        ]);

        $validated = $request->validate([
            'tipo' => ['required', 'string', Rule::in(MatrizCalibreClave::TIPOS)],
            'calibre' => [
                Rule::requiredIf($tipo !== MatrizCalibreClave::TIPO_PIE),
                'nullable',
                'numeric',
                'gt:0',
            ],
            'fibraId' => [
                Rule::requiredIf($tipo !== MatrizCalibreClave::TIPO_PIE),
                'nullable',
                'string',
                'max:60',
            ],
            'cuenta' => ['nullable', 'string', 'max:60'],
        ]);

        $clave = MatrizCalibreClave::tryFromArray($validated);
        if ($clave === null) {
            if (in_array($tipo, [MatrizCalibreClave::TIPO_RIZO, MatrizCalibreClave::TIPO_PIE], true)
                && blank($validated['cuenta'] ?? null)) {
                throw ValidationException::withMessages([
                    'cuenta' => 'Cuenta es obligatoria para las equivalencias de Rizo y Pie.',
                ]);
            }

            throw ValidationException::withMessages([
                'fibraId' => $tipo === MatrizCalibreClave::TIPO_PIE
                    ? 'Para Pie debe existir al menos Fibra o Calibre.'
                    : 'Fibra y Calibre son obligatorios para Rizo y Trama.',
            ]);
        }

        $registro = $this->matrizCalibres->buscar($clave);

        return response()->json([
            'success' => true,
            'found' => $registro !== null,
            'data' => $registro,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $tipo = mb_strtoupper(trim((string) $request->input('Tipo', '')), 'UTF-8');
        $request->merge(['Tipo' => $tipo]);

        $validated = $request->validate([
            'Tipo' => ['required', 'string', Rule::in(MatrizCalibreClave::TIPOS)],
            'Calibre' => [
                Rule::requiredIf($tipo !== MatrizCalibreClave::TIPO_PIE),
                'nullable',
                'numeric',
                'gt:0',
            ],
            'FibraId' => [
                Rule::requiredIf($tipo !== MatrizCalibreClave::TIPO_PIE),
                'nullable',
                'string',
                'max:60',
            ],
            'Cuenta' => [
                Rule::requiredIf(in_array($tipo, [MatrizCalibreClave::TIPO_RIZO, MatrizCalibreClave::TIPO_PIE], true)),
                Rule::prohibitedIf($tipo === MatrizCalibreClave::TIPO_TRAMA),
                'nullable',
                'string',
                'max:60',
            ],
            'ItemId' => ['required', 'string', 'max:60'],
            'ConfigId' => ['required', 'string', 'max:60'],
            'InventSizeId' => ['required', 'string', 'max:60'],
            'InventColorId' => ['required', 'string', 'max:60'],
        ]);

        if (MatrizCalibreClave::tryFromArray($validated) === null) {
            throw ValidationException::withMessages([
                'FibraId' => 'Para Pie debe existir al menos Fibra o Calibre.',
            ]);
        }

        return $validated;
    }
}
