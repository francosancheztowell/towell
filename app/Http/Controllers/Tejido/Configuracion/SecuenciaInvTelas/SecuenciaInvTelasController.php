<?php

namespace App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTelas;

use App\Http\Controllers\Controller;
use App\Models\Inventario\InvSecuenciaTelares;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecuenciaInvTelasController extends Controller
{
    public function index()
    {
        try {
            $registros = InvSecuenciaTelares::orderBy('Secuencia', 'asc')
                ->get();

            return view('modulos.tejido.secuencia.inv-telas', compact('registros'));
        } catch (\Exception $e) {
            Log::error('Error al cargar Secuencia Inv Telas: ' . $e->getMessage());
            return back()->with('error', 'Error al cargar los registros');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'NoTelar' => 'required|integer',
                'TipoTelar' => 'required|string|max:50',
                'Secuencia' => 'required|integer',
                'Observaciones' => 'nullable|string|max:500',
            ]);

            $registro = InvSecuenciaTelares::create([
                'NoTelar' => $validated['NoTelar'],
                'TipoTelar' => $validated['TipoTelar'],
                'Secuencia' => $validated['Secuencia'],
                'Observaciones' => $validated['Observaciones'] ?? null,
                'Created_At' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
                'data' => $registro
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear Secuencia Inv Telas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $registro = InvSecuenciaTelares::findOrFail($id);

            $validated = $request->validate([
                'NoTelar' => 'required|integer',
                'TipoTelar' => 'required|string|max:50',
                'Secuencia' => 'required|integer',
                'Observaciones' => 'nullable|string|max:500',
            ]);

            $registro->update([
                'NoTelar' => $validated['NoTelar'],
                'TipoTelar' => $validated['TipoTelar'],
                'Secuencia' => $validated['Secuencia'],
                'Observaciones' => $validated['Observaciones'] ?? null,
                'Updated_At' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado exitosamente',
                'data' => $registro
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar Secuencia Inv Telas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $registro = InvSecuenciaTelares::findOrFail($id);
            $registro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar Secuencia Inv Telas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el registro: ' . $e->getMessage()
            ], 500);
        }
    }
}

