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
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\VincularTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\MovimientoDesarrolladorService;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
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
     * Elimina las órdenes seleccionadas (cualquier registro con NoProduccion, sin importar EnProceso).
     *
     * Por cada registro eliminado:
     *  1. Maneja OrdCompartida: transfiere saldo al líder si aplica.
     *  2. Sincroniza FechaFinaliza a CatCodificados.
     *  3. Sincroniza Pedido/Produccion/Saldos a CatCodificados.
     *  4. Elimina el registro físicamente.
     *
     * Después, por cada telar afectado:
     *  5. Si el registro eliminado tenía EnProceso=1, asigna EnProceso=1 al primer restante por Posicion.
     *  6. Recalcula la cadena de fechas (FechaInicio/FechaFinal) con DateHelpers.
     *  7. Dispara ReqProgramaTejidoObserver para regenerar líneas diarias y fórmulas.
     */
    public function finalizarOrdenes(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer',
        ]);

        $ids = $request->input('ids');

        $dispatcher     = ReqProgramaTejido::getEventDispatcher();
        $idsAfectados   = [];   // IDs de registros restantes en telares afectados
        $tabla          = ReqProgramaTejido::tableName();

        DB::beginTransaction();
        try {
            $registros = ReqProgramaTejido::whereIn('Id', $ids)
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->lockForUpdate()
                ->get();

            if ($registros->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron órdenes con NoOrden válido en los IDs proporcionados',
                ], 422);
            }

            $telaresAfectados        = [];
            $telaresNecesitanEnProceso = [];  // telares cuyo EnProceso=1 fue eliminado
            $ahora                   = Carbon::now();
            $movimientoService       = new MovimientoDesarrolladorService();
            $ordCompartidasVistas    = [];

            // ─── PASO 1: Finalizar cada registro y sincronizar CatCodificados ────────
            /** @var ReqProgramaTejido $registro */
            foreach ($registros as $registro) {
                $salonTejido = $registro->SalonTejidoId;
                $noTelarId   = $registro->NoTelarId;
                $key         = $salonTejido . '|' . $noTelarId;

                // 1a) Manejar OrdCompartida: transferir saldo al líder
                $ordCompartidaRaw = trim((string) ($registro->OrdCompartida ?? ''));
                $ordCompartida    = $ordCompartidaRaw !== '' ? (int) $ordCompartidaRaw : null;

                if ($ordCompartida && $ordCompartida > 0) {
                    $saldoTransferir = (float) ($registro->SaldoPedido ?? 0);
                    if ($saldoTransferir !== 0.0) {
                        $lider = ReqProgramaTejido::query()
                            ->where('OrdCompartida', $ordCompartida)
                            ->where('OrdCompartidaLider', 1)
                            ->where('Id', '!=', $registro->Id)
                            ->lockForUpdate()
                            ->first();

                        if (!$lider) {
                            $lider = ReqProgramaTejido::query()
                                ->where('OrdCompartida', $ordCompartida)
                                ->where('Id', '!=', $registro->Id)
                                ->orderBy('FechaInicio', 'asc')
                                ->lockForUpdate()
                                ->first();
                            if ($lider) {
                                $lider->OrdCompartidaLider = 1;
                            }
                        }

                        if ($lider) {
                            $saldoActual = (float) ($lider->SaldoPedido ?? 0);
                            $lider->SaldoPedido = $saldoActual + $saldoTransferir;
                            $lider->saveQuietly();
                            $movimientoService->actualizarReqModelosDesdePrograma($lider);
                        }
                    }

                    $ordCompartidasVistas[$ordCompartida] = true;
                }

                // 1b) Si tenía EnProceso=1, este telar necesitará reasignación
                if ($registro->EnProceso) {
                    $telaresNecesitanEnProceso[$key] = true;
                }

                // 1c) Sincronizar FechaFinaliza a CatCodificados antes de eliminar
                $registro->FechaFinaliza = $ahora;
                $movimientoService->actualizarFechasArranqueFinaliza($registro, null, 'now');

                // 1d) Sincronizar Pedido/Produccion/Saldos a CatCodificados
                $movimientoService->actualizarReqModelosDesdePrograma($registro);

                // 1e) Eliminar el registro físicamente
                $registro->delete();

                // Registrar telar afectado
                if (!isset($telaresAfectados[$key])) {
                    $telaresAfectados[$key] = [
                        'salon' => $salonTejido,
                        'telar' => $noTelarId,
                    ];
                }
            }

            // 1f) Actualizar OrdPrincipal para todas las OrdCompartida afectadas
            foreach (array_keys($ordCompartidasVistas) as $ordComp) {
                VincularTejido::actualizarOrdPrincipalPorOrdCompartida((int) $ordComp);
            }

            // 1g) Asignar EnProceso=1 al primer restante en telares que lo perdieron
            foreach ($telaresNecesitanEnProceso as $key => $_) {
                if (!isset($telaresAfectados[$key])) {
                    continue;
                }
                $info  = $telaresAfectados[$key];
                $salon = $info['salon'];
                $telar = $info['telar'];

                $primerRestante = ReqProgramaTejido::query()
                    ->where('SalonTejidoId', $salon)
                    ->where('NoTelarId', $telar)
                    ->orderBy('Posicion', 'asc')
                    ->orderBy('FechaInicio', 'asc')
                    ->lockForUpdate()
                    ->first();

                if ($primerRestante) {
                    DB::table($tabla)
                        ->where('SalonTejidoId', $salon)
                        ->where('NoTelarId', $telar)
                        ->update(['EnProceso' => 0]);

                    DB::table($tabla)
                        ->where('Id', $primerRestante->Id)
                        ->update(['EnProceso' => 1]);
                }
            }

            // ─── PASO 2: Recalcular fechas encadenadas por telar ─────────────────────
            ReqProgramaTejido::unsetEventDispatcher();

            foreach ($telaresAfectados as $info) {
                $salon = $info['salon'];
                $telar = $info['telar'];

                $registrosTelar = ReqProgramaTejido::query()
                    ->where('SalonTejidoId', $salon)
                    ->where('NoTelarId', $telar)
                    ->orderBy('Posicion', 'asc')
                    ->orderBy('FechaInicio', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($registrosTelar->isEmpty()) {
                    continue;
                }

                $primeroConFecha = $registrosTelar->first(fn ($r) => !empty($r->FechaInicio));
                if (!$primeroConFecha) {
                    continue;
                }

                $inicioOriginal = Carbon::parse($primeroConFecha->FechaInicio);

                [$updates] = DateHelpers::recalcularFechasSecuencia(
                    $registrosTelar->values(),
                    $inicioOriginal,
                    true
                );

                if (empty($updates)) {
                    continue;
                }

                // No dejar que recalcularFechasSecuencia sobrescriba EnProceso:
                // esa función siempre pone el primer registro como EnProceso=1,
                // pero aquí los estados fueron definidos en el Paso 1 y deben respetarse.
                foreach ($updates as &$upd) {
                    unset($upd['EnProceso']);
                }
                unset($upd);

                // Collision-avoidance para índice único en Posicion
                DB::table($tabla)
                    ->whereIn('Id', array_keys($updates))
                    ->where('SalonTejidoId', $salon)
                    ->where('NoTelarId', $telar)
                    ->update(['Posicion' => DB::raw('ISNULL(Posicion, 0) + 10000')]);

                foreach ($updates as $idU => $dataU) {
                    if (isset($dataU['Posicion'])) {
                        $dataU['Posicion'] = (int) $dataU['Posicion'];
                    }
                    DB::table($tabla)
                        ->where('Id', $idU)
                        ->where('SalonTejidoId', $salon)
                        ->where('NoTelarId', $telar)
                        ->update($dataU);

                    $idsAfectados[] = (int) $idU;
                }
            }

            // ─── PASO 3: Restaurar dispatcher y confirmar ────────────────────────────
            ReqProgramaTejido::setEventDispatcher($dispatcher);
            DB::commit();

        } catch (\Throwable $e) {
            ReqProgramaTejido::setEventDispatcher($dispatcher);
            DB::rollBack();
            Log::error('Utilería/Finalizar - Error al finalizar órdenes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar las órdenes: ' . $e->getMessage(),
            ], 500);
        }

        // ─── PASO 4: Disparar observer (fuera de transacción) ────────────────────
        $idsAfectados = array_values(array_unique(array_filter($idsAfectados)));
        if (!empty($idsAfectados)) {
            $observer = new ReqProgramaTejidoObserver();
            $modelos  = ReqProgramaTejido::query()->whereIn('Id', $idsAfectados)->get();
            /** @var ReqProgramaTejido $modelo */
            foreach ($modelos as $modelo) {
                $modelo->refresh();
                $observer->saved($modelo);
            }
        }

        return response()->json([
            'success'     => true,
            'message'     => 'Se finalizaron ' . $registros->count() . ' orden(es) correctamente',
            'finalizadas' => $registros->count(),
        ]);
    }
}
