<?php

declare(strict_types=1);

namespace App\Actions\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use App\Services\Planeacion\ProgramaTejido\FormulasEficiencia;
use App\Services\Planeacion\ProgramaTejido\HorasProduccion;
use App\Services\Planeacion\ProgramaTejido\SecuenciaFechasTelar;
use App\Support\Planeacion\NumeroPrograma;
use Carbon\Carbon;

/**
 * Recalcula fechas y fórmulas de un registro por cambios en Produccion/SaldoPedido
 * (movido tal cual de BalancearTejido). EnProceso=1 usa now() como inicio y lo guarda
 * en FechaInicio. Para cuando un proceso externo actualiza Produccion/SaldoPedido por SQL
 * directo (comando recalcular-fechas-produccion). También lo usa el balanceo.
 */
final class RecalcularRegistroPorProduccion
{
    /** Guarda el registro, regenera líneas y marbetes y encadena los posteriores del telar. */
    public static function ejecutar(ReqProgramaTejido $registro): bool
    {
        if (empty($registro->FechaInicio)) {
            return false;
        }

        [$inicio, $fin, $horas] = self::calcularInicioFinExactos($registro);
        if (! $inicio || ! $fin) {
            return false;
        }

        $registro->FechaInicio = $inicio->format('Y-m-d H:i:s');
        $registro->FechaFinal = $fin->format('Y-m-d H:i:s');
        if ($horas > 0) {
            $registro->HorasProd = $horas;
        }

        $formulas = FormulasEficiencia::calcularFormulasEficienciaPorContexto(
            $registro,
            FormulasEficiencia::FORMULAS_CTX_BALANCEAR
        );
        foreach ($formulas as $k => $v) {
            $registro->{$k} = $v;
        }

        $registro->saveQuietly();

        // regenerarLineas() bypassa el guard shouldRegenerateLines() (ver docblock).
        // Pasamos $registro (no fresh()) porque la instancia en memoria ya tiene las fechas recalculadas.
        ReqProgramaTejido::regenerarLineas([$registro]);

        // El update SQL externo + saveQuietly() nunca dispararon el observer: recalcular
        // marbetes (TotalRollos/SaldoMarbete/NoMarbete) con la fila refetcheada (valores
        // finales en BD). recalcularFormulasProduccion trae try/catch y logging propios.
        $registroRefreshed = ReqProgramaTejido::find($registro->Id);
        if ($registroRefreshed) {
            (new ReqProgramaTejidoObserver)->recalcularFormulasProduccion($registroRefreshed);
        }

        // recalcularFormulasProduccion solo toca campos de marbete, no fechas:
        // la instancia refetcheada sigue siendo válida para la cascada.
        if (! $registro->esUltimo() && $registroRefreshed) {
            SecuenciaFechasTelar::cascade($registroRefreshed);
        }

        return true;
    }

    /**
     * Inicio/fin según el estado del registro: EnProceso arranca en now() sin snap;
     * saldo negativo termina en now() (EnProceso) o al final del día de FechaInicio.
     *
     * @return array{0: ?Carbon, 1: ?Carbon, 2: float}
     */
    public static function calcularInicioFinExactos(ReqProgramaTejido $r): array
    {
        if (empty($r->FechaInicio)) {
            return [null, null, 0.0];
        }

        $esEnProceso = (bool) $r->EnProceso;
        $inicio = $esEnProceso ? Carbon::now() : Carbon::parse($r->FechaInicio);

        // Saldo negativo: FechaFin = now() si EnProceso, si no el mismo día que FechaInicio
        $saldo = NumeroPrograma::sanitizeNumber($r->SaldoPedido ?? $r->Produccion ?? $r->TotalPedido ?? 0);
        if ($saldo < 0) {
            $fin = $esEnProceso
                ? Carbon::now()
                : Carbon::parse($r->FechaInicio)->copy()->endOfDay();

            return [$inicio, $fin, 0.0];
        }

        return self::resolverInicioFin($inicio, $r, ! $esEnProceso);
    }

    /**
     * Snap $inicio al calendario y calcula $fin según HorasProd del registro.
     * Lógica EnProceso/saldo-negativo queda en los callers; este método recibe
     * el inicio ya resuelto y solo aplica snap + cálculo de fin.
     *
     * @param  Carbon  $inicio  Inicio candidato (cursor, FechaInicio parseada, etc.)
     * @param  ReqProgramaTejido  $r  Registro con SaldoPedido, CalendarioId, etc.
     * @param  bool  $aplicarSnap  Si false (EnProceso), omite snap al calendario.
     * @return array{0:Carbon, 1:Carbon, 2:float} [$inicio, $fin, $horasNecesarias]
     */
    public static function resolverInicioFin(Carbon $inicio, ReqProgramaTejido $r, bool $aplicarSnap = true): array
    {
        if ($aplicarSnap && ! empty($r->CalendarioId)) {
            $snap = CalendarioProduccion::snapInicioAlCalendario(
                $r->CalendarioId,
                $inicio,
                CalendarioProduccion::lineas($r->CalendarioId)
            );
            if ($snap) {
                $inicio = $snap;
            }
        }

        $horasNecesarias = HorasProduccion::calcularHorasProd($r);

        if ($horasNecesarias <= 0) {
            $fin = CalendarioProduccion::esRepaso($r)
                ? $inicio->copy()->addHours(CalendarioProduccion::DEFAULT_DURACION_REPASO_HORAS)
                : $inicio->copy()->addDays(CalendarioProduccion::DEFAULT_DURACION_DIAS);

            return [$inicio, $fin, 0.0];
        }

        $fin = CalendarioProduccion::finDesdeHoras($inicio, $horasNecesarias, $r->CalendarioId);

        return [$inicio, $fin, $horasNecesarias];
    }
}
