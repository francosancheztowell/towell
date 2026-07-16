<?php

declare(strict_types=1);

namespace App\DTO\Planeacion\PesosRollos;

final readonly class PesoRolloFilters
{
    /**
     * @param  array{item_id?: string|null, item_name?: string|null, invent_size_id?: string|null, peso_min?: numeric-string|int|float|null, peso_max?: numeric-string|int|float|null}  $filters
     */
    public function __construct(
        public int $page,
        public int $perPage,
        public ?string $search,
        public string $sort,
        public string $direction,
        public array $filters,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            page: self::integer($data['page'] ?? null, 1),
            perPage: self::integer($data['per_page'] ?? null, 25),
            search: self::nullableString($data['search'] ?? null),
            sort: self::string($data['sort'] ?? null, 'item_id'),
            direction: self::string($data['direction'] ?? null, 'asc'),
            filters: self::filters($data['filters'] ?? null),
        );
    }

    private static function integer(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && ctype_digit($value) ? (int) $value : $default;
    }

    private static function string(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @return array{item_id?: string|null, item_name?: string|null, invent_size_id?: string|null, peso_min?: numeric-string|int|float|null, peso_max?: numeric-string|int|float|null}
     */
    private static function filters(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $filters = [];
        foreach (['item_id', 'item_name', 'invent_size_id'] as $key) {
            if (isset($value[$key]) && is_string($value[$key])) {
                $filters[$key] = $value[$key];
            }
        }

        foreach (['peso_min', 'peso_max'] as $key) {
            if (isset($value[$key]) && is_numeric($value[$key])) {
                $filters[$key] = $value[$key];
            }
        }

        return $filters;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
