<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ReqMatrizHilos;
use Illuminate\Support\Facades\DB;

class MatrizHilosController extends Controller
{
    /**
     * Muestra la vista principal de Matriz de Hilos
     */
    public function index()
    {
        $matrizHilos = ReqMatrizHilos::orderBy('Hilo')->get();
        
        return view('catalagos.matriz-hilos', [
            'matrizHilos' => $matrizHilos
        ]);
    }

    /**
     * Crear nuevo registro de matriz hilos
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'Hilo' => 'required|string|max:30',
                'Calibre' => 'nullable|numeric',
                'Calibre2' => 'nullable|numeric',
                'CalibreAX' => 'nullable|string|max:20',
                'Fibra' => 'nullable|string|max:30',
                'CodColor' => 'nullable|string|max:10',
                'NombreColor' => 'nullable|string|max:60',
            ]);

            $matrizHilo = ReqMatrizHilos::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
                'data' => $matrizHilo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un registro específico
     */
    public function show($id)
    {
        $matrizHilo = ReqMatrizHilos::find($id);

        if (!$matrizHilo) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $matrizHilo
        ]);
    }

    /**
     * Actualizar un registro
     */
    public function update(Request $request, $id)
    {
        try {
            $matrizHilo = ReqMatrizHilos::find($id);

            if (!$matrizHilo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            $request->validate([
                'Hilo' => 'required|string|max:30',
                'Calibre' => 'nullable|numeric',
                'Calibre2' => 'nullable|numeric',
                'CalibreAX' => 'nullable|string|max:20',
                'Fibra' => 'nullable|string|max:30',
                'CodColor' => 'nullable|string|max:10',
                'NombreColor' => 'nullable|string|max:60',
            ]);

            $matrizHilo->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado exitosamente',
                'data' => $matrizHilo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un registro
     */
    public function destroy($id)
    {
        try {
            $matrizHilo = ReqMatrizHilos::find($id);

            if (!$matrizHilo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado'
                ], 404);
            }

            $matrizHilo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar registro: ' . $e->getMessage()
            ], 500);
        }
    }
}
