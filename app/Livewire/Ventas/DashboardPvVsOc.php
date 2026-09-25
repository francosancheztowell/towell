<?php

declare(strict_types=1);

namespace App\Livewire\Ventas;

use App\Models\Ventas\TwHistVtasModel;
use App\Services\Ventas\PvVsOcPayloadBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Throwable;

class DashboardPvVsOc extends Component
{
    private const CACHE_MINUTOS = 15;

    public int $anio;

    /** @var list<int> */
    public array $aniosDisponibles = [];

    public ?string $dataError = null;

    public function mount(): void
    {
        abort_unless(
            function_exists('userCan') && userCan('acceso', (string) config('ventas.permission_module')),
            403,
            'No tienes acceso al módulo de Ventas.'
        );

        $this->aniosDisponibles = $this->cargarAniosDisponibles();
        $this->anio = $this->aniosDisponibles[array_key_last($this->aniosDisponibles)] ?? (int) now()->year;
    }

    public function render(PvVsOcPayloadBuilder $builder): View
    {
        $dashboard = null;

        try {
            // Son ~40k filas históricas por año: reconstruirlas en cada visita tardaba varios segundos.
            $dashboard = Cache::remember(
                "ventas:pvoc:payload:{$this->anio}",
                now()->addMinutes(self::CACHE_MINUTOS),
                fn (): string => $builder->build($this->anio),
            );
        } catch (Throwable $e) {
            report($e);
            $this->dataError = "No se pudo cargar la información de Ventas para {$this->anio}. Intenta nuevamente en unos minutos.";
        }

        return view('livewire.ventas.dashboard-pv-vs-oc', [
            'dashboard' => $dashboard,
            'anios' => $this->aniosDisponibles,
        ]);
    }

    /**
     * TwHistoricosVentas es la fuente con más historia; alcanza para poblar el selector de año.
     *
     * @return list<int>
     */
    private function cargarAniosDisponibles(): array
    {
        return Cache::remember('ventas:pvoc:anios', now()->addMinutes(self::CACHE_MINUTOS), static fn (): array => TwHistVtasModel::query()
            ->select('ANIO')
            ->distinct()
            ->orderBy('ANIO')
            ->pluck('ANIO')
            ->map(static fn ($anio) => (int) $anio)
            ->all());
    }
}
