<?php

namespace App\Http\Controllers;

use App\Models\TelActividadesBPM;
use Illuminate\Http\Request;

class TelActividadesBPMController extends Controller
{
    //

     /**
     * Listado + búsqueda por Actividad
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $items = TelActividadesBPM::query()
            ->when($q !== '', fn($qry) => $qry->where('Actividad', 'like', "%{$q}%"))
            ->orderBy('Orden', 'desc')
            ->get();

        return view('tel-actividades-bpm.index', compact('items', 'q'));
    }

    /**
     * Form crear
     */
    public function create()
    {
        return view('tel-actividades-bpm.create');
    }

    /**
     * Guardar
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'Actividad' => ['required', 'string', 'max:100'],
        ]);

        TelActividadesBPM::create($data);

        return redirect()
            ->route('tel-actividades-bpm.index')
            ->with('success', 'Actividad creada correctamente.');
    }

    /**
     * Form editar
     */
    public function edit(TelActividadesBPM $telActividadesBPM)
    {
        // $telActividadesBPM se resuelve por Orden (PK)
        return view('tel-actividades-bpm.edit', [
            'item' => $telActividadesBPM,
        ]);
    }

    /**
     * Actualizar
     */
    public function update(Request $request, TelActividadesBPM $telActividadesBPM)
    {
        $data = $request->validate([
            'Actividad' => ['required', 'string', 'max:100'],
        ]);

        $telActividadesBPM->update($data);

        return redirect()
            ->route('tel-actividades-bpm.index')
            ->with('success', 'Actividad actualizada correctamente.');
    }

    /**
     * Eliminar
     */
    public function destroy(TelActividadesBPM $telActividadesBPM)
    {
        $telActividadesBPM->delete();

        return redirect()
            ->route('tel-actividades-bpm.index')
            ->with('success', 'Actividad eliminada correctamente.');
    }
}
