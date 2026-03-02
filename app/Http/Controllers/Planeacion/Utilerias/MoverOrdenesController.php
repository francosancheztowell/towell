<?php

/**
 * @file MoverOrdenesController.php
 * @description Controlador para mover órdenes de producción entre telares.
 * @dependencies ReqProgramaTejido, TejidoHelpers
 * @relatedFiles FinalizarOrdenesController.php, resources/views/planeacion/utileria/mover-ordenes.blade.php
 *
 * ! REPORTE DE FUNCIONALIDAD - Mover Órdenes
 * * -----------------------------------------------
 * * 1. Obtiene la lista de todos los telares (NoTelarId) que tienen registros en ReqProgramaTejido
 * * 2. Al seleccionar un telar ORIGEN, carga todos los registros de ese telar
 * * 3. Al seleccionar un telar DESTINO, carga todos los registros de ese telar
 * * 4. El usuario selecciona registros del telar origen y los "arrastra" al telar destino
 * *    mediante una interfaz de tipo drag-and-drop con botones de flecha
 * * 5. Al confirmar el movimiento:
 * *    - Se actualiza NoTelarId y SalonTejidoId de cada registro movido al telar destino
 * *    - Se asigna Posicion al final del telar destino
 * *    - Se recalculan las posiciones de ambos telares (origen y destino)
 * * 6. No se permite mover registros que estén EnProceso = 1
 * * -----------------------------------------------
 */

namespace App\Http\Controllers\Planeacion\Utilerias;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\QueryHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\MovimientoDesarrolladorService;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MoverOrdenesController extends Controller
{
    /**
     * Obtiene todos los telares que tienen al menos un registro con NoProduccion (no orden).
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
                    'label' => $t->NoTelarId,
                ]);

            return response()->json(['success' => true, 'telares' => $telares]);
        } catch (\Throwable $e) {
            Log::error('Mover - Error al obtener telares: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener telares'], 500);
        }
    }

    /**
     * Obtiene los registros con NoProduccion (no orden) de un telar específico.
     * Incluye enProceso para que la UI muestre cuáles están en proceso.
     */
    public function getRegistrosByTelar(Request $request): JsonResponse
    {
        try {
            $salonId = $request->query('salon');
            $noTelarId = $request->query('telar');

            if (!$salonId || !$noTelarId) {
                return response()->json(['success' => false, 'message' => 'Salón y telar son requeridos'], 422);
            }

            $registros = ReqProgramaTejido::query()
                ->select('Id', 'NoProduccion', 'TamanoClave', 'NombreProducto', 'SalonTejidoId', 'NoTelarId', 'Posicion', 'EnProceso', 'Produccion')
                ->salon($salonId)
                ->telar($noTelarId)
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->get()
                ->map(function (ReqProgramaTejido $r) {
                    $modelo = trim((string) ($r->NombreProducto ?? ''));
                    $esRepaso1 = $modelo !== '' && stripos($modelo, 'repaso1') !== false;
                    return [
                        'id' => $r->Id,
                        'noOrden' => $r->NoProduccion ?? '',
                        'tamanoClave' => $r->TamanoClave ?? '',
                        'modelo' => $modelo,
                        'posicion' => $r->Posicion ?? 0,
                        'enProceso' => (bool) $r->EnProceso,
                        'esRepaso1' => $esRepaso1,
                        'produccion' => $r->Produccion ?? 0,
                        'telar' => $r->NoTelarId ?? '',
                    ];
                });

            return response()->json(['success' => true, 'registros' => $registros]);
        } catch (\Throwable $e) {
            Log::error('Utilería/Mover - Error al obtener registros: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al obtener registros del telar'], 500);
        }
    }

    /**
     * Mueve registros y actualiza el orden en base a los arrays de IDs enviados.
     *
     * Por cada telar afectado:
     *  1. Evita colisiones en el índice único (Posicion + 10000 antes de reasignar).
     *  2. Guarda posición, telar y salón definitivos.
     *     Si el registro cambia de salón, actualiza también Maquina, EficienciaSTD,
     *     VelocidadSTD, CuentaRizo y CuentaPie según el salón destino.
     *  3. Recalcula la cadena de fechas (FechaInicio/FechaFinal) con DateHelpers.
     *  4. Dispara ReqProgramaTejidoObserver para regenerar líneas diarias y fórmulas.
     *  5. Sincroniza CatCodificados para los registros que cambiaron de salón.
     */
    public function moverOrdenes(Request $request): JsonResponse
    {
        $request->validate([
            'ordenes_origen'   => 'nullable|array',
            'ordenes_origen.*' => 'integer',
            'origen_salon'     => 'nullable|string',
            'origen_telar'     => 'nullable|string',
            'ordenes_destino'   => 'nullable|array',
            'ordenes_destino.*' => 'integer',
            'destino_salon'     => 'nullable|string',
            'destino_telar'     => 'nullable|string',
            'solo_reorden_origen' => 'nullable|boolean',
        ]);

        $soloReordenOrigen = $request->boolean('solo_reorden_origen', false);
        $telares = [
            ['salon' => $request->input('origen_salon'), 'telar' => $request->input('origen_telar'), 'ordenes' => $request->input('ordenes_origen', [])],
        ];
        if (!$soloReordenOrigen) {
            $telares[] = ['salon' => $request->input('destino_salon'), 'telar' => $request->input('destino_telar'), 'ordenes' => $request->input('ordenes_destino', [])];
        }

        $idsSolicitados = array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge(
                $request->input('ordenes_origen', []),
                $request->input('ordenes_destino', [])
            )
        ))));

        $dispatcher        = ReqProgramaTejido::getEventDispatcher();
        $idsAfectados      = [];   // [telarKey => [ids...]]
        $idsCambioSalon    = [];   // ids de registros que cambiaron de salón
        $tabla             = ReqProgramaTejido::tableName();
        $reglasEnProceso   = [];   // [telarKey => ['salon', 'telar', 'idObjetivo' => ?int]]
        $estadoOriginalPorId = []; // [id => ['salon', 'telar', 'enProceso']]

        DB::beginTransaction();
        try {
            $ahora = Carbon::now();

            // ─── PASO 1: Guardar posición/telar/salón con collision-avoidance ────────
            foreach ($telares as $cfg) {
                $salon   = $cfg['salon'];
                $telar   = $cfg['telar'];
                $ordenes = $cfg['ordenes'];

                if (empty($salon) || empty($telar) || empty($ordenes)) {
                    continue;
                }

                $telarKey = $salon . '|' . $telar;

                // Bump temporal para evitar violación del índice único (NoTelarId, Posicion).
                // Se hace sobre TODOS los registros del telar (no solo $ordenes) para que
                // los registros que no están en $ordenes tampoco colisionen al reasignar.
                DB::table($tabla)
                    ->where('SalonTejidoId', $salon)
                    ->where('NoTelarId', $telar)
                    ->update(['Posicion' => DB::raw('ISNULL(Posicion, 0) + 10000')]);

                foreach ($ordenes as $index => $id) {
                    $registro = ReqProgramaTejido::find($id);
                    if (!$registro) {
                        continue;
                    }

                    $cambiaSalon = ((string) $registro->SalonTejidoId !== (string) $salon);

                    $registro->SalonTejidoId = $salon;
                    $registro->NoTelarId     = $telar;
                    $registro->Posicion      = $index + 1;
                    $registro->UpdatedAt     = $ahora;

                    if ($cambiaSalon) {
                        // Actualizar campo Maquina con el nuevo salón/telar
                        $registro->Maquina = TejidoHelpers::construirMaquinaConSalon(
                            $registro->Maquina,
                            $salon,
                            $telar
                        );

                        // Obtener modelo del salón destino para std y cuentas
                        $tamanoClave  = trim((string) ($registro->TamanoClave ?? ''));
                        $modeloDestino = null;
                        if ($tamanoClave !== '') {
                            $modeloDestino = ReqModelosCodificados::query()
                                ->where('TamanoClave', $tamanoClave)
                                ->where('SalonTejidoId', $salon)
                                ->first();
                        }

                        // Actualizar EficienciaSTD y VelocidadSTD según telar/salón destino
                        [$nuevaEficiencia, $nuevaVelocidad] = QueryHelpers::resolverStdSegunTelar(
                            $registro, $modeloDestino, $telar, $salon
                        );
                        if (!is_null($nuevaEficiencia)) {
                            $registro->EficienciaSTD = round((float) $nuevaEficiencia, 2);
                        }
                        if (!is_null($nuevaVelocidad)) {
                            $registro->VelocidadSTD = (float) $nuevaVelocidad;
                        }

                        // Actualizar CuentaRizo y CuentaPie desde el modelo destino
                        if ($modeloDestino) {
                            if (!is_null($modeloDestino->CuentaRizo)) {
                                $registro->CuentaRizo = (string) $modeloDestino->CuentaRizo;
                            }
                            if (!is_null($modeloDestino->CuentaPie)) {
                                $registro->CuentaPie = (string) $modeloDestino->CuentaPie;
                            }
                        }

                        $idsCambioSalon[] = (int) $id;
                    }

                    $registro->saveQuietly();
                    $idsAfectados[$telarKey][] = (int) $id;
                }
            }

            // ─── PASO 2: Recalcular fechas encadenadas por telar ─────────────────────
            ReqProgramaTejido::unsetEventDispatcher();

            $telaresUnicos = [];
            foreach ($telares as $cfg) {
                if (!empty($cfg['salon']) && !empty($cfg['telar']) && !empty($cfg['ordenes'])) {
                    $telaresUnicos[$cfg['salon'] . '|' . $cfg['telar']] = [
                        'salon' => $cfg['salon'],
                        'telar' => $cfg['telar'],
                    ];
                }
            }

            foreach ($telaresUnicos as $telarKey => $cfg) {
                $salon = $cfg['salon'];
                $telar = $cfg['telar'];

                $registros = ReqProgramaTejido::query()
                    ->where('SalonTejidoId', $salon)
                    ->where('NoTelarId', $telar)
                    ->orderBy('Posicion', 'asc')
                    ->orderBy('FechaInicio', 'asc')
                    ->get();

                if ($registros->isEmpty()) {
                    continue;
                }

                $primeroConFecha = $registros->first(fn ($r) => !empty($r->FechaInicio));
                if (!$primeroConFecha) {
                    continue;
                }

                $inicioOriginal = Carbon::parse($primeroConFecha->FechaInicio);

                [$updates] = DateHelpers::recalcularFechasSecuencia(
                    $registros->values(),
                    $inicioOriginal,
                    true // respetar inicio del primer registro
                );

                if (empty($updates)) {
                    continue;
                }

                // No dejar que recalcularFechasSecuencia sobrescriba EnProceso:
                // esa función siempre pone el primer registro como EnProceso=1,
                // pero mover órdenes no debe alterar qué registro está en proceso.
                foreach ($updates as &$upd) {
                    unset($upd['EnProceso']);
                }
                unset($upd);

                // Bump temporal antes de actualizar fechas (evita colisiones de Posicion)
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

                    $idsAfectados[$telarKey][] = (int) $idU;
                }
            }

            // ─── PASO 3: Restaurar dispatcher y confirmar ────────────────────────────
            ReqProgramaTejido::setEventDispatcher($dispatcher);
            DB::commit();

        } catch (\Throwable $e) {
            ReqProgramaTejido::setEventDispatcher($dispatcher);
            DB::rollBack();
            Log::error('Mover - Error al mover órdenes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar los cambios: ' . $e->getMessage(),
            ], 500);
        }

        // ─── PASO 4: Disparar observer (fuera de la transacción) ─────────────────
        $todosIds = array_unique(array_merge(...array_values($idsAfectados ?: [[]])));
        if (!empty($todosIds)) {
            $observer = new ReqProgramaTejidoObserver();
            $modelos  = ReqProgramaTejido::query()->whereIn('Id', $todosIds)->get();
            /** @var ReqProgramaTejido $modelo */
            foreach ($modelos as $modelo) {
                $modelo->refresh();
                $observer->saved($modelo);
            }
        }

        // ─── PASO 5: Sincronizar CatCodificados (solo registros que cambiaron salón) ─
        if (!empty($idsCambioSalon)) {
            $movimientoService = new MovimientoDesarrolladorService();
            $registrosMovidos  = ReqProgramaTejido::query()
                ->whereIn('Id', array_unique($idsCambioSalon))
                ->get();
            /** @var ReqProgramaTejido $regMovido */
            foreach ($registrosMovidos as $regMovido) {
                $movimientoService->actualizarReqModelosDesdePrograma($regMovido);
                $movimientoService->actualizarFechasArranqueFinaliza($regMovido, null, null);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Se guardaron los cambios correctamente.',
        ]);
    }
}
