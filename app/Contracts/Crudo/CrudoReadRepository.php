<?php

declare(strict_types=1);

namespace App\Contracts\Crudo;

use DateTimeImmutable;

interface CrudoReadRepository
{
    /**
     * Totales por telar para el tablero general cuando se consultan todos los turnos.
     * La agregación ocurre en SQL para no transportar cada cabecera a PHP.
     *
     * @return list<object>
     */
    public function aggregateHeadersForRange(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Totales por telar de un turno. Piezas, segundas y kg se agregan en SQL
     * para que el tablero no descargue cabeceras y líneas individuales.
     *
     * @return list<object>
     */
    public function aggregateHeadersForShiftInRange(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        string $shift,
    ): array;

    /**
     * @return list<object>
     */
    public function headersForTelarInRange(string $telar, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @param  list<int|string>  $headerRecIds
     * @return list<object>
     */
    public function defectsForHeaders(array $headerRecIds): array;

    /**
     * @return list<array<string, int|string|null>>
     */
    public function machines(): array;

    /**
     * Paros activos (Estatus = 'Activo') de dbo.ManFallasParos para telares de tejido/calidad.
     *
     * @return list<object>
     */
    public function activeParos(): array;

    /**
     * Programa en proceso (EnProceso = 1) de ReqProgramaTejido para los telares dados.
     *
     * @param  list<string>  $telares
     * @return list<object>
     */
    public function activePrograms(array $telares): array;
}
