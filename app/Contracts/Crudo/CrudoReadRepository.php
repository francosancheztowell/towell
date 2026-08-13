<?php

declare(strict_types=1);

namespace App\Contracts\Crudo;

use DateTimeImmutable;

interface CrudoReadRepository
{
    /**
     * Totales por telar del día de producción completo. TRANSDATE ya viene
     * asignado por TI al día de producción (06:30 a 06:30), así que el rango de
     * fechas de calendario cubre las 24 h sin desplazamiento. La agregación
     * ocurre en SQL para no transportar cada cabecera a PHP.
     *
     * @return list<object>
     */
    public function aggregateHeadersForRange(DateTimeImmutable $from, DateTimeImmutable $to): array;

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
     * Lote del proveedor por folio del programa de urdido. El ORDENURDIDO que
     * captura AX es el Folio de UrdProgramaUrdido, que es único.
     *
     * @param  list<string>  $warpingOrders
     * @return array<string, string> folio => lote
     */
    public function supplierLotsByWarpingOrder(array $warpingOrders): array;

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

    /**
     * Cortes de eficiencia de tejido del rango, en orden ascendente por
     * fecha y turno. Una fila por telar/turno con hasta tres revisiones.
     *
     * @return list<object>
     */
    public function efficiencyLinesForRange(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
