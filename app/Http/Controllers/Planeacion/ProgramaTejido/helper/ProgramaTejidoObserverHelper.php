<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;

/**
 * @file ProgramaTejidoObserverHelper.php
 * @description Encapsula el patrón unset/observe de ReqProgramaTejido. Útil cuando se ejecutan
 *              transacciones que no deben disparar el observer (ej. saveQuietly masivo).
 * @dependencies ReqProgramaTejido, ReqProgramaTejidoObserver
 */
class ProgramaTejidoObserverHelper
{
    /**
     * Ejecuta el callable sin el observer activo. Restaura el observer al terminar.
     *
     * @param callable $fn Función a ejecutar (puede retornar valor)
     * @return mixed Valor retornado por el callable
     */
    public static function withoutObserver(callable $fn): mixed
    {
        $dispatcher = ReqProgramaTejido::getEventDispatcher();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            return $fn();
        } finally {
            if ($dispatcher !== null) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            } else {
                ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            }
        }
    }
}
