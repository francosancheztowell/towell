<?php

declare(strict_types=1);

namespace App\Support\Programas;

use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

enum ProgramaModulo: string
{
    case Urdido = 'urdido';
    case Engomado = 'engomado';

    public static function resolve(string $value): self
    {
        return self::tryFrom(mb_strtolower(trim($value)))
            ?? throw new InvalidArgumentException('Módulo de programación no válido.');
    }

    public function title(): string
    {
        return match ($this) {
            self::Urdido => 'Programación Urdido',
            self::Engomado => 'Programación Engomado',
        };
    }

    public function permissionModule(): string
    {
        return match ($this) {
            self::Urdido => 'Programa Urdido',
            self::Engomado => 'Programa Engomado',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function programModel(): string
    {
        return match ($this) {
            self::Urdido => UrdProgramaUrdido::class,
            self::Engomado => EngProgramaEngomado::class,
        };
    }

    /**
     * @return class-string<Model>
     */
    public function productionModel(): string
    {
        return match ($this) {
            self::Urdido => UrdProduccionUrdido::class,
            self::Engomado => EngProduccionEngomado::class,
        };
    }

    public function machineColumn(): string
    {
        return match ($this) {
            self::Urdido => 'MaquinaId',
            self::Engomado => 'MaquinaEng',
        };
    }

    public function fallbackOrderColumn(): string
    {
        return match ($this) {
            self::Urdido => 'CreatedAt',
            self::Engomado => 'FechaProg',
        };
    }

    /**
     * @return array<int, array{key:string,label:string,short:string}>
     */
    public function lanes(): array
    {
        return match ($this) {
            self::Urdido => [
                ['key' => '1', 'label' => 'MC Coy 1', 'short' => 'MC1'],
                ['key' => '2', 'label' => 'MC Coy 2', 'short' => 'MC2'],
                ['key' => '3', 'label' => 'MC Coy 3', 'short' => 'MC3'],
                ['key' => '4', 'label' => 'Karl Mayer', 'short' => 'KM'],
            ],
            self::Engomado => [
                ['key' => '1', 'label' => 'West Point 2', 'short' => 'WP2'],
                ['key' => '2', 'label' => 'West Point 3', 'short' => 'WP3'],
            ],
        };
    }

    public function laneKey(?string $machine): ?string
    {
        $machine = trim((string) $machine);
        if ($machine === '') {
            return null;
        }

        return match ($this) {
            self::Urdido => $this->urdidoLaneKey($machine),
            self::Engomado => $this->engomadoLaneKey($machine),
        };
    }

    public function productionRouteName(): string
    {
        return match ($this) {
            self::Urdido => 'urdido.modulo.produccion.urdido',
            self::Engomado => 'engomado.modulo.produccion.engomado',
        };
    }

    public function reprintRouteName(): string
    {
        return match ($this) {
            self::Urdido => 'urdido.reimpresion.finalizadas',
            self::Engomado => 'engomado.reimpresion.finalizadas',
        };
    }

    public function legacyRouteName(): string
    {
        return match ($this) {
            self::Urdido => 'urdido.programar.urdido.legacy',
            self::Engomado => 'engomado.programar.engomado.legacy',
        };
    }

    private function urdidoLaneKey(string $machine): ?string
    {
        if (stripos($machine, 'Karl Mayer') !== false) {
            return '4';
        }

        if (! preg_match('/mc\s*coy\s*(\d+)/i', $machine, $matches)) {
            return null;
        }

        $number = (int) $matches[1];

        return $number >= 1 && $number <= 3 ? (string) $number : null;
    }

    private function engomadoLaneKey(string $machine): ?string
    {
        if (preg_match('/west\s*point\s*(\d+)/i', $machine, $matches)) {
            return match ((int) $matches[1]) {
                2 => '1',
                3 => '2',
                default => null,
            };
        }

        if (preg_match('/tabla\s*([12])/i', $machine, $matches)) {
            return $matches[1];
        }

        if (stripos($machine, 'izquierda') !== false) {
            return '1';
        }

        if (stripos($machine, 'derecha') !== false) {
            return '2';
        }

        if (preg_match('/([12])\s*$/', $machine, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
