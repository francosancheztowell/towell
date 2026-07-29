<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use App\Contracts\Crudo\CrudoDashboardProvider;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final readonly class CachedCrudoDashboardProvider implements CrudoDashboardProvider
{
    public function __construct(
        private CrudoDashboardService $dashboard,
    ) {}

    public function get(DateTimeImmutable $date, string $shift, bool $forceRefresh = false): array
    {
        $cacheKey = sprintf('crudo:dashboard:%s:%s', $date->format('Y-m-d'), $shift);
        $cached = Cache::get($cacheKey);

        if (! $forceRefresh && is_array($cached) && $this->isFresh($cached)) {
            return $this->withCacheState($cached, 'fresh');
        }

        $lock = Cache::lock(
            $cacheKey.':lock',
            (int) config('crudo.cache_lock_seconds', 15),
        );

        if (! $lock->get()) {
            if (is_array($cached)) {
                return $this->withCacheState($cached, 'refreshing');
            }

            return $this->waitForFirstSnapshot($lock, $cacheKey, $date, $shift);
        }

        try {
            return $this->rebuild($cacheKey, $date, $shift);
        } catch (Throwable $exception) {
            Log::error('No se pudo actualizar el tablero de Crudo.', [
                'date' => $date->format('Y-m-d'),
                'shift' => $shift,
                'connection' => config('crudo.connections.source'),
                'exception' => $exception::class,
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);

            if (is_array($cached)) {
                $cached['sourceError'] = 'No fue posible consultar TI; se muestran los últimos datos disponibles.';

                return $this->withCacheState($cached, 'stale');
            }

            throw new RuntimeException(
                'No fue posible consultar la información de Crudo en TI.',
                previous: $exception,
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForFirstSnapshot(
        Lock $lock,
        string $cacheKey,
        DateTimeImmutable $date,
        string $shift,
    ): array {
        try {
            return $lock->block(5, function () use ($cacheKey, $date, $shift): array {
                $cached = Cache::get($cacheKey);

                return is_array($cached)
                    ? $this->withCacheState($cached, 'fresh')
                    : $this->rebuild($cacheKey, $date, $shift);
            });
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'El tablero de Crudo está actualizándose. Intenta nuevamente.',
                previous: $exception,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rebuild(string $cacheKey, DateTimeImmutable $date, string $shift): array
    {
        $data = $this->dashboard->build($date, $shift)->toArray();
        Cache::put(
            $cacheKey,
            $data,
            now()->addSeconds((int) config('crudo.cache_stale_seconds', 120)),
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function isFresh(array $cached): bool
    {
        $generatedAt = $cached['generatedAt'] ?? null;
        if (! is_string($generatedAt) || $generatedAt === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($generatedAt)
                ->diffInSeconds(now(), absolute: true) < (int) config('crudo.cache_fresh_seconds', 8);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withCacheState(array $data, string $state): array
    {
        $data['cacheState'] = $state;

        return $data;
    }
}
