<?php

declare(strict_types=1);

namespace App\Services\Planeacion\ProgramaTejido;

/**
 * Canary del shell Livewire de Programa Tejido / Muestras (PT 03 · PT-UI-01). Mismo idiom
 * que las mutaciones v2: PLANEACION_SHELL_V2 = off (default) | canary | on y
 * PLANEACION_SHELL_V2_CANARY = Id de usuarios. Apagado, index() sirve la vista legacy tal cual
 * (sin ningún asset v2 ni Livewire). Rollback: 'off' + config:clear.
 */
final class ShellV2
{
    public static function activo(): bool
    {
        return MutacionesV2::modoActivo(config('planeacion.shell_v2.modo', 'off'), config('planeacion.shell_v2.usuarios_canary', []));
    }
}
