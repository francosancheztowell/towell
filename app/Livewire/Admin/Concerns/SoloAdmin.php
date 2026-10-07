<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Panel /admin: solo área Sistemas (Gate `admin`). Livewire llama boot{Trait}() en
 * cada request —el montaje y cada update—, así que una llamada directa a
 * /livewire/update tampoco se salta la autorización de la ruta.
 */
trait SoloAdmin
{
    public function bootSoloAdmin(): void
    {
        Gate::authorize('admin');
        // "hace 3 min" y no "3 minutes ago": Carbon no toma APP_LOCALE solo. Aquí y no global
        // para no cambiar textos del resto de la app sin revisarlos.
        Carbon::setLocale('es');
    }
}
