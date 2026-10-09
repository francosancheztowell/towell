<?php

namespace App\Http\Controllers\Tejido\Configuracion\SecuenciaInvTelas;

use App\Http\Controllers\Controller;
use App\Models\Urdido\URDCatalogoMaquina;
use App\Services\Tejido\OrdenSecuencia;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Secuencia del inventario de telas = URDCatalogoMaquinas.Secuencia de cada telar.
 * "Alta" pone la secuencia a un telar del catálogo y "eliminar" se la quita; el telar
 * (y su salón) se administra en el Catálogo de Máquinas.
 */
class SecuenciaInvTelasController extends Controller
{
    use HandlesApiErrors;

    public function index()
    {
        try {
            $registros = URDCatalogoMaquina::query()
                ->telares()
                ->whereNotNull('Secuencia')
                ->orderBy('Secuencia', 'asc')
                ->get(['Id', 'MaquinaId', 'Departamento', 'Secuencia'])
                ->map(fn (URDCatalogoMaquina $t) => $this->fila($t));

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
                'Secuencia' => 'nullable|integer',
            ]);

            $telar = $this->telar((string) $validated['NoTelar']);
            if (! $telar) {
                return $this->apiClientErrorResponse('El telar '.$validated['NoTelar'].' no existe en el Catálogo de Máquinas', 422);
            }

            // Siguiente secuencia si no se proporciona o es 0
            $secuencia = $validated['Secuencia'] ?? 0;
            if ($secuencia <= 0) {
                $secuencia = (URDCatalogoMaquina::max('Secuencia') ?? 0) + 1;
            }

            $telar->update(['Secuencia' => $secuencia]);

            return response()->json([
                'success' => true,
                'message' => 'Registro creado exitosamente',
                'data' => $this->fila($telar),
            ]);
        } catch (ValidationException $e) {
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
            $registro = URDCatalogoMaquina::where('Id', $id)->firstOrFail();

            $validated = $request->validate([
                'NoTelar' => 'required|integer',
                'Secuencia' => 'required|integer',
            ]);

            // Cambiar el número de telar pasa la secuencia a ese telar.
            $telar = $this->telar((string) $validated['NoTelar']);
            if (! $telar) {
                return $this->apiClientErrorResponse('El telar '.$validated['NoTelar'].' no existe en el Catálogo de Máquinas', 422);
            }
            if ($telar->Id !== $registro->Id) {
                $registro->update(['Secuencia' => null]);
            }
            $telar->update(['Secuencia' => $validated['Secuencia']]);

            return response()->json([
                'success' => true,
                'message' => 'Registro actualizado exitosamente',
                'data' => $this->fila($telar),
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
            return $this->apiErrorResponse($e, 'Error al actualizar Secuencia Inv Telas', 'Error al actualizar el registro');
        }
    }

    public function destroy($id)
    {
        try {
            URDCatalogoMaquina::where('Id', $id)->firstOrFail()->update(['Secuencia' => null]);

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

            OrdenSecuencia::actualizar(URDCatalogoMaquina::class, 'Id', 'Secuencia', $validated['orden']);

            return response()->json(['success' => true, 'message' => 'Orden actualizado']);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar orden Secuencia Inv Telas', 'Error al actualizar el orden');
        }
    }

    private function telar(string $noTelar): ?URDCatalogoMaquina
    {
        return URDCatalogoMaquina::query()->telares()->where('MaquinaId', $noTelar)->first();
    }

    /** Fila con las columnas que espera la vista (las de la antigua InvSecuenciaTelares). */
    private function fila(URDCatalogoMaquina $t): object
    {
        return (object) [
            'Id' => $t->Id,
            'NoTelar' => $t->MaquinaId,
            'TipoTelar' => $t->tipoTelar(),
            'Secuencia' => $t->Secuencia,
            'Observaciones' => null,
        ];
    }
}
