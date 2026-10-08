<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fechas encadenadas de los registros de un telar con el calendario real: cada registro
 * arranca donde terminó el anterior (ajustado al calendario si cae en un hueco) y termina
 * al consumir sus horas de producción. Recalcula también CambioHilo, Ultimo y las fórmulas
 * de eficiencia que dependen de las fechas.
 *
 * Política sin horas calculables: saldo negativo => fin el mismo día; si no, conserva la
 * duración previa o, sin ella, repaso = 12 h y resto = 30 días.
 */
final class SecuenciaFechasTelar
{
    /**
     * Recalcula la secuencia completa (ya ordenada) a partir de $inicioOriginal.
     *
     * - Inicio = fin del anterior (snap al calendario si cae en gap); EnProceso arranca en now()
     * - EnProceso = 1 el primer registro; Ultimo = '1' el último; Posicion = i + 1
     *
     * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<string,mixed>>} [updates por Id, detalles]
     */
    public static function recalcular(Collection $registrosOrdenados, Carbon $inicioOriginal): array
    {
        $registros = $registrosOrdenados->values();
        $n = $registros->count();
        if ($n < 1) {
            return [[], []];
        }

        $updates = [];
        $detalles = [];
        $now = now();
        $cursor = $inicioOriginal->copy();
        $fibraPrev = '';

        foreach ($registros as $i => $r) {
            /** @var ReqProgramaTejido $r */
            $esEnProceso = ($r->EnProceso == 1 || $r->EnProceso === true);
            $nuevoInicio = self::inicioEnSecuencia($r, $cursor, $esEnProceso);
            $metricas = FormulasSecuenciaTelar::metricasBase($r);
            $nuevoFin = self::finEnSecuencia($r, $nuevoInicio, self::horasNecesarias($r, $metricas), $esEnProceso);

            $cambioHilo = ($i > 0 && self::fibra($r) !== $fibraPrev) ? '1' : '0';
            $fibraPrev = self::fibra($r);

            $tmp = self::conFechas($r, $nuevoInicio, $nuevoFin);
            $id = (int) $r->Id;
            $updates[$id] = array_merge([
                'FechaInicio' => $tmp->FechaInicio,
                'FechaFinal' => $tmp->FechaFinal,
                'EnProceso' => $i === 0 ? 1 : 0,
                'Ultimo' => $i === ($n - 1) ? '1' : '0',
                'CambioHilo' => $cambioHilo,
                'Posicion' => $i + 1,
                'UpdatedAt' => $now,
            ], FormulasSecuenciaTelar::formulas($tmp, $metricas));

            $detalles[] = self::detalleSecuencia($r, $updates[$id], $i + 1);
            $cursor = $nuevoFin->copy();
        }

        return [$updates, $detalles];
    }

    /**
     * Cascade: recalcula y guarda los registros posteriores al actualizado en su telar,
     * con la misma lógica de calendario real. No toca EnProceso ni Posicion.
     *
     * @return array<int,array<string,mixed>> detalles
     */
    public static function cascade(ReqProgramaTejido $registroActualizado): array
    {
        $dispatcher = null;

        DB::beginTransaction();
        try {
            $finActual = Carbon::parse($registroActualizado->FechaFinal);

            $todos = ReqProgramaTejido::query()
                ->salon($registroActualizado->SalonTejidoId)
                ->telar($registroActualizado->NoTelarId)
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get()
                ->values();

            $idx = $todos->search(fn ($r) => $r->Id === $registroActualizado->Id);
            if ($idx === false) {
                DB::commit();

                return [];
            }

            // deshabilitar eventos
            $dispatcher = ReqProgramaTejido::suppressObservers();

            [$detalles, $idsActualizados] = self::guardarPosteriores($todos, $idx, $registroActualizado, $finActual);

            DB::commit();

            // null si quien llama ya lo tenía apagado (p. ej. el comando): se queda apagado.
            ReqProgramaTejido::restoreObservers($dispatcher);

            // regenerar líneas en batch (evita N+1).
            // regenerarLineas() bypassa el guard shouldRegenerateLines() del observer,
            // porque los modelos refetcheados desde BD no tienen isDirty()/wasChanged().
            if (! empty($idsActualizados)) {
                ReqProgramaTejido::regenerarLineas(
                    ReqProgramaTejido::whereIn('Id', $idsActualizados)->get()
                );
            }

            return $detalles;

        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::restoreObservers($dispatcher);

            Log::error('cascadeFechas error', [
                'id' => $registroActualizado->Id ?? null,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Recalcula y guarda (sin eventos) los registros posteriores a $idx.
     *
     * @return array{0: array<int,array<string,mixed>>, 1: list<int>} [detalles, ids actualizados]
     */
    private static function guardarPosteriores(Collection $todos, int $idx, ReqProgramaTejido $registroActualizado, Carbon $finActual): array
    {
        $detalles = [];
        $idsActualizados = [];
        $cursor = $finActual->copy();

        // Fibra para CambioHilo: parte desde el registro actualizado
        $fibraPrev = self::fibra($registroActualizado);
        $total = $todos->count();

        for ($i = $idx + 1; $i < $total; $i++) {
            /** @var ReqProgramaTejido $row */
            $row = $todos[$i];

            $nuevoInicio = self::inicioEnSecuencia($row, $cursor, false);
            $metricas = FormulasSecuenciaTelar::metricasBase($row);
            $nuevoFin = self::finEnCascade($row, $nuevoInicio, self::horasNecesarias($row, $metricas));

            $cambioHilo = (self::fibra($row) !== $fibraPrev) ? '1' : '0';
            $fibraPrev = self::fibra($row);

            $tmp = self::conFechas($row, $nuevoInicio, $nuevoFin);
            $formulas = FormulasSecuenciaTelar::formulas($tmp, $metricas);
            $ultimo = ($i === ($total - 1)) ? '1' : '0';

            DB::table(ReqProgramaTejido::tableName())->where('Id', $row->Id)->update(array_merge([
                'FechaInicio' => $tmp->FechaInicio,
                'FechaFinal' => $tmp->FechaFinal,
                'Ultimo' => $ultimo,
                'CambioHilo' => $cambioHilo,
                'UpdatedAt' => now(),
            ], $formulas));

            $idsActualizados[] = (int) $row->Id;
            $detalles[] = [
                'Id' => (int) $row->Id,
                'NoTelar' => $row->NoTelarId,
                'FechaInicio_nueva' => $tmp->FechaInicio,
                'FechaFinal_nueva' => $tmp->FechaFinal,
                'CambioHilo_nuevo' => $cambioHilo,
                'Ultimo_nuevo' => $ultimo,
                'HorasProd_calc' => $formulas['HorasProd'] ?? null,
            ];

            $cursor = $nuevoFin->copy();
        }

        return [$detalles, $idsActualizados];
    }

    /* =========================================================
     *  INICIO / FIN
     * =======================================================*/

    /** Inicio = cursor (o now() para EnProceso), con snap al calendario si cae en gap (no a EnProceso). */
    private static function inicioEnSecuencia(ReqProgramaTejido $r, Carbon $cursor, bool $esEnProceso): Carbon
    {
        $nuevoInicio = $esEnProceso ? Carbon::now() : $cursor->copy();

        if (! $esEnProceso && ! empty($r->CalendarioId)) {
            $nuevoInicio = CalendarioProduccion::snapInicioAlCalendario($r->CalendarioId, $nuevoInicio) ?? $nuevoInicio;
        }

        return $nuevoInicio;
    }

    /** Horas calculadas (fuente de verdad); si no se pudieron calcular, las HorasProd guardadas. */
    private static function horasNecesarias(ReqProgramaTejido $r, array $metricas): float
    {
        $horas = (float) ($metricas['HorasProdRaw'] ?? 0);
        if ($horas <= 0 && ! empty($r->HorasProd)) {
            $horas = (float) $r->HorasProd;
        }

        return $horas;
    }

    /** Saldo negativo: now() si EnProceso, si no el fin del día de su FechaInicio previa. */
    private static function finEnSecuencia(ReqProgramaTejido $r, Carbon $nuevoInicio, float $horas, bool $esEnProceso): Carbon
    {
        if (FormulasSecuenciaTelar::saldo($r) < 0) {
            return $esEnProceso
                ? Carbon::now()
                : Carbon::parse($r->FechaInicio)->copy()->endOfDay();
        }
        if ($horas > 0) {
            return CalendarioProduccion::finDesdeHoras($nuevoInicio, $horas, $r->CalendarioId);
        }
        if (! $esEnProceso) {
            return self::finConDuracionPrevia($r, $nuevoInicio);
        }

        // EnProceso: HorasProd guardado como referencia de duración (no diff FechaInicio-FechaFinal).
        $horasGuardadas = (float) ($r->HorasProd ?? 0);

        return $horasGuardadas > 0
            ? CalendarioProduccion::finDesdeHoras($nuevoInicio, $horasGuardadas, $r->CalendarioId)
            : self::finPorDefecto($r, $nuevoInicio);
    }

    /** Cascade no toca EnProceso; saldo negativo => fin = mismo día que el nuevo inicio. */
    private static function finEnCascade(ReqProgramaTejido $row, Carbon $nuevoInicio, float $horas): Carbon
    {
        if (FormulasSecuenciaTelar::saldo($row) < 0) {
            return $nuevoInicio->copy()->endOfDay();
        }
        if ($horas > 0) {
            return CalendarioProduccion::finDesdeHoras($nuevoInicio, $horas, $row->CalendarioId);
        }

        return self::finConDuracionPrevia($row, $nuevoInicio);
    }

    /** Conserva la duración previa si existía; si no, repaso = 12 h / resto = 30 días. */
    private static function finConDuracionPrevia(ReqProgramaTejido $r, Carbon $nuevoInicio): Carbon
    {
        if (empty($r->FechaInicio) || empty($r->FechaFinal)) {
            return self::finPorDefecto($r, $nuevoInicio);
        }

        try {
            $dur = Carbon::parse($r->FechaInicio)->diff(Carbon::parse($r->FechaFinal));

            return (clone $nuevoInicio)->add($dur);
        } catch (\Throwable $e) {
            return self::finPorDefecto($r, $nuevoInicio);
        }
    }

    /** Repasos: medio día; resto: 30 días. */
    private static function finPorDefecto(ReqProgramaTejido $r, Carbon $nuevoInicio): Carbon
    {
        return CalendarioProduccion::esRepaso($r)
            ? $nuevoInicio->copy()->addHours(CalendarioProduccion::DEFAULT_DURACION_REPASO_HORAS)
            : $nuevoInicio->copy()->addDays(CalendarioProduccion::DEFAULT_DURACION_DIAS);
    }

    private static function conFechas(ReqProgramaTejido $r, Carbon $inicio, Carbon $fin): ReqProgramaTejido
    {
        $tmp = clone $r;
        $tmp->FechaInicio = $inicio->format('Y-m-d H:i:s');
        $tmp->FechaFinal = $fin->format('Y-m-d H:i:s');

        return $tmp;
    }

    private static function detalleSecuencia(ReqProgramaTejido $r, array $update, int $posicion): array
    {
        return [
            'Id' => (int) $r->Id,
            'NoTelar' => $r->NoTelarId,
            'Posicion' => $posicion,
            'FechaInicio_nueva' => $update['FechaInicio'] ?? $r->FechaInicio,
            'FechaFinal_nueva' => $update['FechaFinal'],
            'EnProceso_nuevo' => $update['EnProceso'],
            'Ultimo_nuevo' => $update['Ultimo'],
            'CambioHilo_nuevo' => $update['CambioHilo'],
            'CalendarioId' => $r->CalendarioId ?? null,
            'HorasProd_calc' => $update['HorasProd'] ?? null,
        ];
    }

    private static function fibra(ReqProgramaTejido $r): string
    {
        return trim((string) $r->getAttribute('FibraRizo'));
    }
}
