<?php

namespace App\Http\Controllers\Tejedores;

use App\Http\Controllers\Controller;
use App\Models\Tejedores\TelActividadesBPM;
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
            ->when($q !== '', fn ($qry) => $qry->where('Actividad', 'like', "%{$q}%"))
            ->orderBy('Orden', 'asc')
            ->get();

        return view('modulos.tel-actividades-bpm.index', compact('items', 'q'));
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
