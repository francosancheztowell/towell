<?php

namespace App\Http\Controllers\Engomado\ProgramaEngomado;

use App\Http\Controllers\Controller;
use App\Models\Engomado\EngProgramaEngomado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Solo abre la pantalla: la edición la lleva el componente
 * App\Livewire\UrdEng\EdicionOrden, compartido con Urdido.
 */
class EditarOrdenesEngomadoController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $fromReimpresion = $request->query('from') === 'reimpresion';
        $routeBack = $fromReimpresion ? 'engomado.reimpresion.finalizadas' : 'engomado.programar.engomado';

        $ordenId = $request->query('orden_id');
        if (! $ordenId) {
            Log::warning('Editar Engomado: No se proporcionó orden_id');

            return redirect()->route($routeBack)->with('error', 'No se proporcionó el ID de la orden');
        }

        $orden = EngProgramaEngomado::find($ordenId);
        if (! $orden) {
            Log::warning('Editar Engomado: Orden no encontrada', ['orden_id' => $ordenId]);

            return redirect()->route($routeBack)->with('error', 'Orden no encontrada');
        }

        return view('modulos.engomado.editar-orden-engomado', [
            'orden' => $orden,
            'fromReimpresion' => $fromReimpresion,
        ]);
    }
}
