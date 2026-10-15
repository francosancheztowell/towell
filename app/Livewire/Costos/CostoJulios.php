<?php

declare(strict_types=1);

namespace App\Livewire\Costos;

use App\Exports\CostoJuliosExport;
use App\Models\Costos\CosCuota;
use App\Models\Costos\CosCuotasReal;
use App\Models\Inventario\InvKardexMPConsol;
use App\Models\Urdido\Urdbom;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Services\Costos\CostosJulio;
use App\Services\Costos\CuotasUrdidoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Costo por julio (/costos/curdeng): los julios urdidos en un mes con el costo que ya tienen
 * guardado en UrdProduccionUrdido (ImporteMP del kardex + lo que "Calcular Urdido" reparte, ver
 * App\Services\Costos\CostosJulio). Solo lectura: aquí no se recalcula nada.
 *
 * Los julios se agrupan por folio (orden de urdido) y se despliegan; los indicadores y el cuadre
 * contra la sábana de la cuota real son del mes completo, la tabla respeta búsqueda y máquina.
 * Arriba va el veredicto del mes (veredicto()): si la conversión cuadra con la sábana.
 */
class CostoJulios extends Component
{
    /** Columnas de costo de conversión de cada julio, con el nombre que ve el contador. */
    public const CONCEPTOS = [
        'MOD' => 'Mano de obra directa', 'MOI' => 'Mano de obra indirecta', 'GtsV' => 'Gastos variables',
        'GtsF' => 'Gastos fijos', 'Pf' => 'Prorrateo fijo', 'PV' => 'Prorrateo variable', 'Maquila' => 'Maquila',
    ];

    /** Costos fijos: el costeo directo no los toma (igual que CosCuota::FIJOS en las cuotas). */
    public const FIJOS = ['GtsF', 'Pf'];

    /** Máquina como se ve en pantalla => prefijo de sus columnas de consumo en el kardex (MC1KG, MC1IMP…). */
    public const KARDEX = ['Mc Coy 1' => 'MC1', 'Mc Coy 2' => 'MC2', 'Mc Coy 3' => 'MC3', 'Karl Mayer' => 'KM'];

    /** Sábana de la cuota real => columna del julio: Σ julios del mes = Sab (CostosJulio). */
    public const SABANA = ['SabMO' => 'MOD', 'SabMOI' => 'MOI', 'SabGtosVariable' => 'GtsV', 'SabGtosFijos' => 'GtsF'];

    /** Método de costeo: 'directo' deja fuera los fijos. Mismo switch del navbar que Cuotas y la Hoja de costos. */
    #[Url(except: 'absorbente')]
    public string $costeo = 'absorbente';

    #[Url(as: 'julios_anio')]
    public int $anio = 0;

    #[Url(as: 'julios_mes')]
    public int $mes = 0;

    #[Url(as: 'julios_buscar', except: '')]
    public string $buscar = '';

    #[Url(as: 'julios_maquina', except: '')]
    public string $maquina = '';

    /** @var array<int, array<string, array{kg: float, imp: float}>|null> kardex por mes, leído una vez por petición */
    private array $kardexMes = [];

    /** @var array{desde?: string, hasta?: string}|null diálogo "Exportar" abierto; null = cerrado */
    public ?array $rangoExcel = null;

    public function mount(): void
    {
        abort_unless(userCan('acceso', Cuotas::MODULO), 403, 'No tienes acceso a las cuotas de costos.');

        // Lo que no venga en la URL (o venga mal) toma el último mes cerrado; uno no reinicia al otro.
        $previo = now()->subMonth();
        if ($this->anio < 2000) {
            $this->anio = $previo->year;
        }
        if ($this->mes < 1 || $this->mes > 12) {
            $this->mes = $previo->month;
        }
    }

    public function updatedAnio(): void
    {
        $this->maquina = '';
    }

    public function updatedMes(): void
    {
        $this->maquina = '';
    }

    /** Clic en una máquina del resumen: filtra la tabla; otro clic la suelta. */
    public function filtrarMaquina(string $maquina): void
    {
        $this->maquina = $this->maquina === $maquina ? '' : $maquina;
    }

    public function limpiarFiltros(): void
    {
        $this->buscar = '';
        $this->maquina = '';
    }

    /**
     * Veredicto del mes, lo primero que ve el contador. Conversión: Σ julios contra la sábana, con $5 de
     * tolerancia por redondeo. Julios sin kg: el mes aún no se captura completo.
     * estado: 'ok' | 'fuera' | 'sin' (no hay con qué comparar).
     *
     * @param  array{julios: float, sab: float}|null  $sabana  null = no hay cuota real del mes
     * @return list<array{estado: string, titulo: string, detalle: string}>
     */
    public static function veredicto(?array $sabana, int $sinCosto, int $sinKg = 0): array
    {
        $pesos = fn (float $v): string => ($v < 0 ? '-$' : '$').number_format(abs($v), 0);
        $puntos = [];

        if ($sinKg > 0) {
            $puntos[] = ['estado' => 'fuera', 'titulo' => number_format($sinKg).' julios sin kg capturados',
                'detalle' => 'Sin kg no entran al costo por kg; si el mes sigue abierto, los números aún no son finales.'];
        }

        if ($sabana === null) {
            $puntos[] = ['estado' => 'sin', 'titulo' => 'Sin cuota real del mes', 'detalle' => 'No hay sábana de AX con qué cuadrar la conversión.'];
        } else {
            $dif = $sabana['julios'] - $sabana['sab'];
            $puntos[] = abs($dif) < 5
                ? ['estado' => 'ok', 'titulo' => 'La conversión cuadra con la sábana',
                    'detalle' => sprintf('Julios %s contra sábana de AX %s.', $pesos($sabana['julios']), $pesos($sabana['sab']))]
                : ['estado' => 'fuera', 'titulo' => 'La conversión no cuadra con la sábana',
                    'detalle' => sprintf('Julios %s contra sábana de AX %s: diferencia de %s. Revisa Calcular Urdido del mes en Cuotas.',
                        $pesos($sabana['julios']), $pesos($sabana['sab']), $pesos($dif))];
        }

        if ($sinCosto > 0) {
            $puntos[] = ['estado' => 'fuera', 'titulo' => number_format($sinCosto).' julios sin costo',
                'detalle' => 'Aún no los costea Calcular Urdido (Cuotas): no suman al costo del mes.'];
        }

        return $puntos;
    }

    /**
     * Julios del mes, uno por renglón, con los datos del folio (programa) y su costo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function julios(?int $desdeMes = null, ?int $hastaMes = null): Collection
    {
        $desde = sprintf('%04d-%02d-01', $this->anio, $desdeMes ?? $this->mes);
        $hasta = date('Y-m-t', (int) strtotime(sprintf('%04d-%02d-01', $this->anio, $hastaMes ?? $desdeMes ?? $this->mes)));

        $paros = $this->parosPorJulio($desdeMes ?? $this->mes, $hastaMes ?? $desdeMes ?? $this->mes);

        $filas = UrdProduccionUrdido::query()
            ->with('programa:Folio,MaquinaId,Cuenta,Calibre,Fibra,RizoPie')
            ->whereBetween('Fecha', [$desde, $hasta])
            ->orderBy('Fecha')->orderBy('Folio')->orderBy('HoraInicial')
            ->get(['Id', 'Folio', 'Fecha', 'NoJulio', 'Hilos', 'KgNeto', 'HoraInicial', 'HoraFinal', 'ImporteMP', ...array_keys(self::CONCEPTOS)])
            ->map(fn (UrdProduccionUrdido $j): array => $this->renglon($j, $paros[(int) $j->Id] ?? 0.0));

        // MP real: la de fórmula (guardada) se reescala al importe del kardex de AX, que incluye la merma.
        $meses = range($desdeMes ?? $this->mes, $hastaMes ?? $desdeMes ?? $this->mes);

        return self::ajustarAlKardex($filas, array_combine($meses, array_map(fn ($m) => $this->kardex($m), $meses)));
    }

    /**
     * MP según kardex: por mes y máquina, factor = importe del kardex ÷ Σ MP de fórmula de sus julios, y cada
     * julio queda con su MP × factor. Mes o máquina sin kardex (o sin MP de fórmula) se quedan como estaban.
     * 'mpFormula' conserva la MP original para comparar contra el kardex (la merma).
     *
     * @param  Collection<int, array<string, mixed>>  $julios  con 'mes', 'maquina' y 'mp'
     * @param  array<int, array<string, array{kg: float, imp: float}>|null>  $kardexPorMes
     * @return Collection<int, array<string, mixed>>
     */
    public static function ajustarAlKardex(Collection $julios, array $kardexPorMes): Collection
    {
        $formula = $julios->groupBy(fn ($j) => $j['mes'].'|'.$j['maquina'])->map(fn ($l) => (float) $l->sum('mp'));

        /** @var Collection<int, array<string, mixed>> $ajustados */
        $ajustados = $julios->map(function (array $j) use ($formula, $kardexPorMes): array {
            $j['mpFormula'] = $j['mp'];
            $kardex = $kardexPorMes[$j['mes']][$j['maquina']]['imp'] ?? null;
            $base = $formula[$j['mes'].'|'.$j['maquina']] ?? 0.0;
            if ($kardex !== null && $base > 0 && $j['mp'] !== null) {
                $j['mp'] = $j['mp'] * $kardex / $base;
            }

            return $j;
        });

        return $ajustados;
    }

    /**
     * Minutos de paro de su máquina que cayeron dentro de cada julio, con la misma regla que el cálculo
     * (CuotasUrdidoService::paros, sin Tiempo Muerto, y CostosJulio::paroDurante).
     *
     * @return array<int, float> id del julio => minutos
     */
    private function parosPorJulio(int $desdeMes, int $hastaMes): array
    {
        $servicio = app(CuotasUrdidoService::class);
        $minutos = [];
        foreach (range($desdeMes, $hastaMes) as $mes) {
            $paros = $servicio->paros($this->anio, $mes);
            foreach ($servicio->julios($this->anio, $mes) as $j) {
                $minutos[$j['id']] = CostosJulio::paroDurante($j, $paros[$j['maquina']] ?? []);
            }
        }

        return $minutos;
    }

    /** @return array<string, mixed> */
    private function renglon(UrdProduccionUrdido $j, float $paro): array
    {
        $p = $j->programa;
        $fila = [
            'id' => (int) $j->Id,
            'folio' => trim((string) $j->Folio),
            'fecha' => $j->Fecha ? date('d/m/Y', (int) strtotime((string) $j->Fecha)) : '',
            'mes' => $j->Fecha ? (int) date('n', (int) strtotime((string) $j->Fecha)) : $this->mes,
            'julio' => trim((string) $j->NoJulio),
            'hilos' => $j->Hilos,
            'horario' => $j->HoraInicial ? substr((string) $j->HoraInicial, 0, 5).'–'.substr((string) $j->HoraFinal, 0, 5) : '',
            'minutos' => CuotasUrdidoService::duracion($j->HoraInicial, $j->HoraFinal),
            'paro' => $paro,
            // El programa trae "MC Coy 3" y "Mc Coy 3": misma máquina.
            'maquina' => preg_replace('/^mc\s*coy\s*/i', 'Mc Coy ', trim((string) $p?->MaquinaId)) ?: 'Sin máquina',
            'articulo' => trim(implode(' · ', array_filter([$p?->Cuenta, $p?->Calibre, $p?->Fibra]))),
            // Karl Mayer guarda la barra (1-4) donde las Mc Coy guardan Rizo/Pie.
            'rizoPie' => ctype_digit($rp = trim((string) $p?->RizoPie)) ? 'Barra '.$rp : $rp,
            'kg' => $j->KgNeto === null ? null : (float) $j->KgNeto,
            'mp' => $j->ImporteMP === null ? null : (float) $j->ImporteMP,
            'sinCosto' => $j->getAttribute('MOD') === null,
        ];
        foreach (array_keys(self::CONCEPTOS) as $col) {
            $fila[$col] = $j->getAttribute($col) === null ? null : (float) $j->getAttribute($col);
        }

        return $fila;
    }

    /**
     * Conceptos que se muestran: los que tienen importe en el mes y, en directo, sin los fijos.
     *
     * @return array<string, string>
     */
    private function conceptosVisibles(Collection $julios): array
    {
        return array_filter(
            self::CONCEPTOS,
            fn (string $col): bool => ! ($this->costeo === 'directo' && in_array($col, self::FIJOS, true))
                && abs((float) $julios->sum($col)) > 0.0001,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Suma de un grupo de julios: kg, MP, cada concepto visible, conversión, total y $/kg.
     *
     * @param  array<string, string>  $conceptos
     * @return array<string, mixed>
     */
    public static function totales(Collection $julios, array $conceptos): array
    {
        $t = ['julios' => $julios->count(), 'minutos' => (float) $julios->sum('minutos'), 'paro' => (float) $julios->sum('paro'), 'kg' => (float) $julios->sum('kg'), 'mp' => (float) $julios->sum('mp')];
        foreach (array_keys($conceptos) as $col) {
            $t[$col] = (float) $julios->sum($col);
        }
        $t['conversion'] = array_sum(array_map(fn ($col) => $t[$col], array_keys($conceptos)));
        $t['total'] = $t['mp'] + $t['conversion'];
        foreach (['mp', 'conversion', 'total'] as $campo) {
            $t[$campo.'Kg'] = $t['kg'] > 0 ? $t[$campo] / $t['kg'] : null;
        }

        return $t;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function filtrados(Collection $julios): Collection
    {
        $termino = mb_strtolower(trim($this->buscar));

        return $julios
            ->when($this->maquina !== '', fn ($c) => $c->where('maquina', $this->maquina))
            ->when($termino !== '', fn ($c) => $c->filter(fn ($j) => str_contains(
                mb_strtolower($j['folio'].' '.$j['julio'].' '.$j['articulo'].' '.$j['rizoPie'].' '.$j['maquina']),
                $termino,
            )));
    }

    /**
     * Meses del año que ya tienen costo (al menos un julio costeado por Calcular Urdido), con sus julios.
     *
     * @return array<int, array{julios: int, sinCosto: int}>
     */
    private function meses(): array
    {
        // Conteo agrupado por mes: el builder no expresa MONTH()/CASE sin raw.
        return UrdProduccionUrdido::query()
            ->whereBetween('Fecha', [$this->anio.'-01-01', $this->anio.'-12-31'])
            ->selectRaw('MONTH(Fecha) AS mes, COUNT(*) AS julios, SUM(CASE WHEN MOD IS NULL THEN 1 ELSE 0 END) AS sinCosto')
            ->groupByRaw('MONTH(Fecha)')
            ->orderByRaw('MONTH(Fecha)')
            ->toBase()->get()
            ->filter(fn ($m) => (int) $m->julios > (int) $m->sinCosto)
            ->mapWithKeys(fn ($m) => [(int) $m->mes => ['julios' => (int) $m->julios, 'sinCosto' => (int) $m->sinCosto]])
            ->all();
    }

    /**
     * Σ julios contra la sábana de la cuota real del mes, por concepto. null = no hay cuota real.
     *
     * @return array<string, array{sab: float, julios: float}>|null
     */
    private function cuadre(Collection $julios): ?array
    {
        $cuota = CosCuotasReal::existente(CuotasUrdidoService::DEPTO, $this->anio, $this->mes);
        if ($cuota === null) {
            return null;
        }

        $cuadre = [];
        foreach (self::SABANA as $sab => $col) {
            if ($this->costeo === 'directo' && in_array($col, self::FIJOS, true)) {
                continue; // en directo los fijos no se cobran a los julios: tampoco se cuadran
            }
            $cuadre[self::CONCEPTOS[$col]] = ['sab' => (float) $cuota->getAttribute($sab), 'julios' => (float) $julios->sum($col)];
        }

        return $cuadre;
    }

    /**
     * Consumo de MP de cada máquina en el mes según el kardex de AX (hilo que salió del almacén), para
     * cuadrarlo contra la MP de los julios. Todos los tamaños: medianos y cabezuelas son devoluciones.
     * null = el kardex aún no tiene el mes o no se pudo leer (servidor .24); la pantalla sigue sin el cuadre.
     *
     * @return array<string, array{kg: float, imp: float}>|null máquina => kg e importe consumidos
     */
    private function kardex(?int $mes = null): ?array
    {
        $mes ??= $this->mes;

        return array_key_exists($mes, $this->kardexMes) ? $this->kardexMes[$mes] : $this->kardexMes[$mes] = $this->leerKardex($mes);
    }

    /** @return array<string, array{kg: float, imp: float}>|null */
    private function leerKardex(int $mes): ?array
    {
        // Una suma por columna de máquina: el builder no la expresa sin raw. Prefijos fijos, sin datos del usuario.
        $fila = rescue(fn () => InvKardexMPConsol::query()
            ->where('YEARDATE', $this->anio)->where('MONTHDATE', $mes)
            ->selectRaw(implode(', ', array_map(fn ($p) => "SUM({$p}KG) AS {$p}KG, SUM({$p}IMP) AS {$p}IMP", self::KARDEX)))
            ->toBase()->first());
        if ($fila === null || $fila->MC1KG === null) {
            return null;
        }

        return array_map(fn ($p) => ['kg' => (float) $fila->{$p.'KG'}, 'imp' => (float) $fila->{$p.'IMP'}], self::KARDEX);
    }

    /**
     * Composición de la LMAT de cada folio (dbo.Urdbom): materiales con su % y costo unitario (cump).
     * ponytail: un whereIn con los folios en pantalla (~150 al mes); si pasan de 2000, partir en lotes (límite de bindings).
     *
     * @param  list<string>  $folios
     * @return Collection<string, Collection<int, Urdbom>>
     */
    private function lmat(array $folios): Collection
    {
        return $folios === [] ? collect() : Urdbom::query()
            ->whereIn('Folio', $folios)
            ->orderBy('Id')
            ->get(['Folio', 'Lmat', 'Calibre', 'Config', 'Color', 'Porcentaje', 'cump'])
            ->groupBy(fn (Urdbom $m) => trim((string) $m->Folio));
    }

    public function abrirExportar(): void
    {
        $this->resetValidation();
        $this->rangoExcel = ['desde' => (string) $this->mes, 'hasta' => (string) $this->mes];
    }

    /**
     * Excel con un renglón por julio de los meses elegidos del año en pantalla (sin la búsqueda ni la
     * máquina), con todos los decimales para que el contador recalcule.
     */
    public function exportar(): BinaryFileResponse
    {
        $rango = $this->validate([
            'rangoExcel.desde' => ['required', 'integer', 'between:1,12'],
            'rangoExcel.hasta' => ['required', 'integer', 'between:1,12', 'gte:rangoExcel.desde'],
        ], ['rangoExcel.hasta.gte' => 'El mes final no puede ser antes del inicial.'])['rangoExcel'];
        [$desde, $hasta] = [(int) $rango['desde'], (int) $rango['hasta']];

        $julios = $this->julios($desde, $hasta);
        $nombre = sprintf('costo-julios-urdido-%04d-%02d%s.xlsx', $this->anio, $desde, $hasta > $desde ? sprintf('-a-%02d', $hasta) : '');
        $this->rangoExcel = null;

        return Excel::download(new CostoJuliosExport($julios, $this->conceptosVisibles($julios)), $nombre);
    }

    public function render(): View
    {
        // Un mes sin costo (de la URL o al cambiar de año) no se muestra: se pasa al último que sí tiene.
        $meses = $this->meses();
        if ($meses !== [] && ! isset($meses[$this->mes])) {
            $this->mes = max(array_keys($meses));
        }

        $julios = $this->julios();
        $conceptos = $this->conceptosVisibles($julios);
        $filtrados = $this->filtrados($julios);
        $porMaquina = $julios->groupBy('maquina')->sortKeys()->map(fn ($l) => self::totales($l, $conceptos));
        $folios = $filtrados->groupBy('folio')->map(fn ($l) => ['julios' => $l] + self::totales($l, $conceptos) + $l->first());
        $cuadre = $this->cuadre($julios);
        $kardex = $this->kardex();
        $sabana = $cuadre === null ? null : ['julios' => (float) array_sum(array_column($cuadre, 'julios')), 'sab' => (float) array_sum(array_column($cuadre, 'sab'))];

        return view('livewire.costos.costo-julios', [
            'conceptos' => $conceptos,
            'meses' => $meses,
            'nombreMes' => CosCuota::MESES[$this->mes] ?? '',
            'mesTotal' => self::totales($julios, $conceptos),
            'porMaquina' => $porMaquina,
            'folios' => $folios,
            'lmat' => $this->lmat($folios->keys()->map(fn ($f) => (string) $f)->all()),
            'filtroTotal' => self::totales($filtrados, $conceptos),
            'cuadre' => $cuadre,
            'kardex' => $kardex,
            'veredicto' => self::veredicto(
                $sabana,
                $meses[$this->mes]['sinCosto'] ?? 0,
                $julios->filter(fn ($j) => ! ($j['kg'] > 0))->count(),
            ),
            'anios' => range(now()->year, 2025),
        ]);
    }
}
