<?php

declare(strict_types=1);

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion;

use App\Enums\Planeacion\VarianteEstandar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\Catalogos\EstandarRequest;
use App\Http\Requests\Planeacion\Catalogos\ExcelCatalogoRequest;
use App\Imports\Contracts\ImportConEstadisticas;
use App\Services\Planeacion\Catalogos\EstandarCatalogoService;
use App\Services\Planeacion\Catalogos\ImportarExcelCatalogo;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Eficiencia STD y Velocidad STD comparten todo (19-06b): vista catalagos/comun/estandar,
 * EstandarCatalogoService y este controller. Las subclases solo dicen su variante y su import.
 */
abstract class EstandarCatalogoController extends Controller
{
    use HandlesApiErrors;

    public function __construct(protected readonly EstandarCatalogoService $estandares) {}

    abstract protected function variante(): VarianteEstandar;

    abstract protected function import(): ImportConEstadisticas;

    public function index(): View
    {
        $v = $this->variante();
        $registros = $v->modelo()::orderBy('SalonTejidoId')->orderBy('NoTelarId')->orderBy('FibraId')->get();

        // Las vistas originales se conservan (hacen @include de comun/estandar con su variante).
        return view($v === VarianteEstandar::Eficiencia ? 'catalagos.catalagoEficiencia' : 'catalagos.catalagoVelocidad', [
            $v->nombre() => $registros,
            'noResults' => false,
        ]);
    }

    public function procesarExcel(ExcelCatalogoRequest $request, ImportarExcelCatalogo $importar): JsonResponse
    {
        try {
            $stats = $importar->importar($this->import(), $request->file('archivo_excel'));

            return response()->json(['success' => true, 'message' => 'Archivo procesado exitosamente', 'data' => $stats]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Excel '.$this->variante()->nombre(), 'Error al procesar el archivo Excel.');
        }
    }

    public function store(EstandarRequest $request): JsonResponse
    {
        try {
            $r = $this->estandares->crear($this->variante(), $request->validated());

            return response()->json($r->aJson(), $r->status);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Crear '.$this->variante()->nombre(), "Error al crear la {$this->variante()->nombre()}.");
        }
    }

    public function update(EstandarRequest $request, string $id): JsonResponse
    {
        $registro = $this->variante()->modelo()::findOrFail($id);
        try {
            $r = $this->estandares->actualizar($this->variante(), $registro, $request->validated());

            return response()->json($r->aJson(), $r->status);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Actualizar '.$this->variante()->nombre(), "Error al actualizar la {$this->variante()->nombre()}.");
        }
    }

    public function destroy(string $id): JsonResponse
    {
        $registro = $this->variante()->modelo()::findOrFail($id);
        try {
            $r = $this->estandares->eliminar($this->variante(), $registro);

            return response()->json($r->aJson(), $r->status);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Eliminar '.$this->variante()->nombre(), "Error al eliminar la {$this->variante()->nombre()}.");
        }
    }
}
