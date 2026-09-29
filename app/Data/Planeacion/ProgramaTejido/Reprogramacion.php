<?php

declare(strict_types=1);

namespace App\Data\Planeacion\ProgramaTejido;

use App\Http\Requests\Planeacion\ProgramaTejido\ReprogramarProgramaTejidoRequest;

/** Valor de Reprogramar para una orden en proceso: '1', '2' o null (PT-05). */
final readonly class Reprogramacion
{
    public function __construct(
        public int $id,
        public ?string $valor,
    ) {}

    public static function desdeRequest(ReprogramarProgramaTejidoRequest $request, int $id): self
    {
        $valor = $request->validated('reprogramar');

        return new self($id, $valor === null || $valor === '' ? null : (string) $valor);
    }
}
