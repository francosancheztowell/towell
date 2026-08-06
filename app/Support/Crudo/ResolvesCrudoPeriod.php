<?php

declare(strict_types=1);

namespace App\Support\Crudo;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

trait ResolvesCrudoPeriod
{
    private function rangeFrom(): DateTimeImmutable
    {
        $timezone = $this->crudoTimezone();

        if ($this->modo !== 'rango') {
            return new DateTimeImmutable($this->normalizeDate($this->fecha), $timezone);
        }

        $from = new DateTimeImmutable($this->normalizeDate($this->fechaInicio), $timezone);
        $to = new DateTimeImmutable($this->normalizeDate($this->fechaFin), $timezone);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $maxDays = max(1, (int) config('crudo.max_range_days', 31));
        $earliestAllowed = $to->sub(new DateInterval('P'.($maxDays - 1).'D'));

        return $from < $earliestAllowed ? $earliestAllowed : $from;
    }

    private function rangeTo(): DateTimeImmutable
    {
        $timezone = $this->crudoTimezone();

        if ($this->modo !== 'rango') {
            return new DateTimeImmutable($this->normalizeDate($this->fecha), $timezone);
        }

        $from = new DateTimeImmutable($this->normalizeDate($this->fechaInicio), $timezone);
        $to = new DateTimeImmutable($this->normalizeDate($this->fechaFin), $timezone);

        return $from > $to ? $from : $to;
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        $timezone = $this->crudoTimezone();
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        $valid = $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $date;

        return $valid ? $date : $this->crudoProductionDay();
    }

    /**
     * El día de producción corre de 06:30 a 06:30. Restar ese arranque mapea
     * cualquier instante a su día de producción sin ramas: a las 05:00 devuelve
     * ayer, a las 07:00 devuelve hoy.
     */
    private function crudoProductionDay(): string
    {
        return now($this->crudoTimezone())
            ->subMinutes((int) config('crudo.production_day_start_minutes', 390))
            ->format('Y-m-d');
    }

    private function crudoTimezone(): DateTimeZone
    {
        return new DateTimeZone((string) config('app.timezone'));
    }
}
