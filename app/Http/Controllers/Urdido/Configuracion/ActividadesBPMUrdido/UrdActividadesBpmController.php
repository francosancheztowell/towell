<?php

namespace App\Http\Controllers\Urdido\Configuracion\ActividadesBPMUrdido;

use App\Http\Controllers\Controller;
use App\Models\Urdido\UrdActividadesBpmModel;
use Illuminate\Http\Request;

class UrdActividadesBpmController extends Controller
{

    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 15);

        $items = UrdActividadesBpmModel::query()
            ->when($q !== '', fn($qry) =>
                $qry->where('Actividad', 'like', "%{$q}%")
                    ->orWhere('Orden', 'like', "%{$q}%")
            )
            ->orderByRaw("CASE WHEN Maquina = 'MC' THEN 0 ELSE 1 END")
            ->orderBy('Orden')
            ->orderBy('Id')
            ->get();

        return view('modulos.urdido.urd-actividades-bpm.index', compact('items', 'q'));
    }

    /**
     * Crear (desde modal).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'Orden'     => ['nullable', 'integer', 'min:1'],
            'Actividad' => ['required', 'string', 'max:100'],
            'Maquina'   => ['required', 'in:MC,KM'],
        ]);

        UrdActividadesBpmModel::create($data);

        return back()->with('success', 'Actividad creada correctamente.');
    }

    /**
     * Editar (desde modal).
     */
    public function update(Request $request, UrdActividadesBpmModel $urdActividadesBpm)
    {
        $data = $request->validate([
            'Orden'     => ['nullable', 'integer', 'min:1'],
            'Actividad' => ['required', 'string', 'max:100'],
            'Maquina'   => ['required', 'in:MC,KM'],
        ]);

        $urdActividadesBpm->update($data);

        return back()->with('success', 'Actividad actualizada correctamente.');
    }

    /**
     * Eliminar (confirmado con SweetAlert en la vista).
     */
    public function destroy(UrdActividadesBpmModel $urdActividadesBpm)
    {
        $urdActividadesBpm->delete();

        return back()->with('success', 'Actividad eliminada.');
    }

    /* ====== No usamos create()/edit() porque la UI va por modales ====== */

    public function create()
    {
        return redirect()->route('urd-actividades-bpm.index');
    }

    public function edit(UrdActividadesBpmModel $urdActividadesBpm)
    {
        return redirect()->route('urd-actividades-bpm.index');
    }
}
