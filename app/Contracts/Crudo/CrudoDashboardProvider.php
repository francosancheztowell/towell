<?php

declare(strict_types=1);

namespace App\Contracts\Crudo;

use DateTimeImmutable;

interface CrudoDashboardProvider
{
    /**
     * @return array<string, mixed>
     */
    public function get(DateTimeImmutable $date, string $shift, bool $forceRefresh = false): array;
}
