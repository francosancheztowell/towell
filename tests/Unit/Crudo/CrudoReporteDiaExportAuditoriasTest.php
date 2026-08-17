<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\DTOs\Crudo\CrudoDashboardData;
use App\Exports\CrudoReporteDiaExport;
use App\Models\Crudo\CrudoAuditoria;
use DateTimeImmutable;
use Tests\TestCase;

final class CrudoReporteDiaExportAuditoriasTest extends TestCase
{
    public function test_incluye_el_bloque_de_auditorias_de_calidad(): void
    {
        $auditoria = new CrudoAuditoria([
            'Fecha' => '2026-08-17 08:15:00',
            'NoTelarId' => 'T-101',
            'Salon' => 'JAC 1',
            'OrdenTrabajo' => 'OT-9',
            'Turno' => 1,
            'NomEmpl' => 'Ana',
            'AlineacionOrden' => true,
            'DibujoJacquard' => false,
            'IdentificacionJulio' => null,
            'Marbetes' => 3,
            'Observaciones' => 'Revisar orillo',
        ]);

        $export = new CrudoReporteDiaExport(
            new CrudoDashboardData('2026-08-17', [], [], [], '2026-08-17 06:30'),
            new DateTimeImmutable('2026-08-17'),
            null,
            [$auditoria],
        );

        $rows = $export->array();
        $flat = array_map(static fn (array $row): string => implode('|', array_map(strval(...), $row)), $rows);

        $this->assertContains('AUDITORÍAS DE CALIDAD', $flat);
        $this->assertNotEmpty(array_filter(
            $flat,
            static fn (string $row): bool => str_contains($row, 'T-101')
                && str_contains($row, 'Bien')
                && str_contains($row, 'Mal')
                && str_contains($row, 'Revisar orillo'),
        ));
    }

    public function test_sin_auditorias_deja_una_nota_en_lugar_de_filas(): void
    {
        $export = new CrudoReporteDiaExport(
            new CrudoDashboardData('2026-08-17', [], [], [], '2026-08-17 06:30'),
            new DateTimeImmutable('2026-08-17'),
        );

        $this->assertContains(['Sin auditorías capturadas para el día.'], $export->array());
    }
}
