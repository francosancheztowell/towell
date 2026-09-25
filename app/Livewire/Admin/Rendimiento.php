<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\SoloAdmin;
use App\Services\Monitoreo\PanelConsultas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /admin/rendimiento — p50/p95 de ServidorMs y CargaMs por ruta, últimos 7 días
 * contra los 7 previos (MON-26). Se calcula en SQL (PanelConsultas::percentiles) y
 * se guarda 5 minutos en cache: el rango completo de 14 días es la consulta más cara del panel.
 */
class Rendimiento extends Component
{
    use SoloAdmin;

    public const CACHE = 'mon:panel:rendimiento';

    #[Url(except: '')]
    public string $buscar = '';

    #[Url(except: false)]
    public bool $soloLentas = false;

    public function recalcular(): void
    {
        Cache::forget(self::CACHE);
    }

    public function render(): View
    {
        $datos = Cache::remember(self::CACHE, 300, fn (): array => $this->calcular());
        $umbrales = [
            'ServidorMs' => (int) config('monitoreo.umbrales.servidor_ms', 800),
            'CargaMs' => (int) config('monitoreo.umbrales.carga_ms', 3000),
        ];

        $filas = collect($datos['rutas'])
            ->map(function (array $fila) use ($umbrales): array {
                $fila['lenta'] = ($fila['ServidorMs']['actual']['p95'] ?? 0) > $umbrales['ServidorMs']
                    || ($fila['CargaMs']['actual']['p95'] ?? 0) > $umbrales['CargaMs'];

                return $fila;
            })
            ->when(trim($this->buscar) !== '', fn ($c) => $c->filter(fn (array $f) => str_contains(mb_strtolower($f['ruta']), mb_strtolower(trim($this->buscar)))))
            ->when($this->soloLentas, fn ($c) => $c->where('lenta', true))
            ->sortByDesc(fn (array $f) => max($f['ServidorMs']['actual']['p95'] ?? 0, intdiv($f['CargaMs']['actual']['p95'] ?? 0, 4)))
            ->values();

        return view('livewire.admin.rendimiento', [
            'filas' => $filas,
            'umbrales' => $umbrales,
            'calculadoEn' => $datos['calculadoEn'],
            'pulse' => (bool) config('pulse.enabled'),
        ]);
    }

    /** @return array{calculadoEn: string, rutas: array<string, array<string, mixed>>} */
    private function calcular(): array
    {
        $hoy = now();
        $semana = [$hoy->copy()->subDays(7), $hoy];
        $previa = [$hoy->copy()->subDays(14), $hoy->copy()->subDays(7)];

        $rutas = [];
        foreach (PanelConsultas::METRICAS as $metrica) {
            foreach (['actual' => $semana, 'previa' => $previa] as $periodo => [$desde, $hasta]) {
                foreach (PanelConsultas::percentiles($metrica, $desde, $hasta) as $ruta => $valores) {
                    $rutas[$ruta]['ruta'] = $ruta;
                    $rutas[$ruta][$metrica][$periodo] = $valores;
                }
            }
        }

        foreach ($rutas as $ruta => $fila) {
            $rutas[$ruta] += ['ServidorMs' => [], 'CargaMs' => []];
            // Solo rutas con datos en la semana actual.
            if (! isset($fila['ServidorMs']['actual']) && ! isset($fila['CargaMs']['actual'])) {
                unset($rutas[$ruta]);
            }
        }

        return ['calculadoEn' => $hoy->format('d/m/Y H:i'), 'rutas' => $rutas];
    }
}
