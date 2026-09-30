<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Catalogos;

/** Resultado de una operación de catálogo: mensaje para el usuario y código HTTP. */
final readonly class ResultadoCatalogo
{
    /** @param  array<string, mixed>  $datos */
    public function __construct(
        public bool $exito,
        public string $mensaje,
        public int $status = 200,
        public array $datos = [],
    ) {}

    public static function ok(string $mensaje): self
    {
        return new self(true, $mensaje);
    }

    public static function rechazo(string $mensaje, int $status = 422): self
    {
        return new self(false, $mensaje, $status);
    }

    /** @return array<string, mixed> */
    public function aJson(): array
    {
        return ['success' => $this->exito, 'message' => $this->mensaje] + $this->datos;
    }
}
