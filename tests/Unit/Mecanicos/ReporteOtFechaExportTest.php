<?php

declare(strict_types=1);

namespace Tests\Unit\Mecanicos;

use App\Exports\ReporteOtFechaExport;
use App\Services\Mecanicos\ReporteOtFechaService;
use Tests\TestCase;

class ReporteOtFechaExportTest extends TestCase
{
    public function test_cada_trabajo_lleva_numero_de_empleado_y_nombre_del_mecanico(): void
    {
        $reporte = (new ReporteOtFechaService)->armarReporte(['desde' => '2026-10-01', 'hasta' => '2026-10-01'], [[
            'cve' => '2002',
            'nombre' => 'PEDRO LÓPEZ',
            'fecha' => '2026-10-01',
            'folio' => 'MEC00001',
            'telar' => '201',
            'falla' => 'Rotura de hilo',
            'trabajos' => ['Ajusto' => true],
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:15:00',
            'total_minutos' => 75,
            'estatus' => 'Terminado',
            'comentarios' => 'Se ajustó',
        ]]);
        $reporte['mecanico_nombre'] = '';

        $filas = (new ReporteOtFechaExport($reporte))->array();

        $this->assertSame(['No. Empleado', 'Nombre', 'Fecha', 'Folio'], array_slice($filas[4], 0, 4));
        $this->assertSame(['2002', 'PEDRO LÓPEZ', '01/10/2026', 'MEC00001'], array_slice($filas[6], 0, 4));
        $this->assertSame(75, $filas[6][9]);
    }
}
