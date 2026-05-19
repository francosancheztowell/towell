<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\VincularTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * @file OrdCompartidaHelper.php
 * @description Helper para operaciones con OrdCompartida. El valor de OrdCompartida se deriva
 *              del NoProduccion del registro líder del grupo (no es un contador sintético).
 * @dependencies ReqProgramaTejido
 * @relatedFiles DuplicarTejido.php, VincularTejido.php, DividirTejido.php
 */
class OrdCompartidaHelper
{
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
        if ($limpio === '' || !is_numeric($limpio)) {
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
            ->map(fn($registro) => self::normalizarFechaInicioDia($registro))
            ->uniqueStrict()
            ->count() <= 1;

        return $elegibles->sort(function ($a, $b) use ($todosMismaFechaInicio) {
            if (!$todosMismaFechaInicio) {
                return self::compararPorFechaInicio($a, $b);
            }

            $fechaCreacionA = self::combinarFechaCreacion($a);
            $fechaCreacionB = self::combinarFechaCreacion($b);

            if ($fechaCreacionA && $fechaCreacionB && !$fechaCreacionA->equalTo($fechaCreacionB)) {
                return $fechaCreacionA->lt($fechaCreacionB) ? -1 : 1;
            }

            if ($fechaCreacionA && !$fechaCreacionB) {
                return -1;
            }

            if (!$fechaCreacionA && $fechaCreacionB) {
                return 1;
            }

            return self::compararPorPedidoDesc($a, $b);
        })->first();
    }

    public static function recalcularLiderYOrdPrincipalPorOrdCompartida(int $ordCompartida): ?int
    {
        if ($ordCompartida <= 0) {
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
        if (!$lider) {
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

        VincularTejido::actualizarOrdPrincipalPorOrdCompartida($ordCompartida);

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

            return Carbon::parse($fecha . ' ' . $hora);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function compararPorFechaInicio(ReqProgramaTejido $a, ReqProgramaTejido $b): int
    {
        $inicioA = !empty($a->FechaInicio) ? Carbon::parse($a->FechaInicio) : null;
        $inicioB = !empty($b->FechaInicio) ? Carbon::parse($b->FechaInicio) : null;

        if ($inicioA && $inicioB && !$inicioA->equalTo($inicioB)) {
            return $inicioA->lt($inicioB) ? -1 : 1;
        }

        if ($inicioA && !$inicioB) {
            return -1;
        }

        if (!$inicioA && $inicioB) {
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
