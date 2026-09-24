<?php

namespace App\Services\Monitoreo;

use Laravel\Pulse\Facades\Pulse;

/**
 * Conteo de accesos a URLs de legado (ADOP-01, se usa desde la Ola 2 en los
 * redirects 301). Con Pulse apagado no hace nada. Nunca lanza.
 */
final class Legado
{
    public const TIPO = 'legado';

    public static function registrar(string $uri): void
    {
        if (! config('pulse.enabled')) {
            return;
        }

        Monitoreo::seguro('registrar legado', function () use ($uri): void {
            Pulse::record(self::TIPO, '/'.ltrim(mb_substr($uri, 0, 300), '/'))->count();
        });
    }
}
