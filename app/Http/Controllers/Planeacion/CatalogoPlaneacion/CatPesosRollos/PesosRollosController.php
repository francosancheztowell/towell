<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatPesosRollos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\Catalogos\PesoRolloRequest;
use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use App\Support\Http\Concerns\HandlesApiErrors;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Pesos por Rollos: un registro por artículo + tamaño, con auditoría de quién crea/modifica. */
class PesosRollosController extends Controller
{
    use HandlesApiErrors;

    public function index(): View
    {
        return view('catalagos.pesos-rollos', [
            'pesosRollos' => ReqPesosRollosTejido::orderBy('ItemId')->orderBy('InventSizeId')->get(),
        ]);
    }

    public function store(PesoRolloRequest $request): JsonResponse
    {
        try {
            $datos = $request->validated();
            if ($this->duplicado($datos)) {
                return response()->json(['success' => false, 'message' => 'Ya existe un registro con el mismo ItemId e InventSizeId'], 422);
            }
            $ahora = Carbon::now();
            ReqPesosRollosTejido::create($datos + [
                'FechaCreacion' => $ahora->toDateString(),
                'HoraCreacion' => $ahora->toTimeString(),
                'UsuarioCrea' => $this->usuario(),
            ]);

            return response()->json(['success' => true, 'message' => 'Peso por rollo creado exitosamente']);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Crear peso por rollo', 'Error al crear el registro.');
        }
    }

    public function update(PesoRolloRequest $request, string $id): JsonResponse
    {
        $pesoRollo = ReqPesosRollosTejido::findOrFail($id);
        try {
            $datos = $request->validated();
            if ($this->duplicado($datos, $id)) {
                return response()->json(['success' => false, 'message' => 'Ya existe otro registro con el mismo ItemId e InventSizeId'], 422);
            }
            $ahora = Carbon::now();
            $pesoRollo->update($datos + [
                'FechaModificacion' => $ahora->toDateString(),
                'HoraModificacion' => $ahora->toTimeString(),
                'UsuarioModifica' => $this->usuario(),
            ]);

            return response()->json(['success' => true, 'message' => 'Peso por rollo actualizado exitosamente']);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Actualizar peso por rollo', 'Error al actualizar el registro.');
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $pesoRollo = ReqPesosRollosTejido::findOrFail($id);
        try {
            $pesoRollo->delete();

            return response()->json(['success' => true, 'message' => 'Peso por rollo eliminado exitosamente']);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Eliminar peso por rollo', 'Error al eliminar el registro.');
        }
    }

    /** @param  array<string, mixed>  $datos */
    private function duplicado(array $datos, ?string $excepto = null): bool
    {
        return ReqPesosRollosTejido::where('ItemId', $datos['ItemId'])
            ->where('InventSizeId', $datos['InventSizeId'])
            ->when($excepto !== null, fn ($q) => $q->where('Id', '!=', $excepto))
            ->exists();
    }

    private function usuario(): string
    {
        return Auth::check() ? (Auth::user()->nombre ?? 'Sistema') : 'Sistema';
    }
}
