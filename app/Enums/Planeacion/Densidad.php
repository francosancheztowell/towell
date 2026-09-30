<?php

declare(strict_types=1);

namespace App\Enums\Planeacion;

/**
 * Densidad de los estándares de Eficiencia y Velocidad (ReqEficienciaStd / ReqVelocidadStd).
 * Mismo texto que la columna. Sin cast en los modelos a propósito: los imports aceptan texto libre
 * de 10 caracteres y no hay un SELECT DISTINCT de producción que garantice que solo existen estos
 * dos valores (runbook en 19-06b-SUMMARY); un valor fuera del enum rompería la lectura.
 */
enum Densidad: string
{
    case Normal = 'Normal';
    case Alta = 'Alta';

    /** Calibre de trama por encima del cual el programa usa el estándar de densidad Alta. */
    public const CALIBRE_TRAMA_ALTA = 40;

    /** Densidad que corresponde a un programa de tejido según su calibre de trama. */
    public static function deCalibreTrama(mixed $calibreTrama): self
    {
        return $calibreTrama !== null && (float) $calibreTrama > self::CALIBRE_TRAMA_ALTA ? self::Alta : self::Normal;
    }

    /** Valor enviado (o guardado) → densidad; vacío = Normal, como siempre. */
    public static function deTexto(?string $valor): string
    {
        return $valor === null || trim($valor) === '' ? self::Normal->value : $valor;
    }
}
