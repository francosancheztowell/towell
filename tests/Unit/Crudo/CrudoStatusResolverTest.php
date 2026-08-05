<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Enums\Crudo\CrudoMachineState;
use App\Services\Crudo\CrudoStatusResolver;
use Tests\TestCase;

final class CrudoStatusResolverTest extends TestCase
{
    private CrudoStatusResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('crudo.bad_quality_percent', 10);
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
}
