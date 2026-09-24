<?php

namespace App\Services\Monitoreo;

use Illuminate\Support\Str;

/**
 * Gate `admin` (contrato §1.6): SYSUsuario.area normalizada ∈ monitoreo.areas_admin.
 */
final class AccesoAdmin
{
    public static function permite(mixed $usuario): bool
    {
        $area = self::normalizar(is_object($usuario) ? ($usuario->area ?? null) : null);

        if ($area === '') {
            return false;
        }

        $permitidas = array_map(self::normalizar(...), (array) config('monitoreo.areas_admin', ['Sistemas']));

        return in_array($area, $permitidas, true);
    }

    /** trim, sin acentos, minúsculas. */
    public static function normalizar(mixed $valor): string
    {
        return Str::lower(trim(Str::ascii((string) ($valor ?? ''))));
    }
}
