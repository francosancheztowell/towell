<?php

declare(strict_types=1);

namespace App\Helpers;

final class TowellLogo
{
    public static function path(): ?string
    {
        $ruta = public_path('images/fondosTowell/logo.png');

        return is_file($ruta) ? $ruta : null;
    }
}
