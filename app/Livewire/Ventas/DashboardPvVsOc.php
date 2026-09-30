<?php

declare(strict_types=1);

namespace App\Livewire\Ventas;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Solo pinta el esqueleto: los datos los pide el navegador a VentasDatosController, así la
 * página no espera las consultas ni carga los datos incrustados en el HTML.
 */
class DashboardPvVsOc extends Component
{
    public function mount(): void
    {
        abort_unless(
            function_exists('userCan') && userCan('acceso', (string) config('ventas.permission_module')),
            403,
            'No tienes acceso al módulo de Ventas.'
        );
    }

    public function render(): View
    {
        return view('livewire.ventas.dashboard-pv-vs-oc');
    }
}
