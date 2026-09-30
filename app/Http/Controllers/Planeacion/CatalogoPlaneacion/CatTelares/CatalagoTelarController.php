<?php

namespace App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatTelares;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\Catalogos\ExcelCatalogoRequest;
use App\Http\Requests\Planeacion\Catalogos\TelarRequest;
use App\Imports\ReqTelaresImport;
use App\Models\Planeacion\ReqTelares;
use App\Services\Planeacion\Catalogos\ImportarExcelCatalogo;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Catálogo de Telares. Llave de ruta: "Salon_Telar". */
class CatalagoTelarController extends Controller
{
    use HandlesApiErrors;

    public function index(Request $request): View
    {
        try {
            $telares = ReqTelares::buscar($request->salon, $request->telar, $request->nombre, $request->grupo);

            return view('catalagos.catalagoTelares', ['telares' => $telares, 'noResults' => $telares->isEmpty()]);
        } catch (\Throwable $e) {
            report($e);

            return view('catalagos.catalagoTelares', ['telares' => collect(), 'noResults' => true])
                ->with('error', 'Error al cargar los telares');
        }
    }

    public function procesarExcel(ExcelCatalogoRequest $request, ImportarExcelCatalogo $importar): JsonResponse
    {
        try {
            $stats = $importar->importar(new ReqTelaresImport, $request->file('archivo_excel'));

            return response()->json([
                'success' => true,
                'message' => "Procesado: {$stats['registros_procesados']} filas (Creados {$stats['registros_creados']}, Actualizados {$stats['registros_actualizados']}, Saltadas {$stats['registros_omitidos']})",
                'data' => $stats,
            ]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Excel telares', 'Error al procesar el Excel de telares.');
        }
    }

    public function store(TelarRequest $request): JsonResponse
    {
        try {
            $datos = $request->validated();
            if (ReqTelares::existeTelar($datos['SalonTejidoId'], $datos['NoTelarId'])) {
                return response()->json(['success' => false, 'message' => 'Ya existe un telar con el mismo salón y número'], 422);
            }
            $nombre = ($datos['Nombre'] ?? null) ?: ReqTelares::nombreSugerido($datos['SalonTejidoId'], $datos['NoTelarId']);
            ReqTelares::create(['Nombre' => $nombre, 'Grupo' => $datos['Grupo'] ?? null] + $datos);

            return response()->json(['success' => true, 'message' => "Telar '{$nombre}' creado exitosamente"]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Crear telar', 'Error al crear el telar.');
        }
    }

    public function update(TelarRequest $request, string $uniqueId): JsonResponse
    {
        try {
            if (strrpos($uniqueId, '_') === false) {
                return response()->json(['success' => false, 'message' => 'ID de telar inválido'], 400);
            }
            $telar = ReqTelares::porLlave($uniqueId);
            if (! $telar) {
                return response()->json(['success' => false, 'message' => 'Telar no encontrado'], 404);
            }

            $datos = $request->validated();
            $cambiaLlave = $datos['SalonTejidoId'] !== $telar->SalonTejidoId || $datos['NoTelarId'] !== $telar->NoTelarId;
            if ($cambiaLlave && ReqTelares::existeTelar($datos['SalonTejidoId'], $datos['NoTelarId'])) {
                return response()->json(['success' => false, 'message' => 'Ya existe otro telar con ese Salón/Telar'], 422);
            }
            $nombre = ($datos['Nombre'] ?? null) ?: ReqTelares::nombreSugerido($datos['SalonTejidoId'], $datos['NoTelarId']);
            $telar->update(['Nombre' => $nombre, 'Grupo' => $datos['Grupo'] ?? null] + $datos);

            return response()->json(['success' => true, 'message' => "Telar '{$nombre}' actualizado exitosamente"]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Actualizar telar', 'Error al actualizar el telar.');
        }
    }

    public function destroy(string $uniqueId): JsonResponse
    {
        try {
            if (strrpos($uniqueId, '_') === false) {
                return response()->json(['success' => false, 'message' => 'ID de telar inválido'], 400);
            }
            $telar = ReqTelares::porLlave($uniqueId);
            if (! $telar) {
                return response()->json(['success' => false, 'message' => 'Telar no encontrado'], 404);
            }
            $nombre = $telar->Nombre;
            $telar->delete();

            return response()->json(['success' => true, 'message' => "Telar '{$nombre}' eliminado exitosamente"]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse($e, 'Eliminar telar', 'Error al eliminar el telar.');
        }
    }
}
