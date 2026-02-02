<?php

namespace App\Http\Controllers\Tejedores\Configuracion\TelaresOperador;

use App\Http\Controllers\Controller;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Planeacion\ReqTelares;
use App\Models\Sistema\SYSUsuario;
use Illuminate\Http\Request;
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
            ->orderByRaw("CASE WHEN ISNUMERIC(numero_empleado) = 1 THEN CAST(numero_empleado AS INT) ELSE 999999 END ASC")
            ->orderBy('NoTelarId')
            ->get();

        $telares = ReqTelares::obtenerTodos();
        $usuarios = SYSUsuario::select('numero_empleado','nombre','turno')
            ->orderByRaw("CASE WHEN ISNUMERIC(numero_empleado) = 1 THEN CAST(numero_empleado AS INT) ELSE 999999 END ASC")
            ->get();
        return view('modulos.tel-telares-operador.index', compact('items', 'q', 'telares','usuarios'));
    }

    /**
     * Form crear
     */
    public function create()
    {
        $telares = ReqTelares::obtenerTodos();
        $usuarios = SYSUsuario::select('numero_empleado','nombre','turno')
            ->orderByRaw("CASE WHEN ISNUMERIC(numero_empleado) = 1 THEN CAST(numero_empleado AS INT) ELSE 999999 END ASC")
            ->get();
        return view('modulos.tel-telares-operador.create', compact('telares','usuarios'));
    }

    /**
     * Guardar (usando Id como PK - IDENTITY)
     * Soporta múltiples telares: crear un registro por cada telar seleccionado
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'numero_empleado' => ['required', 'string', 'max:30', 'exists:SYSUsuario,numero_empleado'],
            'nombreEmpl'      => ['required', 'string', 'max:100'],
            'Turno'           => ['required', 'string', 'max:10'],
            'SalonTejidoId'   => ['required', 'string', 'max:10'],
            'telares'         => ['required', 'array', 'min:1'],
            'telares.*'       => ['required', 'string', 'max:10'],
            'Supervisor'      => ['nullable', 'boolean'],
        ]);

        $usuario = SYSUsuario::where('numero_empleado', $data['numero_empleado'])->first();
        $telares = $request->input('telares', []);
        $creados = 0;
        $duplicados = [];

        DB::beginTransaction();
        try {
            foreach ($telares as $telar) {
                // Validación: no permitir asignar el mismo telar al mismo usuario más de una vez
                $yaExiste = TelTelaresOperador::query()
                    ->where('numero_empleado', $data['numero_empleado'])
                    ->where('NoTelarId', $telar)
                    ->exists();
                
                if ($yaExiste) {
                    $duplicados[] = $telar;
                    continue;
                }

                $payload = [
                    'numero_empleado' => $data['numero_empleado'],
                    'nombreEmpl'      => $data['nombreEmpl'] ?? ($usuario->nombre ?? ''),
                    'NoTelarId'       => $telar,
                    'Turno'           => $data['Turno'] ?? (string)($usuario->turno ?? ''),
                    'SalonTejidoId'   => $data['SalonTejidoId'],
                    'Supervisor'      => $request->boolean('Supervisor', false),
                ];

                TelTelaresOperador::create($payload);
                $creados++;
            }

            DB::commit();

            $mensaje = "Se crearon {$creados} registro(s) correctamente.";
            if (count($duplicados) > 0) {
                $mensaje .= " Los telares " . implode(', ', $duplicados) . " ya estaban asignados.";
            }

            // Obtener los registros recién creados para devolverlos
            $registrosCreados = TelTelaresOperador::query()
                ->where('numero_empleado', $data['numero_empleado'])
                ->whereIn('NoTelarId', array_diff($telares, $duplicados))
                ->orderByDesc('Id')
                ->limit($creados)
                ->get();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'creados' => $creados,
                    'duplicados' => count($duplicados),
                    'data' => $registrosCreados->map(function($item) {
                        return [
                            'Id' => $item->Id,
                            'numero_empleado' => $item->numero_empleado,
                            'nombreEmpl' => $item->nombreEmpl,
                            'NoTelarId' => $item->NoTelarId,
                            'Turno' => $item->Turno,
                            'SalonTejidoId' => $item->SalonTejidoId,
                            'Supervisor' => (bool)($item->Supervisor ?? false),
                        ];
                    })
                ]);
            }

            return redirect()
                ->route('tel-telares-operador.index')
                ->with('success', $mensaje);
        } catch (\Throwable $e) {
            DB::rollBack();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear los registros: ' . $e->getMessage()
                ], 500);
            }

            return back()
                ->withErrors('Error al crear los registros: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Form editar
     * Route Model Binding por Id (PK)
     */
    public function edit(TelTelaresOperador $telTelaresOperador)
    {
        $telares = ReqTelares::obtenerTodos();
        $usuarios = SYSUsuario::select('numero_empleado','nombre','turno')
            ->orderByRaw("CASE WHEN ISNUMERIC(numero_empleado) = 1 THEN CAST(numero_empleado AS INT) ELSE 999999 END ASC")
            ->get();
        return view('modulos.tel-telares-operador.edit', [
            'item' => $telTelaresOperador,
            'telares' => $telares,
            'usuarios' => $usuarios,
        ]);
    }

    /**
     * Actualizar (Id no cambia, solo los demás campos)
     */
    public function update(Request $request, TelTelaresOperador $telTelaresOperador)
    {
        $data = $request->validate([
            'numero_empleado' => ['required', 'string', 'max:30'],
            'NoTelarId'       => ['required', 'string', 'max:10'],
            'SalonTejidoId'   => ['required', 'string', 'max:10'],
            'Supervisor'      => ['nullable', 'boolean'],
        ]);

        // Validación: no permitir duplicar (numero_empleado + NoTelarId) excepto el registro actual
        $yaExiste = TelTelaresOperador::query()
            ->where('numero_empleado', $data['numero_empleado'])
            ->where('NoTelarId', $data['NoTelarId'])
            ->where('Id', '!=', $telTelaresOperador->Id)
            ->exists();
        if ($yaExiste) {
            return back()->withErrors('Este operador ya tiene asignado el telar seleccionado.')->withInput();
        }

        // Recalcular nombre y turno desde SYSUsuario
        $usuario = SYSUsuario::where('numero_empleado', $data['numero_empleado'])->first();
        $data['nombreEmpl'] = $usuario->nombre ?? $telTelaresOperador->nombreEmpl;
        $data['Turno'] = (string)($usuario->turno ?? $telTelaresOperador->Turno);
        $data['Supervisor'] = $request->boolean('Supervisor', false);

        try {
            $telTelaresOperador->update($data);
            $telTelaresOperador->refresh();
        } catch (\Throwable $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()], 422);
            }
            return back()->withErrors('Error al actualizar: ' . $e->getMessage())->withInput();
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Operador actualizado correctamente.',
                'data' => [
                    'Id' => $telTelaresOperador->Id,
                    'numero_empleado' => $telTelaresOperador->numero_empleado,
                    'nombreEmpl' => $telTelaresOperador->nombreEmpl,
                    'NoTelarId' => $telTelaresOperador->NoTelarId,
                    'Turno' => $telTelaresOperador->Turno,
                    'SalonTejidoId' => $telTelaresOperador->SalonTejidoId,
                    'Supervisor' => (bool)($telTelaresOperador->Supervisor ?? false),
                ],
            ]);
        }

        return redirect()->route('tel-telares-operador.index')
            ->with('success', 'Operador actualizado correctamente.');
    }

    /**
     * Eliminar
     */
    public function destroy(Request $request, TelTelaresOperador $telTelaresOperador)
    {
        $id = $telTelaresOperador->Id;
        try {
            $telTelaresOperador->delete();
        } catch (\Throwable $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el operador: ' . $e->getMessage()
                ], 500);
            }
            return back()->withErrors('No se puede eliminar el operador: ' . $e->getMessage());
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Operador eliminado correctamente.',
                'id' => $id
            ]);
        }

        return redirect()->route('tel-telares-operador.index')
            ->with('success', 'Operador eliminado correctamente.');
    }
}
