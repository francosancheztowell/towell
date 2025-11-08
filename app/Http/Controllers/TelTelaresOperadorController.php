<?php

namespace App\Http\Controllers;

use App\Models\TelTelaresOperador;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TelTelaresOperadorController extends Controller
{
    //

    /**
     * Listado + búsqueda por número, nombre o telar
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $perPage = (int) $request->get('per_page', 15);

        $items = TelTelaresOperador::query()
            ->when($q !== '', function ($qry) use ($q) {
                $qry->where(function ($w) use ($q) {
                    $w->where('numero_empleado', 'like', "%{$q}%")
                      ->orWhere('nombreEmpl', 'like', "%{$q}%")
                      ->orWhere('NoTelarId', 'like', "%{$q}%");
                });
            })
            ->orderBy('numero_empleado')
            ->paginate($perPage)
            ->withQueryString();

        return view('tel-telares-operador.index', compact('items', 'q'));
    }

    /**
     * Form crear
     */
    public function create()
    {
        return view('tel-telares-operador.create');
    }

    /**
     * Guardar (numero_empleado como PK lógica)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'numero_empleado' => ['required', 'string', 'max:30', 'unique:TelTelaresOperador,numero_empleado'],
            'nombreEmpl'      => ['required', 'string', 'max:150'],
            'NoTelarId'       => ['nullable', 'string', 'max:10'],
        ]);

        TelTelaresOperador::create($data);

        return redirect()
            ->route('tel-telares-operador.index')
            ->with('success', 'Operador registrado correctamente.');
    }

    /**
     * Form editar
     * Route Model Binding por clave primaria: numero_empleado
     */
    public function edit(TelTelaresOperador $telTelaresOperador)
    {
        return view('tel-telares-operador.edit', [
            'item' => $telTelaresOperador,
        ]);
    }

    /**
     * Actualizar (permite cambiar numero_empleado)
     */
    public function update(Request $request, TelTelaresOperador $telTelaresOperador)
    {
        $data = $request->validate([
            'numero_empleado' => [
                'required', 'string', 'max:30',
                Rule::unique('TelTelaresOperador', 'numero_empleado')
                    ->ignore($telTelaresOperador->getKey(), $telTelaresOperador->getKeyName()),
            ],
            'nombreEmpl' => ['required', 'string', 'max:150'],
            'NoTelarId'  => ['nullable', 'string', 'max:10'],
        ]);

        // Si cambia la PK, Eloquent lo maneja porque no es incrementing
        $telTelaresOperador->update($data);

        return redirect()
            ->route('tel-telares-operador.index')
            ->with('success', 'Operador actualizado correctamente.');
    }

    /**
     * Eliminar
     */
    public function destroy(TelTelaresOperador $telTelaresOperador)
    {
        $telTelaresOperador->delete();

        return redirect()
            ->route('tel-telares-operador.index')
            ->with('success', 'Operador eliminado correctamente.');
    }
}
