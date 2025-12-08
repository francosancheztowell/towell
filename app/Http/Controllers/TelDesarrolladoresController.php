<?php
namespace App\Http\Controllers;

use App\Models\TelTelaresOperador;
use Illuminate\Http\Request;

class TelDesarrolladoresController extends Controller
{
    public function index(Request $request)
    {
        $operadores = TelTelaresOperador::selectRaw('MIN(numero_empleado) as numero_empleado, nombreEmpl')
            ->whereNotNull('nombreEmpl')
            ->groupBy('nombreEmpl')
            ->orderBy('nombreEmpl')
            ->get();

        return view('modulos.desarrolladores.desarrolladores', compact('operadores'));
    }
}
