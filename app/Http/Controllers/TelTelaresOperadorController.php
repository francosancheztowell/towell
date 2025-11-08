<?php

namespace App\Http\Controllers;

use App\Models\TelTelaresOperador;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

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

        $originalKey = $telTelaresOperador->getKey();

        try {
            DB::transaction(function () use ($telTelaresOperador, $data, $originalKey) {
                // Si la PK cambia, actualizar manualmente con where por clave original
                if ($data['numero_empleado'] !== $originalKey) {
                    TelTelaresOperador::where($telTelaresOperador->getKeyName(), $originalKey)
                        ->update($data);
                } else {
                    $telTelaresOperador->update($data);
                }
            });
        } catch (\Throwable $e) {
            return back()->withErrors('Error al actualizar: ' . $e->getMessage())->withInput();
        }

        return redirect()->route('tel-telares-operador.index')
            ->with('success', 'Operador actualizado correctamente.');
    }

    /**
     * Eliminar
     */
    public function destroy(TelTelaresOperador $telTelaresOperador)
    {
        try {
            $telTelaresOperador->delete();
        } catch (\Throwable $e) {
            return back()->withErrors('No se puede eliminar el operador: ' . $e->getMessage());
        }

        return redirect()->route('tel-telares-operador.index')
            ->with('success', 'Operador eliminado correctamente.');
    }
}
