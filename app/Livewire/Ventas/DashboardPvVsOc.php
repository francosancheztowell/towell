<?php

declare(strict_types=1);

namespace App\Livewire\Ventas;

use App\Services\Ventas\PvVsOcMockPayload;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardPvVsOc extends Component
{
    public ?string $dataError = null;

    public function mount(): void
    {
        abort_unless(
            function_exists('userCan') && userCan('acceso', (string) config('ventas.permission_module')),
            403,
            'No tienes acceso al mÃ³dulo de Ventas.'
        );
    }

    public function render(PvVsOcMockPayload $payload): View
    {
        return view('livewire.ventas.dashboard-pv-vs-oc', [
            'dashboard' => $payload->build(),
        ]);
    }
}
