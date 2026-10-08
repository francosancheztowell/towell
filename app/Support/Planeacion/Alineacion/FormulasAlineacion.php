<?php

namespace App\Support\Planeacion\Alineacion;

use App\Models\Planeacion\Catalogos\CatCodificados;
use Carbon\Carbon;

/**
 * Fórmulas y formatos de la hoja ALINEACION (columnas AF, AH-AK, G), puras y sin consulta.
 * Las usa AlineacionItemsService para armar cada fila.
 */
final class FormulasAlineacion
{
    /**
     * Peso Min/Max: formulas AH/AI de la hoja ALINEACION.
     *   AH = IF(G="N", M/(1+3%), M/(1+0%))
     *   AI = IF(G="N", M*(1+3%), M*(1+5%))
     *
     * El minimo divide y el maximo multiplica: asi esta capturado en la hoja, no es simetrico.
     *
     * @return array{0: ''|int, 1: ''|int}
     */
    public static function rangoPeso(?CatCodificados $cat, mixed $pesoCrudo): array
    {
        $m = (float) ($pesoCrudo ?? 0);
        if ($m <= 0) {
            return ['', ''];
        }
        $esN = self::toleranciaEsN($cat);

        return [
            (int) round($esN ? $m / 1.03 : $m / 1.00),
            (int) round($esN ? $m * 1.03 : $m * 1.05),
        ];
    }

    /**
     * Muestra Min/Max: formulas AJ/AK de la hoja ALINEACION. Solo aplican cuando el
     * articulo se pesa por muestra ("Mu"); si se pesa la pieza completa ("To") van vacias.
     *   AJ = IF(AF="Mu", IF(G="N", AB*(1-2%), AB*(1+0%)), "")
     *   AK = IF(AG="Mu", IF(G="N", AB*(1+2%), AB*(1+4%)), "")
     *
     * @return array{0: ''|float, 1: ''|float}
     */
    public static function rangoMuestra(?CatCodificados $cat, mixed $pesoCrudo, mixed $ancho, mixed $largo): array
    {
        $ab = (float) ($cat?->getAttribute('PesoMuestra') ?? 0);
        if ($ab <= 0 || ! self::sePesaPorMuestra($pesoCrudo, $ancho, $largo)) {
            return ['', ''];
        }
        $esN = self::toleranciaEsN($cat);

        return [
            round($esN ? $ab * 0.98 : $ab * 1.00, 3),
            round($esN ? $ab * 1.02 : $ab * 1.04, 3),
        ];
    }

    /**
     * Columna AF de la hoja: "To" (se pesa la pieza completa) vs "Mu" (se pesa una muestra).
     *   AF = IF(M<=220,"To", IF(AC<=0.3,"To", IF(AE>=AC*1.2,"To","Mu")))   con AC = (Anc*Lar)/10000
     *
     * ponytail: la tercera condicion (AE, area de tiras) necesita el largo de tira que en la
     * hoja se captura a mano fila por fila (col. AD) y no existe en la base; se omite.
     */
    public static function sePesaPorMuestra(mixed $pesoCrudo, mixed $ancho, mixed $largo): bool
    {
        $m = (float) ($pesoCrudo ?? 0);
        if ($m <= 0 || $m <= 220) {
            return false;
        }
        $areaCrudo = ((float) ($ancho ?? 0) * (float) ($largo ?? 0)) / 10000;

        return $areaCrudo > 0.3;
    }

    /**
     * Columna G de la hoja: la tolerancia del catalogo es "N".
     */
    public static function toleranciaEsN(?CatCodificados $cat): bool
    {
        return trim((string) ($cat?->getAttribute('Tolerancia') ?? '')) === 'N';
    }

    /**
     * ¿El valor es un cero disfrazado? Cubre "0", "0.0", "0/0" y "0/0/0/0" (las cenefas
     * concatenan con diagonal). Un valor mixto como "0/ALG" no cuenta: ahí sí hay dato.
     */
    public static function esCeroSinDato(mixed $valor): bool
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return false;
        }

        foreach (preg_split('#\s*/\s*#', $texto) ?: [] as $parte) {
            if (! is_numeric($parte) || (float) $parte !== 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Primer valor con dato real (no null, no cadena vacia).
     */
    public static function primeroConDato(mixed ...$valores): mixed
    {
        foreach ($valores as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * Concatena Calibre/Fibra para Cenefa Trama (ReqProgramaTejido).
     */
    public static function concatCalibreFibra(mixed $calibre, mixed $fibra): string
    {
        $c = trim((string) ($calibre ?? ''));
        $f = trim((string) ($fibra ?? ''));
        if ($c === '' || $f === '') {
            return $c.$f;
        }

        return $c.'/'.$f;
    }

    /**
     * Formatea fecha/datetime en español para la vista Alineación.
     */
    public static function formatearFecha(mixed $value, string $format): string
    {
        try {
            return Carbon::parse($value)->locale('es')->translatedFormat($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
