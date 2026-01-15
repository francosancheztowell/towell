<?php

namespace App\Http\Controllers\UrdEngomado;

use App\Http\Controllers\Controller;
use App\Models\UrdEngomado\UrdEngNucleos;
use Illuminate\Http\Request;

class UrdEngNucleosController extends Controller
{
    /**
     * Mostrar la lista de núcleos con paginación y búsqueda
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 15);

        $items = UrdEngNucleos::query()
            ->when($q !== '', function($query) use ($q) {
                $query->where('Salon', 'like', "%{$q}%")
                      ->orWhere('Nombre', 'like', "%{$q}%");
            })
            ->orderBy('Salon')
            ->orderBy('Nombre')
            ->paginate($perPage)
            ->withQueryString();

        return view('modulos.engomado.urd-eng-nucleos.index', compact('items', 'q'));
    }

    /**
     * Crear un nuevo núcleo
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'Salon' => ['required', 'string', 'max:50'],
            'Nombre' => ['required', 'string', 'max:120'],
        ], [
            'Salon.required' => 'El salón es requerido.',
            'Salon.max' => 'El salón no puede tener más de 50 caracteres.',
            'Nombre.required' => 'El nombre es requerido.',
            'Nombre.max' => 'El nombre no puede tener más de 120 caracteres.',
        ]);

        // Verificar duplicados (basado en el índice único)
        $exists = UrdEngNucleos::where('Salon', $data['Salon'])
                                ->where('Nombre', $data['Nombre'])
                                ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Ya existe un núcleo con este salón y nombre.');
        }

        try {
            UrdEngNucleos::create($data);
            return back()->with('success', 'Núcleo creado correctamente.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error al crear el núcleo: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar un núcleo existente
     */
    public function update(Request $request, UrdEngNucleos $urdEngNucleo)
    {
        $data = $request->validate([
            'Salon' => ['required', 'string', 'max:50'],
            'Nombre' => ['required', 'string', 'max:120'],
        ], [
            'Salon.required' => 'El salón es requerido.',
            'Salon.max' => 'El salón no puede tener más de 50 caracteres.',
            'Nombre.required' => 'El nombre es requerido.',
            'Nombre.max' => 'El nombre no puede tener más de 120 caracteres.',
        ]);

        // Verificar duplicados excluyendo el registro actual
        $exists = UrdEngNucleos::where('Salon', $data['Salon'])
                                ->where('Nombre', $data['Nombre'])
                                ->where('Id', '!=', $urdEngNucleo->Id)
                                ->exists();

        if ($exists) {
            return back()->withInput()->with('error', 'Ya existe otro núcleo con este salón y nombre.');
        }

        try {
            $urdEngNucleo->update($data);
            return back()->with('success', 'Núcleo actualizado correctamente.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Error al actualizar el núcleo: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar un núcleo
     */
    public function destroy(UrdEngNucleos $urdEngNucleo)
    {
        try {
            $urdEngNucleo->delete();
            return back()->with('success', 'Núcleo eliminado correctamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al eliminar el núcleo: ' . $e->getMessage());
        }
    }

    /**
     * Redirigir a index (para rutas create/edit si se usan)
     */
    public function create()
    {
        return redirect()->route('urd-eng-nucleos.index');
    }

    public function edit(UrdEngNucleos $urdEngNucleo)
    {
        return redirect()->route('urd-eng-nucleos.index');
    }

    /**
     * API: Obtener lista de núcleos para select
     */
    public function getNucleos()
    {
        try {
            $nucleos = UrdEngNucleos::select('Id', 'Salon', 'Nombre')
                ->orderBy('Salon')
                ->orderBy('Nombre')
                ->get()
                ->map(function($item) {
                    return [
                        'id' => $item->Id,
                        'salon' => $item->Salon,
                        'nombre' => $item->Nombre,
                        'value' => $item->Nombre, // Valor para el select
                        'text' => $item->Nombre,  // Texto para mostrar
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $nucleos,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener núcleos: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
