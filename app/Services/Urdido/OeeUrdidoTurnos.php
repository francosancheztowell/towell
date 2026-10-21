<?php

declare(strict_types=1);

namespace App\Services\Urdido;

use App\Support\Tramos;
use Carbon\CarbonImmutable;

/**
 * Desglose del OEE Urdido por turno de reloj (TurnoHelper: 06:30 / 14:30 / 22:30), para todo el periodo
 * y las 4 máquinas. Mismas reglas que OeeUrdidoService::armar() (el julio manda, paro solo dentro del
 * calendario), así que la suma de los tres turnos da los totales del reporte.
 *  - Programado, producción y paros se parten por reloj: un tramo que cruza las 14:30 cae en los dos turnos.
 *  - Julios y kg cuentan en el turno en que terminó el julio.
 */
final class OeeUrdidoTurnos
{
    public const NOMBRES = [1 => '1er · 06:30–14:30', 2 => '2do · 14:30–22:30', 3 => '3er · 22:30–06:30'];

    private const SUMAS = ['julios' => 0, 'kg' => 0.0, 'minutos' => 0.0, 'paro_nec' => 0.0, 'paro_oport' => 0.0, 'programado' => 0.0];

    /**
     * Sumas por turno (1..3) del periodo y por mes → máquina.
     *
     * @param  list<string>  $meses
     * @return array{tot: array<int, array<string, int|float>>, meses: array<string, array<string, array<int, array<string, int|float>>>>}
     */
    public static function desglose(array $meses, array $julios, array $paros, array $calendario): array
    {
        $vacio = array_fill_keys(array_keys(self::NOMBRES), self::SUMAS);
        $porMes = [];
        $ventanas = OeeUrdidoService::ventanas($julios);
        foreach ($meses as $mes) {
            $ini = CarbonImmutable::parse($mes.'-01');
            [$m0, $m1] = [$ini->getTimestamp(), $ini->addMonth()->getTimestamp()];
            foreach (array_keys(OeeUrdidoService::MAQUINAS) as $maq) {
                $cal = Tramos::unir($calendario[$maq] ?? [], $m0, $m1);
                $v = Tramos::unir($ventanas[$maq] ?? [], $m0, $m1);
                $nec = self::paroLibre(array_filter($paros[$maq] ?? [], fn ($p) => $p[2] ?? false), $cal, $v, $m0, $m1);
                $todo = self::paroLibre($paros[$maq] ?? [], $cal, $v, $m0, $m1);
                $t = $vacio;
                foreach (self::porTurno($cal) as $k => $min) {
                    [$t[$k]['programado'], $t[$k]['paro_nec'], $t[$k]['paro_oport']] = [$min, $nec[$k], $todo[$k] - $nec[$k]];
                }
                $porMes[$mes][$maq] = $t;
            }
        }
        $porMes = self::sumarJulios($porMes, $julios);

        return ['tot' => self::total($vacio, $porMes), 'meses' => $porMes];
    }

    /** Suma por turno de todos los meses y máquinas. */
    private static function total(array $tot, array $porMes): array
    {
        foreach ($porMes as $maquinas) {
            foreach ($maquinas as $t) {
                foreach ($t as $k => $sumas) {
                    foreach ($sumas as $campo => $valor) {
                        $tot[$k][$campo] += $valor;
                    }
                }
            }
        }

        return $tot;
    }

    /** Producción, julios y kg de los julios finalizados, en su mes (por fecha) y máquina. */
    private static function sumarJulios(array $porMes, array $julios): array
    {
        foreach ($julios as $j) {
            $mes = substr($j['fecha'], 0, 7);
            // ponytail: un julio sin hora de inicio no se puede ubicar en un turno; queda fuera del desglose.
            if (! $j['finalizado'] || $j['inicio'] === null || ! isset($porMes[$mes][$j['maquina']])) {
                continue;
            }
            $fin = $j['inicio'] + (int) round($j['minutos'] * 60);
            foreach (self::porTurno([[$j['inicio'], $fin]]) as $k => $min) {
                $porMes[$mes][$j['maquina']][$k]['minutos'] += $min;
            }
            $k = self::turnoEn(max($j['inicio'], $fin - 1))[0];
            $porMes[$mes][$j['maquina']][$k]['julios']++;
            $porMes[$mes][$j['maquina']][$k]['kg'] += $j['kg'];
        }

        return $porMes;
    }

    /** Minutos de paro dentro de [m0, m1) y del calendario que no caen en ningún julio, por turno. */
    private static function paroLibre(array $paros, array $cal, array $v, int $m0, int $m1): array
    {
        $p = Tramos::unir($cal === [] ? $paros : Tramos::recortar($paros, $cal), $m0, $m1);
        $libre = self::porTurno($p);
        foreach (self::porTurno(Tramos::recortar($p, $v)) as $k => $min) {
            $libre[$k] -= $min;
        }

        return $libre;
    }

    /**
     * Minutos de tramos sin encimar, partidos por turno de reloj.
     *
     * @return array<int, float>
     */
    public static function porTurno(array $tramos): array
    {
        $min = [1 => 0.0, 2 => 0.0, 3 => 0.0];
        foreach ($tramos as [$a, $b]) {
            for ($t = $a; $t < $b; $t = $corte) {
                [$k, $siguiente] = self::turnoEn($t);
                $corte = min($siguiente, $b);
                $min[$k] += ($corte - $t) / 60;
            }
        }

        return $min;
    }

    /**
     * Turno del instante y cuándo empieza el siguiente.
     *
     * @return array{0: int, 1: int}
     */
    private static function turnoEn(int $ts): array
    {
        $dia = date('Y-m-d', $ts);
        [$t1, $t2, $t3] = [strtotime($dia.' 06:30'), strtotime($dia.' 14:30'), strtotime($dia.' 22:30')];

        return match (true) {
            $ts < $t1 => [3, $t1],
            $ts < $t2 => [1, $t2],
            $ts < $t3 => [2, $t3],
            default => [3, (int) strtotime($dia.' 06:30 +1 day')],
        };
    }
}
