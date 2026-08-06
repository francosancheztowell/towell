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
        bool $forceRefresh = false,
        ?DateTimeImmutable $to = null,
        bool $allowRebuild = true,
    ): array;

    /**
     * Defectos y capturas de un solo telar. La implementación puede usar una
     * caché breve para absorber aperturas repetidas sin perder sensación de tiempo real.
     *
     * @return array<string, mixed>
     */
    public function detail(string $telar, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
