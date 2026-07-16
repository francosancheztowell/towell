<?php

declare(strict_types=1);

namespace App\DTO\Planeacion\PesosRollos;

final readonly class PesoRolloData
{
    public function __construct(
        public string $itemId,
        public string $itemName,
        public string $inventSizeId,
        public float $pesoRollo,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $itemId = self::requiredString($data, 'item_id');
        $itemName = self::requiredString($data, 'item_name');
        $inventSizeId = self::requiredString($data, 'invent_size_id');
        $pesoRollo = $data['peso_rollo'] ?? null;

        if (! is_numeric($pesoRollo)) {
            throw new \InvalidArgumentException('peso_rollo debe ser numerico.');
        }

        return new self(
            itemId: $itemId,
            itemName: $itemName,
            inventSizeId: $inventSizeId,
            pesoRollo: (float) $pesoRollo,
        );
    }

    /** @return array<string, string|float> */
    public function toDatabaseAttributes(): array
    {
        return [
            'ItemId' => $this->itemId,
            'ItemName' => $this->itemName,
            'InventSizeId' => $this->inventSizeId,
            'PesoRollo' => $this->pesoRollo,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("{$key} debe ser texto no vacio.");
        }

        return trim($value);
    }
}
