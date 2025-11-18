<?php

namespace App\Http\Controllers;

use App\Models\EngActividadesBpmModel;
use Illuminate\Http\Request;

class EngActividadesBpmController extends Controller
{
    /**
     * Listado con búsqueda y paginación.
     * La vista es única y contiene los modales de Crear/Editar.
     */
    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 15);

        $items = EngActividadesBpmModel::query()
            ->when($q !== '', fn($qry) =>
                $qry->where('Actividad', 'like', "%{$q}%")
                    ->orWhere('Orden', 'like', "%{$q}%")
            )
            ->orderBy('Orden')
            ->orderBy('Id')
            ->paginate($perPage)
            ->withQueryString();

        // Ajusta el path si usas otra carpeta
        return view('modulos.engomado.eng-actividades-bpm.index', compact('items', 'q'));
    }

    /**
     * Crear (desde modal).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'Orden'     => ['nullable', 'integer', 'min:1'],
            'Actividad' => ['required', 'string', 'max:100'],
            // Si quieres que no se repita el nombre:
            // 'Actividad' => ['required','string','max:100','unique:EngActividadesBPM,Actividad'],
        ]);

        EngActividadesBpmModel::create($data);

        return redirect()
            ->route('eng-actividades-bpm.index')
            ->with('success', 'Actividad (Eng) creada correctamente.');
    }

    /**
     * Editar (desde modal).
     */
    public function update(Request $request, EngActividadesBpmModel $engActividadesBpm)
    {
        $data = $request->validate([
            'Orden'     => ['nullable', 'integer', 'min:1'],
            'Actividad' => ['required', 'string', 'max:100'],
            // Si activaste unique en store, cámbialo a:
            // 'Actividad' => ['required','string','max:100','unique:EngActividadesBPM,Actividad,'.$engActividadesBpm->Id.',Id'],
        ]);

        $engActividadesBpm->update($data);

        return redirect()
            ->route('eng-actividades-bpm.index')
            ->with('success', 'Actividad (Eng) actualizada correctamente.');
    }

    /**
     * Eliminar (confirmado con SweetAlert en la vista).
     */
    public function destroy(EngActividadesBpmModel $engActividadesBpm)
    {
        $engActividadesBpm->delete();

        return redirect()
            ->route('eng-actividades-bpm.index')
            ->with('success', 'Actividad (Eng) eliminada.');
    }

    /* ====== No usamos create()/edit() porque la UI va por modales ====== */

    public function create()
    {
        return redirect()->route('eng-actividades-bpm.index');
    }

    public function edit(EngActividadesBpmModel $engActividadesBpm)
    {
        return redirect()->route('eng-actividades-bpm.index');
    }
}
