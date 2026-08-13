<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Services\Crudo\CrudoProductionTargetService;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

final class CrudoProductionTargetServiceTest extends TestCase
{
    public function test_historical_day_uses_current_in_process_prod_kg_dia(): void
    {
        $targets = $this->calculate(
            from: '2026-08-03',
            to: '2026-08-03',
            now: '2026-08-05 12:00:00',
            activePrograms: [$this->program('201', 360)],
        );

        $this->assertSame(360.0, $targets['201']['expectedKilos']);
        $this->assertSame(360.0, $targets['201']['dailyKilos']);
        $this->assertSame(CrudoProductionTargetService::COMPLETE, $targets['201']['standardStatus']);
    }

    public function test_today_prorates_the_standard_from_the_six_thirty_shift_start(): void
    {
        $targets = $this->calculate(
            from: '2026-08-05',
            to: '2026-08-05',
            now: '2026-08-05 12:00:00',
            activePrograms: [$this->program('201', 400)],
        );

        // 06:30 → 12:00 son 5.5 h de 24, no 12 h: prorratear desde medianoche
        // inflaba la meta y marcaba "bajos kg" a telares que iban al corriente.
        $this->assertEqualsWithDelta(400 * 19800 / 86400, $targets['201']['expectedKilos'], 0.001);
        $this->assertSame(400.0, $targets['201']['dailyKilos']);
    }

    public function test_before_six_thirty_the_running_production_day_is_the_previous_date(): void
    {
        $targets = $this->calculate(
            from: '2026-08-04',
            to: '2026-08-04',
            now: '2026-08-05 05:00:00',
            activePrograms: [$this->program('201', 480)],
        );

        // A las 05:00 del 5 llevamos 22.5 h del día de producción del 4.
        $this->assertSame(450.0, $targets['201']['expectedKilos']);
    }

    public function test_before_six_thirty_the_calendar_date_has_not_started_producing(): void
    {
        $targets = $this->calculate(
            from: '2026-08-05',
            to: '2026-08-05',
            now: '2026-08-05 05:00:00',
            activePrograms: [$this->program('201', 480)],
        );

        $this->assertSame(0.0, $targets['201']['expectedKilos']);
    }

    public function test_range_uses_the_same_daily_standard_and_accumulates_eligible_days(): void
    {
        $targets = $this->calculate(
            from: '2026-08-03',
            to: '2026-08-04',
            now: '2026-08-05 12:00:00',
            activePrograms: [$this->program('201', 300)],
        );

        $this->assertSame(600.0, $targets['201']['expectedKilos']);
        $this->assertSame(300.0, $targets['201']['dailyKilos']);
    }

    public function test_range_that_includes_today_prorates_only_the_current_day(): void
    {
        $targets = $this->calculate(
            from: '2026-08-04',
            to: '2026-08-05',
            now: '2026-08-05 12:00:00',
            activePrograms: [$this->program('201', 300)],
        );

        $this->assertEqualsWithDelta(300 + 300 * 19800 / 86400, $targets['201']['expectedKilos'], 0.001);
        $this->assertSame(300.0, $targets['201']['dailyKilos']);
    }

    public function test_it_uses_the_first_positive_standard_when_data_has_duplicate_active_rows(): void
    {
        $targets = $this->calculate(
            from: '2026-08-03',
            to: '2026-08-03',
            now: '2026-08-05 12:00:00',
            activePrograms: [
                $this->program('201', null),
                $this->program('201', 320),
                $this->program('201', 280),
            ],
        );

        $this->assertSame(320.0, $targets['201']['dailyKilos']);
    }

    public function test_it_does_not_invent_a_standard_when_active_prod_kg_dia_is_empty(): void
    {
        $targets = $this->calculate(
            from: '2026-08-03',
            to: '2026-08-03',
            now: '2026-08-05 12:00:00',
            activePrograms: [$this->program('201', null)],
        );

        $this->assertArrayNotHasKey('201', $targets);
    }

    public function test_it_uses_the_fixed_daily_kilos_when_the_program_has_no_standard(): void
    {
        config()->set('crudo.fixed_daily_kilos', ['401' => 600.0]);

        $targets = $this->calculate(
            from: '2026-08-03',
            to: '2026-08-03',
            now: '2026-08-05 12:00:00',
            activePrograms: [$this->program('401', null)],
        );

        $this->assertSame(600.0, $targets['401']['dailyKilos']);
        $this->assertSame(600.0, $targets['401']['expectedKilos']);
    }

    public function test_the_program_standard_wins_over_the_fixed_daily_kilos(): void
    {
        config()->set('crudo.fixed_daily_kilos', ['401' => 600.0]);

        $targets = $this->calculate(
            from: '2026-08-03',
            to: '2026-08-03',
            now: '2026-08-05 12:00:00',
            activePrograms: [$this->program('401', 450)],
        );

        $this->assertSame(450.0, $targets['401']['dailyKilos']);
    }

    /**
     * @param  list<object>  $activePrograms
     * @return array<string, array{expectedKilos: float, dailyKilos: float, standardStatus: string}>
     */
    private function calculate(
        string $from,
        string $to,
        string $now,
        array $activePrograms,
    ): array {
        $service = new CrudoProductionTargetService;
        $timezone = new DateTimeZone('America/Mexico_City');

        return $service->expectedByTelar(
            new DateTimeImmutable($from, $timezone),
            new DateTimeImmutable($to, $timezone),
            new DateTimeImmutable($now, $timezone),
            $activePrograms,
        );
    }

    private function program(string $telar, ?float $prodKgDia): object
    {
        return (object) [
            'NoTelarId' => $telar,
            'ProdKgDia' => $prodKgDia,
        ];
    }
}
