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

    public function get(
        DateTimeImmutable $date,
        string $shift,
        bool $forceRefresh = false,
        ?DateTimeImmutable $to = null,
        bool $allowRebuild = true,
    ): array {
        $to ??= $date;
        $cacheKey = sprintf(
            'crudo:dashboard:%s:%s:%s',
            $date->format('Y-m-d'),
            $to->format('Y-m-d'),
            $shift,
        );
        $cached = Cache::get($cacheKey);

        if (! $forceRefresh && is_array($cached) && $this->isFresh($cached)) {
            return $this->withCacheState($cached, 'fresh');
        }

        if (! $forceRefresh && ! $allowRebuild) {
            if (is_array($cached)) {
                $this->deferRebuild($cacheKey, $date, $to, $shift);

                return $this->withCacheState($cached, 'stale');
            }

            // Sin snapshot previo (primera carga): no hay nada que servir, hay que construirlo.
        }

        $lock = Cache::lock(
            $cacheKey.':lock',
            (int) config('crudo.cache_lock_seconds', 60),
        );

        if (! $lock->get()) {
            if (is_array($cached)) {
                return $this->withCacheState($cached, 'refreshing');
            }

            return $this->waitForFirstSnapshot($lock, $cacheKey, $date, $to, $shift);
        }

        try {
            return $this->rebuild($cacheKey, $date, $to, $shift);
        } catch (Throwable $exception) {
            Log::error('No se pudo actualizar el tablero de Crudo.', [
                'date' => $date->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
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

    public function detail(string $telar, DateTimeImmutable $from, DateTimeImmutable $to, string $shift): array
    {
        $seconds = max(0, (int) config('crudo.detail_cache_seconds', 14));
        if ($seconds === 0) {
            return $this->dashboard->machineDetail($telar, $from, $to, $shift);
        }

        $key = sprintf(
            'crudo:detail:%s:%s:%s:%s',
            sha1(trim($telar)),
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $shift,
        );

        return Cache::remember(
            $key,
            now()->addSeconds($seconds),
            fn (): array => $this->dashboard->machineDetail($telar, $from, $to, $shift),
        );
    }

    private function deferRebuild(
        string $cacheKey,
        DateTimeImmutable $date,
        DateTimeImmutable $to,
        string $shift,
    ): void {
        defer(
            function () use ($cacheKey, $date, $to, $shift): void {
                $lock = Cache::lock(
                    $cacheKey.':lock',
                    (int) config('crudo.cache_lock_seconds', 60),
                );

                if (! $lock->get()) {
                    return;
                }

                try {
                    $this->rebuild($cacheKey, $date, $to, $shift);
                } catch (Throwable $exception) {
                    Log::warning('No se pudo renovar Crudo después del poll.', [
                        'date' => $date->format('Y-m-d'),
                        'to' => $to->format('Y-m-d'),
                        'shift' => $shift,
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ]);
                } finally {
                    $lock->release();
                }
            },
            name: 'crudo-dashboard-rebuild-'.sha1($cacheKey),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function waitForFirstSnapshot(
        Lock $lock,
        string $cacheKey,
        DateTimeImmutable $date,
        DateTimeImmutable $to,
        string $shift,
    ): array {
        try {
            return $lock->block(5, function () use ($cacheKey, $date, $to, $shift): array {
                $cached = Cache::get($cacheKey);

                return is_array($cached)
                    ? $this->withCacheState($cached, 'fresh')
                    : $this->rebuild($cacheKey, $date, $to, $shift);
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
    private function rebuild(string $cacheKey, DateTimeImmutable $date, DateTimeImmutable $to, string $shift): array
    {
        $data = $this->dashboard->buildRange($date, $to, $shift)->toArray();
        Cache::put(
            $cacheKey,
            $data,
            now()->addSeconds((int) config('crudo.cache_stale_seconds', 300)),
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
                ->diffInSeconds(now(), absolute: true) < (int) config('crudo.cache_fresh_seconds', 14);
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
