<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tramos de tiempo [inicio, fin) en segundos (timestamps): unir los que se enciman y medir cuánto
 * se cruzan dos listas. Lo usan las cuotas de Urdido para saber qué paros pasaron durante un julio.
 */
final class Tramos
{
    /**
     * Tramos [inicio, fin] recortados a [desde, hasta), ordenados y con los encimados unidos.
     *
     * @param  array<int, array{0: int, 1: int}>  $tramos
     * @return list<array{0: int, 1: int}>
     */
    public static function unir(array $tramos, int $desde, int $hasta): array
    {
        $tramos = array_filter(array_map(fn ($t) => [max($t[0], $desde), min($t[1], $hasta)], $tramos), fn ($t) => $t[1] > $t[0]);
        usort($tramos, fn ($a, $b) => $a[0] <=> $b[0]);
        $unidos = [];
        foreach ($tramos as $t) {
            $ultimo = count($unidos) - 1;
            if ($ultimo >= 0 && $t[0] <= $unidos[$ultimo][1]) {
                $unidos[$ultimo][1] = max($unidos[$ultimo][1], $t[1]);
            } else {
                $unidos[] = $t;
            }
        }

        return $unidos;
    }

    /**
     * Segundos en común de dos listas de tramos ya unidas y ordenadas.
     *
     * @param  list<array{0: int, 1: int}>  $a
     * @param  list<array{0: int, 1: int}>  $b
     */
    public static function cruce(array $a, array $b): int
    {
        [$i, $j, $total] = [0, 0, 0];
        while ($i < count($a) && $j < count($b)) {
            $total += max(0, min($a[$i][1], $b[$j][1]) - max($a[$i][0], $b[$j][0]));
            $a[$i][1] < $b[$j][1] ? $i++ : $j++;
        }

        return $total;
    }
}
