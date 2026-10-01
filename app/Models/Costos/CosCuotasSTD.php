<?php

declare(strict_types=1);

namespace App\Models\Costos;

/** Cuotas estándar por departamento y mes (dbo.CosCuotasSTD). Sin SabMOI ni MOI. */
class CosCuotasSTD extends CosCuota
{
    protected $table = 'CosCuotasSTD';

    public static function columnasValor(): array
    {
        return [
            'Minutos', 'MinParo',
            'SabMO', 'SabGtosFijos', 'SabGtosVariable', 'SabProrrateoFijo', 'SabProrrateoVariable', 'SabMaquila',
            'MO', 'GtosFijos', 'GtosVariables', 'ProrrateoFijo', 'ProrrateoVariable', 'Maquila',
        ];
    }
}
