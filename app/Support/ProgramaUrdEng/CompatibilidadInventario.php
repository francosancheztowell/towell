<?php

declare(strict_types=1);

namespace App\Support\ProgramaUrdEng;

/**
 * Reglas de compatibilidad entre un telar y una pieza del inventario de TI-PRO.
 *
 * Vivian duplicadas: en JS dentro de reservar-programar.blade.php (matchLote,
 * matchCuenta, deriveInventBatchFromSerial, sameGroup) y en PHP dentro de
 * InventarioReservasService::ejecutarReserva(). Son negocio, no presentacion,
 * asi que la copia buena es esta y es la unica con test.
 *
 * Todo estatico y puro: sin BD, sin Eloquent, sin request.
 */
final class CompatibilidadInventario
{
    /** Tolerancia al comparar calibres (vienen como float desde SQL Server). */
    private const EPSILON = 1e-6;

    /**
     * El lote es el prefijo del numero de julio: '00061-744' -> '00061'.
     * Si el serial no trae guion, se conserva el lote que ya venia.
     */
    public static function loteDerivado(?string $serial, ?string $batch = null): string
    {
        $s = trim((string) $serial);

        if ($s === '' || ! str_contains($s, '-')) {
            return trim((string) $batch);
        }

        $prefijo = trim(explode('-', $s)[0]);

        return $prefijo !== '' ? $prefijo : trim((string) $batch);
    }

    /**
     * Un telar sin No. Orden acepta cualquier lote. Con No. Orden, tiene que
     * coincidir el InventBatchId de la pieza o el prefijo de su InventSerialId.
     */
    public static function coincideLote(?string $telarNoOrden, ?string $batch, ?string $serial): bool
    {
        $orden = trim((string) $telarNoOrden);
        if ($orden === '') {
            return true;
        }

        $batchLimpio = trim((string) $batch);

        return $batchLimpio === $orden || self::loteDerivado($serial, $batchLimpio) === $orden;
    }

    /**
     * La cuenta del telar es un prefijo del InventSizeId de la pieza:
     * cuenta '3156' casa con InventSizeId '3156-X'. Sin cuenta no hay match.
     */
    public static function coincideCuenta(?string $cuentaTelar, ?string $inventSizeId): bool
    {
        $cuenta = self::sinEspacios($cuentaTelar);
        if ($cuenta === '') {
            return false;
        }

        return str_starts_with(self::sinEspacios($inventSizeId), $cuenta);
    }

    /**
     * Dos telares se pueden programar juntos si comparten tipo, calibre y salon.
     * La cuenta puede variar: es la regla que ya aplicaba la seleccion multiple.
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public static function mismoGrupo(array $a, array $b): bool
    {
        return self::mismoTexto($a['tipo'] ?? null, $b['tipo'] ?? null)
            && self::mismoNumero($a['calibre'] ?? null, $b['calibre'] ?? null)
            && self::mismoTexto($a['salon'] ?? null, $b['salon'] ?? null);
    }

    public static function mismoTexto(mixed $a, mixed $b): bool
    {
        return mb_strtoupper(trim((string) $a), 'UTF-8') === mb_strtoupper(trim((string) $b), 'UTF-8');
    }

    public static function mismoNumero(mixed $a, mixed $b): bool
    {
        if (! is_numeric($a) || ! is_numeric($b)) {
            return (string) $a === (string) $b;
        }

        return abs((float) $a - (float) $b) < self::EPSILON;
    }

    private static function sinEspacios(?string $v): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/', '', (string) $v), 'UTF-8');
    }
}
