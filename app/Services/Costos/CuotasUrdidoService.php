<?php

declare(strict_types=1);

namespace App\Services\Costos;

use App\Models\Costos\CosCuota;
use App\Models\Costos\CosCuotasReal;
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
 * Sab ÷ Minutos, el producto absorbe los paros. Solo se escriben estas columnas: Prorrateo* y Maquila se capturan.
 */
class CuotasUrdidoService
{
    public const DEPTO = 'Urdido';

    public const CENTROS = ['003', '005'];

    private const BASES = ['Tow_Tow', 'Tow_Pro'];

    private const TIPOS = ['MOD' => 'SabMO', 'VARIABLES' => 'SabGtosVariable', 'FIJOS' => 'SabGtosFijos', 'MOI' => 'SabMOI'];

    private const CUOTA = ['SabMO' => 'MO', 'SabGtosVariable' => 'GtosVariables', 'SabGtosFijos' => 'GtosFijos', 'SabMOI' => 'MOI'];

    /**
     * Calcula y guarda la cuota (alta o reemplazo de las columnas calculadas) y la lleva a los julios del mes
     * (MOD, MOI, GtsV, GtsF, Pf, PV, Maquila de UrdProduccionUrdido, ver CostosJulio).
     *
     * @return array<string, float|int|null> valores guardados + 'SinClasificar' (importe que no se pudo tipificar) + 'Julios'
     */
    public function actualizar(int $año, int $mes, bool $paroTotal = false, bool $conParos = false): array
    {
        $valores = $this->calcular($año, $mes, $paroTotal, $conParos);
        $sinClasificar = $valores['SinClasificar'];
        unset($valores['SinClasificar']);

        $cuota = CosCuotasReal::existente(self::DEPTO, $año, $mes);
        $cuota
            ? $cuota->update($valores)
            : $cuota = CosCuotasReal::create(['Depto' => self::DEPTO, 'Año' => $año, 'Mes' => $mes] + $valores);
        $julios = CostosJulio::aplicar($this->julios($año, $mes), $this->paros($año, $mes, $paroTotal), $cuota, $conParos);

        return $valores + ['SinClasificar' => $sinClasificar, 'Julios' => $julios];
    }

    /**
     * actualizar() de cada mes del rango, con un resumen para avisar: meses sin producción (cuota vacía)
     * e importe de AX que no se pudo tipificar.
     *
     * @return array{completo: bool, texto: string}
     */
    public function actualizarRango(int $año, int $desde, int $hasta, bool $paroTotal = false, bool $conParos = false): array
    {
        $sinProduccion = [];
        $sinClasificar = 0.0;
        $julios = 0;
        foreach (range($desde, $hasta) as $mes) {
            $r = $this->actualizar($año, $mes, $paroTotal, $conParos);
            $sinClasificar += (float) $r['SinClasificar'];
            $julios += (int) $r['Julios'];
            if ((float) $r['Minutos'] === 0.0) {
                $sinProduccion[] = CosCuota::MESES[$mes];
            }
        }

        $texto = 'Urdido: '.($hasta - $desde + 1).' mes(es) calculado(s), '.number_format($julios).' julio(s) actualizados.';
        $texto .= $sinProduccion === [] ? '' : ' Sin producción: '.implode(', ', $sinProduccion).'.';
        $texto .= $sinClasificar === 0.0 ? '' : ' $'.number_format($sinClasificar, 2).' de AX sin clasificar.';

        return ['completo' => $sinProduccion === [] && $sinClasificar === 0.0, 'texto' => $texto];
    }

    /**
     * Solo lectura.
     *
     * @return array<string, float|int|null>
     */
    public function calcular(int $año, int $mes, bool $paroTotal = false, bool $conParos = false): array
    {
        $minutos = round($this->minutos($año, $mes), 4);
        $sab = array_fill_keys(self::TIPOS, 0.0);
        $sinClasificar = 0.0;
        $reglas = null;

        foreach ($this->movimientos($año, $mes) as $m) {
            $tipo = strtoupper(trim((string) $m->tipo));
            if (! isset(self::TIPOS[$tipo])) {
                $reglas ??= $this->reglas();
                $tipo = $reglas[trim((string) $m->cuenta).'|'.trim((string) $m->centro)] ?? self::tipoPorCuenta((string) $m->cuenta);
            }
            $tipo === null ? $sinClasificar += (float) $m->total : $sab[self::TIPOS[$tipo]] += (float) $m->total;
        }

        $minParo = round($this->minutosParo($año, $mes, $paroTotal), 4);
        $base = $conParos ? $minutos : $minutos - $minParo; // sin paros: solo el tiempo productivo
        $valores = ['Minutos' => $minutos, 'MinParo' => $minParo];
        foreach ($sab as $col => $total) {
            $valores[$col] = round($total, 4);
            $valores[self::CUOTA[$col]] = $base > 0 ? round($total / $base, 4) : null;
        }

        return $valores + ['SinClasificar' => round($sinClasificar, 2)];
    }

    /**
     * Movimientos del mes en los centros de urdido, por cuenta y tipo, de las dos empresas.
     * Cada base va por separado: tienen distinta collation y no se pueden unir en SQL.
     *
     * @return Collection<int, \stdClass> cuenta, centro, tipo (CLASIFICACTA) y total
     */
    public function movimientos(int $año, int $mes): Collection
    {
        return collect(self::BASES)->flatMap(fn (string $base) => DB::connection('sqlsrv_tow_pro')
            ->table("{$base}.dbo.TWEXPORTATRANSACCIONES")
            ->where('YEARDATE', $año)
            ->where('MONTHDATE', CosCuota::MESES[$mes])
            ->whereIn('DIMENSION2_', self::CENTROS)
            ->groupBy('ACCOUNTNUM', 'DIMENSION2_', 'CLASIFICACTA')
            ->select('ACCOUNTNUM as cuenta', 'DIMENSION2_ as centro', 'CLASIFICACTA as tipo')
            ->selectRaw('SUM(AMOUNTMST) AS total') // agregado agrupado: el builder no lo expresa sin raw
            ->get());
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
