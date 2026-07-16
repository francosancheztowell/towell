<?php

declare(strict_types=1);

namespace App\DTO\Planeacion\ProgramaTejido;

final readonly class ProgramaTejidoFilters
{
    public function __construct(
        public int $page,
        public int $perPage,
        public ?string $search,
        public string $sort,
        public string $direction,
        public ?string $salon,
        public ?string $telar,
        public ?bool $enProceso,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];

        return new self(
            page: self::integer($data['page'] ?? null, 1),
            perPage: self::integer($data['per_page'] ?? null, 25),
            search: self::nullableString($data['search'] ?? null),
            sort: self::string($data['sort'] ?? null, 'telar'),
            direction: self::string($data['direction'] ?? null, 'asc'),
            salon: self::nullableString($filters['salon'] ?? null),
            telar: self::nullableString($filters['telar'] ?? null),
            enProceso: self::nullableBoolean($filters['en_proceso'] ?? null),
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

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function nullableBoolean(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1' => true,
            false, 0, '0' => false,
            default => null,
        };
    }
}
