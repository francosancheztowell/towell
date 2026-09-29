<?php

declare(strict_types=1);

namespace App\Actions\Planeacion\ProgramaTejido;

use RuntimeException;
use Throwable;

/** Una fila de una mutación masiva falló y la transacción ya se revirtió completa. */
final class FalloEnRegistro extends RuntimeException
{
    public function __construct(public readonly int $registroId, Throwable $previa)
    {
        parent::__construct("Falló el registro {$registroId}", 0, $previa);
    }
}
