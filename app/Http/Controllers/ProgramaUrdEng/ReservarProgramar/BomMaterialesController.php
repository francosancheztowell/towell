<?php

declare(strict_types=1);

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Services\ProgramaUrdEng\BomMaterialesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BomMaterialesController extends Controller
{
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
            Log::error('buscarBomUrdido', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al buscar BOM'], 500);
        }
    }

    public function buscarBomEngomado(Request $request): JsonResponse
    {
        try {
            $query = trim((string) $request->query('q', ''));
            $results = $this->service->buscarBomEngomado($query);
            return response()->json($results);
        } catch (\Throwable $e) {
            Log::error('buscarBomEngomado', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al buscar BOM de engomado'], 500);
        }
    }

    public function getMaterialesUrdido(Request $request): JsonResponse
    {
        try {
            $bomId = trim((string) $request->query('bomId', ''));
            $results = $this->service->getMaterialesUrdido($bomId);
            return response()->json($results);
        } catch (\Throwable $e) {
            Log::error('getMaterialesUrdido', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al obtener materiales'], 500);
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
            Log::error('getMaterialesUrdidoCompleto', ['msg' => $e->getMessage()]);
            return response()->json(['resumen' => [], 'detalle' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function getMaterialesEngomado(Request $request): JsonResponse
    {
        try {
            $itemIds = $request->input('itemIds', $request->query('itemIds', []));
            $configIds = $request->input('configIds', $request->query('configIds', []));
            if (is_string($itemIds)) $itemIds = [$itemIds];
            if (is_string($configIds)) $configIds = [$configIds];
            $itemIds = array_values((array) $itemIds);
            $configIds = array_values((array) $configIds);

            $results = $this->service->getMaterialesEngomado($itemIds, $configIds);
            return response()->json($results);
        } catch (\Throwable $e) {
            Log::error('getMaterialesEngomado', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Error al obtener materiales de engomado'], 500);
        }
    }

    public function getAnchosBalona(Request $request): JsonResponse
    {
        try {
            $request->validate(['cuenta' => ['nullable','string','max:50'], 'tipo' => ['nullable','string','max:20']]);
            $cuenta = $request->input('cuenta');
            $tipo = $request->input('tipo');
            $data = $this->service->getAnchosBalona($cuenta, $tipo);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('getAnchosBalona', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al obtener anchos de balona: ' . $e->getMessage()], 500);
        }
    }

    public function getMaquinasEngomado(): JsonResponse
    {
        try {
            $data = $this->service->getMaquinasEngomado();
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('getMaquinasEngomado', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Error al obtener máquinas de engomado: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerHilos(): JsonResponse
    {
        try {
            $data = $this->service->obtenerHilos();
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('obtenerHilos', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al obtener los hilos: ' . $e->getMessage()], 500);
        }
    }

    public function obtenerTamanos(): JsonResponse
    {
        try {
            $data = $this->service->obtenerTamanos();
            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('obtenerTamanos', ['msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error al obtener los tamaños: ' . $e->getMessage()], 500);
        }
    }

    public function getBomFormula(Request $request): JsonResponse
    {
        try {
            $bomId = trim((string) ($request->query('bomId') ?? $request->input('bomId', '')));
            $formula = $this->service->getBomFormula($bomId ?: null);

            return response()->json([
                'success' => true,
                'bomFormula' => $formula,
            ]);
        } catch (\Throwable $e) {
            Log::error('getBomFormula', ['msg' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'bomFormula' => null,
                'message' => 'Error al obtener BomFormula',
            ], 500);
        }
    }
}
