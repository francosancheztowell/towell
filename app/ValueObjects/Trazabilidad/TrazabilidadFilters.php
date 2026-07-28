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
        public string $color = '',
        public string $mes = '',
        public string $metrica = 'cantidad',
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
            color: self::stringValue($values['color'] ?? ''),
            mes: self::normalizeMonths($values['mes'] ?? ''),
            metrica: ($values['metrica'] ?? null) === 'peso' ? 'peso' : 'cantidad',
        );
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromArray([
            'flog' => $request->query('flog'),
            'articulo' => $request->query('articulo'),
            'tamano' => $request->query('tamano'),
            'color' => $request->query('color'),
            'mes' => $request->query('mes'),
            'metrica' => $request->query('metrica'),
        ]);
    }

    /**
     * @return array{flog:string,articulo:string,tamano:string,color:string,mes:string}
     */
    public function toArray(): array
    {
        return [
            'flog' => $this->flog,
            'articulo' => $this->articulo,
            'tamano' => $this->tamano,
            'color' => $this->color,
            'mes' => $this->mes,
        ];
    }

    /**
     * @return array<int, int>
     */
    public function months(): array
    {
        if ($this->mes === '') {
            return [];
        }

        return array_map('intval', explode(',', $this->mes));
    }

    public function hasAny(): bool
    {
        return $this->flog !== ''
            || $this->articulo !== ''
            || $this->tamano !== ''
            || $this->color !== ''
            || $this->mes !== '';
    }

    public function hasFlog(): bool
    {
        return $this->flog !== '';
    }

    public function decimals(): int
    {
        return $this->metrica === 'peso' ? 1 : 0;
    }

    private static function stringValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private static function normalizeMonths(mixed $value): string
    {
        $months = collect(explode(',', self::stringValue($value)))
            ->map(static fn (string $month): int => (int) trim($month))
            ->filter(static fn (int $month): bool => $month >= 1 && $month <= 12)
            ->unique()
            ->values()
            ->all();

        return implode(',', $months);
    }
}
