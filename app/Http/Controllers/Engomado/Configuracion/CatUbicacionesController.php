<?php

namespace App\Http\Controllers\Engomado\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Engomado\CatUbicaciones;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CatUbicacionesController extends Controller
{
    use HandlesApiErrors;

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
            report($e);

            // SEC-07: mensaje genérico con la referencia del error, no getMessage().
            return view('modulos.engomado.configuracion.catalogo-ubicaciones', [
                'ubicaciones' => collect(),
                'noResults' => true,
            ])->with('error', 'Error al cargar los datos (ref: '.$this->traceIdDeError($e).')');
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
                'message' => 'Ubicación creada exitosamente',
            ]);
        } catch (ValidationException $e) {
            return $this->errorValidacion($e);
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e, 'Error al crear ubicación', 'No se pudo crear la ubicación.');
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
                'message' => 'Ubicación actualizada exitosamente',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La ubicación no fue encontrada',
            ], 404);
        } catch (ValidationException $e) {
            return $this->errorValidacion($e);
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar ubicación', 'No se pudo actualizar la ubicación.');
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
                'message' => 'Ubicación eliminada exitosamente',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La ubicación no fue encontrada',
            ], 404);
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e, 'Error al eliminar ubicación', 'No se pudo eliminar la ubicación.');
        }
    }

    /** 422 con el primer mensaje en `message` (texto) y el detalle en `errors`, como Laravel. */
    private function errorValidacion(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->validator->errors()->first(),
            'errors' => $e->errors(),
        ], 422);
    }
}
