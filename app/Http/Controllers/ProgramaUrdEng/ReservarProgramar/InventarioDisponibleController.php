<?php

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Services\ProgramaUrdEng\InventarioReservasService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Throwable;
use Illuminate\Support\Facades\Log;

/**
 * Consultas de inventario disponible y reservas (solo GET).
 * Nombres claros: inventario disponible, reservas por telar, diagnóstico.
 */
class InventarioDisponibleController extends Controller
{
    public function __construct(
        private InventarioReservasService $reservasService
    ) {}

    /**
     * GET inventario disponible (TI-PRO) + marca si ya está reservado.
     * Filtros opcionales: [{ columna, valor }]. Soporta query o body.
     */
    public function disponible(Request $request): JsonResponse
    {
        try {
            $filtros = $this->reservasService->normalizeFilters(
                $request->input('filtros', $request->query('filtros', []))
            );

            if (!empty($filtros)) {
                $request->validate([
                    'filtros'            => ['array'],
                    'filtros.*.columna'  => ['required', 'string', Rule::in(InventarioReservasService::ALLOWED_FILTERS)],
                    'filtros.*.valor'    => ['required', 'string'],
                ]);
            }

            $result = $this->reservasService->getDisponibleData($filtros);

            return response()->json([
                'success' => true,
                'data'    => $result['data'],
                'total'   => $result['total'],
            ]);
        } catch (Throwable $e) {
            Log::error('InventarioDisponible.disponible error', ['msg' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inventario disponible',
            ], 500);
        }
    }

    /** GET reservas activas por número de telar. */
    public function porTelar(string $noTelar): JsonResponse
    {
        $rows = $this->reservasService->getReservasPorTelar($noTelar);
        return response()->json([
            'success' => true,
            'data'    => $rows,
            'total'   => $rows->count(),
        ]);
    }

    /** GET diagnóstico: reservas recientes y sus dimKeys. */
    public function diagnosticarReservas(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $noTelar = $request->query('noTelar');

            $reservas = $this->reservasService->getDiagnosticoReservas($noTelar, $limit);
            $diagnostico = $reservas->map(function ($r) {
                return [
                    'id' => $r->Id,
                    'noTelarId' => $r->NoTelarId,
                    'itemId' => $r->ItemId,
                    'configId' => $r->ConfigId,
                    'inventSizeId' => $r->InventSizeId,
                    'inventColorId' => $r->InventColorId,
                    'inventLocationId' => $r->InventLocationId,
                    'inventBatchId' => $r->InventBatchId,
                    'wmsLocationId' => $r->WMSLocationId,
                    'inventSerialId' => $r->InventSerialId,
                    'dimKey' => $this->reservasService->dimKey($r),
                    'status' => $r->Status,
                    'created_at' => $r->created_at?->toIso8601String(),
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $diagnostico,
                'total'   => $reservas->count(),
            ]);
        } catch (Throwable $e) {
            Log::error('InventarioDisponible.diagnosticarReservas error', ['msg' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al diagnosticar reservas: ' . $e->getMessage(),
            ], 500);
        }
    }
}
