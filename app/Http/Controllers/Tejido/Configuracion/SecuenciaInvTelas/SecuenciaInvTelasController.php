<?php

namespace App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTelas;

use App\Http\Controllers\Controller;
use App\Models\Inventario\InvSecuenciaTelares;
use App\Services\Tejido\OrdenSecuencia;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecuenciaInvTelasController extends Controller
{
    use HandlesApiErrors;

    public function index()
    {
        try {
            $registros = InvSecuenciaTelares::orderBy('Secuencia', 'asc')
                ->get();

            return view('modulos.tejido.secuencia.inv-telas', compact('registros'));
        } catch (\Exception $e) {
            Log::error('Error al cargar Secuencia Inv Telas: '.$e->getMessage());

            return back()->with('error', 'Error al cargar los registros');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'NoTelar' => 'required|integer',
                'TipoTelar' => 'required|string|max:50',
                'Secuencia' => 'nullable|integer',
                'Observaciones' => 'nullable|string|max:500',
            ]);

            // Calcular siguiente secuencia si no se proporciona o es 0
            $secuencia = $validated['Secuencia'] ?? 0;
            if ($secuencia <= 0) {
                $maxSecuencia = InvSecuenciaTelares::max('Secuencia') ?? 0;
                $secuencia = $maxSecuencia + 1;
            }

            $registro = InvSecuenciaTelares::create([
                'NoTelar' => $validated['NoTelar'],
                'TipoTelar' => $validated['TipoTelar'],
                'Secuencia' => $secuencia,
                'Observaciones' => $validated['Observaciones'] ?? null,
                'Created_At' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
                'data' => $registro,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al crear Secuencia Inv Telas', 'Error al crear el registro');
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
                'data' => $registro,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return $this->apiClientErrorResponse('Registro no encontrado', 404);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar Secuencia Inv Telas', 'Error al actualizar el registro');
        }
    }

    public function destroy($id)
    {
        try {
            $registro = InvSecuenciaTelares::findOrFail($id);
            $registro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente',
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->apiClientErrorResponse('Registro no encontrado', 404);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al eliminar Secuencia Inv Telas', 'Error al eliminar el registro');
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

            OrdenSecuencia::actualizar(InvSecuenciaTelares::class, 'Id', 'Secuencia', $validated['orden'], ['Updated_At' => now()]);

            return response()->json(['success' => true, 'message' => 'Orden actualizado']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar orden Secuencia Inv Telas', 'Error al actualizar el orden');
        }
    }
}
