<?php

declare(strict_types=1);

namespace App\Services\Costos;

use App\Models\Costos\CosCuota;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Support\ActualizacionPorId;
use App\Support\Tramos;
use Illuminate\Support\Collection;

/**
 * Lleva la cuota del mes (CosCuotasReal) a cada julio de UrdProduccionUrdido:
 * importe = cuota ($/min) × base del julio, con base = minutos del julio − paro ocurrido durante él
 * (sin paros, default) o los minutos completos (con paros). Igual que la base del mes, así que
 * Σ de los julios = Sab de la cuota. Cuota vacía (no capturada) → la columna queda NULL.
 */
final class CostosJulio
{
    /** Columna de UrdProduccionUrdido => cuota de CosCuotasReal. */
    public const COLUMNAS = [
        'MOD' => 'MO', 'MOI' => 'MOI', 'GtsV' => 'GtosVariables', 'GtsF' => 'GtosFijos',
        'Pf' => 'ProrrateoFijo', 'PV' => 'ProrrateoVariable', 'Maquila' => 'Maquila',
    ];

    /**
     * Guarda solo los julios que cambian.
     *
     * @param  Collection<int, array<string, mixed>>  $julios  id, maquina, inicio, minutos (CuotasUrdidoService::julios)
     * @param  array<string, list<array{0: int, 1: int}>>  $paros  máquina => tramos de paro ya unidos
     * @return int julios actualizados
     */
    public static function aplicar(Collection $julios, array $paros, CosCuota $cuota, bool $conParos): int
    {
        $nuevos = [];
        foreach ($julios as $j) {
            $base = $j['minutos'] - ($conParos ? 0 : self::paroDurante($j, $paros[$j['maquina']] ?? []));
            foreach (self::COLUMNAS as $col => $deCuota) {
                $cuotaMin = $cuota->getAttribute($deCuota);
                $nuevos[$j['id']][$col] = $cuotaMin === null ? null : round((float) $cuotaMin * max(0.0, $base), 4);
            }
        }

        $actuales = collect(array_keys($nuevos))->chunk(1000) // < 2100 parámetros por consulta
            ->flatMap(fn ($ids) => UrdProduccionUrdido::query()->whereIn('Id', $ids->all())->get(['Id', ...array_keys(self::COLUMNAS)]))
            ->keyBy('Id');
        $cambios = array_filter($nuevos, fn ($v, $id) => self::distinto($v, $actuales->get($id)), ARRAY_FILTER_USE_BOTH);

        ActualizacionPorId::ejecutar('UrdProduccionUrdido', $cambios, array_keys(self::COLUMNAS));

        return count($cambios);
    }

    /**
     * Minutos de paro de su máquina que cayeron dentro del julio.
     *
     * @param  array<string, mixed>  $julio
     * @param  list<array{0: int, 1: int}>  $paros
     */
    public static function paroDurante(array $julio, array $paros): float
    {
        if ($julio['inicio'] === null || $paros === []) {
            return 0.0;
        }

        return Tramos::cruce([[$julio['inicio'], $julio['inicio'] + (int) round($julio['minutos'] * 60)]], $paros) / 60;
    }

    /** @param array<string, float|null> $nuevo */
    private static function distinto(array $nuevo, ?UrdProduccionUrdido $actual): bool
    {
        foreach ($nuevo as $col => $valor) {
            $antes = $actual?->{$col};
            if (($antes === null) !== ($valor === null) || round((float) $antes, 4) !== round((float) $valor, 4)) {
                return true;
            }
        }

        return false;
    }
}
