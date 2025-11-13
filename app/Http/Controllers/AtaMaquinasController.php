<?php

namespace App\Http\Controllers;

use App\Models\AtaMaquinasModel;
use Illuminate\Http\Request;

class AtaMaquinasController extends Controller
{
    /**
     * Mostrar la vista principal con todas las máquinas
     */
    public function index()
    {
        $maquinas = AtaMaquinasModel::all();
        return view('modulos.catalogos-atadores.maquinas.index', compact('maquinas'));
    }

    /**
     * Guardar una nueva máquina
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'MaquinaId' => 'required|string|max:255|unique:AtaMaquinas,MaquinaId'
            ]);

            AtaMaquinasModel::create([
                'MaquinaId' => $request->MaquinaId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Máquina creada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la máquina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una máquina existente
     */
    public function update(Request $request, $maquinaId)
    {
        try {
            $maquina = AtaMaquinasModel::where('MaquinaId', $maquinaId)->firstOrFail();
            
            $request->validate([
                'MaquinaId' => 'required|string|max:255|unique:AtaMaquinas,MaquinaId,' . $maquinaId . ',MaquinaId'
            ]);

            $maquina->update([
                'MaquinaId' => $request->MaquinaId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Máquina actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la máquina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una máquina
     */
    public function destroy($maquinaId)
    {
        try {
            $maquina = AtaMaquinasModel::where('MaquinaId', $maquinaId)->firstOrFail();
            $maquina->delete();

            return response()->json([
                'success' => true,
                'message' => 'Máquina eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la máquina: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una máquina específica
     */
    public function show($maquinaId)
    {
        try {
            $maquina = AtaMaquinasModel::where('MaquinaId', $maquinaId)->firstOrFail();
            return response()->json([
                'success' => true,
                'data' => $maquina
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Máquina no encontrada'
            ], 404);
        }
    }
}
