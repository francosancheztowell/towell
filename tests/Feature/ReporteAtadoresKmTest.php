<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Reporte KM: efectividad = ideal de la barra / minutos reales de enhebrado.
 * Casos tomados del Excel de producción (07-sep).
 */
class ReporteAtadoresKmTest extends TestCase
{
    use UsesSqlsrvSqlite;

    public function test_calcula_efectividad_por_barra_y_total_ponderado(): void
    {
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        $this->withoutVite();

        $schema = Schema::connection('sqlsrv');
        $schema->create('AtaMontadoTelas', function (Blueprint $t) {
            $t->increments('Id');
            foreach (['Turno', 'NoJulio', 'NoProduccion', 'Tipo', 'NoTelarId'] as $c) {
                $t->string($c)->nullable();
            }
            $t->float('MergaKg')->nullable();
            $t->date('Fecha')->nullable();
        });
        foreach (['AtaKmMontado', 'AtaKmEnhebrado'] as $tabla) {
            $schema->create($tabla, function (Blueprint $t) {
                $t->increments('Id');
                foreach (['NoJulio', 'NoProduccion', 'CveEmpl1', 'CveEmpl2', 'CveEmpl3'] as $c) {
                    $t->string($c)->nullable();
                }
                $t->dateTime('FechaInicio')->nullable();
                $t->dateTime('FechaFin')->nullable();
            });
        }

        $db = DB::connection('sqlsrv');
        $atado = function (string $julio, string $telar, string $tipo, array $montado, array $enhebrado) use ($db) {
            $db->table('AtaMontadoTelas')->insert(['Turno' => '1', 'NoJulio' => $julio, 'NoProduccion' => 'OP', 'Tipo' => $tipo, 'NoTelarId' => $telar, 'Fecha' => substr($montado[0], 0, 10)]);
            $db->table('AtaKmMontado')->insert(['NoJulio' => $julio, 'NoProduccion' => 'OP', 'CveEmpl1' => '470', 'CveEmpl2' => '99', 'FechaInicio' => $montado[0], 'FechaFin' => $montado[1]]);
            $db->table('AtaKmEnhebrado')->insert(['NoJulio' => $julio, 'NoProduccion' => 'OP', 'FechaInicio' => $enhebrado[0], 'FechaFin' => $enhebrado[1]]);
        };

        // 68 min contra 60 ideal (barra 1) → 88.24 %
        $atado('K1', '401', '1', ['2026-09-07 18:00', '2026-09-07 18:25'], ['2026-09-07 18:25', '2026-09-07 19:33']);
        // cruza medianoche: 250 min contra 210 ideal (barra 4) → 84.00 %
        $atado('K2', '402', '4', ['2026-09-07 22:30', '2026-09-07 22:50'], ['2026-09-07 22:50', '2026-09-08 03:00']);
        // fuera de rango y rizo viejo en telar 401: no salen
        $atado('K3', '402', '2', ['2026-09-10 07:00', '2026-09-10 07:25'], ['2026-09-10 07:25', '2026-09-10 09:38']);
        $atado('K4', '401', 'Rizo', ['2026-09-07 08:00', '2026-09-07 08:20'], ['2026-09-07 08:20', '2026-09-07 09:00']);

        $this->actingAs($this->createUsuario(), 'web');

        $resp = $this->get('/atadores/reportes-atadores/km?fecha_ini=2026-09-07&fecha_fin=2026-09-07')->assertOk();

        $filas = $resp->viewData('filas');
        $this->assertSame(['K1', 'K2'], array_column($filas, 'julio'));
        $this->assertSame([88.24, 84.0], array_column($filas, 'efectividad'));
        $this->assertSame(['00:25', '00:20'], array_column($filas, 'montado_total'));
        $this->assertSame(['01:08', '04:10'], array_column($filas, 'enhebrado_total'));
        $this->assertSame('470-99', $filas[0]['montador']);
        $this->assertSame(['07/09', '07/09 (2)'], array_column($filas, 'etiqueta'));

        // Σideal / Σreal = 270 / 318
        $this->assertSame(84.91, $resp->viewData('total')['efectividad']);
    }
}
