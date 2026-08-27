<?php

namespace App\Support\Programas;

final class ProgramaConfig
{
    public const ACTIVE_STATUSES = ['Programado', 'En Proceso', 'Parcial'];

    public const STATUS_OPTIONS = ['Programado', 'En Proceso', 'Parcial', 'Cancelado'];

    /** No se pueden asignar si UrdProduccionUrdido ya tiene AX = 1 para el mismo folio. */
    public const STATUS_BLOQUEADOS_CON_AX_PRODUCCION = ['Cancelado', 'Programado', 'En Proceso'];

    public const MENSAJE_AX_BLOQUEA_ESTATUS = 'No se puede poner Cancelado, Programado ni En Proceso: este folio ya tiene producción en AX (AX = 1) en UrdProduccionUrdido.';

    public const OBSERVACIONES_MAX_LENGTH = 500;

    public const CALIDAD_COMENTARIO_MAX_LENGTH = 60;

    public static function mensajeAxBloqueaEstatus(string $tablaProduccion): string
    {
        return "No se puede poner Cancelado, Programado ni En Proceso: este folio ya tiene producción en AX (AX = 1) en {$tablaProduccion}.";
    }

    public static function estatusBloqueadoPorAxProduccion(string $status): bool
    {
        return in_array($status, self::STATUS_BLOQUEADOS_CON_AX_PRODUCCION, true);
    }
}
