<?php

declare(strict_types=1);

namespace App\Actions\Planeacion\ProgramaTejido;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Regla de negocio que rechaza la mutación (422 legacy). La lanza el Action dentro de su
 * transacción para que nada quede escrito; el controller devuelve la respuesta tal cual.
 */
final class MutacionRechazada extends RuntimeException
{
    public function __construct(public readonly JsonResponse $respuesta)
    {
        parent::__construct((string) ($respuesta->getData(true)['message'] ?? 'Mutación rechazada'));
    }

    /** @param  array<string, mixed>  $cuerpo */
    public static function con(array $cuerpo, int $status = 422): self
    {
        return new self(response()->json($cuerpo, $status));
    }
}
