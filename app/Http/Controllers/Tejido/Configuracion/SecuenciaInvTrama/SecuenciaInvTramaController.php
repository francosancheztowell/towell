<?php

namespace App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTrama;

use App\Http\Controllers\Controller;
use App\Models\Inventario\InvSecuenciaTrama;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SecuenciaInvTramaController extends Controller
{
    public function index()
    {
        try {
            $registros = InvSecuenciaTrama::orderBy('Secuencia', 'asc')
                ->get();

            return view('modulos.tejido.secuencia.inv-trama', compact('registros'));
        } catch (\Exception $e) {
            Log::error('Error al cargar Secuencia Inv Trama: ' . $e->getMessage());
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
            ]);

            $registro = InvSecuenciaTrama::create([
                'NoTelar' => $validated['NoTelar'],
                'TipoTelar' => $validated['TipoTelar'],
                'Secuencia' => $validated['Secuencia'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
                'data' => $registro
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear Secuencia Inv Trama: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $registro = InvSecuenciaTrama::findOrFail($id);

            $validated = $request->validate([
                'NoTelar' => 'required|integer',
                'TipoTelar' => 'required|string|max:50',
                'Secuencia' => 'required|integer',
            ]);

            $registro->update([
                'NoTelar' => $validated['NoTelar'],
                'TipoTelar' => $validated['TipoTelar'],
                'Secuencia' => $validated['Secuencia'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado exitosamente',
                'data' => $registro
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar Secuencia Inv Trama: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $registro = InvSecuenciaTrama::findOrFail($id);
            $registro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar Secuencia Inv Trama: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el registro: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar orden (Secuencia) después de drag & drop.
     * Body: { orden: [ { Id: 1, Secuencia: 1 }, ... ] }
     */
    public function updateOrden(Request $request)
    {
        try {
            $validated = $request->validate([
                'orden' => 'required|array',
                'orden.*.Id' => 'required|integer',
                'orden.*.Secuencia' => 'required|integer|min:1',
            ]);

            foreach ($validated['orden'] as $item) {
                InvSecuenciaTrama::where('Id', $item['Id'])->update(['Secuencia' => $item['Secuencia']]);
            }

            return response()->json(['success' => true, 'message' => 'Orden actualizado']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar orden Secuencia Inv Trama: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al actualizar el orden'], 500);
        }
    }
}
















