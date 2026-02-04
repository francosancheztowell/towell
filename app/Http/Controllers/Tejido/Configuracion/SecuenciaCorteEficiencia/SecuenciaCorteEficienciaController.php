<?php

namespace App\Http\Controllers\Tejido\Configuracion\SecuenciaCorteEficiencia;

use App\Http\Controllers\Controller;
use App\Models\Inventario\InvSecuenciaCorteEf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SecuenciaCorteEficienciaController extends Controller
{
    public function index()
    {
        try {
            $registros = InvSecuenciaCorteEf::orderBy('Orden', 'asc')->get();

            return view('modulos.tejido.secuencia.corte-eficiencia', compact('registros'));
        } catch (\Exception $e) {
            Log::error('Error al cargar Secuencia Corte Eficiencia: ' . $e->getMessage());
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
                $maxOrden = InvSecuenciaCorteEf::max('Orden') ?? 0;
                $orden = $maxOrden + 1;
            }

            if (InvSecuenciaCorteEf::where('NoTelarId', $validated['NoTelarId'])->exists()) {
                throw ValidationException::withMessages([
                    'NoTelarId' => ['Ya existe un registro con este número de telar.'],
                ]);
            }

            InvSecuenciaCorteEf::create([
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
            Log::error('Error al crear Secuencia Corte Eficiencia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $registro = InvSecuenciaCorteEf::findOrFail($id);

            $validated = $request->validate([
                'NoTelarId' => 'required|integer',
                'SalonTejidoId' => 'required|string|max:100',
                'Orden' => 'required|integer',
            ]);

            $otroConMismoTelar = InvSecuenciaCorteEf::where('NoTelarId', $validated['NoTelarId'])
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
            Log::error('Error al actualizar Secuencia Corte Eficiencia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $registro = InvSecuenciaCorteEf::findOrFail($id);
            $registro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar Secuencia Corte Eficiencia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar solo el orden (después de drag & drop).
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
                InvSecuenciaCorteEf::where('NoTelarId', $item['NoTelarId'])
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
            Log::error('Error al actualizar orden Secuencia Corte Eficiencia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el orden',
            ], 500);
        }
    }
}
