<?php

namespace App\Http\Controllers\ProgramaTejido\helper;

use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DateHelpers
{
    public static function setSafeDate(ReqProgramaTejido $r, string $attr, $value): void
    {
        try {
            $r->{$attr} = Carbon::parse($value);
        } catch (\Throwable $e) {
            // silencioso
        }
    }

    /**
     * Recalcular fechas de una secuencia de registros
     *
     * Este método recalcula las fechas de inicio y fin de una secuencia de registros
     * basándose en una fecha de inicio original. También calcula automáticamente:
     * - EnProceso: 1 para el primer registro, 0 para los demás
     * - Ultimo: 1 para el último registro, 0 para los demás
     * - CambioHilo: 1 si cambia FibraRizo respecto al registro anterior, 0 en caso contrario
     *
     * @param Collection $registrosOrdenados Colección de registros ordenados por fecha
     * @param Carbon $inicioOriginal Fecha de inicio original para el primer registro
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     *         Retorna un array con dos elementos:
     *         [0] => Array asociativo de updates por ID de registro
     *         [1] => Array de detalles con información de los cambios
     * @throws \RuntimeException Si algún registro no tiene FechaInicio y FechaFinal completas
     */
    public static function recalcularFechasSecuencia(Collection $registrosOrdenados, Carbon $inicioOriginal): array
    {
        $updates = [];
        $detalles = [];
        $lastFin = null;
        $now = now();
        $n = $registrosOrdenados->count();

        foreach ($registrosOrdenados as $i => $r) {
            $ini = $r->FechaInicio ? Carbon::parse($r->FechaInicio) : null;
            $fin = $r->FechaFinal ? Carbon::parse($r->FechaFinal) : null;

            if (!$ini || !$fin) {
                throw new \RuntimeException("El registro {$r->Id} debe tener FechaInicio y FechaFinal completas.");
            }

            $dur = $ini->diff($fin);

            if ($i === 0) {
                $nuevoInicio = $inicioOriginal->copy();
            } else {
                if ($lastFin === null) {
                    throw new \RuntimeException("Error: lastFin es null en iteración {$i}");
                }
                $nuevoInicio = $lastFin->copy();
            }

            $nuevoFin = (clone $nuevoInicio)->add($dur);

            // Calcular CambioHilo: comparar FibraRizo con el registro anterior
            $cambioHilo = '0';
            if ($i > 0) {
                $registroAnterior = $registrosOrdenados[$i - 1];
                $fibraRizoActual = trim((string) $r->FibraRizo);
                $fibraRizoAnterior = trim((string) $registroAnterior->FibraRizo);

                // Si cambia FibraRizo respecto al anterior → CambioHilo = 1
                $cambioHilo = ($fibraRizoActual !== $fibraRizoAnterior) ? '1' : '0';
            }

            $updates[$r->Id] = [
                'FechaInicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                'FechaFinal'  => $nuevoFin->format('Y-m-d H:i:s'),
                'EnProceso'   => $i === 0 ? 1 : 0,
                'Ultimo'      => $i === ($n - 1) ? '1' : '0',
                'CambioHilo'  => $cambioHilo,
                'UpdatedAt'   => $now,
            ];

            $detalles[] = [
                'Id' => $r->Id,
                'NoTelar' => $r->NoTelarId,
                'Posicion' => $i,
                'FechaInicio_nueva' => $updates[$r->Id]['FechaInicio'],
                'FechaFinal_nueva'  => $updates[$r->Id]['FechaFinal'],
                'EnProceso_nuevo'   => $updates[$r->Id]['EnProceso'],
                'Ultimo_nuevo'      => $updates[$r->Id]['Ultimo'],
                'CambioHilo_nuevo'  => $cambioHilo,
            ];

            $lastFin = $nuevoFin;
        }

        return [$updates, $detalles];
    }

    public static function cascadeFechas(ReqProgramaTejido $registroActualizado)
    {
        DB::beginTransaction();
        try {
            $salon = $registroActualizado->SalonTejidoId;
            $telar = $registroActualizado->NoTelarId;
            $fin   = Carbon::parse($registroActualizado->FechaFinal);

            $todos = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('FechaInicio','asc')
                ->get()
                ->values();

            $idx = $todos->search(fn($r) => $r->Id === $registroActualizado->Id);
            if ($idx === false) {
                DB::commit();
                return [];
            }

            $detalles = [];
            $finAnterior = $fin;
            $idsActualizados = [];

            ReqProgramaTejido::unsetEventDispatcher();

            for ($i = $idx + 1; $i < $todos->count(); $i++) {
                $row = $todos[$i];

                if (!$row->FechaInicio || !$row->FechaFinal) {
                    Log::warning('cascade skip (fechas nulas)', ['id'=>$row->Id]);
                    continue;
                }

                $dInicio = Carbon::parse($row->FechaInicio);
                $dFinal  = Carbon::parse($row->FechaFinal);
                $dur     = $dInicio->diff($dFinal);

                $nuevoInicio = clone $finAnterior;
                $nuevoFin    = (clone $nuevoInicio)->add($dur);

                DB::table('ReqProgramaTejido')->where('Id',$row->Id)->update([
                    'FechaInicio' => $nuevoInicio->format('Y-m-d H:i:s'),
                    'FechaFinal'  => $nuevoFin->format('Y-m-d H:i:s'),
                    'UpdatedAt'   => now(),
                ]);

                $idsActualizados[] = $row->Id;
                $finAnterior = $nuevoFin;

                $detalles[] = [
                    'Id' => $row->Id,
                    'NoTelar' => $row->NoTelarId,
                    'FechaInicio_anterior' => $dInicio->format('Y-m-d H:i:s'),
                    'FechaInicio_nueva'    => $nuevoInicio->format('Y-m-d H:i:s'),
                    'FechaFinal_anterior'  => $dFinal->format('Y-m-d H:i:s'),
                    'FechaFinal_nueva'     => $nuevoFin->format('Y-m-d H:i:s'),
                    'Duracion_dias' => $dur->days,
                    'Duracion_horas'=> $dur->h,
                    'Duracion_minutos'=>$dur->i,
                ];
            }

            DB::commit();

            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            if (!empty($idsActualizados)) {
                $observer = new ReqProgramaTejidoObserver();
                foreach ($idsActualizados as $idAct) {
                    if ($r = ReqProgramaTejido::find($idAct)) {
                        $observer->saved($r);
                    }
                }
                Log::info('cascadeFechas: Líneas regeneradas', ['ids_actualizados'=>count($idsActualizados)]);
            }

            return $detalles;

        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            Log::error('cascadeFechas error', [
                'id'=>$registroActualizado->Id ?? null,
                'msg'=>$e->getMessage(),
                'trace'=>$e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

