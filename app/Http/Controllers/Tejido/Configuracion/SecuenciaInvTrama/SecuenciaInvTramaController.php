<?php

namespace App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTrama;

use App\Http\Controllers\Controller;
use App\Models\Inventario\InvSecuenciaTrama;
use App\Services\Tejido\OrdenSecuencia;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SecuenciaInvTramaController extends Controller
{
    use HandlesApiErrors;

    public function index()
    {
        try {
            $registros = InvSecuenciaTrama::orderBy('Secuencia', 'asc')
                ->get();

            return view('modulos.tejido.secuencia.inv-trama', compact('registros'));
        } catch (\Exception $e) {
            Log::error('Error al cargar Secuencia Inv Trama: '.$e->getMessage());

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
                'data' => $registro,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al crear Secuencia Inv Trama', 'Error al crear el registro');
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
                'data' => $registro,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return $this->apiClientErrorResponse('Registro no encontrado', 404);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar Secuencia Inv Trama', 'Error al actualizar el registro');
        }
    }

    public function destroy($id)
    {
        try {
            $registro = InvSecuenciaTrama::findOrFail($id);
            $registro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente',
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->apiClientErrorResponse('Registro no encontrado', 404);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al eliminar Secuencia Inv Trama', 'Error al eliminar el registro');
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

            OrdenSecuencia::actualizar(InvSecuenciaTrama::class, 'Id', 'Secuencia', $validated['orden']);

            return response()->json(['success' => true, 'message' => 'Orden actualizado']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar orden Secuencia Inv Trama', 'Error al actualizar el orden');
        }
    }
}
