<?php

namespace Tests\Unit;

use App\Traits\ProduccionTrait;
use Carbon\Carbon;
use Tests\TestCase;

class ProduccionTraitMonthlyClosureTest extends TestCase
{
    public function test_monthly_cutoff_backdates_before_830_on_first_day(): void
    {
        config()->set('app.timezone', 'America/Mexico_City');

        $subject = $this->makeSubject();
        $context = $subject->resolveClosure(Carbon::parse('2026-05-01 08:29:59', 'America/Mexico_City'));

        $this->assertTrue($context['applies']);
        $this->assertSame('2026-04-30', $context['fecha_efectiva']);
        $this->assertSame('2026-05-01', $context['fecha_normal']);
    }

    public function test_monthly_cutoff_does_not_apply_at_830_exactly(): void
    {
        config()->set('app.timezone', 'America/Mexico_City');

        $subject = $this->makeSubject();
        $context = $subject->resolveClosure(Carbon::parse('2026-05-01 08:30:00', 'America/Mexico_City'));

        $this->assertFalse($context['applies']);
        $this->assertSame('2026-05-01', $context['fecha_efectiva']);
    }

    public function test_monthly_cutoff_uses_last_day_of_previous_month_in_leap_year(): void
    {
        config()->set('app.timezone', 'America/Mexico_City');

        $subject = $this->makeSubject();
        $context = $subject->resolveClosure(Carbon::parse('2024-03-01 07:00:00', 'America/Mexico_City'));

        $this->assertTrue($context['applies']);
        $this->assertSame('2024-02-29', $context['fecha_efectiva']);
    }

    private function makeSubject(): object
    {
        return new class
        {
            use ProduccionTrait;

            public function resolveClosure(Carbon $momento): array
            {
                return $this->resolveMonthlyClosureDateContext($momento);
            }

            protected function getProduccionModelClass(): string
            {
                return \stdClass::class;
            }

            protected function getProgramaModelClass(): string
            {
                return \stdClass::class;
            }

            protected function getDepartamento(): string
            {
                return 'Pruebas';
            }

            protected function shouldRoundKgBruto(): bool
            {
                return false;
            }

            protected function getModuleNameForPermissions(): string
            {
                return 'Producción Pruebas';
            }
        };
    }
}
