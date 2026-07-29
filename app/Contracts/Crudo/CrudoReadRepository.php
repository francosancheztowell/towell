<?php

declare(strict_types=1);

namespace App\Contracts\Crudo;

use DateTimeImmutable;

interface CrudoReadRepository
{
    /**
     * @return list<object>
     */
    public function headersForDate(DateTimeImmutable $date): array;

    /**
     * @param  list<int|string>  $headerRecIds
     * @return list<object>
     */
    public function defectsForHeaders(array $headerRecIds): array;

    /**
     * @return list<array<string, int|string|null>>
     */
    public function machines(): array;
}
