<?php

declare(strict_types=1);

namespace App\Support\Crudo;

enum CrudoAuditAnswer: string
{
    case Bien = 'bien';
    case Mal = 'mal';
    case SinEvaluar = 'sin_evaluar';

    public static function fromBool(?bool $answer): self
    {
        return match ($answer) {
            true => self::Bien,
            false => self::Mal,
            null => self::SinEvaluar,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Bien => 'Bien',
            self::Mal => 'Mal',
            self::SinEvaluar => 'Sin evaluar',
        };
    }
}
