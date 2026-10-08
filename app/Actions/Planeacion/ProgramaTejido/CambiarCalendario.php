<?php

declare(strict_types=1);

namespace App\Actions\Planeacion\ProgramaTejido;

use App\Data\Planeacion\ProgramaTejido\CambioCalendario;
use App\Helpers\AuditoriaHelper;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Services\Planeacion\Calendarios\FormulasCalendario;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cambio masivo de calendario (PT-05). Una sola implementación para las dos versiones:
 *
 * - legacy ($estricto = false): lo que hacía ProgramaTejidoCalendariosController. Una fila
 *   que truena se cuenta en 'errores', se sigue y se confirma el resto.
 * - v2 ($estricto = true): lock de las filas elegidas, líneas regeneradas dentro de la
 *   transacción y relanzando, y cualquier fila que truene revierte TODO (CR-02.3).
 *
 * Igual en las dos (divergencias documentadas en 05-SUMMARY.md, no se corrigen aquí): las
 * fórmulas salen de FormulasCalendario (12 días / 4 decimales, WR-10) y solo se encadenan
 * las filas elegidas de cada telar (CR-02.2). EnProceso conserva su FechaInicio.
 */
final class CambiarCalendario
{
    /**
     * @return array{actualizados: int, procesados: int, errores: int}
     *
     * @throws FalloEnRegistro (solo estricto) con el Id de la fila que falló; ya revertido
     *
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     * Loop movido tal cual de CalendarioController en PT 05: deuda previa al gate de PHPMD (fase 22); se parte en PT 05.1 con tests primero.
     */
    public function ejecutar(CambioCalendario $cambio, bool $estricto): array
    {
        AuditoriaHelper::contexto('CALENDARIOS');

        $calendarioId = $cambio->calendarioId;
        $procesados = 0;
        $actualizados = 0;
        $errores = 0;

        $dispatcher = ReqProgramaTejido::suppressObservers();
        DB::beginTransaction();

        try {
            if ($estricto) {
                // Serializa contra arrastrar/balancear sobre las mismas filas (WR-03).
                ReqProgramaTejido::query()->whereIn('Id', $cambio->ids)->lockForUpdate()->get(['Id']);
            }

            $actualizados = ReqProgramaTejido::whereIn('Id', $cambio->ids)
                ->update(['CalendarioId' => $calendarioId]);

            $registros = ReqProgramaTejido::whereIn('Id', $cambio->ids)
                ->whereNotNull('FechaInicio')
                ->orderBy('SalonTejidoId')
                ->orderBy('NoTelarId')
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->orderBy('Id', 'asc')
                ->get([
                    'Id',
                    'CalendarioId',
                    'SalonTejidoId',
                    'NoTelarId',
                    'FechaInicio',
                    'FechaFinal',
                    'HorasProd',
                    'SaldoPedido',
                    'Produccion',
                    'TotalPedido',
                    'PesoCrudo',
                    'DiasEficiencia',
                    'StdHrsEfect',
                    'ProdKgDia2',
                    'DiasJornada',
                    'Ultimo',
                    'EnProceso',
                ]);

            // Instancia por corrida: su caché de modelos no sobrevive entre cambios.
            $formulas = new FormulasCalendario;
            $prevFin = null;
            $prevTelar = null;

            foreach ($registros as $p) {
                try {
                    if (empty($p->FechaInicio)) {
                        $errores++;

                        continue;
                    }

                    $inicioOriginal = Carbon::parse($p->FechaInicio);
                    $inicio = $inicioOriginal->copy();

                    $esPrimerRegistroTelar = ($prevTelar === null ||
                        ($prevTelar->SalonTejidoId !== $p->SalonTejidoId ||
                         $prevTelar->NoTelarId !== $p->NoTelarId));

                    $esEnProceso = ($p->EnProceso == 1 || $p->EnProceso === true);

                    if ($esEnProceso) {
                        $inicio = Carbon::now();
                    } elseif ($esPrimerRegistroTelar) {
                        $inicio = $inicioOriginal->copy();
                    } else {
                        if ($prevFin) {
                            if (! $prevFin->equalTo($inicioOriginal)) {
                                $inicio = $prevFin->copy();
                            }
                        }
                        $snap = $formulas->snapInicio($calendarioId, $inicio);
                        if ($snap && ! $snap->equalTo($inicio)) {
                            $inicio = $snap;
                        }
                    }

                    $horas = (float) ($p->HorasProd ?? 0);
                    if ($horas <= 0) {
                        $horas = $formulas->horasProd($p);
                        if ($horas > 0) {
                            $p->HorasProd = $horas;
                        }
                    }
                    if ($horas <= 0) {
                        $errores++;

                        continue;
                    }

                    $fin = CalendarioProduccion::finDesdeHoras($inicio, $horas, $calendarioId);
                    if ($fin->lt($inicio)) {
                        $fin = $inicio->copy();
                    }

                    $inicioStr = $inicio->format('Y-m-d H:i:s');
                    $finStr = $fin->format('Y-m-d H:i:s');

                    $oldInicioStr = null;
                    try {
                        $oldInicioStr = Carbon::parse($p->FechaInicio)->format('Y-m-d H:i:s');
                    } catch (Throwable $e) {
                    }
                    $oldFinStr = null;
                    if (! empty($p->FechaFinal)) {
                        try {
                            $oldFinStr = Carbon::parse($p->FechaFinal)->format('Y-m-d H:i:s');
                        } catch (Throwable $e) {
                        }
                    }

                    $cambioFechas = (! $esEnProceso && $oldInicioStr !== $inicioStr) || ($oldFinStr !== $finStr);

                    if (! $esEnProceso) {
                        $p->FechaInicio = $inicioStr;
                    }
                    $p->FechaFinal = $finStr;

                    $deps = $formulas->dependientesDeFechas($p, $inicio, $fin, $horas);
                    foreach ($deps as $campo => $valor) {
                        $p->{$campo} = $valor;
                    }

                    $p->saveQuietly();
                    $procesados++;

                    if ($cambioFechas) {
                        $actualizados++;
                    }

                    if ($estricto) {
                        (new ReqProgramaTejidoObserver)->regenerateLinesFor($p, relanzar: true);
                    } else {
                        // regenerarLineas() bypassa el guard del observer (modelos refetcheados no tienen isDirty)
                        ReqProgramaTejido::regenerarLineas([$p]);
                    }

                    $prevFin = $fin->copy();
                    $prevTelar = $p;
                } catch (Throwable $e) {
                    if ($estricto) {
                        throw new FalloEnRegistro((int) $p->Id, $e);
                    }
                    $errores++;
                    Log::error('Error recalculando registro', [
                        'registro_id' => $p->Id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            ReqProgramaTejido::restoreObservers($dispatcher);
        }

        return ['actualizados' => $actualizados, 'procesados' => $procesados, 'errores' => $errores];
    }
}
