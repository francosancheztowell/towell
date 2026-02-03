<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\CatTipoFalla;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CatalogosFallasController extends Controller
{
    /**
     * Mostrar la vista principal con todas las fallas
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $tipoFallaFilter = trim((string) $request->get('tipo_falla', ''));
        $departamentoFilter = trim((string) $request->get('departamento', ''));

        $query = CatParosFallas::query()
            ->with('tipoFalla')
            ->when($q !== '', function ($qry) use ($q) {
                $qry->where(function ($subQry) use ($q) {
                    $subQry->where('Falla', 'like', "%{$q}%")
                        ->orWhere('Descripcion', 'like', "%{$q}%")
                        ->orWhere('Abreviado', 'like', "%{$q}%");
                });
            })
            ->when($tipoFallaFilter !== '', function ($qry) use ($tipoFallaFilter) {
                $qry->where('TipoFallaId', $tipoFallaFilter);
            })
            ->when($departamentoFilter !== '', function ($qry) use ($departamentoFilter) {
                $qry->where('Departamento', $departamentoFilter);
            })
            ->orderBy('TipoFallaId')
            ->orderBy('Departamento')
            ->orderBy('Falla');

        $items = $query->get();

        // Obtener tipos de falla y departamentos para los filtros
        $tiposFalla = CatTipoFalla::orderBy('TipoFallaId')->pluck('TipoFallaId');
        $departamentos = CatParosFallas::select('Departamento')
            ->distinct()
            ->orderBy('Departamento')
            ->pluck('Departamento');

        return view('modulos.mantenimiento.catalogos-fallas.index', compact(
            'items',
            'q',
            'tipoFallaFilter',
            'departamentoFilter',
            'tiposFalla',
            'departamentos'
        ));
    }

    /**
     * Guardar una nueva falla
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'TipoFallaId' => 'required|string|max:50',
                'Departamento' => 'required|string|max:50',
                'Falla' => 'required|string|max:100',
                'Descripcion' => 'nullable|string|max:255',
                'Abreviado' => 'nullable|string|max:50',
                'Seccion' => 'nullable|string|max:50',
            ]);

            CatParosFallas::create($data);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Falla creada correctamente.']);
            }

            return back()->with('success', 'Falla creada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error de validación', 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('Error al crear falla', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error al crear la falla: ' . $e->getMessage()], 500);
            }
            
            return back()->with('error', 'Error al crear la falla: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Actualizar una falla existente
     */
    public function update(Request $request, CatParosFallas $catalogosFalla)
    {
        try {
            $data = $request->validate([
                'TipoFallaId' => 'required|string|max:50',
                'Departamento' => 'required|string|max:50',
                'Falla' => 'required|string|max:100',
                'Descripcion' => 'nullable|string|max:255',
                'Abreviado' => 'nullable|string|max:50',
                'Seccion' => 'nullable|string|max:50',
            ]);

            $catalogosFalla->update($data);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Falla actualizada correctamente.']);
            }

            return back()->with('success', 'Falla actualizada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error de validación', 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('Error al actualizar falla', [
                'id' => $catalogosFalla->Id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error al actualizar la falla: ' . $e->getMessage()], 500);
            }
            
            return back()->with('error', 'Error al actualizar la falla: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Eliminar una falla
     */
    public function destroy(Request $request, CatParosFallas $catalogosFalla)
    {
        try {
            $catalogosFalla->delete();
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Falla eliminada correctamente.']);
            }
            
            return back()->with('success', 'Falla eliminada correctamente.');
        } catch (\Throwable $e) {
            Log::error('Error al eliminar falla', [
                'id' => $catalogosFalla->Id,
                'error' => $e->getMessage(),
            ]);
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error al eliminar la falla: ' . $e->getMessage()], 500);
            }
            
            return back()->with('error', 'Error al eliminar la falla: ' . $e->getMessage());
        }
    }
}
