<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Sistema\SYSUsuario;
use Illuminate\Support\Facades\Schema;

class BaseDeDatosController extends Controller
{
    public function index()
    {
        // Verificar y agregar la columna 'Productivo' si no existe
        if (!Schema::hasColumn('SYSUsuario', 'Productivo')) {
            Schema::table('SYSUsuario', function ($table) {
                $table->tinyInteger('Productivo')->default(0)->notNull(); // 0 = Prueba, 1 = Productivo
            });
        }

        $usuarios = SYSUsuario::select('idusuario', 'nombre', 'area', 'puesto', 'Productivo')
            ->orderBy('nombre')
            ->get();

        return view('modulos.configuracion.basededatos', [
            'usuarios' => $usuarios,
        ]);
    }

    public function updateProductivo()
    {
        try {
            $request = request();
            
            $request->validate([
                'user_id' => 'required|integer|exists:SYSUsuario,idusuario',
                'productivo' => 'required|integer|in:0,1'
            ]);

            $usuario = SYSUsuario::findOrFail($request->user_id);
            $usuario->Productivo = $request->productivo;
            $usuario->save();

            $estado = $request->productivo == 1 ? 'Productivo' : 'Prueba';

            return response()->json([
                'success' => true,
                'message' => "Estado actualizado a '{$estado}' correctamente",
                'estado' => $estado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado: ' . $e->getMessage()
            ], 500);
        }
    }
}
