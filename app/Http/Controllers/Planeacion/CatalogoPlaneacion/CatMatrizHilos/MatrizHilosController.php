<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatMatrizHilos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\Catalogos\MatrizHilosRequest;
use App\Models\Planeacion\ReqMatrizHilos;
use App\Services\Planeacion\Catalogos\MatrizHilosService;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/** Matriz de Hilos. Un hilo usado como FibraRizo no se renombra ni se borra. */
class MatrizHilosController extends Controller
{
    use HandlesApiErrors;

    public function __construct(private readonly MatrizHilosService $hilos) {}

    public function index(): View
    {
        return view('catalagos.matriz-hilos', ['matrizHilos' => ReqMatrizHilos::orderBy('Hilo')->get()]);
    }

    /** Hilos únicos para selects (JSON). */
    public function list(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => ReqMatrizHilos::select('Hilo', 'Fibra')->distinct()->orderBy('Hilo')->get(),
            ]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Listar hilos', 'Error al obtener hilos.');
        }
    }

    public function store(MatrizHilosRequest $request): JsonResponse
    {
        try {
            $hilo = ReqMatrizHilos::create($request->validated());

            return response()->json(['success' => true, 'message' => 'Registro creado exitosamente', 'data' => $hilo]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Crear hilo', 'Error al crear el registro.');
        }
    }

    public function show(string $id): JsonResponse
    {
        $hilo = ReqMatrizHilos::find($id);

        return $hilo
            ? response()->json(['success' => true, 'data' => $hilo])
            : response()->json(['success' => false, 'message' => 'Registro no encontrado'], 404);
    }

    public function update(MatrizHilosRequest $request, string $id): JsonResponse
    {
        try {
            $hilo = ReqMatrizHilos::find($id);
            if (! $hilo) {
                return response()->json(['success' => false, 'message' => 'Registro no encontrado con ID: '.$id], 404);
            }
            $datos = $request->validated();
            $original = (string) $hilo->Hilo;
            $enUso = $this->hilos->enUso($original);
            if ($enUso && $original !== $datos['Hilo']) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cambiar el nombre del hilo "'.$original.'" porque está siendo utilizado en el programa de tejido (campo FibraRizo).',
                ], 422);
            }
            $recalcular = $enUso && $this->hilos->cambiaCalculo($hilo, $datos);
            $hilo->update($datos);
            if ($recalcular) {
                $this->hilos->recalcularMtsRizo($hilo);
            }

            return response()->json(['success' => true, 'message' => 'Registro actualizado exitosamente', 'data' => $hilo]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Actualizar hilo', 'Error al actualizar el registro.', context: ['id' => $id]);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $hilo = ReqMatrizHilos::find($id);
            if (! $hilo) {
                return response()->json(['success' => false, 'message' => 'Registro no encontrado con ID: '.$id], 404);
            }
            if ($hilo->Hilo && $this->hilos->enUso($hilo->Hilo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el hilo "'.$hilo->Hilo.'" porque está siendo utilizado en el programa de tejido (campo FibraRizo).',
                ], 422);
            }
            $hilo->delete();

            return response()->json(['success' => true, 'message' => 'Registro eliminado exitosamente', 'deleted_id' => $id]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Eliminar hilo', 'Error al eliminar el registro.', context: ['id' => $id]);
        }
    }
}
