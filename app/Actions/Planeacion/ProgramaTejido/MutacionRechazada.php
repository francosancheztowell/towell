<?php

declare(strict_types=1);

namespace App\Actions\Planeacion\ProgramaTejido;

use RuntimeException;

/**
 * Regla de negocio que rechaza la mutación (422 legacy). La lanzan el Action o el servicio
 * dentro de su transacción para que nada quede escrito; el controller la convierte en
 * response()->json($e->cuerpo, $e->status), el mismo JSON que devolvía el legacy.
 */
final class MutacionRechazada extends RuntimeException
{
    /** @param  array<string, mixed>  $cuerpo */
    public function __construct(public readonly array $cuerpo, public readonly int $status = 422)
    {
        parent::__construct((string) ($cuerpo['message'] ?? 'Mutación rechazada'));
    }

    /** @param  array<string, mixed>  $cuerpo */
    public static function con(array $cuerpo, int $status = 422): self
    {
        return new self($cuerpo, $status);
    }
}
