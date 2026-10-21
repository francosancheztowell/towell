<?php

declare(strict_types=1);

namespace App\Services\Urdido;

use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Services\Costos\CuotasUrdidoService;
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
 *  - Disponibilidad = prod ÷ (prod + paro); Rendimiento = 100 % (no hay velocidad estándar);
 *    Calidad = kg sin defecto ÷ kg.
 */
final class OeeUrdidoService
{
    public const MAQUINAS = ['MCCOY1' => 'Mc Coy 1', 'MCCOY2' => 'Mc Coy 2', 'MCCOY3' => 'Mc Coy 3', 'KM1' => 'KM1'];

    private const SUMAS = ['julios' => 0, 'kg' => 0.0, 'kg_buenos' => 0.0, 'metros' => 0.0, 'minutos' => 0.0, 'paro_nec' => 0.0, 'paro_oport' => 0.0];

    /**
     * @param  list<string>  $meses  'Y-m'
     */
    public function reporte(array $meses): array
    {
        sort($meses);
        $desde = CarbonImmutable::parse($meses[0].'-01');
        $hasta = CarbonImmutable::parse(end($meses).'-01')->addMonth();

        return self::armar($meses, $this->julios($desde, $hasta), $this->paros($desde, $hasta));
    }

    /**
     * Pura (sin BD) para poder probarla.
     *
     * @param  list<string>  $meses
     * @param  list<array<string, mixed>>  $julios  ver julios(); 'finalizado' false solo sirve de ventana
     * @param  array<string, list<array{0: int, 1: int, 2?: bool}>>  $paros  por máquina, sin unir; [2] = necesario
     */
    public static function armar(array $meses, array $julios, array $paros): array
    {
        $ventanas = self::ventanas($julios);
        $reporte = ['meses' => [], 'tot' => self::SUMAS];
        foreach ($meses as $mes) {
            $delMes = ['etiqueta' => ucfirst(CarbonImmutable::parse($mes.'-01')->locale('es')->translatedFormat('F Y')), 'maquinas' => [], 'tot' => self::SUMAS];
            foreach (self::MAQUINAS as $maq => $nombre) {
                // La semana solo sirve para prorratear el paro (decisión del owner: igual que antes);
                // el reporte agrupa Mes → Máquina.
                $maquina = ['nombre' => $nombre, 'julios' => [], 'tot' => self::SUMAS];
                foreach (self::segmentos($mes) as [$s0, $s1]) {
                    $grupo = self::grupo($nombre, $julios, $maq, $s0, $s1, ...self::paroLibre($paros[$maq] ?? [], $ventanas[$maq] ?? [], $s0, $s1));
                    if ($grupo !== null) {
                        $maquina['julios'] = [...$maquina['julios'], ...$grupo['julios']];
                        $maquina['tot'] = self::sumarTot($maquina['tot'], $grupo['tot']);
                    }
                }
                if ($maquina['julios'] !== [] || $maquina['tot']['paro_nec'] + $maquina['tot']['paro_oport'] > 0) {
                    $delMes['maquinas'][$maq] = $maquina;
                    $delMes['tot'] = self::sumarTot($delMes['tot'], $maquina['tot']);
                }
            }
            $reporte['meses'][$mes] = $delMes;
            $reporte['tot'] = self::sumarTot($reporte['tot'], $delMes['tot']);
        }

        return $reporte;
    }

    /** Ventanas [inicio, fin) de todos los julios (finalizados o no) por máquina: ahí la máquina estaba urdiendo. */
    private static function ventanas(array $julios): array
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
     * Minutos de paro de la máquina dentro de [s0, s1) que no caen en ningún julio: [necesario, oportunidad].
     *
     * @return array{0: float, 1: float}
     */
    private static function paroLibre(array $paros, array $ventanas, int $s0, int $s1): array
    {
        $v = Tramos::unir($ventanas, $s0, $s1);
        $libre = function (array $tramos) use ($v, $s0, $s1): float {
            $p = Tramos::unir($tramos, $s0, $s1);

            return (array_sum(array_map(fn ($t) => $t[1] - $t[0], $p)) - Tramos::cruce($p, $v)) / 60;
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

    /** Julios del periodo (±1 día para las ventanas en el borde); solo los finalizados salen en el reporte. */
    private function julios(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        return UrdProduccionUrdido::query()
            ->with('programa:Folio,MaquinaId,BomId,Fibra,InventSizeId')
            ->with('defecto:Id,Clave,Defecto')
            ->whereBetween('Fecha', [$desde->subDay()->toDateString(), $hasta->toDateString()])
            ->orderBy('Fecha')->orderBy('HoraInicial')
            ->get()
            ->map(function (UrdProduccionUrdido $j) use ($desde, $hasta) {
                $fecha = $j->Fecha instanceof \DateTimeInterface ? $j->Fecha->format('Y-m-d') : substr((string) $j->Fecha, 0, 10);
                $minutos = (float) (CuotasUrdidoService::duracion($j->HoraInicial, $j->HoraFinal) ?? 0);
                $kg = (float) $j->KgNeto;
                $oficiales = [];
                foreach ([1, 2, 3] as $n) {
                    if (trim((string) $j->{'NomEmpl'.$n}) !== '') {
                        $oficiales[] = trim((string) $j->{'NomEmpl'.$n});
                    }
                }

                return [
                    'maquina' => self::maquina($j->programa?->MaquinaId),
                    'fecha' => $fecha,
                    'dia' => self::dia($fecha),
                    'hora' => substr((string) $j->HoraInicial, 0, 5),
                    'hora_fin' => substr((string) $j->HoraFinal, 0, 5),
                    'inicio' => $j->HoraInicial ? strtotime($fecha.' '.substr((string) $j->HoraInicial, 0, 8)) : null,
                    'minutos' => $minutos,
                    'finalizado' => (bool) $j->Finalizar && $fecha >= $desde->toDateString() && $fecha < $hasta->toDateString(),
                    'orden' => $j->Folio,
                    'julio' => $j->NoJulio,
                    'lmat' => $j->programa?->BomId,
                    'config' => $j->programa?->Fibra,
                    'tamano' => $j->programa?->InventSizeId,
                    'kg' => $kg,
                    'kg_buenos' => $j->ClaveDefecto ? 0.0 : $kg,
                    'metros' => (float) $j->Metros1 + (float) $j->Metros2 + (float) $j->Metros3,
                    'defecto' => $j->defecto?->Clave,
                    'defecto_nombre' => $j->defecto?->Defecto,
                    'penalizacion' => $j->Penalizacion !== null ? (float) $j->Penalizacion : null,
                    'oficiales' => implode(' / ', $oficiales),
                ];
            })
            ->all();
    }

    /** Paros cerrados de Urdido que tocan el periodo, por máquina (los abiertos se ignoran): [inicio, fin, necesario]. */
    private function paros(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $ts = fn ($f, $h) => strtotime(($f instanceof \DateTimeInterface ? $f->format('Y-m-d') : substr((string) $f, 0, 10)).' '.substr((string) $h, 0, 8));
        // ManFallasParos.Falla guarda el código (CatParosFallas.Falla), no la descripción.
        $necesarios = CatParosFallas::query()
            ->where('Departamento', 'URDIDO')->where('Necesario', true)
            ->get(['TipoFallaId', 'Falla'])
            ->mapWithKeys(fn ($c) => [$c->TipoFallaId.'|'.$c->Falla => true]);

        return ManFallasParos::query()
            ->where('Depto', 'Urdido')
            ->whereNotNull('FechaFin')->whereNotNull('HoraFin')
            ->where('Fecha', '<', $hasta->toDateString())
            ->where('FechaFin', '>=', $desde->toDateString())
            ->get(['MaquinaId', 'Fecha', 'Hora', 'FechaFin', 'HoraFin', 'TipoFallaId', 'Falla'])
            ->groupBy(fn ($p) => self::maquina($p->MaquinaId))
            ->map(fn ($l) => $l->map(fn ($p) => [$ts($p->Fecha, $p->Hora), $ts($p->FechaFin, $p->HoraFin), $necesarios->has($p->TipoFallaId.'|'.$p->Falla)])->all())
            ->all();
    }

    /** '2026-10-13' → '13 oct martes'. */
    public static function dia(string $fecha): string
    {
        $c = CarbonImmutable::parse($fecha)->locale('es');

        return $c->day.' '.rtrim($c->translatedFormat('M'), '.').' '.$c->translatedFormat('l');
    }

    /** 'Mc Coy 1' / 'MC Coy 1' → MCCOY1; 'Karl Mayer' / 'KM1' → KM1. */
    private static function maquina(?string $id): string
    {
        $m = strtoupper(str_replace(' ', '', (string) $id));

        return $m === 'KARLMAYER' ? 'KM1' : $m;
    }
}
