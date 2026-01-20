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
                $table->tinyInteger('Productivo')->default(1)->notNull();
            });
        }

        $usuarios = SYSUsuario::select('idusuario', 'nombre', 'area', 'puesto', 'Productivo')
            ->orderBy('nombre')
            ->get();

        return view('modulos.configuracion.basededatos', [
            'usuarios' => $usuarios,
        ]);
    }
}
