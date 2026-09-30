<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatAplicaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\Catalogos\AplicacionRequest;
use App\Http\Requests\Planeacion\Catalogos\ExcelCatalogoRequest;
use App\Imports\ReqAplicacionesImport;
use App\Models\Planeacion\ReqAplicaciones;
use App\Services\Planeacion\Catalogos\AplicacionesService;
use App\Services\Planeacion\Catalogos\ImportarExcelCatalogo;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/** Catálogo de Aplicaciones (AplicacionId, Nombre, Factor). La ruta acepta Id o AplicacionId. */
class AplicacionesController extends Controller
{
    use HandlesApiErrors;

    public function __construct(private readonly AplicacionesService $aplicaciones) {}

    public function index(): View
    {
        return view('catalagos.aplicaciones', [
            'aplicaciones' => ReqAplicaciones::obtenerTodas(),
            'noResults' => false,
        ]);
    }

    public function procesarExcel(ExcelCatalogoRequest $request, ImportarExcelCatalogo $importar): JsonResponse
    {
        try {
            $stats = $importar->importar(new ReqAplicacionesImport, $request->file('archivo_excel'));

            return response()->json(['success' => true, 'message' => 'Archivo procesado exitosamente', 'data' => $stats]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Excel aplicaciones', 'Error al procesar el Excel de aplicaciones.');
        }
    }

    public function store(AplicacionRequest $request): JsonResponse
    {
        try {
            $datos = $request->validated();
            $aplicacion = ReqAplicaciones::create([
                'AplicacionId' => $datos['AplicacionId'],
                'Nombre' => $datos['Nombre'],
                'Factor' => $datos['Factor'] ?? null,
            ]);

            return response()->json(['success' => true, 'message' => 'Aplicación creada exitosamente', 'data' => $aplicacion]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Crear aplicación', 'Error al crear la aplicación.');
        }
    }

    public function update(AplicacionRequest $request, string $aplicacion): JsonResponse
    {
        try {
            $registro = ReqAplicaciones::buscarPorIdOClave($aplicacion);
            if (! $registro) {
                return response()->json(['success' => false, 'message' => 'Aplicación no encontrada'], 404);
            }
            $this->aplicaciones->actualizar($registro, $request->validated());

            return response()->json(['success' => true, 'message' => 'Aplicación actualizada exitosamente', 'data' => $registro]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Actualizar aplicación', 'Error al actualizar la aplicación.');
        }
    }

    public function destroy(string $aplicacion): JsonResponse
    {
        try {
            $registro = ReqAplicaciones::buscarPorIdOClave($aplicacion);
            if (! $registro) {
                return response()->json(['success' => false, 'message' => 'Aplicación no encontrada'], 404);
            }
            if ($this->aplicaciones->enUso($registro->AplicacionId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la aplicación porque está siendo utilizada en programas de tejido.',
                ], 422);
            }
            $registro->delete();

            return response()->json(['success' => true, 'message' => 'Aplicación eliminada exitosamente']);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Eliminar aplicación', 'Error al eliminar la aplicación.');
        }
    }
}
