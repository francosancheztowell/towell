<?php

declare(strict_types=1);

namespace App\Support\Crudo;

use Illuminate\Support\Str;

final class CrudoSalon
{
    public static function isJacquard(?string $salon): bool
    {
        return str_contains(Str::upper(trim((string) $salon)), 'JAC');
    }
}
