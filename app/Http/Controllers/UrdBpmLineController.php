<?php

namespace App\Http\Controllers;

use App\Models\UrdBpmModel;
use App\Models\UrdActividadesBpmModel;
use App\Models\UrdBpmLineModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UrdBpmLineController extends Controller
{
    public function index($folio)
    {
        // Obtener el registro del header
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();
        
        // Obtener todas las actividades ordenadas
        $actividades = UrdActividadesBpmModel::orderBy('Orden')->get();
        
        // Obtener las líneas existentes del checklist
        $lineas = UrdBpmLineModel::where('Folio', $folio)->get()->keyBy('Actividad');
        
        return view('modulos.urdido.Urdido-BPM-Line.index', compact('header', 'actividades', 'lineas'));
    }

    public function toggleActividad(Request $request, $folio)
    {
        $validated = $request->validate([
            'actividad' => 'required|string',
            'checked' => 'required|boolean'
        ]);

        if ($validated['checked']) {
            // Crear o actualizar la línea
            UrdBpmLineModel::updateOrCreate(
                [
                    'Folio' => $folio,
                    'Actividad' => $validated['actividad']
                ],
                [
                    'Valor' => 1,
                    'TurnoRecibe' => Auth::user()->turno ?? null
                ]
            );
        } else {
            // Eliminar la línea si se desmarca
            UrdBpmLineModel::where('Folio', $folio)
                ->where('Actividad', $validated['actividad'])
                ->delete();
        }

        return response()->json(['success' => true]);
    }

    public function terminar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();
        
        if ($header->Status !== 'Creado') {
            return redirect()->back()->with('error', 'Solo se puede terminar un registro en estado Creado');
        }

        $header->update(['Status' => 'Terminado']);
        
        return redirect()->back()->with('success', 'Registro marcado como Terminado');
    }

    public function autorizar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();
        
        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede autorizar un registro Terminado');
        }

        $header->update(['Status' => 'Autorizado']);
        
        return redirect()->back()->with('success', 'Registro Autorizado exitosamente');
    }

    public function rechazar($folio)
    {
        $header = UrdBpmModel::where('Folio', $folio)->firstOrFail();
        
        if ($header->Status !== 'Terminado') {
            return redirect()->back()->with('error', 'Solo se puede rechazar un registro Terminado');
        }

        $header->update(['Status' => 'Creado']);
        
        return redirect()->back()->with('success', 'Registro rechazado, regresado a estado Creado');
    }
}
