<?php

namespace App\Http\Controllers\Engomado\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Engomado\CatUbicaciones;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatUbicacionesController extends Controller
{
    /**
     * Mostrar catálogo de ubicaciones
     */
    public function index(Request $request)
    {
        try {
            $query = CatUbicaciones::query();

            // Filtros opcionales
            if ($request->filled('codigo')) {
                $query->where('Codigo', 'like', "%{$request->codigo}%");
            }

            $ubicaciones = $query->orderBy('Codigo')->get();

            $noResults = $ubicaciones->isEmpty();

            return view('modulos.engomado.configuracion.catalogo-ubicaciones', compact('ubicaciones', 'noResults'));
        } catch (\Exception $e) {
            Log::error('Error en CatUbicacionesController::index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return view('modulos.engomado.configuracion.catalogo-ubicaciones', [
                'ubicaciones' => collect(),
                'noResults' => true
            ])->with('error', 'Error al cargar los datos: ' . $e->getMessage());
        }
    }

    /**
     * Crear una nueva ubicación
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'Codigo' => 'required|string|max:10|unique:CatUbicaciones,Codigo',
            ]);

            CatUbicaciones::create([
                'Codigo' => strtoupper(trim($request->Codigo)),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ubicación creada exitosamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear ubicación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la ubicación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una ubicación existente
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $ubicacion = CatUbicaciones::findOrFail($id);

            $rules = [
                'Codigo' => 'required|string|max:10',
            ];

            // Si el Codigo cambió, validar que no exista
            if ($request->Codigo !== $ubicacion->Codigo) {
                $rules['Codigo'] .= '|unique:CatUbicaciones,Codigo';
            }

            $request->validate($rules);

            $ubicacion->update([
                'Codigo' => strtoupper(trim($request->Codigo)),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ubicación actualizada exitosamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar ubicación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la ubicación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una ubicación
     */
    public function destroy($id): JsonResponse
    {
        try {
            $ubicacion = CatUbicaciones::findOrFail($id);
            $ubicacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ubicación eliminada exitosamente'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La ubicación no fue encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar ubicación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la ubicación: ' . $e->getMessage()
            ], 500);
        }
    }
}
