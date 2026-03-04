<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Models\Planeacion\ReqProgramaTejido;

/**
 * @file OrdCompartidaHelper.php
 * @description Helper para operaciones con OrdCompartida. Proporciona métodos para obtener
 *              nuevos OrdCompartida disponibles verificando que no estén en uso.
 * @dependencies ReqProgramaTejido
 * @relatedFiles DuplicarTejido.php, VincularTejido.php, DividirTejido.php
 */
class OrdCompartidaHelper
{
    /**
     * Obtiene un nuevo OrdCompartida disponible verificando que no esté en uso.
     * Útil al crear grupos de registros vinculados (duplicar con vincular, vincular existentes).
     *
     * @return int Siguiente OrdCompartida disponible
     */
    public static function obtenerNuevoOrdCompartidaDisponible(): int
    {
        $maxOrdCompartida = ReqProgramaTejido::max('OrdCompartida') ?? 0;
        $candidato = $maxOrdCompartida + 1;

        $intentos = 0;
        $maxIntentos = 1000;

        while ($intentos < $maxIntentos) {
            $existe = ReqProgramaTejido::where('OrdCompartida', $candidato)->exists();

            if (!$existe) {
                return $candidato;
            }

            $candidato++;
            $intentos++;
        }

        return $candidato;
    }
}
