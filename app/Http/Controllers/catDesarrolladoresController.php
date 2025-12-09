<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\catDesarrolladoresModel;

class catDesarrolladoresController extends Controller
{
    public function index(Request $request){
        $items = catDesarrolladoresModel::all();
        return view('modulos.desarrolladores.catalogo-desarrolladores', compact('items'));
    }

    public function store(Request $request){
        $validated = $request->validate([
            'clave_empleado' => 'required|string|max:50',
            'nombre' => 'required|string|max:255',
            'Turno' => 'required|string|max:10'
        ]);

        catDesarrolladoresModel::create($validated);

        return redirect()->route('desarrolladores.catalogo-desarrolladores')
            ->with('success', 'Desarrollador creado exitosamente');
    }

    public function update(Request $request, $id){
        $validated = $request->validate([
            'clave_empleado' => 'required|string|max:50',
            'nombre' => 'required|string|max:255',
            'Turno' => 'required|string|max:10'
        ]);

        $desarrollador = catDesarrolladoresModel::findOrFail($id);
        $desarrollador->update($validated);

        return redirect()->route('desarrolladores.catalogo-desarrolladores')
            ->with('success', 'Desarrollador actualizado exitosamente');
    }

    public function destroy($id){
        $desarrollador = catDesarrolladoresModel::findOrFail($id);
        $desarrollador->delete();

        return redirect()->route('desarrolladores.catalogo-desarrolladores')
            ->with('success', 'Desarrollador eliminado exitosamente');
    }

}

