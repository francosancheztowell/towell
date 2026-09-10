<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\DTOs\Crudo\CrudoDashboardData;
use App\Exports\CrudoReporteDiaExport;
use DateTimeImmutable;
use Tests\TestCase;

final class CrudoReporteDiaExportDefectosTest extends TestCase
{
    public function test_lista_las_segundas_por_telar_de_mayor_a_menor(): void
    {
        $export = new CrudoReporteDiaExport(
            new CrudoDashboardData('2026-08-17', [], [], [], '2026-08-17 06:30'),
            new DateTimeImmutable('2026-08-17'),
            null,
            [],
            [],
            [],
            [
                'columnas' => ['Mancha', 'Otros'],
                'telares' => [
                    ['telar' => '201', 'total' => 3.0, 'defectos' => ['Mancha' => 3.0, 'Otros' => 0.0]],
                    ['telar' => '202', 'total' => 10.0, 'defectos' => ['Mancha' => 6.0, 'Otros' => 4.0]],
                ],
                'recortados' => 2,
            ],
        );

        $flat = array_map(
            static fn (array $row): string => implode('|', array_map(strval(...), $row)),
            $export->array(),
        );

        $this->assertContains('SEGUNDAS POR TELAR', $flat);
        $this->assertContains('Salón|Telar|Mancha|Otros|Total', $flat);

        $filas = array_values(array_filter($flat, static fn (string $r): bool => str_contains($r, '|20')));
        $this->assertSame('Sin clasificar|202|6|4|10', $filas[0]);
        $this->assertSame('Sin clasificar|201|3|0|3', $filas[1]);
        $this->assertContains('TOTAL|2 telar(es)|9|4|13', $flat);
        $this->assertContains('Los 2 tipos de defecto menos frecuentes están sumados en "Otros".', $flat);
    }

    public function test_sin_defectos_deja_el_aviso(): void
    {
        $export = new CrudoReporteDiaExport(
            new CrudoDashboardData('2026-08-17', [], [], [], '2026-08-17 06:30'),
            new DateTimeImmutable('2026-08-17'),
        );

        $flat = array_map(
            static fn (array $row): string => implode('|', array_map(strval(...), $row)),
            $export->array(),
        );

        $this->assertContains('Sin defectos capturados para el día.', $flat);
    }
}
