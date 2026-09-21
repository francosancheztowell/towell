<?php

namespace App\Http\Controllers\Urdido\ProgramaUrdido;

use App\Http\Controllers\Controller;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Solo abre la pantalla: la edición la lleva el componente
 * App\Livewire\UrdEng\EdicionOrden, compartido con Engomado.
 */
class EditarOrdenesProgramadasController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $fromReimpresion = $request->query('from') === 'reimpresion';
        $routeBack = $fromReimpresion ? 'urdido.reimpresion.finalizadas' : 'urdido.programar.urdido';

        $ordenId = $request->query('orden_id');
        if (! $ordenId) {
            Log::warning('No se proporcionó orden_id');

            return redirect()->route($routeBack)->with('error', 'No se proporcionó el ID de la orden');
        }

        $orden = UrdProgramaUrdido::find($ordenId);
        if (! $orden) {
            Log::warning('Orden no encontrada', ['orden_id' => $ordenId]);

            return redirect()->route($routeBack)->with('error', 'Orden no encontrada');
        }

        // El distintivo "AX Urdido" del navbar; el resto de la pantalla lo pinta Livewire.
        $axUrdido = (int) ($orden->AX ?? $orden->getAttribute('ax') ?? 0);
        if ($axUrdido === 0) {
            $axUrdido = (int) (DB::table('UrdProgramaUrdido')->where('Id', $orden->Id)->value('ax') ?? 0);
        }

        return view('modulos.urdido.editar-orden-programada', [
            'orden' => $orden,
            'axUrdido' => $axUrdido,
            'fromReimpresion' => $fromReimpresion,
        ]);
    }
}
