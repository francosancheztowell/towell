<?php
namespace App\Http\Controllers;

use App\Models\TelTelaresOperador;
use Illuminate\Http\Request;

class TelDesarrolladoresController extends Controller
{
    public function index(Request $request)
    {
        // Base query sin duplicados por nombre
        $operadoresQuery = TelTelaresOperador::selectRaw('MIN(numero_empleado) as numero_empleado, nombreEmpl')
            ->whereNotNull('nombreEmpl')
            ->groupBy('nombreEmpl')
            ->orderBy('nombreEmpl');

        // Petición AJAX/JSON: lista simple numero_empleado => nombreEmpl
        if ($request->ajax() || $request->wantsJson()) {
            $operadores = (clone $operadoresQuery)
                ->get()
                ->pluck('nombreEmpl', 'numero_empleado');
            return response()->json(['operadores' => $operadores]);
        }

        // Vista normal
        $operadores = $operadoresQuery->get();

        return view('modulos.desarrolladores.desarrolladores', compact('operadores'));
    }
}
