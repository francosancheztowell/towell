<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Enums\Crudo\CrudoMachineState;
use App\Services\Crudo\CrudoStatusResolver;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

final class CrudoStatusResolverTest extends TestCase
{
    private CrudoStatusResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crudo.bad_quality_percent', 10);
        config()->set('crudo.daily_kg_target', [
            'Jacquard' => 300,
            'Sin clasificar' => 300,
        ]);
        config()->set('crudo.turns_per_day', 4);

        $this->resolver = new CrudoStatusResolver;
    }

    public function test_active_paro_takes_priority_over_every_other_state(): void
    {
        $state = $this->resolver->resolve(
            captureCount: 1,
            pieces: 100,
            secondsPercent: 4,
            kilos: 120,
            expectedKilos: 100,
            hasActiveParo: true,
        );

        $this->assertSame(CrudoMachineState::Paro, $state);
    }

    public function test_no_data_has_priority_when_the_machine_has_no_capture(): void
    {
        $state = $this->resolver->resolve(
            captureCount: 0,
            pieces: 0,
            secondsPercent: 0,
            kilos: 0,
            expectedKilos: 100,
        );

        $this->assertSame(CrudoMachineState::NoData, $state);
    }

    public function test_bad_quality_has_priority_over_low_kilos(): void
    {
        $state = $this->resolver->resolve(
            captureCount: 1,
            pieces: 100,
            secondsPercent: 12,
            kilos: 5,
            expectedKilos: 100,
        );

        $this->assertSame(CrudoMachineState::BadQuality, $state);
    }

    public function test_machine_is_low_kilos_only_when_it_has_no_quality_alert(): void
    {
        $state = $this->resolver->resolve(
            captureCount: 1,
            pieces: 100,
            secondsPercent: 4,
            kilos: 50,
            expectedKilos: 100,
        );

        $this->assertSame(CrudoMachineState::LowKilos, $state);
    }

    public function test_machine_is_operating_when_it_meets_both_rules(): void
    {
        $state = $this->resolver->resolve(
            captureCount: 1,
            pieces: 100,
            secondsPercent: 4,
            kilos: 120,
            expectedKilos: 100,
        );

        $this->assertSame(CrudoMachineState::Operating, $state);
    }

    public function test_expected_kilos_are_prorated_for_today_and_divided_by_shift(): void
    {
        $timezone = new DateTimeZone('America/Mexico_City');
        $date = new DateTimeImmutable('2026-07-28 00:00:00', $timezone);
        $eightAm = new DateTimeImmutable('2026-07-28 08:00:00', $timezone);
        $tenThirtyAm = new DateTimeImmutable('2026-07-28 10:30:00', $timezone);
        $noon = new DateTimeImmutable('2026-07-28 12:00:00', $timezone);

        // Meta diaria 300 / 24 = 12.5 kg por hora.
        $this->assertSame(100.0, $this->resolver->expectedKilos('Jacquard', $date, 'todos', $eightAm));
        $this->assertSame(131.25, $this->resolver->expectedKilos('Jacquard', $date, 'todos', $tenThirtyAm));
        $this->assertSame(150.0, $this->resolver->expectedKilos('Jacquard', $date, 'todos', $noon));
        $this->assertSame(75.0, $this->resolver->expectedKilos('Jacquard', $date, '1', $noon));
    }

    public function test_machine_turns_green_when_it_reaches_the_accumulated_hourly_target(): void
    {
        $timezone = new DateTimeZone('America/Mexico_City');
        $date = new DateTimeImmutable('2026-07-28 00:00:00', $timezone);
        $eightAm = new DateTimeImmutable('2026-07-28 08:00:00', $timezone);
        $expectedKilos = $this->resolver->expectedKilos('Jacquard', $date, 'todos', $eightAm);

        $this->assertSame(100.0, $expectedKilos);
        $this->assertSame(CrudoMachineState::LowKilos, $this->resolver->resolve(
            captureCount: 1,
            pieces: 1,
            secondsPercent: 0,
            kilos: 99.9,
            expectedKilos: $expectedKilos,
        ));
        $this->assertSame(CrudoMachineState::Operating, $this->resolver->resolve(
            captureCount: 1,
            pieces: 1,
            secondsPercent: 0,
            kilos: 100,
            expectedKilos: $expectedKilos,
        ));
    }

    public function test_expected_kilos_for_a_range_sums_past_days_and_prorates_today(): void
    {
        $timezone = new DateTimeZone('America/Mexico_City');
        $from = new DateTimeImmutable('2026-07-26 00:00:00', $timezone);
        $to = new DateTimeImmutable('2026-07-28 00:00:00', $timezone);
        $noon = new DateTimeImmutable('2026-07-28 12:00:00', $timezone);

        // 2026-07-26 y 27 completos (300 c/u) + mitad del 28 (150) = 750
        $this->assertSame(750.0, $this->resolver->expectedKilos('Jacquard', $from, 'todos', $noon, to: $to));

        // shift fijo: no se prorratea por hora, cada día del rango pesa el bloque completo del turno
        $this->assertSame(225.0, $this->resolver->expectedKilos('Jacquard', $from, '1', $noon, to: $to));
    }

    public function test_expected_kilos_for_a_range_entirely_in_the_future_is_zero(): void
    {
        $timezone = new DateTimeZone('America/Mexico_City');
        $from = new DateTimeImmutable('2026-08-01 00:00:00', $timezone);
        $to = new DateTimeImmutable('2026-08-03 00:00:00', $timezone);
        $now = new DateTimeImmutable('2026-07-28 12:00:00', $timezone);

        $this->assertSame(0.0, $this->resolver->expectedKilos('Jacquard', $from, 'todos', $now, to: $to));
    }
}
