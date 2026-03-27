<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\VincularTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * @file OrdCompartidaHelper.php
 * @description Helper para operaciones con OrdCompartida. Proporciona métodos para obtener
 *              nuevos OrdCompartida disponibles verificando que no estén en uso.
 * @dependencies ReqProgramaTejido
 * @relatedFiles DuplicarTejido.php, VincularTejido.php, DividirTejido.php
 */
class OrdCompartidaHelper
{
    /**
     * Obtiene un nuevo OrdCompartida disponible verificando que no esté en uso.
     * Útil al crear grupos de registros vinculados (duplicar con vincular, vincular existentes).
     *
     * @return int Siguiente OrdCompartida disponible
     */
    public static function obtenerNuevoOrdCompartidaDisponible(): int
    {
        $maxOrdCompartida = ReqProgramaTejido::max('OrdCompartida') ?? 0;
        $candidato = $maxOrdCompartida + 1;

        $intentos = 0;
        $maxIntentos = 1000;

        while ($intentos < $maxIntentos) {
            $existe = ReqProgramaTejido::where('OrdCompartida', $candidato)->exists();

            if (!$existe) {
                return $candidato;
            }

            $candidato++;
            $intentos++;
        }

        return $candidato;
    }

    public static function seleccionarLider(Collection $registros): ?ReqProgramaTejido
    {
        if ($registros->isEmpty()) {
            return null;
        }

        $todosMismaFechaInicio = $registros
            ->map(fn($registro) => self::normalizarFechaInicioDia($registro))
            ->uniqueStrict()
            ->count() <= 1;

        return $registros->sort(function ($a, $b) use ($todosMismaFechaInicio) {
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
