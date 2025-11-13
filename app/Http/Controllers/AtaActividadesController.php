<?php

namespace App\Http\Controllers;

use App\Models\AtaActividadesModel;
use Illuminate\Http\Request;

class AtaActividadesController extends Controller
{
    /**
     * Mostrar la vista principal con todas las actividades
     */
    public function index()
    {
        $actividades = AtaActividadesModel::all();
        return view('modulos.catalogos-atadores.actividades.index', compact('actividades'));
    }

    /**
     * Guardar una nueva actividad
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'ActividadId' => 'required|string|max:255|unique:AtaActividades,ActividadId',
                'Porcentaje' => 'required|numeric|min:0|max:100'
            ]);

            AtaActividadesModel::create([
                'ActividadId' => $request->ActividadId,
                'Porcentaje' => $request->Porcentaje
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Actividad creada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la actividad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una actividad existente
     */
    public function update(Request $request, $id)
    {
        try {
            $actividad = AtaActividadesModel::where('ActividadId', $id)->firstOrFail();
            
            $request->validate([
                'ActividadId' => 'required|string|max:255|unique:AtaActividades,ActividadId,' . $id . ',ActividadId',
                'Porcentaje' => 'required|numeric|min:0|max:100'
            ]);

            $actividad->update([
                'ActividadId' => $request->ActividadId,
                'Porcentaje' => $request->Porcentaje
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Actividad actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la actividad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una actividad
     */
    public function destroy($id)
    {
        try {
            $actividad = AtaActividadesModel::where('ActividadId', $id)->firstOrFail();
            $actividad->delete();

            return response()->json([
                'success' => true,
                'message' => 'Actividad eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la actividad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una actividad específica
     */
    public function show($id)
    {
        try {
            $actividad = AtaActividadesModel::where('ActividadId', $id)->firstOrFail();
            return response()->json([
                'success' => true,
                'data' => $actividad
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Actividad no encontrada'
            ], 404);
        }
    }
}
