<?php

declare(strict_types=1);

namespace App\Services\Crudo;

final class CrudoAccess
{
    public function authorize(): void
    {
        abort_unless($this->canAccess(), 403, 'No tienes acceso al módulo de Andon.');
    }

    public function canAccess(): bool
    {
        return function_exists('userCan') && userCan('acceso', $this->permissionModule());
    }

    public function canRegister(): bool
    {
        return function_exists('userCan')
            && userCan('registrar', $this->permissionModule());
    }

    public function authorizeRegister(): void
    {
        abort_unless(
            $this->canRegister(),
            403,
            'No tienes permiso para registrar auditorías de Andon.',
        );
    }

    private function permissionModule(): string
    {
        $module = trim((string) config('crudo.permission_module', ''));

        // ponytail: el módulo se llama "Andon" en SYSRoles (antes "Crudo").
        return $module !== '' ? $module : 'Andon';
    }
}
