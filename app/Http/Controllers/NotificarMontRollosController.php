<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\TelTelaresOperador;
use App\Models\TejInventarioTelares;
use Carbon\Carbon;

class NotificarMontRollosController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Obtener los telares asignados al usuario actual
        $telaresOperador = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
            ->pluck('NoTelarId')
            ->toArray();
        
        // Si es una petición AJAX
        if ($request->ajax() || $request->wantsJson()) {
            // Si se solicita solo el listado de telares
            if ($request->has('listado')) {
                return response()->json(['telares' => $telaresOperador]);
            }
            
            // Si se solicita detalle de un telar específico con tipo
            if ($request->has('no_telar') && $request->has('tipo')) {
                $detalles = TejInventarioTelares::where('no_telar', $request->no_telar)
                    ->where('tipo', $request->tipo)
                    ->whereIn('no_telar', $telaresOperador)
                    ->select('id', 'no_telar', 'cuenta', 'calibre', 'tipo', 'tipo_atado', 'no_orden', 'no_rollo', 'metros', 'horaParo')
                    ->first();
                
                return response()->json(['detalles' => $detalles]);
            }
            
            return response()->json(['error' => 'Parámetros inválidos'], 400);
        }
        
        // Si no es AJAX, devolver vista
        $tipo = $request->query('tipo');
        
        $telares = TejInventarioTelares::whereIn('no_telar', $telaresOperador)
            ->select('no_telar', 'tipo')
            ->distinct()
            ->orderBy('no_telar');
            
        if ($tipo && in_array($tipo, ['rizo', 'pie'])) {
            $telares->where('tipo', $tipo);
        }
        
        $telares = $telares->get();
            
        return view('modulos.notificar-montado-rollos.index', compact('telares', 'tipo'));
    }

    public function notificar(Request $request)
    {
        try {
            $registro = TejInventarioTelares::find($request->id);
            
            if (!$registro) {
                return response()->json(['error' => 'Registro no encontrado'], 404);
            }

            // Actualizar horaParo con la hora actual
            $horaActual = Carbon::now()->format('H:i:s');
            $registro->horaParo = $horaActual;
            $registro->save();

            return response()->json([
                'success' => true,
                'horaParo' => $horaActual,
                'message' => 'Notificación de rollo registrada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
