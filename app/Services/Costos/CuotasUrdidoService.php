<?php

declare(strict_types=1);

namespace App\Services\Costos;

use App\Models\Costos\CosCuota;
use App\Models\Costos\CosCuotasReal;
use App\Models\Inventario\InvKardexMPConsol;
use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use App\Support\Tramos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuota real de Urdido de un mes (dbo.CosCuotasReal, Depto = 'Urdido').
 *
 * Sab* = sábana de gastos del mes: Σ AMOUNTMST de TWEXPORTATRANSACCIONES (Tow_Tow = Towel y
 * Tow_Pro = Textil, servidor .24) en los centros de urdido 003 URDIDOS-MCKOY I y 005 URDIDOS-KARL MAYER,
 * por tipo: MOD → SabMO, VARIABLES → SabGtosVariable, FIJOS → SabGtosFijos, MOI → SabMOI.
 * Desde jun-2026 AX trae el tipo en CLASIFICACTA; antes viene la clasificación de la cuenta y el tipo
 * se toma de la regla cuenta+centro de los meses ya clasificados (nómina 702-001…017 = MOD).
 *
 * Minutos = Σ minutos de los julios urdidos en el mes (UrdProduccionUrdido.Fecha), HoraFinal − HoraInicial
 * con +24 h si cruza la medianoche, −12 h si pasa de 12 h (AM/PM) y la mediana del folio si pasa de 3×
 * la mediana o faltan horas.
 *
 * MinParo = minutos de paro (ManFallasParos, Depto = 'Urdido', de Fecha+Hora a FechaFin+HoraFin; los no cerrados
 * no cuentan) que ocurrieron DURANTE un julio de esa máquina: los únicos que están dentro de Minutos. Un paro
 * con la máquina sin julio no estaba en Minutos y no se resta. Tipos: sin 'Tiempo Muerto' (default) o total.
 *
 * Cuota ($/min): sin paros (default) = Sab ÷ (Minutos − MinParo), solo el tiempo productivo; con paros =
 * Sab ÷ Minutos, el producto absorbe los paros.
 *
 * SabProrrateoFijo = lo que le toca a urdido de los centros de servicio de la sábana (prorrateoFijo(), la regla de
 * TwProrrateoGastos de ReportesTowel). Su cuota siempre es Sab ÷ Minutos: el prorrateo absorbe los paros, como en
 * los costos unitarios del contador. Urdido no recibe nada de Calderas, el único servicio variable: Prorrateo variable = 0.
 * Mes sin kardex de MP (antes de abr-2026) no se calcula y se conserva lo capturado. Maquila se captura.
 */
class CuotasUrdidoService
{
    public const DEPTO = 'Urdido';

    public const CENTROS = ['003', '005'];

    private const BASES = ['Tow_Tow', 'Tow_Pro'];

    private const TIPOS = ['MOD' => 'SabMO', 'VARIABLES' => 'SabGtosVariable', 'FIJOS' => 'SabGtosFijos', 'MOI' => 'SabMOI'];

    private const CUOTA = ['SabMO' => 'MO', 'SabGtosVariable' => 'GtosVariables', 'SabGtosFijos' => 'GtosFijos', 'SabMOI' => 'MOI'];

    /**
     * Centros de servicio => % que reciben los centros de urdido (TwProrrateoGastos, fijos ene–sep 2026, Towel y Textil).
     * null = almacén de hilo, que va por kg del kardex (prorrateoFijo()).
     */
    private const SERVICIOS = ['018' => null, '023' => 0.08, '024' => 0.02, '025' => 0.01, '026' => 0.03, '027' => 0.06, '028' => 0.01];

    /** Antes del reparto, Calidad (028) y luego Refacciones (023) reparten a otros centros de servicio. */
    private const DE_CALIDAD = ['018' => 0.11, '023' => 0.01, '024' => 0.01, '025' => 0.01, '026' => 0.02];

    private const DE_REFACCIONES = ['024' => 0.05, '025' => 0.01, '026' => 0.05, '027' => 0.02];

    /** @var array<string, mixed> lecturas ya hechas en este cálculo (julios, reglas, sábana del rango) */
    private array $memo = [];

    /** @var list<int>|null meses de actualizarRango() en curso: la sábana de AX se lee de una vez para todos */
    private ?array $rango = null;

    /**
     * Calcula y guarda la cuota (alta o reemplazo de las columnas calculadas) y la lleva a los julios del mes
     * (MOD, MOI, GtsV, GtsF, Pf, PV, Maquila de UrdProduccionUrdido, ver CostosJulio).
     *
     * @return array<string, float|int|null> valores guardados + 'SinClasificar' (importe que no se pudo tipificar) + 'Julios' (con cambios) y 'JuliosMes' (revisados)
     */
    public function actualizar(int $año, int $mes, bool $paroTotal = false, bool $conParos = false): array
    {
        try {
            return $this->guardar($año, $mes, $paroTotal, $conParos);
        } finally {
            $this->rango === null && $this->memo = [];
        }
    }

    /** @return array<string, float|int|null> */
    private function guardar(int $año, int $mes, bool $paroTotal, bool $conParos): array
    {
        $valores = $this->calcular($año, $mes, $paroTotal, $conParos);
        $sinClasificar = $valores['SinClasificar'];
        $sinAx = $valores['SinAx'];
        unset($valores['SinClasificar'], $valores['SinAx']);

        $cuota = CosCuotasReal::existente(self::DEPTO, $año, $mes);
        $cuota
            ? $cuota->update($valores)
            : $cuota = CosCuotasReal::create(['Depto' => self::DEPTO, 'Año' => $año, 'Mes' => $mes] + $valores);
        $delMes = $this->julios($año, $mes);
        $julios = CostosJulio::aplicar($delMes, $this->paros($año, $mes, $paroTotal), $cuota, $conParos);

        return $valores + ['SinClasificar' => $sinClasificar, 'SinAx' => $sinAx, 'Julios' => $julios, 'JuliosMes' => $delMes->count()];
    }

    /**
     * actualizar() de cada mes del rango, con un resumen para avisar: meses sin producción (cuota vacía)
     * e importe de AX que no se pudo tipificar.
     *
     * @return array{completo: bool, texto: string}
     */
    public function actualizarRango(int $año, int $desde, int $hasta, bool $paroTotal = false, bool $conParos = false): array
    {
        $sinProduccion = $sinAx = [];
        $sinClasificar = 0.0;
        $julios = $revisados = 0;
        $this->rango = range($desde, $hasta);
        try {
            $resultados = array_map(fn ($mes) => [$mes, $this->actualizar($año, $mes, $paroTotal, $conParos)], $this->rango);
        } finally {
            [$this->rango, $this->memo] = [null, []];
        }
        foreach ($resultados as [$mes, $r]) {
            $sinClasificar += (float) $r['SinClasificar'];
            $julios += (int) $r['Julios'];
            $revisados += (int) $r['JuliosMes'];
            (float) $r['Minutos'] === 0.0 && $sinProduccion[] = CosCuota::MESES[$mes];
            $r['SinAx'] && $sinAx[] = CosCuota::MESES[$mes];
        }

        $texto = 'Urdido: '.($hasta - $desde + 1).' mes(es) calculado(s), '.number_format($revisados).' julio(s) revisados, '.number_format($julios).' con cambios.';
        $texto .= $julios === 0 && $revisados > 0 && $sinAx === [] ? ' Ya tenían estos costos.' : '';
        $texto .= $sinProduccion === [] ? '' : ' Sin producción: '.implode(', ', $sinProduccion).'.';
        $texto .= $sinAx === [] ? '' : ' Sin movimientos en AX (cuota y costos de los julios quedan vacíos): '.implode(', ', $sinAx).'.';
        $texto .= $sinClasificar === 0.0 ? '' : ' $'.number_format($sinClasificar, 2).' de AX sin clasificar.';

        return ['completo' => $sinProduccion === [] && $sinAx === [] && $sinClasificar === 0.0, 'texto' => $texto];
    }

    /**
     * Solo lectura.
     *
     * @return array<string, bool|float|int|null>
     */
    public function calcular(int $año, int $mes, bool $paroTotal = false, bool $conParos = false): array
    {
        $minutos = round($this->minutos($año, $mes), 4);
        $sab = array_fill_keys(self::TIPOS, 0.0);
        $sinClasificar = 0.0;
        $movimientos = $this->movimientos($año, $mes);
        foreach ($movimientos as $m) {
            $tipo = strtoupper(trim((string) $m->tipo));
            if (! isset(self::TIPOS[$tipo])) {
                $reglas = $this->memo['reglas'] ??= $this->reglas();
                $tipo = $reglas[trim((string) $m->cuenta).'|'.trim((string) $m->centro)] ?? self::tipoPorCuenta((string) $m->cuenta);
            }
            $tipo === null ? $sinClasificar += (float) $m->total : $sab[self::TIPOS[$tipo]] += (float) $m->total;
        }

        $minParo = round($this->minutosParo($año, $mes, $paroTotal), 4);
        $base = $conParos ? $minutos : $minutos - $minParo; // sin paros: solo el tiempo productivo
        $valores = ['Minutos' => $minutos, 'MinParo' => $minParo];
        // AX sin movimientos del mes (aún no exporta): gastos y cuotas vacíos, no en cero, para no costear en $0.
        $sinAx = $movimientos->isEmpty();
        foreach ($sab as $col => $total) {
            $valores[$col] = $sinAx ? null : round($total, 4);
            $valores[self::CUOTA[$col]] = $sinAx || $base <= 0 ? null : round($total / $base, 4);
        }

        return $valores + $this->prorrateos($año, $mes, $minutos) + ['SinClasificar' => round($sinClasificar, 2), 'SinAx' => $sinAx];
    }

    /**
     * Prorrateo fijo y variable del mes con su cuota sobre los minutos totales. [] = sin sábana o kardex: no se toca.
     *
     * @return array<string, float|null>
     */
    private function prorrateos(int $año, int $mes, float $minutos): array
    {
        $servicios = $this->servicios($año, $mes);
        if ($servicios === null) {
            return [];
        }
        $prorrateo = self::prorrateoFijo($servicios['sab'], $servicios['pHilo']);

        return [
            'SabProrrateoFijo' => round($prorrateo, 4),
            'ProrrateoFijo' => $minutos > 0 ? round($prorrateo / $minutos, 4) : null,
            'SabProrrateoVariable' => 0.0,
            'ProrrateoVariable' => 0.0,
        ];
    }

    /**
     * Lo que le toca a urdido de los centros de servicio: Calidad reparte primero (también a otros servicios),
     * luego Refacciones, y cada centro da su % (SERVICIOS) de lo propio más lo recibido. El almacén de hilo da
     * $pHilo, la parte de urdido en los kg de hilo surtidos (kardex). Lineal: sirve con Towel + Textil sumados.
     *
     * @param  array<string, float>  $sab  centro de servicio => gasto del mes en la sábana
     */
    public static function prorrateoFijo(array $sab, float $pHilo): float
    {
        $calidad = $sab['028'] ?? 0.0;
        $refacciones = ($sab['023'] ?? 0.0) + self::DE_CALIDAD['023'] * $calidad;

        $total = 0.0;
        foreach (self::SERVICIOS as $centro => $pct) {
            $gasto = match ($centro) {
                '028' => $calidad,
                '023' => $refacciones,
                default => ($sab[$centro] ?? 0.0) + (self::DE_CALIDAD[$centro] ?? 0.0) * $calidad
                    + (self::DE_REFACCIONES[$centro] ?? 0.0) * $refacciones,
            };
            $total += ($pct ?? $pHilo) * $gasto;
        }

        return $total;
    }

    /**
     * Gasto de los centros de servicio en la sábana (Towel + Textil) y la parte de urdido en el hilo surtido
     * (kardex: Mc Coy + Karl Mayer contra eso más Itema, Jacquard y Smit). null = sin kardex o sin sábana del mes.
     * Dentro de actualizarRango() la sábana se lee de una vez para todos los meses, como movimientos().
     *
     * @return array{sab: array<string, float>, pHilo: float}|null
     */
    public function servicios(int $año, int $mes): ?array
    {
        $meses = $this->rango !== null && in_array($mes, $this->rango, true) ? $this->rango : [$mes];
        $porMes = $this->memo['servicios|'.$año.'|'.implode(',', $meses)] ??= collect(self::BASES)
            ->flatMap(fn (string $base) => DB::connection('sqlsrv_tow_pro')
                ->table("{$base}.dbo.TWEXPORTATRANSACCIONES")
                ->where('YEARDATE', $año)
                ->whereIn('MONTHDATE', array_map(fn ($m) => CosCuota::MESES[$m], $meses))
                ->whereIn('DIMENSION2_', array_keys(self::SERVICIOS))
                ->groupBy('MONTHDATE', 'DIMENSION2_')
                ->select('MONTHDATE as mes', 'DIMENSION2_ as centro')
                ->selectRaw('SUM(AMOUNTMST) AS total') // agregado agrupado: el builder no lo expresa sin raw
                ->get())
            ->groupBy(fn ($r) => trim((string) $r->mes));

        $sab = [];
        foreach ($porMes->get(CosCuota::MESES[$mes], collect()) as $r) {
            $centro = trim((string) $r->centro);
            $sab[$centro] = ($sab[$centro] ?? 0.0) + (float) $r->total;
        }

        // Kg surtidos por área en el mes: el builder no expresa la suma de columnas sin raw. Columnas fijas.
        $kg = InvKardexMPConsol::query()->where('YEARDATE', $año)->where('MONTHDATE', $mes)
            ->selectRaw('SUM(MC1KG + MC2KG + MC3KG + KMKG) AS urdido, SUM(ITEMAKG + JAQCUARDKG + SMITHKG) AS tejido')
            ->toBase()->first();
        $hilo = (float) ($kg->urdido ?? 0) + (float) ($kg->tejido ?? 0);

        return $sab === [] || $hilo <= 0 ? null : ['sab' => $sab, 'pHilo' => (float) $kg->urdido / $hilo];
    }

    /**
     * Movimientos del mes en los centros de urdido, por cuenta y tipo, de las dos empresas.
     * Cada base va por separado: tienen distinta collation y no se pueden unir en SQL. Dentro de
     * actualizarRango() se leen todos los meses del rango de una vez (la tabla de AX no tiene índices).
     *
     * @return Collection<int, \stdClass> cuenta, centro, tipo (CLASIFICACTA) y total
     */
    public function movimientos(int $año, int $mes): Collection
    {
        $meses = $this->rango !== null && in_array($mes, $this->rango, true) ? $this->rango : [$mes];
        $porMes = $this->memo['sabana|'.$año.'|'.implode(',', $meses)] ??= collect(self::BASES)
            ->flatMap(fn (string $base) => DB::connection('sqlsrv_tow_pro')
                ->table("{$base}.dbo.TWEXPORTATRANSACCIONES")
                ->where('YEARDATE', $año)
                ->whereIn('MONTHDATE', array_map(fn ($m) => CosCuota::MESES[$m], $meses))
                ->whereIn('DIMENSION2_', self::CENTROS)
                ->groupBy('MONTHDATE', 'ACCOUNTNUM', 'DIMENSION2_', 'CLASIFICACTA')
                ->select('MONTHDATE as mes', 'ACCOUNTNUM as cuenta', 'DIMENSION2_ as centro', 'CLASIFICACTA as tipo')
                ->selectRaw('SUM(AMOUNTMST) AS total') // agregado agrupado: el builder no lo expresa sin raw
                ->get())
            ->groupBy(fn ($m) => trim((string) $m->mes));

        return $porMes->get(CosCuota::MESES[$mes], collect());
    }

    /**
     * Regla cuenta+centro → tipo, aprendida de los movimientos que AX ya trae clasificados.
     *
     * @return array<string, string>
     */
    public function reglas(): array
    {
        $reglas = [];
        foreach (self::BASES as $base) {
            DB::connection('sqlsrv_tow_pro')->table("{$base}.dbo.TWEXPORTATRANSACCIONES")
                ->whereIn('DIMENSION2_', self::CENTROS)
                ->whereIn('CLASIFICACTA', array_keys(self::TIPOS))
                ->distinct()
                ->get(['ACCOUNTNUM', 'DIMENSION2_', 'CLASIFICACTA'])
                ->each(function ($r) use (&$reglas) {
                    $reglas[trim($r->ACCOUNTNUM).'|'.trim($r->DIMENSION2_)] = strtoupper(trim($r->CLASIFICACTA));
                });
        }

        return $reglas;
    }

    /** Respaldo sin regla: nómina (702-001 a 702-017) en un centro productivo de urdido es MOD. */
    private static function tipoPorCuenta(string $cuenta): ?string
    {
        return preg_match('/^702-0(0\d|1[0-7])-/', trim($cuenta)) ? 'MOD' : null;
    }

    /** Σ minutos de los julios urdidos en el mes, con la limpieza de captura (ver clase). */
    public function minutos(int $año, int $mes): float
    {
        return (float) $this->julios($año, $mes)->sum('minutos');
    }

    /**
     * Julios urdidos en el mes con su máquina (la del folio en UrdProgramaUrdido), inicio y minutos limpios.
     *
     * Cada uno: [id, maquina, inicio (timestamp o null), minutos].
     */
    public function julios(int $año, int $mes): Collection
    {
        return $this->memo['julios|'.$año.'|'.$mes] ??= $this->leerJulios($año, $mes);
    }

    private function leerJulios(int $año, int $mes): Collection
    {
        $desde = sprintf('%04d-%02d-01', $año, $mes);
        $julios = UrdProduccionUrdido::query()
            ->whereBetween('Fecha', [$desde, date('Y-m-t', strtotime($desde))])
            ->get(['Id', 'Folio', 'Fecha', 'HoraInicial', 'HoraFinal'])
            ->toBase();
        $maquinas = UrdProgramaUrdido::query()
            ->whereIn('Folio', $julios->pluck('Folio')->unique()->values()->all())
            ->pluck('MaquinaId', 'Folio');

        $salida = [];
        foreach ($julios->groupBy('Folio') as $folio => $delFolio) {
            $min = $delFolio->map(fn ($j) => self::duracion($j->HoraInicial, $j->HoraFinal))->all();
            $mediana = (float) (collect($min)->filter(fn ($m) => $m !== null)->median() ?? 0);
            foreach ($delFolio as $i => $j) {
                $salida[] = [
                    'id' => (int) $j->Id,
                    'maquina' => self::maquina($maquinas[$folio] ?? ''),
                    'inicio' => $j->HoraInicial === null ? null : self::instante($j->Fecha, $j->HoraInicial),
                    'minutos' => ($min[$i] === null || ($mediana > 0 && $min[$i] > 3 * $mediana)) ? $mediana : $min[$i],
                ];
            }
        }

        return collect($salida);
    }

    /**
     * Minutos de paro que ocurrieron durante un julio de su máquina (ver clase): los únicos que están
     * dentro de Minutos. Los paros encimados de una máquina se unen (un paro no se cuenta dos veces en un julio).
     */
    public function minutosParo(int $año, int $mes, bool $total = false): float
    {
        // Julio por julio, igual que el reparto (CostosJulio): así Minutos − MinParo = Σ de las bases de
        // los julios y la sábana se reparte completa aunque haya julios encimados en una máquina.
        $paros = $this->paros($año, $mes, $total);

        return (float) $this->julios($año, $mes)->sum(fn ($j) => CostosJulio::paroDurante($j, $paros[$j['maquina']] ?? []));
    }

    /**
     * Paros cerrados del mes por máquina, recortados al mes y con los encimados unidos.
     *
     * @return array<string, list<array{0: int, 1: int}>>
     */
    public function paros(int $año, int $mes, bool $total = false): array
    {
        [$desde, $hasta] = self::limites($año, $mes);

        return ManFallasParos::query()
            ->where('Depto', self::DEPTO)
            ->whereNotNull('FechaFin')->whereNotNull('HoraFin')
            ->where('Fecha', '<', date('Y-m-d', $hasta))
            ->where('FechaFin', '>=', date('Y-m-d', $desde))
            ->when(! $total, fn ($q) => $q->where('TipoFallaId', '<>', 'Tiempo Muerto'))
            ->get(['MaquinaId', 'Fecha', 'Hora', 'FechaFin', 'HoraFin'])
            ->groupBy(fn ($p) => self::maquina($p->MaquinaId))
            ->map(fn ($l) => Tramos::unir($l->map(fn ($p) => [self::instante($p->Fecha, $p->Hora), self::instante($p->FechaFin, $p->HoraFin)])->all(), $desde, $hasta))
            ->all();
    }

    /** @return array{0: int, 1: int} [inicio del mes, inicio del mes siguiente) en timestamps */
    private static function limites(int $año, int $mes): array
    {
        $desde = (int) strtotime(sprintf('%04d-%02d-01', $año, $mes));

        return [$desde, (int) strtotime(date('Y-m-t', $desde).' +1 day')];
    }

    /** Mismo nombre de máquina en paros (KM1, MC Coy 3) y en el programa (Karl Mayer, Mc Coy 3). */
    private static function maquina(?string $id): string
    {
        $m = strtoupper(str_replace(' ', '', (string) $id));

        return $m === 'KARLMAYER' ? 'KM1' : $m;
    }

    private static function instante(mixed $fecha, mixed $hora): int
    {
        $dia = $fecha instanceof \DateTimeInterface ? $fecha->format('Y-m-d') : substr((string) $fecha, 0, 10);

        return (int) strtotime($dia.' 00:00:00') + self::segundos((string) $hora);
    }

    /** Minutos de un julio: +24 h si cruza la medianoche, −12 h si pasa de 12 h (AM/PM). */
    public static function duracion(mixed $inicio, mixed $fin): ?float
    {
        if ($inicio === null || $fin === null || $inicio === '' || $fin === '') {
            return null;
        }
        $d = (self::segundos((string) $fin) - self::segundos((string) $inicio)) / 60;
        if ($d < 0) {
            $d += 1440;
        }

        return $d > 720 ? $d - 720 : $d;
    }

    private static function segundos(string $hora): int
    {
        [$h, $m, $s] = array_map('intval', explode(':', substr($hora, 0, 8)) + [0, 0, 0]);

        return $h * 3600 + $m * 60 + $s;
    }
}
