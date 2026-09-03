<?php

declare(strict_types=1);

namespace App\Support\Crudo;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * El día de producción corre de 06:30 a 06:30: restar ese arranque mapea
 * cualquier instante a su día de producción sin ramas (a las 05:00 devuelve
 * ayer, a las 07:00 devuelve hoy).
 */
final class CrudoProductionDay
{
    public static function forInstant(DateTimeInterface $instant): string
    {
        $start = (int) config('crudo.production_day_start_minutes', 390);

        return (new DateTimeImmutable('@'.$instant->getTimestamp()))
            ->setTimezone($instant->getTimezone())
            ->modify('-'.$start.' minutes')
            ->format('Y-m-d');
    }
}
