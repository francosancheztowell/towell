<?php

namespace App\Support\Planeacion;

/**
 * Números capturados en el programa de tejido: la coma es separador de MILES (no decimal) y
 * los espacios se ignoran. "1,000" → 1000.0.
 */
final class NumeroPrograma
{
    /** Lo no numérico (y null) vale 0.0. */
    public static function sanitizeNumber($value): float
    {
        return self::sanitizeNullableNumber($value) ?? 0.0;
    }

    /** Null, '' y lo no numérico valen null. */
    public static function sanitizeNullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = str_replace([',', ' '], '', (string) $value);

        return is_numeric($clean) ? (float) $clean : null;
    }
}
