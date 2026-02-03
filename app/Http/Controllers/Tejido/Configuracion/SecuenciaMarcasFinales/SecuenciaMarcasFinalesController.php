<?php

namespace App\Http\Controllers\Tejido\Configuracion\SecuenciaMarcasFinales;

use App\Http\Controllers\Controller;
use App\Models\Inventario\InvSecuenciaMarcas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SecuenciaMarcasFinalesController extends Controller
{
    public function index()
    {
        try {
            $registros = InvSecuenciaMarcas::orderBy('Orden', 'asc')->get();

            return view('modulos.tejido.secuencia.marcas-finales', compact('registros'));
        } catch (\Exception $e) {
            Log::error('Error al cargar Secuencia Marcas Finales: ' . $e->getMessage());
            return back()->with('error', 'Error al cargar los registros');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'NoTelarId' => 'required|integer',
                'SalonTejidoId' => 'required|string|max:100',
                'Orden' => 'nullable|integer',
            ]);

            $orden = $validated['Orden'] ?? null;
            if ($orden === null || $orden <= 0) {
                $maxOrden = InvSecuenciaMarcas::max('Orden') ?? 0;
                $orden = $maxOrden + 1;
            }

            if (InvSecuenciaMarcas::where('NoTelarId', $validated['NoTelarId'])->exists()) {
                throw ValidationException::withMessages([
                    'NoTelarId' => ['Ya existe un registro con este número de telar.'],
                ]);
            }

            InvSecuenciaMarcas::create([
                'NoTelarId' => $validated['NoTelarId'],
                'SalonTejidoId' => $validated['SalonTejidoId'],
                'Orden' => $orden,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear Secuencia Marcas Finales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $registro = InvSecuenciaMarcas::findOrFail($id);

            $validated = $request->validate([
                'NoTelarId' => 'required|integer',
                'SalonTejidoId' => 'required|string|max:100',
                'Orden' => 'required|integer',
            ]);

            $otroConMismoTelar = InvSecuenciaMarcas::where('NoTelarId', $validated['NoTelarId'])
                ->where($registro->getKeyName(), '!=', $registro->getKey())
                ->exists();
            if ($otroConMismoTelar) {
                throw ValidationException::withMessages([
                    'NoTelarId' => ['Ya existe otro registro con este número de telar.'],
                ]);
            }

            $registro->update([
                'NoTelarId' => $validated['NoTelarId'],
                'SalonTejidoId' => $validated['SalonTejidoId'],
                'Orden' => $validated['Orden'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado exitosamente',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar Secuencia Marcas Finales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $registro = InvSecuenciaMarcas::findOrFail($id);
            $registro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar Secuencia Marcas Finales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar solo el orden de los registros (después de drag & drop).
     * Body: { orden: [ { NoTelarId: 201, Orden: 1 }, ... ] }
     */
    public function updateOrden(Request $request)
    {
        try {
            $validated = $request->validate([
                'orden' => 'required|array',
                'orden.*.NoTelarId' => 'required|integer',
                'orden.*.Orden' => 'required|integer|min:1',
            ]);

            foreach ($validated['orden'] as $item) {
                InvSecuenciaMarcas::where('NoTelarId', $item['NoTelarId'])
                    ->update(['Orden' => $item['Orden']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Orden actualizado',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar orden Secuencia Marcas Finales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el orden',
            ], 500);
        }
    }
}
