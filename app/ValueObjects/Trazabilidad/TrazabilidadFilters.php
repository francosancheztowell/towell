<?php

declare(strict_types=1);

namespace App\ValueObjects\Trazabilidad;

use Illuminate\Http\Request;

final readonly class TrazabilidadFilters
{
    public function __construct(
        public string $flog = '',
        public string $articulo = '',
        public string $tamano = '',
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            flog: self::stringValue($values['flog'] ?? ''),
            articulo: self::stringValue($values['articulo'] ?? ''),
            tamano: self::stringValue($values['tamano'] ?? ''),
        );
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['flog', 'articulo', 'tamano']));
    }

    /**
     * @return array{flog:string,articulo:string,tamano:string}
     */
    public function toArray(): array
    {
        return [
            'flog' => $this->flog,
            'articulo' => $this->articulo,
            'tamano' => $this->tamano,
        ];
    }

    public function hasAny(): bool
    {
        return $this->flog !== ''
            || $this->articulo !== ''
            || $this->tamano !== '';
    }

    public function hasFlog(): bool
    {
        return $this->flog !== '';
    }

    private static function stringValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
