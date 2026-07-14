<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatMatrizCalibres;

use App\Http\Controllers\Controller;
use App\Models\Planeacion\Catalogos\CatMatrizCalibres;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MatrizCalibresController extends Controller
{
    public function index()
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

    public function store(Request $request)
    {
        try {
            $validated = $this->validatePayload($request);
            $registro = CatMatrizCalibres::create($validated);

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

    public function show(int $id)
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

    public function update(Request $request, int $id)
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
            $registro->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado exitosamente',
                'data' => $registro->fresh(),
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

    public function destroy(int $id)
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

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'Tipo' => 'nullable|string|max:60',
            'Calibre' => 'nullable|numeric',
            'FibraId' => 'nullable|string|max:60',
            'Cuenta' => 'nullable|string|max:60',
            'ItemId' => 'nullable|string|max:60',
            'ConfigId' => 'nullable|string|max:60',
            'InventSizeId' => 'nullable|string|max:60',
            'InventColorId' => 'nullable|string|max:60',
        ]);

        foreach ($validated as $key => $value) {
            if (is_string($value)) {
                $validated[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        if (array_key_exists('Calibre', $validated) && $validated['Calibre'] !== null) {
            $validated['Calibre'] = (float) $validated['Calibre'];
        }

        return $validated;
    }
}
