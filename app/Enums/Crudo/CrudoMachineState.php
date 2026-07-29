<?php

declare(strict_types=1);

namespace App\Enums\Crudo;

enum CrudoMachineState: string
{
    case BadQuality = 'bad_quality';
    case LowKilos = 'low_kilos';
    case Operating = 'operating';
    case NoData = 'no_data';

    public function label(): string
    {
        return match ($this) {
            self::BadQuality => 'Mala calidad',
            self::LowKilos => 'Bajos kg',
            self::Operating => 'En operación',
            self::NoData => 'Sin datos',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BadQuality => 'fa-circle-xmark',
            self::LowKilos => 'fa-arrow-down',
            self::Operating => 'fa-circle-check',
            self::NoData => 'fa-minus',
        };
    }
}
