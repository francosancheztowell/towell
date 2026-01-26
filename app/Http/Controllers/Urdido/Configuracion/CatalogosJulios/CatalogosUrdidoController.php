<?php

namespace App\Http\Controllers\Urdido\Configuracion\CatalogosJulios;

use App\Http\Controllers\Controller;
use App\Models\Urdido\UrdCatJulios;
use App\Models\Urdido\URDCatalogoMaquina;
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

            // Detectar si viene de urdido o engomado por la ruta
            $isEngomado = $request->route()->getName() === 'engomado.configuracion.catalogos.julios';
            $departamentoFiltro = $isEngomado ? 'Engomado' : 'Urdido';

            // Filtrar automáticamente por departamento según la ruta
            $query->where('Departamento', $departamentoFiltro);

            // Filtros opcionales adicionales
            if ($request->filled('no_julio')) {
                $query->where('NoJulio', 'like', "%{$request->no_julio}%");
            }

            $julios = $query->whereNotNull('NoJulio')
                ->orderBy('NoJulio')
                ->get();

            $noResults = $julios->isEmpty();

            return view('catalogosurdido.catalago-julios', compact('julios', 'noResults', 'departamentoFiltro'));
        } catch (\Exception $e) {
            Log::error('Error en CatalogosUrdidoController::catalogosJulios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $departamentoFiltro = $request->route()->getName() === 'engomado.configuracion.catalogos.julios' ? 'Engomado' : 'Urdido';
            return view('catalogosurdido.catalago-julios', [
                'julios' => collect(),
                'noResults' => true,
                'departamentoFiltro' => $departamentoFiltro
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

    /**
     * Crear un nuevo julio
     */
    public function storeJulio(Request $request)
    {
        try {
            // Detectar departamento según la ruta
            $isEngomado = $request->route()->getName() === 'engomado.configuracion.catalogos.julios.store'
                       || str_contains($request->path(), 'catalogojulioseng');
            $departamento = $isEngomado ? 'Engomado' : 'Urdido';

            $request->validate([
                'NoJulio' => 'required|string|max:50|unique:UrdCatJulios,NoJulio',
                'Tara' => 'nullable|numeric|min:0',
            ]);

            UrdCatJulios::create([
                'NoJulio' => $request->NoJulio,
                'Tara' => $request->Tara ?? 0,
                'Departamento' => $departamento,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Julio creado exitosamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear julio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el julio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un julio existente
     */
    public function updateJulio(Request $request, $id)
    {
        try {
            $julio = UrdCatJulios::where('Id', $id)
                ->orWhere('NoJulio', $id)
                ->firstOrFail();

            // Detectar departamento según la ruta
            $isEngomado = $request->route()->getName() === 'engomado.configuracion.catalogos.julios.update'
                       || str_contains($request->path(), 'catalogojulioseng');
            $departamento = $isEngomado ? 'Engomado' : 'Urdido';

            $rules = [
                'NoJulio' => 'required|string|max:50',
                'Tara' => 'nullable|numeric|min:0',
            ];

            // Si el NoJulio cambió, validar que no exista
            if ($request->NoJulio !== $julio->NoJulio) {
                $rules['NoJulio'] .= '|unique:UrdCatJulios,NoJulio';
            }

            $request->validate($rules);

            $julio->update([
                'NoJulio' => $request->NoJulio,
                'Tara' => $request->Tara ?? 0,
                'Departamento' => $departamento,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Julio actualizado exitosamente'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar julio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el julio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un julio
     */
    public function destroyJulio($id)
    {
        try {
            $julio = UrdCatJulios::where('Id', $id)
                ->orWhere('NoJulio', $id)
                ->firstOrFail();
            $julio->delete();

            return response()->json([
                'success' => true,
                'message' => 'Julio eliminado exitosamente'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'El julio no fue encontrado'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar julio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el julio: ' . $e->getMessage()
            ], 500);
        }
    }
}
