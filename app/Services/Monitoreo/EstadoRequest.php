<?php

namespace App\Services\Monitoreo;

/**
 * Estado de monitoreo de la request en curso (registrado como scoped).
 *
 * - Conteo y tiempo de consultas para el header Server-Timing.
 * - Si ya se reportó un error (para que CapturarRespuesta5xx no lo duplique).
 * - Id del evento de error, que la página 500 muestra como código de referencia.
 */
final class EstadoRequest
{
    public int $consultasN = 0;

    public float $consultasMs = 0.0;

    public bool $errorReportado = false;

    public ?int $errorId = null;

    public ?int $eventoId = null;
}
