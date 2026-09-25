<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

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
    }
}
