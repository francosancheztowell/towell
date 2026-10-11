<?php

declare(strict_types=1);

namespace Tests\Unit\Mejoras;

use App\Support\Crudo\CrudoProductionDay;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

/**
 * Rama claude/mejora-dedup-php: el arranque del día de producción se centralizó en
 * CrudoProductionDay::startMinutes() (antes, cinco lecturas sueltas del config).
 */
class DedupPhpCrudoProductionDayTest extends TestCase
{
    public function test_start_minutes_por_defecto_es_0630(): void
    {
        $crudo = config('crudo');
        unset($crudo['production_day_start_minutes']);
        config()->set('crudo', $crudo);

        $this->assertSame(390, CrudoProductionDay::startMinutes());
    }

    public function test_start_minutes_respeta_el_config_y_lo_castea_a_int(): void
    {
        config()->set('crudo.production_day_start_minutes', '420');
        $this->assertSame(420, CrudoProductionDay::startMinutes());
    }

    public function test_for_instant_corta_el_dia_en_el_arranque(): void
    {
        config()->set('crudo.production_day_start_minutes', 390);
        $tz = new DateTimeZone('America/Mexico_City');

        $this->assertSame('2026-10-09', CrudoProductionDay::forInstant(new DateTimeImmutable('2026-10-10 06:29:59', $tz)));
        $this->assertSame('2026-10-10', CrudoProductionDay::forInstant(new DateTimeImmutable('2026-10-10 06:30:00', $tz)));
        $this->assertSame('2026-10-10', CrudoProductionDay::forInstant(new DateTimeImmutable('2026-10-10 23:59:00', $tz)));
    }

    public function test_for_instant_usa_la_zona_del_instante(): void
    {
        config()->set('crudo.production_day_start_minutes', 390);
        // 12:00 UTC = 06:00 en CDMX (UTC-6): en CDMX aún es el día de producción anterior.
        $utc = new DateTimeImmutable('2026-10-10 12:00:00', new DateTimeZone('UTC'));
        $cdmx = $utc->setTimezone(new DateTimeZone('America/Mexico_City'));

        $this->assertSame('2026-10-10', CrudoProductionDay::forInstant($utc));
        $this->assertSame('2026-10-09', CrudoProductionDay::forInstant($cdmx));
    }

    public function test_cambiar_el_config_mueve_el_corte(): void
    {
        config()->set('crudo.production_day_start_minutes', 420); // 07:00
        $tz = new DateTimeZone('America/Mexico_City');

        $this->assertSame('2026-10-09', CrudoProductionDay::forInstant(new DateTimeImmutable('2026-10-10 06:45:00', $tz)));
    }
}
