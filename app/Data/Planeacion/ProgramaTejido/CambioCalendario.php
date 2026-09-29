<?php

declare(strict_types=1);

namespace App\Data\Planeacion\ProgramaTejido;

use App\Http\Requests\Planeacion\ProgramaTejido\CambiarCalendarioRequest;
use Illuminate\Http\Request;

/** Calendario a aplicar sobre un conjunto de programas (PT-05). */
final readonly class CambioCalendario
{
    /**
     * @param  list<int>  $ids
     */
    public function __construct(
        public string $calendarioId,
        public array $ids,
    ) {}

    public static function desdeRequest(CambiarCalendarioRequest|Request $request): self
    {
        return new self(
            (string) $request->input('calendario_id'),
            array_values(array_unique(array_map('intval', (array) $request->input('registros_ids', [])))),
        );
    }
}
