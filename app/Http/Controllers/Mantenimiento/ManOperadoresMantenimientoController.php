<?php

namespace App\Http\Controllers\Mantenimiento;

use App\Http\Controllers\Controller;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ManOperadoresMantenimientoController extends Controller
{
    /**
     * Mostrar la vista principal con todos los operadores
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $turnoFilter = trim((string) $request->get('turno', ''));
        $deptoFilter = trim((string) $request->get('depto', ''));

        $query = ManOperadoresMantenimiento::query()
            ->when($q !== '', function ($qry) use ($q) {
                $qry->where(function ($subQry) use ($q) {
                    $subQry->where('CveEmpl', 'like', "%{$q}%")
                        ->orWhere('NomEmpl', 'like', "%{$q}%")
                        ->orWhere('Depto', 'like', "%{$q}%")
                        ->orWhere('Telefono', 'like', "%{$q}%");
                });
            })
            ->when($turnoFilter !== '', function ($qry) use ($turnoFilter) {
                $qry->where('Turno', $turnoFilter);
            })
            ->when($deptoFilter !== '', function ($qry) use ($deptoFilter) {
                $qry->where('Depto', 'like', "%{$deptoFilter}%");
            })
            ->orderBy('NomEmpl');

        $items = $query->get();

        // Obtener turnos y departamentos únicos para los filtros
        $turnos = ManOperadoresMantenimiento::select('Turno')
            ->distinct()
            ->whereNotNull('Turno')
            ->orderBy('Turno')
            ->pluck('Turno');
        
        $departamentos = ManOperadoresMantenimiento::select('Depto')
            ->distinct()
            ->whereNotNull('Depto')
            ->orderBy('Depto')
            ->pluck('Depto');

        return view('modulos.mantenimiento.operadores-mantenimiento.index', compact(
            'items',
            'q',
            'turnoFilter',
            'deptoFilter',
            'turnos',
            'departamentos'
        ));
    }

    /**
     * Guardar un nuevo operador
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'CveEmpl' => 'required|string|max:50',
                'NomEmpl' => 'required|string|max:255',
                'Turno' => 'required|integer|min:1|max:3',
                'Depto' => 'required|string|max:100',
                'Telefono' => 'nullable|string|max:50',
            ]);

            ManOperadoresMantenimiento::create($data);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Operador creado correctamente.']);
            }

            return back()->with('success', 'Operador creado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error de validación', 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('Error al crear operador de mantenimiento', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error al crear el operador: ' . $e->getMessage()], 500);
            }
            
            return back()->with('error', 'Error al crear el operador: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Actualizar un operador existente
     */
    public function update(Request $request, ManOperadoresMantenimiento $operador)
    {
        try {
            $data = $request->validate([
                'CveEmpl' => 'required|string|max:50',
                'NomEmpl' => 'required|string|max:255',
                'Turno' => 'required|integer|min:1|max:3',
                'Depto' => 'required|string|max:100',
                'Telefono' => 'nullable|string|max:50',
            ]);

            $operador->update($data);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Operador actualizado correctamente.']);
            }

            return back()->with('success', 'Operador actualizado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error de validación', 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            Log::error('Error al actualizar operador de mantenimiento', [
                'id' => $operador->Id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error al actualizar el operador: ' . $e->getMessage()], 500);
            }
            
            return back()->with('error', 'Error al actualizar el operador: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Eliminar un operador
     */
    public function destroy(Request $request, ManOperadoresMantenimiento $operador)
    {
        try {
            $operador->delete();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Operador eliminado correctamente.']);
            }

            return back()->with('success', 'Operador eliminado correctamente.');
        } catch (\Throwable $e) {
            Log::error('Error al eliminar operador de mantenimiento', [
                'id' => $operador->Id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error al eliminar el operador: ' . $e->getMessage()], 500);
            }
            
            return back()->with('error', 'Error al eliminar el operador: ' . $e->getMessage());
        }
    }
}
