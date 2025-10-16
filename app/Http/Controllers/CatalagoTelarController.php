<?php

namespace App\Http\Controllers;

use App\Models\CatalagoTelar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalagoTelarController extends Controller
{
    public function index(Request $request)
    {
        // Datos de ejemplo estáticos (solo frontend)
        $telares = collect([
            (object)['salon' => 'Salón A', 'telar' => 'T001', 'nombre' => 'Telar Sulzer 1', 'grupo' => 'Grupo 1'],
            (object)['salon' => 'Salón A', 'telar' => 'T002', 'nombre' => 'Telar Sulzer 2', 'grupo' => 'Grupo 1'],
            (object)['salon' => 'Salón B', 'telar' => 'T003', 'nombre' => 'Telar Jacquard 1', 'grupo' => 'Grupo 2'],
            (object)['salon' => 'Salón B', 'telar' => 'T004', 'nombre' => 'Telar Jacquard 2', 'grupo' => 'Grupo 2'],
            (object)['salon' => 'Salón C', 'telar' => 'T005', 'nombre' => 'Telar Air Jet 1', 'grupo' => 'Grupo 3'],
            (object)['salon' => 'Salón C', 'telar' => 'T006', 'nombre' => 'Telar Air Jet 2', 'grupo' => 'Grupo 3'],
            (object)['salon' => 'Salón A', 'telar' => 'T007', 'nombre' => 'Telar Sulzer 3', 'grupo' => 'Grupo 1'],
            (object)['salon' => 'Salón B', 'telar' => 'T008', 'nombre' => 'Telar Jacquard 3', 'grupo' => 'Grupo 2'],
            (object)['salon' => 'Salón C', 'telar' => 'T009', 'nombre' => 'Telar Air Jet 3', 'grupo' => 'Grupo 3'],
            (object)['salon' => 'Salón D', 'telar' => 'T010', 'nombre' => 'Telar Especial 1', 'grupo' => 'Grupo 4'],
        ]);

        // Aplicar filtros de búsqueda (solo frontend)
        if ($request->salon) {
            $telares = $telares->filter(function ($telar) use ($request) {
                return stripos($telar->salon, $request->salon) !== false;
            });
        }

        if ($request->telar) {
            $telares = $telares->filter(function ($telar) use ($request) {
                return stripos($telar->telar, $request->telar) !== false;
            });
        }

        if ($request->nombre) {
            $telares = $telares->filter(function ($telar) use ($request) {
                return stripos($telar->nombre, $request->nombre) !== false;
            });
        }

        if ($request->grupo) {
            $telares = $telares->filter(function ($telar) use ($request) {
                return stripos($telar->grupo, $request->grupo) !== false;
            });
        }

        // Verifica si hay resultados
        $noResults = $telares->isEmpty();

        // Pasa los resultados y el estado de "sin resultados"
        return view('catalagos.catalagoTelares', compact('telares', 'noResults'));
    }

    public function create()
    {
        return view('catalagos.telaresCreate'); // Cargar la vista para agregar un telar
    }

    public function store(Request $request)
    {
        // Validación de los datos
        $request->validate([
            'salon' => 'required',
            'telar' => 'required',
            'nombre' => 'required',
            'cuenta' => 'required',
            'piel' => 'required',
            'ancho' => 'nullable',
        ]);

        // Crear un nuevo telar
        CatalagoTelar::create([
            'salon' => $request->salon,
            'telar' => $request->telar,
            'nombre' => $request->nombre,
            'cuenta' => $request->cuenta,
            'piel' => $request->piel,
            'ancho' => $request->ancho,
        ]);

        // Redirigir a la página de índice o donde desees
        return redirect()->route('telares.index')->with('success', 'Telar agregado exitosamente!');
    }

        public function show($id)
    {
        // Solo para evitar el error
        return redirect()->route('telares.index');
    }


}
