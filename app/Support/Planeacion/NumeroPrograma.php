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

    /**
     * Saldo = Pedido × (1 + %segundas/100) − Producción, sin negativos. Regla confirmada por el
     * owner (2026-10-10) para TODAS las pantallas: Dividir, Duplicar, Eliminar, Balancear y edición.
     */
    public static function saldoDesdePedido(float $pedido, float $produccion, mixed $porcSegundos): float
    {
        return max(0.0, round($pedido * self::factorSegundas($porcSegundos) - $produccion, 2));
    }

    /** Inverso de saldoDesdePedido: el pedido que deja ese saldo pendiente. */
    public static function pedidoDesdeSaldo(float $saldo, float $produccion, mixed $porcSegundos): float
    {
        return round(($saldo + $produccion) / self::factorSegundas($porcSegundos), 2);
    }

    private static function factorSegundas(mixed $porcSegundos): float
    {
        return 1 + max(0.0, self::sanitizeNumber($porcSegundos)) / 100;
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
