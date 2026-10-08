<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\helper;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use App\Services\Planeacion\ProgramaTejido\SecuenciaFechasTelar;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ponytail: adaptador temporal, retirar al migrar consumidores.
 * La cadena de fechas por telar vive en {@see SecuenciaFechasTelar}; esto solo delega
 * mientras los controllers lo sigan llamando (setSafeDate se fue con UpdateTejido a
 * EdicionProgramaTejido).
 */
class DateHelpers
{
    /**
     * @see SecuenciaFechasTelar::recalcular()
     *
     * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<string,mixed>>}
     */
    public static function recalcularFechasSecuencia(
        Collection $registrosOrdenados,
        Carbon $inicioOriginal
    ): array {
        return SecuenciaFechasTelar::recalcular($registrosOrdenados, $inicioOriginal);
    }

    /** @see SecuenciaFechasTelar::cascade() */
    public static function cascadeFechas(ReqProgramaTejido $registroActualizado)
    {
        return SecuenciaFechasTelar::cascade($registroActualizado);
    }

    public static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
    {
        return CalendarioProduccion::snapInicioAlCalendario($calendarioId, $fechaInicio);
    }
}
