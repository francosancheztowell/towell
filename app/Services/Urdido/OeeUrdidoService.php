<?php

declare(strict_types=1);

namespace App\Services\Urdido;

use App\Support\Tramos;
use Carbon\CarbonImmutable;

/**
 * Reporte OEE Urdido: Mes → Máquina → julio finalizado (la vista agrupa por día).
 * Decisiones en .planning/oee_urdido_plan.md:
 *  - El julio manda: un paro solo cuenta fuera de las ventanas de julios de su máquina
 *    (los paros se cierran días tarde mientras la máquina sigue urdiendo).
 *  - Los minutos de paro de cada máquina+semana ISO (partida por mes) se prorratean entre sus julios por
 *    minutos de producción; un paro sin julios en esa semana queda en el total de la máquina.
 *  - El paro se parte en necesario (CatParosFallas.Necesario = 1) y de oportunidad (el resto); un tramo
 *    con paros de las dos clases a la vez cuenta como necesario, para no contarlo dos veces.
 *  - Calendario (UrdEngCalMaquinas): el paro solo cuenta dentro del tiempo programado de la máquina
 *    (un paro abierto de más no suma noches ni fines de semana sin programa). Sin registrar =
 *    programado − producción − paros; negativo = se trabajó fuera del calendario.
 *  - Disponibilidad = prod ÷ (prod + paro); Rendimiento = 100 % (no hay velocidad estándar);
 *    Calidad = kg sin defecto ÷ kg.
 */
final class OeeUrdidoService
{
    public const MAQUINAS = ['MCCOY1' => 'Mc Coy 1', 'MCCOY2' => 'Mc Coy 2', 'MCCOY3' => 'Mc Coy 3', 'KM1' => 'KM1'];

    private const SUMAS = ['julios' => 0, 'kg' => 0.0, 'kg_buenos' => 0.0, 'metros' => 0.0, 'minutos' => 0.0, 'paro_nec' => 0.0, 'paro_oport' => 0.0, 'programado' => 0.0];

    public function __construct(private readonly OeeUrdidoConsultas $consultas) {}

    /**
     * @param  list<string>  $meses  'Y-m'
     */
    public function reporte(array $meses): array
    {
        sort($meses);
        $desde = CarbonImmutable::parse($meses[0].'-01');
        $hasta = CarbonImmutable::parse(end($meses).'-01')->addMonth();
        $c = $this->consultas;
        [$julios, $paros, $calendario] = [$c->julios($desde, $hasta), $c->paros($desde, $hasta), $c->calendario($desde, $hasta)];

        return self::armar($meses, $julios, $paros, $calendario) + ['turnos' => OeeUrdidoTurnos::desglose($meses, $julios, $paros, $calendario)];
    }

    /**
     * Pura (sin BD) para poder probarla.
     *
     * @param  list<string>  $meses
     * @param  list<array<string, mixed>>  $julios  ver julios(); 'finalizado' false solo sirve de ventana
     * @param  array<string, list<array{0: int, 1: int, 2?: bool}>>  $paros  por máquina, sin unir; [2] = necesario
     * @param  array<string, list<array{0: int, 1: int}>>  $calendario  turnos programados por máquina
     */
    public static function armar(array $meses, array $julios, array $paros, array $calendario = []): array
    {
        $ventanas = self::ventanas($julios);
        $reporte = ['meses' => [], 'tot' => self::SUMAS];
        foreach ($meses as $mes) {
            $delMes = ['etiqueta' => ucfirst(CarbonImmutable::parse($mes.'-01')->locale('es')->translatedFormat('F Y')), 'maquinas' => [], 'tot' => self::SUMAS];
            foreach (self::MAQUINAS as $maq => $nombre) {
                $maquina = self::maquinaDelMes($mes, $maq, $nombre, $julios, $paros[$maq] ?? [], $ventanas[$maq] ?? [], $calendario[$maq] ?? []);
                if ($maquina !== null) {
                    $delMes['maquinas'][$maq] = $maquina;
                    $delMes['tot'] = self::sumarTot($delMes['tot'], $maquina['tot']);
                }
            }
            $reporte['meses'][$mes] = $delMes;
            $reporte['tot'] = self::sumarTot($reporte['tot'], $delMes['tot']);
        }

        return $reporte;
    }

    /** Julios, paro y tiempo programado de la máquina en el mes; null si no hay nada. */
    private static function maquinaDelMes(string $mes, string $maq, string $nombre, array $julios, array $paros, array $ventanas, array $calendario): ?array
    {
        $ini = CarbonImmutable::parse($mes.'-01');
        $cal = Tramos::unir($calendario, $ini->getTimestamp(), $ini->addMonth()->getTimestamp());
        // La semana solo sirve para prorratear el paro (decisión del owner: igual que antes);
        // el reporte agrupa Mes → Máquina.
        $maquina = ['nombre' => $nombre, 'julios' => [], 'tot' => self::SUMAS];
        foreach (self::segmentos($mes) as [$s0, $s1]) {
            $grupo = self::grupo($nombre, $julios, $maq, $s0, $s1, ...self::paroLibre($paros, $ventanas, $cal, $s0, $s1));
            if ($grupo !== null) {
                $maquina['julios'] = [...$maquina['julios'], ...$grupo['julios']];
                $maquina['tot'] = self::sumarTot($maquina['tot'], $grupo['tot']);
            }
        }
        $maquina['tot']['programado'] = Tramos::segundos($cal) / 60;
        $t = $maquina['tot'];

        return $maquina['julios'] !== [] || $t['paro_nec'] + $t['paro_oport'] + $t['programado'] > 0 ? $maquina : null;
    }

    /**
     * Minutos programados que no fueron producción ni paro; null sin calendario capturado.
     * Negativo = se produjo o se paró fuera del calendario.
     */
    public static function sinRegistrar(array $t): ?float
    {
        return $t['programado'] > 0 ? $t['programado'] - $t['minutos'] - $t['paro_nec'] - $t['paro_oport'] : null;
    }

    /** Ventanas [inicio, fin) de todos los julios (finalizados o no) por máquina: ahí la máquina estaba urdiendo. */
    public static function ventanas(array $julios): array
    {
        $ventanas = [];
        foreach ($julios as $j) {
            if ($j['inicio'] !== null && $j['minutos'] > 0) {
                $ventanas[$j['maquina']][] = [$j['inicio'], $j['inicio'] + (int) round($j['minutos'] * 60)];
            }
        }

        return $ventanas;
    }

    /** Julios finalizados de la máquina en [s0, s1) con su paro prorrateado; null si no hay julios ni paro. */
    private static function grupo(string $nombre, array $julios, string $maq, int $s0, int $s1, float $nec, float $oport): ?array
    {
        [$d0, $d1] = [date('Y-m-d', $s0), date('Y-m-d', $s1)];
        $delGrupo = array_values(array_filter($julios, fn ($j) => $j['finalizado'] && $j['maquina'] === $maq && $j['fecha'] >= $d0 && $j['fecha'] < $d1));
        if ($delGrupo === [] && $nec + $oport <= 0) {
            return null;
        }
        $grupo = ['nombre' => $nombre, 'julios' => self::prorratear($delGrupo, $nec, $oport), 'tot' => self::SUMAS];
        foreach ($grupo['julios'] as $j) {
            $grupo['tot'] = self::sumar($grupo['tot'], $j);
        }
        // Paro sin julios en la semana (máquina parada toda la semana): solo en el subtotal.
        if ($grupo['julios'] === []) {
            [$grupo['tot']['paro_nec'], $grupo['tot']['paro_oport']] = [$nec, $oport];
        }

        return $grupo;
    }

    /** Disponibilidad, rendimiento, calidad y OEE (0–1) de un total o de un julio. */
    public static function indicadores(array $t): array
    {
        $paro = $t['paro_nec'] + $t['paro_oport'];
        $disp = ($t['minutos'] + $paro) > 0 ? $t['minutos'] / ($t['minutos'] + $paro) : null;
        $cal = $t['kg'] > 0 ? $t['kg_buenos'] / $t['kg'] : null;
        $rend = 1.0; // ponytail: sin velocidad estándar de urdido; reemplazar por real ÷ estándar cuando exista

        return ['disp' => $disp, 'rend' => $rend, 'cal' => $cal, 'oee' => $disp !== null && $cal !== null ? $disp * $rend * $cal : null];
    }

    /**
     * Semanas ISO del mes, partidas en el borde del mes: [clave => [inicio, fin), etiqueta, rango].
     * Etiqueta 'Sem 27 (1–5 jul)'; rango '1–5 jul'.
     *
     * @return array<string, array{0: int, 1: int, 2: string, 3: string}>
     */
    public static function segmentos(string $mes): array
    {
        $ini = CarbonImmutable::parse($mes.'-01');
        $fin = $ini->addMonth();
        $salida = [];
        for ($d = $ini; $d < $fin; $d = $corte) {
            $corte = min($d->startOfWeek(CarbonImmutable::MONDAY)->addWeek(), $fin);
            $ultimo = $corte->subDay();
            $rango = $d->day.($ultimo->day !== $d->day ? '–'.$ultimo->day : '').' '.rtrim($d->locale('es')->translatedFormat('M'), '.');
            $salida[$mes.'-S'.$d->isoWeek()] = [$d->getTimestamp(), $corte->getTimestamp(), 'Sem '.$d->isoWeek().' ('.$rango.')', $rango];
        }

        return $salida;
    }

    /**
     * Minutos de paro de la máquina dentro de [s0, s1) y del calendario que no caen en ningún julio:
     * [necesario, oportunidad].
     *
     * @param  list<array{0: int, 1: int}>  $cal  turnos del mes ya unidos
     * @return array{0: float, 1: float}
     */
    private static function paroLibre(array $paros, array $ventanas, array $cal, int $s0, int $s1): array
    {
        $v = Tramos::unir($ventanas, $s0, $s1);
        $libre = function (array $tramos) use ($v, $cal, $s0, $s1): float {
            // ponytail: mes sin calendario capturado = sin recorte (como antes), para no dejar el paro en cero.
            $p = Tramos::unir($cal === [] ? $tramos : Tramos::recortar($tramos, $cal), $s0, $s1);

            return (Tramos::segundos($p) - Tramos::cruce($p, $v)) / 60;
        };
        $nec = $libre(array_filter($paros, fn ($p) => $p[2] ?? false));

        return [$nec, $libre($paros) - $nec];
    }

    /** Reparte el paro necesario y el de oportunidad entre los julios por sus minutos de producción. */
    private static function prorratear(array $julios, float $nec, float $oport): array
    {
        $base = array_sum(array_column($julios, 'minutos'));
        $parte = fn ($j) => $base > 0 ? $j['minutos'] / $base : 0.0;

        return array_map(fn ($j) => $j + ['paro_nec' => $nec * $parte($j), 'paro_oport' => $oport * $parte($j)], $julios);
    }

    private static function sumar(array $tot, array $j): array
    {
        $tot['julios']++;
        $tot['kg'] += $j['kg'];
        $tot['kg_buenos'] += $j['kg_buenos'];
        $tot['metros'] += $j['metros'];
        $tot['minutos'] += $j['minutos'];
        $tot['paro_nec'] += $j['paro_nec'];
        $tot['paro_oport'] += $j['paro_oport'];

        return $tot;
    }

    private static function sumarTot(array $a, array $b): array
    {
        foreach ($a as $k => $v) {
            $a[$k] = $v + $b[$k];
        }

        return $a;
    }
}
