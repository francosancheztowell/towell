<?php

namespace App\Support\Programas;

final class ProgramaConfig
{
    public const ACTIVE_STATUSES = ['Programado', 'En Proceso', 'Parcial'];

    public const STATUS_OPTIONS = ['Programado', 'En Proceso', 'Parcial', 'Cancelado'];

    public const OBSERVACIONES_MAX_LENGTH = 500;

    public const CALIDAD_COMENTARIO_MAX_LENGTH = 60;
}
