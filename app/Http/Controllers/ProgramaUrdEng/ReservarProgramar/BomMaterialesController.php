<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProgramaUrdEng\ReservarProgramar\Concerns\RespuestasErrorUrdEng;
use App\Services\ProgramaUrdEng\BomMaterialesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BomMaterialesController extends Controller
{
    use RespuestasErrorUrdEng;

    public function __construct(
        private BomMaterialesService $service
    ) {}

    public function buscarBomUrdido(Request $request): JsonResponse
    {
        try {
            $query = trim((string) $request->query('q', ''));
            $results = $this->service->buscarBomUrdido($query);

            return response()->json($results);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.buscarBomUrdido', 'Error al buscar BOM');
        }
    }

    public function buscarBomEngomado(Request $request): JsonResponse
    {
        try {
            $query = trim((string) $request->query('q', ''));
            $results = $this->service->buscarBomEngomado($query);

            return response()->json($results);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.buscarBomEngomado', 'Error al buscar BOM de engomado');
        }
    }

    public function buscarBomFormula(Request $request): JsonResponse
    {
        try {
            $query = trim((string) $request->query('q', ''));
            $results = $this->service->buscarBomFormula($query);

            return response()->json($results);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.buscarBomFormula', 'Error al buscar formulas');
        }
    }

    public function buscarLoteProveedor(Request $request): JsonResponse
    {
        try {
            $query = trim((string) $request->query('q', ''));
            $results = $this->service->buscarLoteProveedor($query);

            return response()->json($results);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.buscarLoteProveedor', 'Error al buscar lotes de proveedor');
        }
    }

    public function getMaterialesUrdido(Request $request): JsonResponse
    {
        try {
            $bomId = trim((string) $request->query('bomId', ''));
            $results = $this->service->getMaterialesUrdido($bomId);

            return response()->json($results);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.getMaterialesUrdido', 'Error al obtener materiales');
        }
    }

    /**
     * API para Karl Mayer: resumen (Articulo, Config, Consumo, Kilos) + detalle inventario.
     */
    public function getMaterialesUrdidoCompleto(Request $request): JsonResponse
    {
        try {
            $bomId = trim((string) ($request->query('bomId') ?? $request->input('bomId', '')));
            $kilosTotal = $request->query('kilosTotal') ? (float) $request->query('kilosTotal') : null;
            $results = $this->service->getMaterialesUrdidoCompleto($bomId, $kilosTotal);

            return response()->json($results);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.getMaterialesUrdidoCompleto', 'Error al obtener los materiales de urdido', ['resumen' => [], 'detalle' => []]);
        }
    }

    public function getMaterialesEngomado(Request $request): JsonResponse
    {
        try {
            $itemIds = $request->input('itemIds', $request->query('itemIds', []));
            $configIds = $request->input('configIds', $request->query('configIds', []));
            if (is_string($itemIds)) {
                $itemIds = [$itemIds];
            }
            if (is_string($configIds)) {
                $configIds = [$configIds];
            }
            $itemIds = array_values((array) $itemIds);
            $configIds = array_values((array) $configIds);

            $results = $this->service->getMaterialesEngomado($itemIds, $configIds);

            return response()->json($results);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.getMaterialesEngomado', 'Error al obtener materiales de engomado');
        }
    }

    public function getAnchosBalona(Request $request): JsonResponse
    {
        try {
            $request->validate(['cuenta' => ['nullable', 'string', 'max:50'], 'tipo' => ['nullable', 'string', 'max:20']]);
            $cuenta = $request->input('cuenta');
            $tipo = $request->input('tipo');
            $data = $this->service->getAnchosBalona($cuenta, $tipo);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.getAnchosBalona', 'Error al obtener anchos de balona');
        }
    }

    public function getMaquinasEngomado(): JsonResponse
    {
        try {
            $data = $this->service->getMaquinasEngomado();

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.getMaquinasEngomado', 'Error al obtener máquinas de engomado');
        }
    }

    public function obtenerHilos(Request $request): JsonResponse
    {
        try {
            $data = $this->service->obtenerHilos((string) $request->query('tipo', ''));

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.obtenerHilos', 'Error al obtener los hilos');
        }
    }

    public function obtenerTamanos(Request $request): JsonResponse
    {
        try {
            $data = $this->service->obtenerTamanos((string) $request->query('tipo', ''));

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.obtenerTamanos', 'Error al obtener los tamaños');
        }
    }

    public function getBomFormula(Request $request): JsonResponse
    {
        try {
            $bomId = trim((string) ($request->query('bomId') ?? $request->input('bomId', '')));
            $formulas = $this->service->getBomFormulasAggregatedForEngProgram($bomId ?: null);

            return response()->json([
                'success' => true,
                'bomFormulas' => $formulas,
            ]);
        } catch (\Throwable $e) {
            return $this->errorServidor($e, 'BomMateriales.getBomFormula', 'Error al obtener BomFormula', ['bomFormulas' => []]);
        }
    }
}
