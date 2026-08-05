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

    public function test_today_prorates_current_standard_to_the_current_hour(): void
    {
        $targets = $this->calculate(
            from: '2026-08-05',
            to: '2026-08-05',
            now: '2026-08-05 12:00:00',
            activePrograms: [$this->program('201', 400)],
        );

        $this->assertSame(200.0, $targets['201']['expectedKilos']);
        $this->assertSame(400.0, $targets['201']['dailyKilos']);
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

        $this->assertSame(450.0, $targets['201']['expectedKilos']);
        $this->assertSame(300.0, $targets['201']['dailyKilos']);
    }

    public function test_range_shift_uses_one_six_hour_window_per_day(): void
    {
        config()->set('crudo.turns_per_day', 4);

        $targets = $this->calculate(
            from: '2026-08-03',
            to: '2026-08-04',
            now: '2026-08-05 12:00:00',
            activePrograms: [$this->program('201', 300)],
            shift: '3',
        );

        $this->assertSame(150.0, $targets['201']['expectedKilos']);
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

        $this->assertSame([], $targets);
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
        string $shift = 'todos',
    ): array {
        $service = new CrudoProductionTargetService;
        $timezone = new DateTimeZone('America/Mexico_City');

        return $service->expectedByTelar(
            new DateTimeImmutable($from, $timezone),
            new DateTimeImmutable($to, $timezone),
            $shift,
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
