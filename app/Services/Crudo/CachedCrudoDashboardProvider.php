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

    /**
     * Corto identificador de qué fuente de datos generó el snapshot. Sin esto,
     * dos entornos que comparten el mismo store de caché (Redis) pero apuntan
     * a bases distintas (source/catalog/data_area_id) podían servirse
     * snapshots ajenos hasta por 1 h.
     */
    private function sourceFingerprint(): string
    {
        return substr(sha1(implode('|', [
            (string) config('crudo.connections.source'),
            (string) config('crudo.connections.catalog'),
            (string) config('crudo.data_area_id'),
        ])), 0, 8);
    }

    public function get(
        DateTimeImmutable $date,
        bool $forceRefresh = false,
        ?DateTimeImmutable $to = null,
        bool $allowRebuild = true,
    ): array {
        $to ??= $date;
        $cacheKey = sprintf(
            'crudo:dashboard:%s:%s:%s',
            $this->sourceFingerprint(),
            $date->format('Y-m-d'),
            $to->format('Y-m-d'),
        );
        $cached = Cache::get($cacheKey);

        if (! $forceRefresh && is_array($cached) && $this->isFresh($cached)) {
            return $this->withCacheState($cached, 'fresh');
        }

        if (! $forceRefresh && ! $allowRebuild) {
            if (is_array($cached)) {
                $this->deferRebuild($cacheKey, $date, $to);

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

            return $this->waitForFirstSnapshot($lock, $cacheKey, $date, $to);
        }

        try {
            return $this->rebuild($cacheKey, $date, $to);
        } catch (Throwable $exception) {
            Log::error('No se pudo actualizar el tablero de Crudo.', [
                'date' => $date->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
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

    public function detail(string $telar, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $seconds = max(0, (int) config('crudo.detail_cache_seconds', 14));
        if ($seconds === 0) {
            return $this->dashboard->machineDetail($telar, $from, $to);
        }

        $key = sprintf(
            'crudo:detail:%s:%s:%s:%s',
            $this->sourceFingerprint(),
            sha1(trim($telar)),
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        return Cache::remember(
            $key,
            now()->addSeconds($seconds),
            fn (): array => $this->dashboard->machineDetail($telar, $from, $to),
        );
    }

    private function deferRebuild(
        string $cacheKey,
        DateTimeImmutable $date,
        DateTimeImmutable $to,
    ): void {
        defer(
            function () use ($cacheKey, $date, $to): void {
                $lock = Cache::lock(
                    $cacheKey.':lock',
                    (int) config('crudo.cache_lock_seconds', 60),
                );

                if (! $lock->get()) {
                    return;
                }

                try {
                    $this->rebuild($cacheKey, $date, $to);
                } catch (Throwable $exception) {
                    Log::warning('No se pudo renovar Crudo después del poll.', [
                        'date' => $date->format('Y-m-d'),
                        'to' => $to->format('Y-m-d'),
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
    ): array {
        try {
            return $lock->block(5, function () use ($cacheKey, $date, $to): array {
                $cached = Cache::get($cacheKey);

                return is_array($cached)
                    ? $this->withCacheState($cached, 'fresh')
                    : $this->rebuild($cacheKey, $date, $to);
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
    private function rebuild(string $cacheKey, DateTimeImmutable $date, DateTimeImmutable $to): array
    {
        $data = $this->dashboard->buildRange($date, $to)->toArray();
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
