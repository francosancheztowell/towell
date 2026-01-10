<?php

namespace App\Http\Controllers\Urdido\Configuracion\CatalogosJulios;

use App\Http\Controllers\Controller;
use App\Models\UrdCatJulios;
use App\Models\URDCatalogoMaquina;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatalogosUrdidoController extends Controller
{
    /**
     * Mostrar catálogo de julios
     */
    public function catalogosJulios(Request $request)
    {
        try {
            $query = UrdCatJulios::query();

            // Filtros opcionales
            if ($request->filled('no_julio')) {
                $query->where('NoJulio', 'like', "%{$request->no_julio}%");
            }
            if ($request->filled('departamento')) {
                $query->where('Departamento', 'like', "%{$request->departamento}%");
            }

            $julios = $query->whereNotNull('NoJulio')
                ->orderBy('NoJulio')
                ->get();

            $noResults = $julios->isEmpty();

            return view('catalogosurdido.catalago-julios', compact('julios', 'noResults'));
        } catch (\Exception $e) {
            Log::error('Error en CatalogosUrdidoController::catalogosJulios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return view('catalogosurdido.catalago-julios', [
                'julios' => collect(),
                'noResults' => true
            ])->with('error', 'Error al cargar los datos: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar catálogo de máquinas
     */
    public function catalogoMaquinas(Request $request)
    {
        try {
            $query = URDCatalogoMaquina::query();

            // Filtros opcionales
            if ($request->filled('maquina_id')) {
                $query->where('MaquinaId', 'like', "%{$request->maquina_id}%");
            }
            if ($request->filled('nombre')) {
                $query->where('Nombre', 'like', "%{$request->nombre}%");
            }
            if ($request->filled('departamento')) {
                $query->where('Departamento', 'like', "%{$request->departamento}%");
            }

            $maquinas = $query->orderBy('MaquinaId')->get();

            $noResults = $maquinas->isEmpty();

            return view('catalogosurdido.catalago-maquinas', compact('maquinas', 'noResults'));
        } catch (\Exception $e) {
            Log::error('Error en CatalogosUrdidoController::catalogoMaquinas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return view('catalogosurdido.catalago-maquinas', [
                'maquinas' => collect(),
                'noResults' => true
            ])->with('error', 'Error al cargar los datos: ' . $e->getMessage());
        }
    }

    /**
     * Crear una nueva máquina
     */
    public function storeMaquina(Request $request)
    {
        try {
            $request->validate([
                'MaquinaId' => 'required|string|max:50|unique:URDCatalogoMaquinas,MaquinaId',
                'Nombre' => 'nullable|string|max:100',
                'Departamento' => 'nullable|string|max:50',
            ]);

            URDCatalogoMaquina::create([
                'MaquinaId' => $request->MaquinaId,
                'Nombre' => $request->Nombre,
                'Departamento' => $request->Departamento,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Máquina creada exitosamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear máquina', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la máquina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una máquina existente
     */
    public function updateMaquina(Request $request, $maquinaId)
    {
        try {
            $maquina = URDCatalogoMaquina::where('MaquinaId', $maquinaId)->firstOrFail();

            $rules = [
                'MaquinaId' => 'required|string|max:50',
                'Nombre' => 'nullable|string|max:100',
                'Departamento' => 'nullable|string|max:50',
            ];

            // Si el MaquinaId cambió, validar que no exista
            if ($request->MaquinaId !== $maquinaId) {
                $rules['MaquinaId'] .= '|unique:URDCatalogoMaquinas,MaquinaId';
            }

            $request->validate($rules);

            // Si el ID cambió, necesitamos eliminar el registro anterior y crear uno nuevo
            if ($request->MaquinaId !== $maquinaId) {
                $maquina->delete();
                URDCatalogoMaquina::create([
                    'MaquinaId' => $request->MaquinaId,
                    'Nombre' => $request->Nombre,
                    'Departamento' => $request->Departamento,
                ]);
            } else {
                $maquina->update([
                    'Nombre' => $request->Nombre,
                    'Departamento' => $request->Departamento,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Máquina actualizada exitosamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar máquina', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la máquina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una máquina
     */
    public function destroyMaquina($maquinaId)
    {
        try {
            $maquina = URDCatalogoMaquina::where('MaquinaId', $maquinaId)->firstOrFail();
            $maquina->delete();

            return response()->json([
                'success' => true,
                'message' => 'Máquina eliminada exitosamente'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La máquina no fue encontrada'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar máquina', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la máquina: ' . $e->getMessage()
            ], 500);
        }
    }
}

