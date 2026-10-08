<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqProgramaTejido;

/**
 * Lectura tolerante de campos del programa para las fórmulas de línea diaria: toma el primer
 * candidato con valor (no null ni '') y lo convierte. Algunas fórmulas prueban varios nombres
 * (p. ej. PasadasComb1 / Pasadas_C1 / PASADAS_C1).
 */
final class CampoPrograma
{
    /**
     * Primer campo candidato con valor, como número (0.0 si no es numérico o no hay ninguno).
     *
     * @param  list<string>  $candidates
     */
    public static function numero(ReqProgramaTejido $programa, array $candidates): float
    {
        $val = self::primerValor($programa, $candidates);

        return ($val !== null && is_numeric($val)) ? (float) $val : 0.0;
    }

    /** @param  list<string>  $candidates */
    public static function texto(ReqProgramaTejido $programa, array $candidates): string
    {
        return (string) (self::primerValor($programa, $candidates) ?? '');
    }

    /** @param  list<string>  $candidates */
    private static function primerValor(ReqProgramaTejido $programa, array $candidates): mixed
    {
        foreach ($candidates as $c) {
            if (isset($programa->{$c}) && $programa->{$c} !== '') {
                return $programa->{$c};
            }
        }

        return null;
    }
}
