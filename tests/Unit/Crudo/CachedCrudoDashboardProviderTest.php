<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Contracts\Crudo\CrudoReadRepository;
use App\Services\Crudo\CachedCrudoDashboardProvider;
use App\Services\Crudo\CrudoDashboardService;
use App\Services\Crudo\CrudoProductionTargetService;
use App\Services\Crudo\CrudoStatusResolver;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class CachedCrudoDashboardProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_repeated_modal_open_uses_the_short_detail_cache(): void
    {
        config()->set('crudo.detail_cache_seconds', 30);
        $repository = Mockery::mock(CrudoReadRepository::class);
        $repository->shouldReceive('headersForTelarInRange')
            ->once()
            ->with('201', Mockery::type(DateTimeImmutable::class), Mockery::type(DateTimeImmutable::class))
            ->andReturn([]);
        $repository->shouldReceive('defectsForHeaders')
            ->once()
            ->with([])
            ->andReturn([]);

        $provider = $this->provider($repository);
        $date = new DateTimeImmutable('2026-08-03');

        $first = $provider->detail('201', $date, $date);
        $second = $provider->detail('201', $date, $date);

        $this->assertSame($first, $second);
        $this->assertSame(0, $second['captureCount']);
    }

    public function test_detail_cache_can_be_disabled_for_diagnostics(): void
    {
        config()->set('crudo.detail_cache_seconds', 0);
        $repository = Mockery::mock(CrudoReadRepository::class);
        $repository->shouldReceive('headersForTelarInRange')->twice()->andReturn([]);
        $repository->shouldReceive('defectsForHeaders')->twice()->with([])->andReturn([]);

        $provider = $this->provider($repository);
        $date = new DateTimeImmutable('2026-08-03');

        $provider->detail('201', $date, $date);
        $provider->detail('201', $date, $date);

        $this->addToAssertionCount(1);
    }

    public function test_manual_force_refresh_rebuilds_even_when_synchronous_rebuilds_are_normally_disabled(): void
    {
        config()->set('crudo.catalog_cache_seconds', 0);
        $repository = Mockery::mock(CrudoReadRepository::class);
        $repository->shouldReceive('machines')->once()->andReturn([]);
        $repository->shouldReceive('aggregateHeadersForRange')->once()->andReturn([]);
        $repository->shouldReceive('efficiencyLinesForRange')->once()->andReturn([]);

        $date = new DateTimeImmutable('2026-07-01');
        Cache::put('crudo:dashboard:2026-07-01:2026-07-01:todos', [
            'generatedAt' => '2026-07-01T00:00:00-06:00',
            'machines' => [['telar' => 'dato-anterior']],
        ], now()->addHour());

        $result = $this->provider($repository)->get(
            $date,
            forceRefresh: true,
            to: $date,
            allowRebuild: false,
        );

        $this->assertSame('fresh', $result['cacheState']);
        $this->assertSame([], $result['machines']);
    }

    private function provider(CrudoReadRepository $repository): CachedCrudoDashboardProvider
    {
        return new CachedCrudoDashboardProvider(
            new CrudoDashboardService(
                $repository,
                new CrudoStatusResolver,
                new CrudoProductionTargetService,
            ),
        );
    }
}
