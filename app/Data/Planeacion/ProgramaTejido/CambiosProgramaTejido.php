<?php

declare(strict_types=1);

namespace App\Data\Planeacion\ProgramaTejido;

use App\Http\Requests\Planeacion\ProgramaTejido\ActualizarProgramaTejidoRequest;

/**
 * Edición inline ya validada (PT-05). Los nombres de campo son los del payload legacy
 * (snake_case): el mapeo a columnas vive en UpdateTejido::aplicarCambios(). Solo trae
 * los campos presentes: "ausente" y "null" significan cosas distintas.
 */
final readonly class CambiosProgramaTejido
{
    /**
     * @param  array<string, mixed>  $campos
     */
    public function __construct(
        public int $id,
        public array $campos,
    ) {}

    public static function desdeRequest(ActualizarProgramaTejidoRequest $request, int $id): self
    {
        return new self($id, $request->validated());
    }

    /** VelocidadSTD/EficienciaSTD cambian las horas de producción (CR-03 de 05-REVIEW). */
    public function cambiaStd(): bool
    {
        return array_key_exists('velocidad_std', $this->campos) || array_key_exists('eficiencia_std', $this->campos);
    }
}
