<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TelTelaresOperador;
use App\Models\TejInventarioTelares;

class NotificarMontadoJulioController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Obtener los telares asignados al usuario actual
        $telaresOperador = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
            ->pluck('NoTelarId')
            ->toArray();
        
        // Obtener telares del inventario
        $telares = TejInventarioTelares::whereIn('no_telar', $telaresOperador)
            ->orderBy('no_telar')
            ->get(['no_telar', 'tipo']);
        
        // Si es una petición AJAX, devolver JSON
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['telares' => $telares]);
        }
        
        // Si no, devolver la vista (por compatibilidad)
        return view('modulos.notificar-montado-julios.index', compact('telares'));
    }
}
