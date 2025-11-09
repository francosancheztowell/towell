<?php

namespace App\Http\Controllers;

use App\Models\TelTelaresOperador;
use App\Models\ReqTelares;
use App\Models\SYSUsuario;
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

        $items = TelTelaresOperador::query()
            ->when($q !== '', function ($qry) use ($q) {
                $qry->where(function ($w) use ($q) {
                    $w->where('numero_empleado', 'like', "%{$q}%")
                      ->orWhere('nombreEmpl', 'like', "%{$q}%")
                      ->orWhere('NoTelarId', 'like', "%{$q}%");
                });
            })
            ->orderBy('numero_empleado')
            ->get();

        $telares = ReqTelares::obtenerTodos();
        $usuarios = SYSUsuario::select('numero_empleado','nombre','turno')->orderBy('numero_empleado')->get();
        return view('tel-telares-operador.index', compact('items', 'q', 'telares','usuarios'));
    }

    /**
     * Form crear
     */
    public function create()
    {
        $telares = ReqTelares::obtenerTodos();
        $usuarios = SYSUsuario::select('numero_empleado','nombre','turno')->orderBy('numero_empleado')->get();
        return view('tel-telares-operador.create', compact('telares','usuarios'));
    }

    /**
     * Guardar (numero_empleado como PK lógica)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'numero_empleado' => ['required', 'string', 'max:30', 'exists:SYSUsuario,numero_empleado', 'unique:TelTelaresOperador,numero_empleado'],
            'NoTelarId'       => ['required', 'string', 'max:10'],
            'SalonTejidoId'   => ['required', 'string', 'max:10'],
        ]);

        $usuario = SYSUsuario::where('numero_empleado', $data['numero_empleado'])->first();
        $payload = [
            'numero_empleado' => $data['numero_empleado'],
            'nombreEmpl'      => $usuario->nombre ?? '',
            'NoTelarId'       => $data['NoTelarId'],
            'Turno'           => (string)($usuario->turno ?? ''),
            'SalonTejidoId'   => $data['SalonTejidoId'],
        ];

        TelTelaresOperador::create($payload);

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
        $telares = ReqTelares::obtenerTodos();
        $usuarios = SYSUsuario::select('numero_empleado','nombre','turno')->orderBy('numero_empleado')->get();
        return view('tel-telares-operador.edit', [
            'item' => $telTelaresOperador,
            'telares' => $telares,
            'usuarios' => $usuarios,
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
            'NoTelarId'     => ['required', 'string', 'max:10'],
            'SalonTejidoId' => ['required', 'string', 'max:10'],
        ]);

        // Recalcular nombre y turno desde SYSUsuario
        $usuario = SYSUsuario::where('numero_empleado', $data['numero_empleado'])->first();
        $data['nombreEmpl'] = $usuario->nombre ?? $telTelaresOperador->nombreEmpl;
        $data['Turno'] = (string)($usuario->turno ?? $telTelaresOperador->Turno);

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
