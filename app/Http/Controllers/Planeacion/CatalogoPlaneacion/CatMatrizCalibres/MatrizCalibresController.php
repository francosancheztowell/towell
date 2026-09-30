<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatMatrizCalibres;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\Catalogos\MatrizCalibreLookupRequest;
use App\Http\Requests\Planeacion\Catalogos\MatrizCalibreLoteRequest;
use App\Http\Requests\Planeacion\Catalogos\MatrizCalibreRequest;
use App\Models\Planeacion\Catalogos\CatMatrizCalibres;
use App\Services\Planeacion\MatrizCalibresService;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

final class MatrizCalibresController extends Controller
{
    use HandlesApiErrors;

    public function __construct(
        private readonly MatrizCalibresService $matrizCalibres,
    ) {}

    public function index(): View
    {
        $registros = CatMatrizCalibres::query()
            ->orderBy('Tipo')
            ->orderBy('Calibre')
            ->orderBy('Id')
            ->get();

        $tipos = $registros
            ->pluck('Tipo')
            ->filter(fn ($tipo) => filled($tipo))
            ->unique()
            ->sort()
            ->values();

        return view('catalagos.matriz-calibres', [
            'registros' => $registros,
            'tipos' => $tipos,
        ]);
    }

    public function store(MatrizCalibreRequest $request): JsonResponse
    {
        try {
            $registro = $this->matrizCalibres->guardarRegistroCompleto($request->validated());

            return response()->json(['success' => true, 'message' => 'Registro creado exitosamente', 'data' => $registro]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al crear CatMatrizCalibres', 'Error al crear el registro.');
        }
    }

    public function show(int $id): JsonResponse
    {
        $registro = CatMatrizCalibres::find($id);

        if (! $registro) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $registro,
        ]);
    }

    public function update(MatrizCalibreRequest $request, int $id): JsonResponse
    {
        $registro = CatMatrizCalibres::find($id);
        if (! $registro) {
            return response()->json(['success' => false, 'message' => 'Registro no encontrado'], 404);
        }

        try {
            $registro = $this->matrizCalibres->guardarRegistroCompleto($request->validated(), $registro);

            return response()->json(['success' => true, 'message' => 'Registro actualizado exitosamente', 'data' => $registro]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al actualizar CatMatrizCalibres', 'Error al actualizar el registro.', context: ['id' => $id]);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $registro = CatMatrizCalibres::find($id);

            if (! $registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado',
                ], 404);
            }

            $registro->delete();

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado exitosamente',
                'deleted_id' => $id,
            ]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Error al eliminar CatMatrizCalibres', 'Error al eliminar el registro.', context: ['id' => $id]);
        }
    }

    /** Una equivalencia (L.Mat, 19-06a). */
    public function lookup(MatrizCalibreLookupRequest $request): JsonResponse
    {
        $registro = $this->matrizCalibres->buscar($request->clave() ?? throw new \LogicException('Clave validada inválida'));

        return response()->json(['success' => true, 'found' => $registro !== null, 'data' => $registro]);
    }

    /** Hasta 10 equivalencias por llave (L.Mat, 19-06a). */
    public function lookupBatch(MatrizCalibreLoteRequest $request): JsonResponse
    {
        $registros = $this->matrizCalibres->buscarMultiples($request->claves());
        $data = [];
        foreach (array_values((array) $request->validated('claves')) as $index => $entrada) {
            $data[$entrada['key']] = $registros[$index] ?? null;
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
