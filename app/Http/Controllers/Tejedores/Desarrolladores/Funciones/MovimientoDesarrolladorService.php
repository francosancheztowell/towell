<?php
namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\VincularTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Exception;

class MovimientoDesarrolladorService
{
    /**
     * Mueve un registro a estado EnProceso=1 y procesa los registros anteriores.
     */
    public function moverRegistroEnProceso(ReqProgramaTejido $registroActualizado, bool $throwOnError = false): void
    {
        $salonTejido = $registroActualizado->SalonTejidoId;
        $noTelarId = $registroActualizado->NoTelarId;

        if (!$salonTejido || !$noTelarId) return;

        $dispatcher = ReqProgramaTejido::getEventDispatcher();
        $idsAfectados = [];
        $fechaFinalizaAnterior = null; 

        try {
            DB::transaction(function () use ($registroActualizado, $salonTejido, $noTelarId, &$idsAfectados, &$fechaFinalizaAnterior) {
                $registrosEnProceso = ReqProgramaTejido::query()
                    ->where('SalonTejidoId', $salonTejido)
                    ->where('NoTelarId', $noTelarId)
                    ->where('EnProceso', 1)
                    ->where('Id', '!=', $registroActualizado->Id)
                    ->lockForUpdate()
                    ->get();

                /** @var ReqProgramaTejido $registroEnProceso */
                foreach ($registrosEnProceso as $registroEnProceso) {
                    $reprogramar = $registroEnProceso->Reprogramar;

                    if (!empty($reprogramar) && ($reprogramar == '1' || $reprogramar == '2')) {
                        $todosLosRegistros = ReqProgramaTejido::query()
                            ->where('SalonTejidoId', $salonTejido)
                            ->where('NoTelarId', $noTelarId)
                            ->orderBy('Posicion', 'asc')
                            ->orderBy('FechaInicio', 'asc')
                            ->lockForUpdate()
                            ->get();

                        $idsMovidos = $this->moverRegistroConReprogramar($registroEnProceso, $todosLosRegistros, $reprogramar);
                        $idsAfectados = array_merge($idsAfectados, $idsMovidos);
                    } else {
                        $ordCompartidaRaw = trim((string) ($registroEnProceso->OrdCompartida ?? ''));
                        $ordCompartida = $ordCompartidaRaw !== '' ? (int) $ordCompartidaRaw : null;

                        if ($ordCompartida && $ordCompartida > 0) {
                            $saldoTransferir = (float) ($registroEnProceso->SaldoPedido ?? 0);
                            if ($saldoTransferir !== 0.0) {
                                $lider = ReqProgramaTejido::query()
                                    ->where('OrdCompartida', $ordCompartida)
                                    ->where('OrdCompartidaLider', 1)
                                    ->lockForUpdate()
                                    ->first();

                                if (!$lider || $lider->Id === $registroEnProceso->Id) {
                                    $lider = ReqProgramaTejido::query()
                                        ->where('OrdCompartida', $ordCompartida)
                                        ->where('Id', '!=', $registroEnProceso->Id)
                                        ->orderBy('FechaInicio', 'asc')
                                        ->lockForUpdate()
                                        ->first();
                                    if ($lider) $lider->OrdCompartidaLider = 1;
                                }

                                if ($lider) {
                                    $saldoActual = (float) ($lider->SaldoPedido ?? 0);
                                    $lider->SaldoPedido = $saldoActual + $saldoTransferir;
                                    $lider->saveQuietly();
                                    $this->actualizarReqModelosDesdePrograma($lider);
                                }
                            }
                        }

                        $fechaFinalizaAnterior = now()->format('Y-m-d H:i:s');
                        $this->actualizarFechasArranqueFinaliza($registroEnProceso, null, 'now');
                        $this->actualizarReqModelosDesdePrograma($registroEnProceso);
                        $registroEnProceso->delete();
                    }
                }

                ReqProgramaTejido::query()
                    ->where('SalonTejidoId', $salonTejido)
                    ->where('NoTelarId', $noTelarId)
                    ->update(['EnProceso' => 0]);

                ReqProgramaTejido::query()
                    ->where('Id', $registroActualizado->Id)
                    ->update(['EnProceso' => 1]);

                $registros = ReqProgramaTejido::query()
                    ->where('SalonTejidoId', $salonTejido)
                    ->where('NoTelarId', $noTelarId)
                    ->orderBy('Posicion', 'asc')
                    ->orderBy('FechaInicio', 'asc')
                    ->lockForUpdate()
                    ->get();

                if ($registros->isEmpty()) return;

                $registroEnLista = $registros->firstWhere('Id', $registroActualizado->Id) ?: $registroActualizado;
                $ordenados = collect([$registroEnLista])
                    ->merge($registros->filter(function ($registro) use ($registroEnLista) {
                        return $registro->Id !== $registroEnLista->Id;
                    }))
                    ->values();

                $inicioOriginal = null;
                if (!empty($registroEnLista->FechaInicio)) {
                    $inicioOriginal = Carbon::parse($registroEnLista->FechaInicio);
                } else {
                    $primeroConFecha = $ordenados->first(function ($registro) { return !empty($registro->FechaInicio); });
                    if ($primeroConFecha) $inicioOriginal = Carbon::parse($primeroConFecha->FechaInicio);
                }

                if (!$inicioOriginal) return;

                ReqProgramaTejido::unsetEventDispatcher();

                [$updates] = DateHelpers::recalcularFechasSecuencia($ordenados, $inicioOriginal, true);

                if (!empty($updates)) {
                    $idsActualizar = array_keys($updates);
                    DB::table('ReqProgramaTejido')
                        ->whereIn('Id', $idsActualizar)
                        ->where('SalonTejidoId', $salonTejido)
                        ->where('NoTelarId', $noTelarId)
                        ->update(['Posicion' => DB::raw('Posicion + 10000')]);
                }

                foreach ($updates as $idU => $dataU) {
                    if (isset($dataU['Posicion'])) $dataU['Posicion'] = (int) $dataU['Posicion'];
                    DB::table('ReqProgramaTejido')
                        ->where('Id', $idU)
                        ->where('SalonTejidoId', $salonTejido)
                        ->where('NoTelarId', $noTelarId)
                        ->update($dataU);
                    $idsAfectados[] = (int) $idU;
                }

                $registroActualizadoRecargado = ReqProgramaTejido::query()->where('Id', $registroActualizado->Id)->lockForUpdate()->first();
                if ($registroActualizadoRecargado) {
                    $ordCompartidaRaw = trim((string) ($registroActualizadoRecargado->OrdCompartida ?? ''));
                    $ordCompartida = $ordCompartidaRaw !== '' ? (int) $ordCompartidaRaw : null;
                    if ($ordCompartida && $ordCompartida > 0) {
                        $registrosCompartidos = ReqProgramaTejido::query()
                            ->where('OrdCompartida', $ordCompartida)
                            ->whereNotNull('FechaInicio')
                            ->orderBy('FechaInicio', 'asc')
                            ->lockForUpdate()
                            ->get();

                        if ($registrosCompartidos->isNotEmpty()) {
                            $nuevoLider = $registrosCompartidos->first();
                            ReqProgramaTejido::query()->where('OrdCompartida', $ordCompartida)->update(['OrdCompartidaLider' => null]);
                            $nuevoLider->OrdCompartidaLider = 1;
                            $nuevoLider->saveQuietly();
                            VincularTejido::actualizarOrdPrincipalPorOrdCompartida($ordCompartida);
                        }
                    }
                }
            });
        } catch (Exception $e) {
            Log::error('moverRegistroEnProceso - Error en transacciÃ³n', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'registroId' => $registroActualizado->Id ?? null,
            ]);
            if ($throwOnError) {
                throw $e;
            }
        } finally {
            if ($dispatcher) ReqProgramaTejido::setEventDispatcher($dispatcher);
        }

        if (!empty($idsAfectados)) {
            $observer = new ReqProgramaTejidoObserver();
            $modelos = ReqProgramaTejido::query()->whereIn('Id', $idsAfectados)->get();
            /** @var ReqProgramaTejido $modelo */
            foreach ($modelos as $modelo) {
                $observer->saved($modelo);
            }
        }

        $registroParaModelo = ReqProgramaTejido::query()->where('Id', $registroActualizado->Id)->first();
        if ($registroParaModelo) {
            $this->actualizarReqModelosDesdePrograma($registroParaModelo);
            $this->actualizarFechasArranqueFinaliza($registroParaModelo, $fechaFinalizaAnterior, null);
        }
    }

    /**
     * Mueve físicamente el registro a otro salón/telar y lo deja como EnProceso en destino.
     * También reordena el telar origen para no dejar huecos tras la salida del registro.
     *
     * @throws Exception
     */
    public function moverRegistroConCambioTelarEnProceso(
        ReqProgramaTejido $registroActualizado,
        string $salonDestino,
        string $telarDestino
    ): ?ReqProgramaTejido {
        $salonOrigen = trim((string) ($registroActualizado->SalonTejidoId ?? ''));
        $telarOrigen = trim((string) ($registroActualizado->NoTelarId ?? ''));
        $salonDestino = trim($salonDestino);
        $telarDestino = trim($telarDestino);

        if ($salonOrigen === '' || $telarOrigen === '' || $salonDestino === '' || $telarDestino === '') {
            throw new Exception('No se pudo resolver el telar origen/destino para mover el registro.');
        }

        if ($salonOrigen === $salonDestino && $telarOrigen === $telarDestino) {
            $this->moverRegistroEnProceso($registroActualizado, true);
            return ReqProgramaTejido::query()->where('Id', $registroActualizado->Id)->first();
        }

        $idsOrigenAfectados = [];

        DB::transaction(function () use (
            $registroActualizado,
            $salonOrigen,
            $telarOrigen,
            $salonDestino,
            $telarDestino,
            &$idsOrigenAfectados
        ) {
            $registroBloqueado = ReqProgramaTejido::query()
                ->where('Id', $registroActualizado->Id)
                ->where('SalonTejidoId', $salonOrigen)
                ->where('NoTelarId', $telarOrigen)
                ->lockForUpdate()
                ->first();

            if (!$registroBloqueado) {
                throw new Exception('El registro a mover ya no está disponible en el telar origen.');
            }

            // Reserva una posición temporal alta para evitar colisiones al reordenar.
            DB::table('ReqProgramaTejido')
                ->where('Id', $registroBloqueado->Id)
                ->where('SalonTejidoId', $salonOrigen)
                ->where('NoTelarId', $telarOrigen)
                ->update([
                    'SalonTejidoId' => $salonDestino,
                    'NoTelarId' => $telarDestino,
                    'EnProceso' => 0,
                    'Posicion' => DB::raw('ISNULL(Posicion, 0) + 1000000'),
                ]);

            $this->recalcularSecuenciaTelar($salonOrigen, $telarOrigen, $idsOrigenAfectados);

            $registroMovido = ReqProgramaTejido::query()
                ->where('Id', $registroBloqueado->Id)
                ->where('SalonTejidoId', $salonDestino)
                ->where('NoTelarId', $telarDestino)
                ->lockForUpdate()
                ->first();

            if (!$registroMovido) {
                throw new Exception('No fue posible ubicar el registro en el telar destino.');
            }

            $this->moverRegistroEnProceso($registroMovido, true);
        });

        if (!empty($idsOrigenAfectados)) {
            $this->dispararObserverPorIds($idsOrigenAfectados);
        }

        return ReqProgramaTejido::query()->where('Id', $registroActualizado->Id)->first();
    }

    /**
     * Recalcula fechas y posición de la secuencia completa de un telar.
     */
    private function recalcularSecuenciaTelar(string $salonTejido, string $noTelarId, array &$idsAfectados = []): void
    {
        $registros = ReqProgramaTejido::query()
            ->where('SalonTejidoId', $salonTejido)
            ->where('NoTelarId', $noTelarId)
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->lockForUpdate()
            ->get();

        if ($registros->isEmpty()) {
            return;
        }

        $primeroConFecha = $registros->first(function ($registro) {
            return !empty($registro->FechaInicio);
        });
        $inicioOriginal = $primeroConFecha
            ? Carbon::parse($primeroConFecha->FechaInicio)
            : Carbon::now();

        [$updates] = DateHelpers::recalcularFechasSecuencia($registros->values(), $inicioOriginal, true);

        if (empty($updates)) {
            return;
        }

        $idsActualizar = array_keys($updates);
        DB::table('ReqProgramaTejido')
            ->whereIn('Id', $idsActualizar)
            ->where('SalonTejidoId', $salonTejido)
            ->where('NoTelarId', $noTelarId)
            ->update(['Posicion' => DB::raw('ISNULL(Posicion, 0) + 10000')]);

        foreach ($updates as $idU => $dataU) {
            if (isset($dataU['Posicion'])) {
                $dataU['Posicion'] = (int) $dataU['Posicion'];
            }
            DB::table('ReqProgramaTejido')
                ->where('Id', $idU)
                ->where('SalonTejidoId', $salonTejido)
                ->where('NoTelarId', $noTelarId)
                ->update($dataU);
            $idsAfectados[] = (int) $idU;
        }
    }

    private function dispararObserverPorIds(array $idsAfectados): void
    {
        $idsAfectados = array_values(array_unique(array_filter(array_map('intval', $idsAfectados))));
        if (empty($idsAfectados)) {
            return;
        }

        $observer = new ReqProgramaTejidoObserver();
        $modelos = ReqProgramaTejido::query()->whereIn('Id', $idsAfectados)->get();
        foreach ($modelos as $modelo) {
            $observer->saved($modelo);
        }
    }

    private function moverRegistroConReprogramar(ReqProgramaTejido $registro, $todosLosRegistros, string $reprogramar): array
    {
        $idsAfectados = [];
        try {
            $salonTejido = $registro->SalonTejidoId;
            $noTelarId = $registro->NoTelarId;

            if ($todosLosRegistros->count() < 2) return $idsAfectados;

            $primero = $todosLosRegistros->first();
            $inicioOriginal = $primero->FechaInicio ? Carbon::parse($primero->FechaInicio) : null;
            if (!$inicioOriginal) return $idsAfectados;

            $idx = $todosLosRegistros->search(function ($r) use ($registro) { return $r->Id === $registro->Id; });
            if ($idx === false) return $idsAfectados;

            $this->actualizarReqModelosDesdePrograma($registro);
            $registroMovido = $todosLosRegistros->splice($idx, 1)->first();

            if ($reprogramar == '1') {
                $posicionAjustada = $idx;
                if ($posicionAjustada > $todosLosRegistros->count()) $posicionAjustada = $todosLosRegistros->count();
            } else {
                $posicionAjustada = $todosLosRegistros->count();
            }

            $todosLosRegistros->splice($posicionAjustada, 0, [$registroMovido]);
            $registrosReordenados = $todosLosRegistros->values();

            [$updates] = DateHelpers::recalcularFechasSecuencia($registrosReordenados, $inicioOriginal, true);

            if (!empty($updates)) {
                $idsActualizar = array_keys($updates);
                DB::table('ReqProgramaTejido')
                    ->whereIn('Id', $idsActualizar)
                    ->where('SalonTejidoId', $salonTejido)
                    ->where('NoTelarId', $noTelarId)
                    ->update(['Posicion' => DB::raw('Posicion + 10000')]);
            }

            foreach ($updates as $idU => $data) {
                if (isset($data['Posicion'])) $data['Posicion'] = (int) $data['Posicion'];
                DB::table('ReqProgramaTejido')
                    ->where('Id', $idU)
                    ->where('SalonTejidoId', $salonTejido)
                    ->where('NoTelarId', $noTelarId)
                    ->update($data);
                $idsAfectados[] = (int) $idU;
            }

            $registro->EnProceso = 0;
            $registro->Reprogramar = null;
            $registro->save();

        } catch (Exception $e) {
            Log::error('moverRegistroConReprogramar - Error', ['message' => $e->getMessage()]);
        }
        return $idsAfectados;
    }

    public function actualizarFechasArranqueFinaliza(ReqProgramaTejido $programa, $fechaArranque = null, $fechaFinaliza = null): bool
    {
        $noProduccion = trim((string) ($programa->NoProduccion ?? ''));
        $noTelarId = trim((string) ($programa->NoTelarId ?? ''));

        if ($noProduccion === '' || $noTelarId === '') return false;

        if ($fechaArranque === null) {
            $fechaArranque = !empty($programa->FechaInicio) ? Carbon::parse($programa->FechaInicio)->format('Y-m-d H:i:s') : null;
        } elseif ($fechaArranque instanceof \DateTime || $fechaArranque instanceof Carbon) {
            $fechaArranque = $fechaArranque->format('Y-m-d H:i:s');
        } elseif (is_string($fechaArranque)) {
            try { Carbon::parse($fechaArranque); } catch (Exception $e) { $fechaArranque = null; }
        }

        if ($fechaFinaliza === null) {
            $fechaFinaliza = null;
        } elseif ($fechaFinaliza === 'now' || $fechaFinaliza === true) {
            $fechaFinaliza = now()->format('Y-m-d H:i:s');
        } elseif ($fechaFinaliza instanceof \DateTime || $fechaFinaliza instanceof Carbon) {
            $fechaFinaliza = $fechaFinaliza->format('Y-m-d H:i:s');
        } elseif (is_string($fechaFinaliza)) {
            try { Carbon::parse($fechaFinaliza); } catch (Exception $e) { $fechaFinaliza = null; }
        }

        $programaActualizado = false;
        if ($programa->exists) {
            $programa->FechaArranque = $fechaArranque;
            $programa->FechaFinaliza = $fechaFinaliza;
            if ($programa->isDirty(['FechaArranque', 'FechaFinaliza'])) {
                $programa->save();
                $programaActualizado = true;
            }
        }

        $modelo = new CatCodificados();
        $table = $modelo->getTable();
        $columns = Schema::getColumnListing($table);

        if (!in_array('FechaArranque', $columns, true) || !in_array('FechaFinaliza', $columns, true)) {
            return $programaActualizado;
        }

        $query = CatCodificados::query();
        $hasKeyFilter = false;

        if (in_array('OrdenTejido', $columns, true)) { $query->where('OrdenTejido', $noProduccion); $hasKeyFilter = true; } 
        elseif (in_array('NumOrden', $columns, true)) { $query->where('NumOrden', $noProduccion); $hasKeyFilter = true; }

        if (in_array('TelarId', $columns, true)) { $query->where('TelarId', $noTelarId); } 
        elseif (in_array('NoTelarId', $columns, true)) { $query->where('NoTelarId', $noTelarId); }

        if (!$hasKeyFilter) $query->where('NoProduccion', $noProduccion);

        $registroCodificado = $query->first();

        if (!$registroCodificado) return $programaActualizado;

        $registroCodificado->FechaArranque = $fechaArranque;
        $registroCodificado->FechaFinaliza = $fechaFinaliza;

        if ($registroCodificado->isDirty(['FechaArranque', 'FechaFinaliza'])) {
            $registroCodificado->save();
            return true;
        }

        return $programaActualizado;
    }

    public function actualizarReqModelosDesdePrograma(ReqProgramaTejido $programa): void
    {
        $noProduccion = trim((string) ($programa->NoProduccion ?? ''));
        $noTelarId = trim((string) ($programa->NoTelarId ?? ''));

        if ($noProduccion === '' || $noTelarId === '') return;

        $modelo = new CatCodificados();
        $table = $modelo->getTable();
        $columns = Schema::getColumnListing($table);

        $query = CatCodificados::query();
        $hasKeyFilter = false;

        if (in_array('OrdenTejido', $columns, true)) { $query->where('OrdenTejido', $noProduccion); $hasKeyFilter = true; } 
        elseif (in_array('NumOrden', $columns, true)) { $query->where('NumOrden', $noProduccion); $hasKeyFilter = true; }

        if (in_array('TelarId', $columns, true)) { $query->where('TelarId', $noTelarId); } 
        elseif (in_array('NoTelarId', $columns, true)) { $query->where('NoTelarId', $noTelarId); }

        if (!$hasKeyFilter) $query->where('NoProduccion', $noProduccion);

        $registroCodificado = $query->first();

        if (!$registroCodificado) return;

        $payload = [
            'Pedido' => $programa->TotalPedido,
            'Produccion' => $programa->Produccion,
            'Saldos' => $programa->SaldoPedido,
            'OrdCompartida' => $programa->OrdCompartida,
            'OrdCompartidaLider' => $programa->OrdCompartidaLider,
        ];

        foreach ($payload as $column => $value) {
            if (!in_array($column, $columns, true)) continue;
            $registroCodificado->setAttribute($column, $value);
        }

        if ($registroCodificado->isDirty()) $registroCodificado->save();
    }
}
