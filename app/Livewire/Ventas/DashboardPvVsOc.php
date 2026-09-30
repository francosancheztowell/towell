<?php

declare(strict_types=1);

namespace App\Livewire\Ventas;

use App\Services\Ventas\PvVsOcPayloadBuilder;
use App\Services\Ventas\VentasHistoricasPayloadBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Throwable;

class DashboardPvVsOc extends Component
{
    /**
     * [fresco, vencido] en segundos: los primeros 15 min se sirve tal cual; después y hasta 24 h se
     * sirve el valor anterior al instante y se reconstruye tras enviar la respuesta. Así nadie espera
     * los ~4 s que tardan las consultas en frío (tablas heap sin índices en SQL Server 2008 R2).
     */
    private const CACHE_TTL = [15 * 60, 24 * 60 * 60];

    public ?string $dataError = null;

    public function mount(): void
    {
        abort_unless(
            function_exists('userCan') && userCan('acceso', (string) config('ventas.permission_module')),
            403,
            'No tienes acceso al módulo de Ventas.'
        );
    }

    public function render(PvVsOcPayloadBuilder $builder, VentasHistoricasPayloadBuilder $historicoBuilder): View
    {
        $dashboard = null;
        $historico = null;
        $historicoError = null;

        try {
            // TwHistoricosVentas no tiene índices: agrupar las ~89k facturas tarda ~2.5 s.
            $historico = Cache::flexible(
                'ventas:historico:payload',
                self::CACHE_TTL,
                fn (): array => $historicoBuilder->build(),
            );
        } catch (Throwable $e) {
            report($e);
            $historicoError = 'No se pudieron cargar las ventas históricas. Intenta nuevamente en unos minutos.';
        }

        try {
            // Todos los años (~260k filas, ~10 s en frío): el filtro de año se aplica en el navegador.
            $dashboard = Cache::flexible(
                'ventas:pvoc:payload:todos',
                self::CACHE_TTL,
                fn (): string => $builder->build(),
            );
        } catch (Throwable $e) {
            report($e);
            $this->dataError = 'No se pudo cargar la información de Ventas. Intenta nuevamente en unos minutos.';
        }

        return view('livewire.ventas.dashboard-pv-vs-oc', [
            'dashboard' => $dashboard,
            'historico' => $historico,
            'historicoError' => $historicoError,
        ]);
    }
}
