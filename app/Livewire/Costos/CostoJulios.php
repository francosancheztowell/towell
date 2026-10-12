<?php

declare(strict_types=1);

namespace App\Livewire\Costos;

use App\Exports\CostoJuliosExport;
use App\Models\Costos\CosCuota;
use App\Models\Costos\CosCuotasReal;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Services\Costos\CuotasUrdidoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pestaña "Costo por julio" de Cuotas: los julios urdidos en un mes con el costo que ya tienen
 * guardado en UrdProduccionUrdido (ImporteMP del kardex + lo que "Calcular Urdido" reparte, ver
 * App\Services\Costos\CostosJulio). Solo lectura: aquí no se recalcula nada.
 *
 * Los julios se agrupan por folio (orden de urdido) y se despliegan; los indicadores y el cuadre
 * contra la sábana de la cuota real son del mes completo, la tabla respeta búsqueda y máquina.
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

    /** Sábana de la cuota real => columna del julio: Σ julios del mes = Sab (CostosJulio). */
    public const SABANA = ['SabMO' => 'MOD', 'SabMOI' => 'MOI', 'SabGtosVariable' => 'GtsV', 'SabGtosFijos' => 'GtsF'];

    #[Reactive]
    public string $costeo = 'absorbente';

    #[Url(as: 'julios_anio')]
    public int $anio = 0;

    #[Url(as: 'julios_mes')]
    public int $mes = 0;

    #[Url(as: 'julios_buscar', except: '')]
    public string $buscar = '';

    #[Url(as: 'julios_maquina', except: '')]
    public string $maquina = '';

    public function mount(): void
    {
        abort_unless(userCan('acceso', Cuotas::MODULO), 403, 'No tienes acceso a las cuotas de costos.');

        if ($this->anio < 2000 || $this->mes < 1 || $this->mes > 12) {
            $previo = now()->subMonth(); // el último mes cerrado
            [$this->anio, $this->mes] = [$previo->year, $previo->month];
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

    /**
     * Julios del mes, uno por renglón, con los datos del folio (programa) y su costo.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function julios(): Collection
    {
        $desde = sprintf('%04d-%02d-01', $this->anio, $this->mes);

        return UrdProduccionUrdido::query()
            ->with('programa:Folio,MaquinaId,Cuenta,Calibre,Fibra,RizoPie')
            ->whereBetween('Fecha', [$desde, date('Y-m-t', (int) strtotime($desde))])
            ->orderBy('Fecha')->orderBy('Folio')->orderBy('HoraInicial')
            ->get(['Id', 'Folio', 'Fecha', 'NoJulio', 'Hilos', 'KgNeto', 'HoraInicial', 'HoraFinal', 'ImporteMP', ...array_keys(self::CONCEPTOS)])
            ->map(fn (UrdProduccionUrdido $j): array => $this->renglon($j));
    }

    /** @return array<string, mixed> */
    private function renglon(UrdProduccionUrdido $j): array
    {
        $p = $j->programa;
        $fila = [
            'id' => (int) $j->Id,
            'folio' => trim((string) $j->Folio),
            'fecha' => $j->Fecha ? date('d/m/Y', (int) strtotime((string) $j->Fecha)) : '',
            'julio' => trim((string) $j->NoJulio),
            'hilos' => $j->Hilos,
            'horario' => $j->HoraInicial ? substr((string) $j->HoraInicial, 0, 5).'–'.substr((string) $j->HoraFinal, 0, 5) : '',
            'minutos' => CuotasUrdidoService::duracion($j->HoraInicial, $j->HoraFinal),
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
        $t = ['julios' => $julios->count(), 'kg' => (float) $julios->sum('kg'), 'mp' => (float) $julios->sum('mp')];
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
     * Julios por mes del año, para las pills: [mes => [julios, sin costo]].
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
            ->toBase()->get()
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
            $cuadre[self::CONCEPTOS[$col]] = ['sab' => (float) $cuota->getAttribute($sab), 'julios' => (float) $julios->sum($col)];
        }

        return $cuadre;
    }

    /** Excel con un renglón por julio (lo filtrado), con todos los decimales para que el contador recalcule. */
    public function exportar(): BinaryFileResponse
    {
        $conceptos = $this->conceptosVisibles($this->julios());
        $nombre = sprintf('costo-julios-urdido-%04d-%02d.xlsx', $this->anio, $this->mes);

        return Excel::download(new CostoJuliosExport($this->filtrados($this->julios()), $conceptos), $nombre);
    }

    public function render(): View
    {
        $julios = $this->julios();
        $conceptos = $this->conceptosVisibles($julios);
        $filtrados = $this->filtrados($julios);

        return view('livewire.costos.costo-julios', [
            'conceptos' => $conceptos,
            'meses' => $this->meses(),
            'nombreMes' => CosCuota::MESES[$this->mes] ?? '',
            'mesTotal' => self::totales($julios, $conceptos),
            'porMaquina' => $julios->groupBy('maquina')->sortKeys()->map(fn ($l) => self::totales($l, $conceptos)),
            'folios' => $filtrados->groupBy('folio')->map(fn ($l) => ['julios' => $l] + self::totales($l, $conceptos) + $l->first()),
            'filtroTotal' => self::totales($filtrados, $conceptos),
            'cuadre' => $this->cuadre($julios),
            'avisos' => [
                'sinCosto' => $julios->where('sinCosto', true)->count(),
                'sinMp' => $julios->filter(fn ($j) => ! $j['mp'])->count(),
                'sinKg' => $julios->filter(fn ($j) => ! $j['kg'])->count(),
            ],
            'anios' => range(now()->year, 2025),
        ]);
    }
}
