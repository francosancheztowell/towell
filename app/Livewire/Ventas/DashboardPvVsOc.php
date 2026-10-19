<?php

declare(strict_types=1);

namespace App\Livewire\Ventas;

use App\Services\Ventas\VentasDatosVersion;
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

    /** La versión de los datos va en las URLs de datos (?v=) para que el navegador no reutilice una anterior. */
    public function render(): View
    {
        return view('livewire.ventas.dashboard-pv-vs-oc', ['versionDatos' => app(VentasDatosVersion::class)->actual()]);
    }
}
