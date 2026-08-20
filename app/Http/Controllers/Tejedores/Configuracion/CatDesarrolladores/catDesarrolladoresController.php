<?php

namespace App\Http\Controllers\Tejedores\Configuracion\CatDesarrolladores;

use App\Http\Controllers\Controller;
use App\Models\Sistema\Usuario;
use App\Models\Tejedores\catDesarrolladoresModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class catDesarrolladoresController extends Controller
{
    /** Nombre del modulo en SYSRoles (idrol 170). */
    private const MODULO = 'Catalogo Desarrolladores';

    public function __construct()
    {
        // El invitado pasa de largo para que 'auth' lo mande al login. Los permisos finos
        // de store/update/destroy coinciden con los <x-navbar.button-*> que la vista ya
        // oculta, asi que exigirlos no deja fuera a nadie que hoy pueda pulsar el boton.
        abort_if(Auth::check() && ! userCan('acceso', self::MODULO), 403, 'Sin permiso para el catalogo de desarrolladores.');
    }

    public function index(Request $request)
    {
        $items = catDesarrolladoresModel::all();
        $query = Usuario::porArea('Desarrolladores')->orderBy('nombre');
        $yaEnCatalogo = $items->pluck('clave_empleado')->filter()->values()->toArray();
        if (count($yaEnCatalogo) > 0) {
            $query->whereNotIn('numero_empleado', $yaEnCatalogo);
        }
        $desarrolladores = $query->get(['idusuario', 'numero_empleado', 'nombre', 'turno']);

        return view('modulos.desarrolladores.catalogo-desarrolladores', compact('items', 'desarrolladores'));
    }

    public function store(Request $request)
    {
        abort_if(Auth::check() && ! userCan('crear', self::MODULO), 403, 'Sin permiso para crear.');

        $validated = $request->validate([
            'clave_empleado' => 'required|string|max:50',
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

    public function update(Request $request, $id)
    {
        abort_if(Auth::check() && ! userCan('modificar', self::MODULO), 403, 'Sin permiso para modificar.');

        $validated = $request->validate([
            'clave_empleado' => 'required|string|max:50',
            'nombre' => 'required|string|max:255',
            'Turno' => 'required|string|max:10',
        ]);

        $desarrollador = catDesarrolladoresModel::findOrFail($id);
        $desarrollador->update($validated);

        return redirect()->route('desarrolladores.catalogo-desarrolladores')
            ->with('success', 'Desarrollador actualizado exitosamente');
    }

    public function destroy($id)
    {
        abort_if(Auth::check() && ! userCan('eliminar', self::MODULO), 403, 'Sin permiso para eliminar.');

        $desarrollador = catDesarrolladoresModel::findOrFail($id);
        $desarrollador->delete();

        return redirect()->route('desarrolladores.catalogo-desarrolladores')
            ->with('success', 'Desarrollador eliminado exitosamente');
    }
}
