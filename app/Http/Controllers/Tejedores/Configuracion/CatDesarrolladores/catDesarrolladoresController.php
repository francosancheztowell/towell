<?php

namespace App\Http\Controllers\Tejedores\Configuracion\CatDesarrolladores;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tejedores\catDesarrolladoresModel;
use App\Models\Sistema\Usuario;

class catDesarrolladoresController extends Controller
{
    public function index(Request $request){
        $items = catDesarrolladoresModel::all();
        $query = Usuario::porArea('Desarrolladores')->orderBy('nombre');
        $yaEnCatalogo = $items->pluck('clave_empleado')->filter()->values()->toArray();
        if (count($yaEnCatalogo) > 0) {
            $query->whereNotIn('numero_empleado', $yaEnCatalogo);
        }
        $desarrolladores = $query->get(['idusuario', 'numero_empleado', 'nombre', 'turno']);
        return view('modulos.desarrolladores.catalogo-desarrolladores', compact('items', 'desarrolladores'));
    }

    public function store(Request $request){
        $validated = $request->validate([
            'clave_empleado' => 'required|string|max:50'
        ]);
        $usuario = Usuario::porArea('Desarrolladores')
            ->where('numero_empleado', $validated['clave_empleado'])
            ->firstOrFail();
        catDesarrolladoresModel::create([
            'clave_empleado' => $usuario->numero_empleado,
            'nombre' => $usuario->nombre,
            'Turno' => (string) ($usuario->turno ?? ''),
        ]);

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

