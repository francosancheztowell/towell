<?php

/**
 * @file FinalizarOrdenesController.php
 * @description Controlador para finalizar órdenes de producción en telares.
 * @dependencies ReqProgramaTejido, CatCodificados, TejidoHelpers
 * @relatedFiles MoverOrdenesController.php, resources/views/planeacion/utileria/finalizar-ordenes.blade.php
 *
 * ! REPORTE DE FUNCIONALIDAD - Finalizar Órdenes
 * * -----------------------------------------------
 * * 1. Obtiene la lista de telares (NoTelarId) que tienen al menos una orden con EnProceso = 1
 * * 2. Al seleccionar un telar, carga las órdenes en proceso de ese telar con columnas:
 * *    - No. Orden (NoProduccion)
 * *    - Fecha Cambio (FechaTejido de CatCodificados)
 * *    - Tamaño Clave (TamanoClave)
 * *    - Modelo (NombreProducto)
 * * 3. El usuario selecciona las órdenes a finalizar mediante checkboxes
 * * 4. Al confirmar, se actualiza cada orden seleccionada:
 * *    - EnProceso = 0 (ya no está en proceso)
 * *    - FechaFinaliza = fecha/hora actual
 * * 5. Recalcula las posiciones del telar afectado para mantener consistencia
 * * -----------------------------------------------
 */

namespace App\Http\Controllers\Planeacion\Utilerias;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinalizarOrdenesController extends Controller
{
    /**
     * Obtiene telares que tienen al menos un registro con NoProduccion (no orden).
     * Agrupa por SalonTejidoId y NoTelarId para identificar cada telar único.
     */
    public function getTelares(): JsonResponse
    {
        try {
            $telares = ReqProgramaTejido::query()
                ->select('SalonTejidoId', 'NoTelarId')
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->whereNotNull('NoTelarId')
                ->where('NoTelarId', '!=', '')
                ->distinct()
                ->orderBy('SalonTejidoId')
                ->orderBy('NoTelarId')
                ->get()
                ->map(fn ($t) => [
                    'salon' => $t->SalonTejidoId,
                    'telar' => $t->NoTelarId,
                    'label' => $t->SalonTejidoId . ' - ' . $t->NoTelarId,
                ]);

            return response()->json(['success' => true, 'telares' => $telares]);
        } catch (\Throwable $e) {
            Log::error('Utilería/Finalizar - Error al obtener telares: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener telares'], 500);
        }
    }

    /**
     * Obtiene todos los registros con NoProduccion (no orden) de un telar específico.
     * Cruza con CatCodificados para obtener FechaTejido (fecha de cambio).
     * Incluye enProceso para marcar en la UI cuáles están en proceso.
     *
     * @param string $salonId Salón del telar
     * @param string $noTelarId Número de telar
     */
    public function getOrdenesByTelar(Request $request): JsonResponse
    {
        try {
            $salonId = $request->query('salon');
            $noTelarId = $request->query('telar');

            if (!$salonId || !$noTelarId) {
                return response()->json(['success' => false, 'message' => 'Salón y telar son requeridos'], 422);
            }

            $registros = ReqProgramaTejido::query()
                ->select('Id', 'NoProduccion', 'TamanoClave', 'NombreProducto', 'SalonTejidoId', 'NoTelarId', 'Posicion', 'EnProceso', 'SaldoPedido', 'Produccion', 'TotalPedido')
                ->salon($salonId)
                ->telar($noTelarId)
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->get();

            // ? Cruzar con CatCodificados para obtener FechaTejido por OrdenTejido
            $ordenes = $registros->pluck('NoProduccion')->filter()->unique()->values()->toArray();
            $catMap = [];

            if (!empty($ordenes)) {
                $placeholders = implode(',', array_fill(0, count($ordenes), '?'));
                $cats = CatCodificados::query()
                    ->select('OrdenTejido', 'FechaTejido')
                    ->whereRaw("CAST([OrdenTejido] AS NVARCHAR(100)) IN ({$placeholders})", $ordenes)
                    ->get();

                foreach ($cats as $cat) {
                    $key = trim((string) ($cat->OrdenTejido ?? ''));
                    if ($key !== '' && !isset($catMap[$key])) {
                        $catMap[$key] = $cat->FechaTejido
                            ? Carbon::parse($cat->FechaTejido)->format('d/m/Y')
                            : '';
                    }
                }
            }

            $items = $registros->map(function (ReqProgramaTejido $r) use ($catMap) {
                $noOrden = trim((string) ($r->NoProduccion ?? ''));
                return [
                    'id' => $r->Id,
                    'noOrden' => $noOrden,
                    'fechaCambio' => $catMap[$noOrden] ?? '',
                    'tamanoClave' => $r->TamanoClave ?? '',
                    'modelo' => $r->NombreProducto ?? '',
                    'enProceso' => (bool) $r->EnProceso,
                    'saldoPedido' => $r->SaldoPedido !== null ? (float) $r->SaldoPedido : null,
                    'produccion' => $r->Produccion !== null ? (float) $r->Produccion : null,
                    'totalPedido' => $r->TotalPedido !== null ? (float) $r->TotalPedido : null,
                ];
            });

            return response()->json(['success' => true, 'ordenes' => $items]);
        } catch (\Throwable $e) {
            Log::error('Utilería/Finalizar - Error al obtener órdenes: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener órdenes del telar'], 500);
        }
    }

    /**
     * Finaliza las órdenes seleccionadas: EnProceso = 0, FechaFinaliza = now().
     * Recalcula posiciones del telar para mantener consecutividad.
     */
    public function finalizarOrdenes(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
        ]);

        $ids = $request->input('ids');

        DB::beginTransaction();
        try {
            $registros = ReqProgramaTejido::whereIn('Id', $ids)
                ->where('EnProceso', 1)
                ->get();

            if ($registros->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron órdenes en proceso con los IDs proporcionados',
                ], 422);
            }

            $telaresAfectados = [];
            $ahora = Carbon::now();

            /** @var \App\Models\Planeacion\ReqProgramaTejido $registro */
            foreach ($registros as $registro) {
                $registro->EnProceso = false;
                $registro->FechaFinaliza = $ahora;
                $registro->UpdatedAt = $ahora;
                $registro->save();

                // * Registrar telar afectado para recalcular posiciones después
                $key = $registro->SalonTejidoId . '|' . $registro->NoTelarId;
                if (!isset($telaresAfectados[$key])) {
                    $telaresAfectados[$key] = [
                        'salon' => $registro->SalonTejidoId,
                        'telar' => $registro->NoTelarId,
                    ];
                }
            }

            // * Recalcular posiciones en cada telar afectado
            foreach ($telaresAfectados as $info) {
                TejidoHelpers::recalcularPosicionesPorTelar($info['salon'], $info['telar']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Se finalizaron ' . $registros->count() . ' orden(es) correctamente',
                'finalizadas' => $registros->count(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Utilería/Finalizar - Error al finalizar órdenes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar las órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }
}
