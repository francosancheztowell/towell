<?php

declare(strict_types=1);

namespace App\Models\Costos;

/** Cuotas reales por departamento y mes (dbo.CosCuotasReal). Trae MOI, que STD no tiene. */
class CosCuotasReal extends CosCuota
{
    protected $table = 'CosCuotasReal';

    public static function columnasValor(): array
    {
        return [
            'Minutos', 'MinParo',
            'SabMO', 'SabGtosFijos', 'SabMOI', 'SabGtosVariable', 'SabProrrateoFijo', 'SabProrrateoVariable', 'SabMaquila',
            'MO', 'MOI', 'GtosFijos', 'GtosVariables', 'ProrrateoFijo', 'ProrrateoVariable', 'Maquila',
        ];
    }
}
