<?php

namespace App\Http\Controllers\Atadores\Catalogos\Comentarios;

use App\Http\Controllers\Controller;
use App\Models\AtaComentariosModel;
use Illuminate\Http\Request;

class AtaComentariosController extends Controller
{
    /**
     * Mostrar la vista principal con todos los comentarios
     */
    public function index()
    {
        $comentarios = AtaComentariosModel::all();
        return view('modulos.catalogos-atadores.comentarios.index', compact('comentarios'));
    }

    /**
     * Guardar un nuevo comentario
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'Nota1' => 'required|string|max:500',
                'Nota2' => 'nullable|string|max:500'
            ]);

            AtaComentariosModel::create([
                'Nota1' => $request->Nota1,
                'Nota2' => $request->Nota2
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comentario creado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el comentario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un comentario existente
     */
    public function update(Request $request, $nota1)
    {
        try {
            $comentario = AtaComentariosModel::where('Nota1', $nota1)->firstOrFail();
            
            $request->validate([
                'Nota1' => 'required|string|max:500|unique:AtaComentarios,Nota1,' . $nota1 . ',Nota1',
                'Nota2' => 'nullable|string|max:500'
            ]);

            $comentario->update([
                'Nota1' => $request->Nota1,
                'Nota2' => $request->Nota2
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comentario actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el comentario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un comentario
     */
    public function destroy($nota1)
    {
        try {
            $comentario = AtaComentariosModel::where('Nota1', $nota1)->firstOrFail();
            $comentario->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comentario eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el comentario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un comentario específico
     */
    public function show($nota1)
    {
        try {
            $comentario = AtaComentariosModel::where('Nota1', $nota1)->firstOrFail();
            return response()->json([
                'success' => true,
                'data' => $comentario
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Comentario no encontrado'
            ], 404);
        }
    }
}
