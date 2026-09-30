<?php

declare(strict_types=1);

namespace App\Enums\Planeacion;

use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqVelocidadStd;

/**
 * Eficiencia STD y Velocidad STD: el mismo catálogo con otra columna de valor (dedupe 19-06b).
 * Lo que cambia entre los dos vive aquí; EstandarCatalogoService tiene una sola implementación.
 */
enum VarianteEstandar: string
{
    case Eficiencia = 'eficiencia';
    case Velocidad = 'velocidad';

    /** @return class-string<ReqEficienciaStd|ReqVelocidadStd> */
    public function modelo(): string
    {
        return $this === self::Eficiencia ? ReqEficienciaStd::class : ReqVelocidadStd::class;
    }

    /** Columna del valor en el catálogo. */
    public function columna(): string
    {
        return $this === self::Eficiencia ? 'Eficiencia' : 'Velocidad';
    }

    /** Columna del programa de tejido que toma este estándar. */
    public function columnaPrograma(): string
    {
        return $this === self::Eficiencia ? 'EficienciaSTD' : 'VelocidadSTD';
    }

    public function valor(mixed $valor): float|int
    {
        return $this === self::Eficiencia ? (float) $valor : (int) $valor;
    }

    /** Salón que se guarda en el alta si no llega (así estaban los dos controllers). */
    public function salonPorDefectoAlta(): string
    {
        return $this === self::Eficiencia ? 'JACQUARD' : 'Ninguno';
    }

    /** Eficiencia distingue el salón al buscar duplicados; Velocidad no. */
    public function duplicadoPorSalon(): bool
    {
        return $this === self::Eficiencia;
    }

    public function nombre(): string
    {
        return $this === self::Eficiencia ? 'eficiencia' : 'velocidad';
    }

    public function mensajeCreado(string $salon, string $telar, string $fibra): string
    {
        return $this === self::Eficiencia
            ? "Eficiencia para '{$salon} {$telar} - {$fibra}' creada exitosamente"
            : 'Velocidad creada exitosamente';
    }

    public function mensajeDuplicado(bool $edicion): string
    {
        return match (true) {
            $this === self::Eficiencia && ! $edicion => 'Ya existe una eficiencia para este telar y tipo de fibra',
            $this === self::Eficiencia => 'Ya existe otra eficiencia para este telar y tipo de fibra',
            ! $edicion => 'Ya existe una velocidad con los mismos datos',
            default => 'Ya existe otra velocidad con los mismos datos',
        };
    }
}
