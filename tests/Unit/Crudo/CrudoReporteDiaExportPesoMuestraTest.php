<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\DTOs\Crudo\CrudoDashboardData;
use App\Exports\CrudoReporteDiaExport;
use DateTimeImmutable;
use Tests\TestCase;

final class CrudoReporteDiaExportPesoMuestraTest extends TestCase
{
    public function test_lista_los_telares_sin_peso_muestra(): void
    {
        $export = new CrudoReporteDiaExport(
            new CrudoDashboardData('2026-08-17', [], [], [], '2026-08-17 06:30'),
            new DateTimeImmutable('2026-08-17'),
            null,
            [],
            [['telar' => 'T-101', 'orden' => 'OP-9', 'producto' => 'Toalla baño']],
        );

        $flat = array_map(
            static fn (array $row): string => implode('|', array_map(strval(...), $row)),
            $export->array(),
        );

        $this->assertContains('TELARES SIN PESO MUESTRA — 1 telar(es)', $flat);
        $this->assertContains('T-101|OP-9|Toalla baño', $flat);
    }

    public function test_sin_faltantes_no_agrega_el_bloque(): void
    {
        $export = new CrudoReporteDiaExport(
            new CrudoDashboardData('2026-08-17', [], [], [], '2026-08-17 06:30'),
            new DateTimeImmutable('2026-08-17'),
        );

        $flat = array_map(
            static fn (array $row): string => implode('|', array_map(strval(...), $row)),
            $export->array(),
        );

        $this->assertEmpty(array_filter($flat, static fn (string $r): bool => str_contains($r, 'SIN PESO MUESTRA')));
    }
}
