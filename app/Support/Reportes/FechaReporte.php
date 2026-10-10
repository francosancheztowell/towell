<?php

declare(strict_types=1);

namespace App\Support\Reportes;

use Carbon\Carbon;

/** Fechas de los reportes Urdido/Engomado: Y-m-d o d/m/Y, siempre al inicio del día. */
final class FechaReporte
{
    public static function parse(string $value): Carbon
    {
        $value = trim($value);
        if ($value === '') {
            return Carbon::now()->startOfDay();
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Throwable) {
                // El otro formato (o Carbon::parse) puede leerlo.
            }
        }

        return Carbon::parse($value)->startOfDay();
    }
}
