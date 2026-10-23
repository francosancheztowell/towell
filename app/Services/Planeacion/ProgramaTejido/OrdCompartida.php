<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Actions\Planeacion\ProgramaTejido\ActualizarOrdPrincipal;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * Operaciones con OrdCompartida (antes Controllers/.../helper/OrdCompartidaHelper). El valor
 * de OrdCompartida se deriva del NoProduccion del registro líder del grupo (no es un contador
 * sintético). Lo usan VincularTejido, DividirTejido y DuplicarTejido.
 *
 * Un grupo sin ningún NoProduccion (Vincular en el modal, Dividir sin liberar) lleva un número
 * temporal hasta que se libera su primera orden: entonces toma el NoProduccion de esa orden.
 */
final class OrdCompartida
{
    /**
     * Base del número temporal: 900000000 + Id de una fila nueva del grupo. El Id no se repite y
     * los folios de NoProduccion (37 mil en oct-2026) no llegan aquí, así que no choca con nada.
     */
    public const TEMPORAL = 900000000;

    public static function temporal(int $idFilaNueva): int
    {
        return self::TEMPORAL + $idFilaNueva;
    }

    public static function esTemporal(?int $ordCompartida): bool
    {
        return $ordCompartida !== null && $ordCompartida >= self::TEMPORAL;
    }

    /**
     * Al liberar la primera orden de un grupo con número temporal, el grupo toma su NoProduccion y
     * ella queda de líder. Llamar después de guardar el NoProduccion y antes de escribir
     * CatCodificados: recarga el registro para que lleve el número, el líder y el OrdPrincipal nuevos.
     */
    public static function fijarAlLiberar(ReqProgramaTejido $registro): void
    {
        $temporal = (int) $registro->getAttribute('OrdCompartida');
        $definitivo = self::obtenerOrdCompartidaDesdeRegistro($registro);
        // Un NoProduccion manual no numérico no puede ser número de grupo: espera a la siguiente.
        if (! self::esTemporal($temporal) || $definitivo === null) {
            return;
        }

        ReqProgramaTejido::where('OrdCompartida', $temporal)->update(['OrdCompartida' => $definitivo, 'UpdatedAt' => now()]);
        self::recalcularLiderYOrdPrincipalPorOrdCompartida($definitivo);
        $registro->refresh();
    }

    /**
     * Deriva el OrdCompartida desde el NoProduccion del registro líder propuesto.
     * Retorna null si el registro no tiene NoProduccion válido (no puede ser líder).
     *
     * @param  ReqProgramaTejido|array|null  $lider
     */
    public static function obtenerOrdCompartidaDesdeRegistro($lider): ?int
    {
        if ($lider === null) {
            return null;
        }

        $noProduccion = is_array($lider)
            ? ($lider['NoProduccion'] ?? null)
            : ($lider->NoProduccion ?? null);

        if ($noProduccion === null) {
            return null;
        }

        $limpio = trim((string) $noProduccion);
        if ($limpio === '' || ! is_numeric($limpio)) {
            return null;
        }

        return (int) $limpio;
    }

    public static function seleccionarLider(Collection $registros): ?ReqProgramaTejido
    {
        $elegibles = $registros->filter(function ($registro) {
            $np = $registro->NoProduccion ?? null;
            if ($np === null) {
                return false;
            }
            $limpio = trim((string) $np);

            return $limpio !== '' && is_numeric($limpio);
        });

        if ($elegibles->isEmpty()) {
            return null;
        }

        $todosMismaFechaInicio = $elegibles
            ->map(fn ($registro) => self::normalizarFechaInicioDia($registro))
            ->uniqueStrict()
            ->count() <= 1;

        return $elegibles->sort(function ($a, $b) use ($todosMismaFechaInicio) {
            if (! $todosMismaFechaInicio) {
                return self::compararPorFechaInicio($a, $b);
            }

            return self::compararFechas(self::combinarFechaCreacion($a), self::combinarFechaCreacion($b), $a, $b);
        })->first();
    }

    public static function recalcularLiderYOrdPrincipalPorOrdCompartida(int $ordCompartida): ?int
    {
        // Temporal = nadie tiene NoProduccion todavía: no hay líder posible hasta liberar.
        if ($ordCompartida <= 0 || self::esTemporal($ordCompartida)) {
            return null;
        }

        $registros = ReqProgramaTejido::query()
            ->where('OrdCompartida', $ordCompartida)
            ->get([
                'Id',
                'OrdCompartida',
                'OrdCompartidaLider',
                'ItemId',
                'NoProduccion',
                'TotalPedido',
                'FechaInicio',
                'FechaCreacion',
                'HoraCreacion',
                'UpdatedAt',
            ]);

        if ($registros->isEmpty()) {
            return null;
        }

        $lider = self::seleccionarLider($registros);
        if (! $lider) {
            LogFacade::warning('recalcularLiderYOrdPrincipalPorOrdCompartida: ningún registro del grupo tiene NoProduccion válido para ser líder', [
                'OrdCompartida' => $ordCompartida,
                'ids' => $registros->pluck('Id')->all(),
            ]);

            return null;
        }

        ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
            ->update([
                'OrdCompartidaLider' => null,
                'UpdatedAt' => now(),
            ]);

        ReqProgramaTejido::where('Id', $lider->Id)
            ->update([
                'OrdCompartidaLider' => 1,
                'UpdatedAt' => now(),
            ]);

        ActualizarOrdPrincipal::ejecutar($ordCompartida);

        return (int) $lider->Id;
    }

    private static function normalizarFechaInicioDia(ReqProgramaTejido $registro): ?string
    {
        if (empty($registro->FechaInicio)) {
            return null;
        }

        try {
            return Carbon::parse($registro->FechaInicio)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function combinarFechaCreacion(ReqProgramaTejido $registro): ?Carbon
    {
        if (empty($registro->FechaCreacion)) {
            return null;
        }

        try {
            $fecha = Carbon::parse($registro->FechaCreacion)->format('Y-m-d');
            $hora = trim((string) ($registro->HoraCreacion ?? '00:00:00'));
            if ($hora === '') {
                $hora = '00:00:00';
            }

            return Carbon::parse($fecha.' '.$hora);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function compararPorFechaInicio(ReqProgramaTejido $a, ReqProgramaTejido $b): int
    {
        $inicioA = ! empty($a->FechaInicio) ? Carbon::parse($a->FechaInicio) : null;
        $inicioB = ! empty($b->FechaInicio) ? Carbon::parse($b->FechaInicio) : null;

        return self::compararFechas($inicioA, $inicioB, $a, $b);
    }

    /** Fecha más temprana primero; sin fecha al final; empate (o ambas sin fecha) por pedido desc. */
    private static function compararFechas(?Carbon $fechaA, ?Carbon $fechaB, ReqProgramaTejido $a, ReqProgramaTejido $b): int
    {
        if ($fechaA && $fechaB && ! $fechaA->equalTo($fechaB)) {
            return $fechaA->lt($fechaB) ? -1 : 1;
        }

        if ($fechaA && ! $fechaB) {
            return -1;
        }

        if (! $fechaA && $fechaB) {
            return 1;
        }

        return self::compararPorPedidoDesc($a, $b);
    }

    private static function compararPorPedidoDesc(ReqProgramaTejido $a, ReqProgramaTejido $b): int
    {
        $pedidoA = (float) ($a->TotalPedido ?? 0);
        $pedidoB = (float) ($b->TotalPedido ?? 0);

        if ($pedidoA !== $pedidoB) {
            return $pedidoA > $pedidoB ? -1 : 1;
        }

        return ((int) $a->Id) <=> ((int) $b->Id);
    }
}
