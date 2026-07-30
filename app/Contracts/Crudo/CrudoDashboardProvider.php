<?php

declare(strict_types=1);

namespace App\Contracts\Crudo;

use DateTimeImmutable;

interface CrudoDashboardProvider
{
    /**
     * @param  bool  $allowRebuild  false = nunca reconstruir de forma síncrona; sirve lo que
     *                              haya en caché (fresco o no) y solo construye si no existe nada aún.
     * @return array<string, mixed>
     */
    public function get(
        DateTimeImmutable $date,
        string $shift,
        bool $forceRefresh = false,
        ?DateTimeImmutable $to = null,
        bool $allowRebuild = true,
    ): array;

    /**
     * Defectos y capturas de un solo telar, en vivo (sin pasar por el snapshot cacheado).
     *
     * @return array<string, mixed>
     */
    public function detail(string $telar, DateTimeImmutable $from, DateTimeImmutable $to, string $shift): array;
}
