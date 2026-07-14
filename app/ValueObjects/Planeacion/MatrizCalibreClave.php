<?php

declare(strict_types=1);

namespace App\ValueObjects\Planeacion;

use InvalidArgumentException;

final readonly class MatrizCalibreClave
{
    public const TIPO_RIZO = 'RIZO';

    public const TIPO_PIE = 'PIE';

    public const TIPO_TRAMA = 'TRAMA';

    /** @var list<string> */
    public const TIPOS = [self::TIPO_RIZO, self::TIPO_PIE, self::TIPO_TRAMA];

    public function __construct(
        public string $tipo,
        public ?float $calibre,
        public ?string $fibraId,
        public ?string $cuenta,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function tryFromArray(array $data): ?self
    {
        $tipo = self::normalizarTexto($data['Tipo'] ?? $data['tipo'] ?? null);
        $fibraId = self::normalizarTexto($data['FibraId'] ?? $data['fibraId'] ?? null) ?: null;
        $calibreRaw = $data['Calibre'] ?? $data['calibre'] ?? null;

        if (! in_array($tipo, self::TIPOS, true)) {
            return null;
        }

        $calibre = null;
        if (is_numeric($calibreRaw)) {
            $calibre = round((float) $calibreRaw, 1);
            if ($calibre <= 0) {
                $calibre = null;
            }
        }

        if ($tipo !== self::TIPO_PIE && ($calibre === null || $fibraId === null)) {
            return null;
        }
        if ($tipo === self::TIPO_PIE && $calibre === null && $fibraId === null) {
            return null;
        }

        $cuenta = self::normalizarTexto($data['Cuenta'] ?? $data['cuenta'] ?? null);
        if ($tipo === self::TIPO_TRAMA) {
            $cuenta = null;
        } elseif ($cuenta === '') {
            return null;
        }

        return new self(
            tipo: $tipo,
            calibre: $calibre,
            fibraId: $fibraId,
            cuenta: $cuenta,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return self::tryFromArray($data)
            ?? throw new InvalidArgumentException('La clave de Matriz de Calibres está incompleta o no es válida.');
    }

    /**
     * @return array{Tipo: string, Calibre: float|null, FibraId: string|null, Cuenta: string|null}
     */
    public function toArray(): array
    {
        return [
            'Tipo' => $this->tipo,
            'Calibre' => $this->calibre,
            'FibraId' => $this->fibraId,
            'Cuenta' => $this->cuenta,
        ];
    }

    private static function normalizarTexto(mixed $value): string
    {
        return mb_strtoupper(trim((string) ($value ?? '')), 'UTF-8');
    }
}
