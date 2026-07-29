<?php

declare(strict_types=1);

namespace App\Services\Crudo;

final class CrudoAccess
{
    public function authorize(): void
    {
        $module = trim((string) config('crudo.permission_module', ''));

        if ($module === '') {
            return;
        }

        abort_unless(
            function_exists('userCan') && userCan('acceso', $module),
            403,
            'No tienes acceso al módulo de Crudo.',
        );
    }
}
