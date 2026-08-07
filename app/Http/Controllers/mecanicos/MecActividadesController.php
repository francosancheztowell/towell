<?php

namespace App\Http\Controllers\mecanicos;

use App\Http\Controllers\Controller;
use App\Models\Mecanicos\MecActividadesModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MecActividadesController extends Controller
{
    /**
     * Mostrar el catálogo de actividades mecánicos.
     */
    public function index(): View
    {
        $actividades = MecActividadesModel::query()
            ->orderBy('Orden')
            ->orderBy('Id')
            ->get();

        return view('modulos.mecanicos.catalogos.actividades.index', compact('actividades'));
    }

    /**
     * Guardar una nueva actividad.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'Orden' => 'required|integer|min:1',
                'Actividad' => 'required|string|max:100',
            ]);

            $actividad = MecActividadesModel::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Actividad creada exitosamente',
                'data' => $actividad,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la actividad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener una actividad específica.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $actividad = MecActividadesModel::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $actividad,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Actividad no encontrada',
            ], 404);
        }
    }

    /**
     * Actualizar una actividad existente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $actividad = MecActividadesModel::findOrFail($id);

            $validated = $request->validate([
                'Orden' => 'required|integer|min:1',
                'Actividad' => 'required|string|max:100',
            ]);

            $actividad->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Actividad actualizada exitosamente',
                'data' => $actividad->fresh(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la actividad: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar una actividad.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $actividad = MecActividadesModel::findOrFail($id);
            $actividad->delete();

            return response()->json([
                'success' => true,
                'message' => 'Actividad eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la actividad: ' . $e->getMessage(),
            ], 500);
        }
    }
}