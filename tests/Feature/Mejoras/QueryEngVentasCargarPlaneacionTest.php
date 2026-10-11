<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Http\Controllers\Configuracion\ConfiguracionController;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Rama claude/mejora-query-ventas-traza-resto: los dos bloques duplicados de procesarExcel y
 * procesarExcelUpdate se unieron en regenerarLineasConFechasValidas(). Se fija el filtro de
 * siempre: solo programas con fechas desde el año 2000 y FechaFinal > FechaInicio, en lotes de
 * 100, y un lote que truena no aborta la importación.
 */
final class QueryEngVentasCargarPlaneacionTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTablaDesdeModelo(ReqProgramaTejido::class);
    }

    /** @return array<int, int> ids que se pidieron completos para regenerar líneas */
    private function regenerar(): array
    {
        $ids = [];
        DB::connection()->listen(function ($q) use (&$ids) {
            if (preg_match('/^select \* from "ReqProgramaTejido" where "Id" in/i', $q->sql)) {
                $ids = [...$ids, ...array_map('intval', $q->bindings)];
            }
        });

        $controller = app(ConfiguracionController::class);
        (fn () => $this->regenerarLineasConFechasValidas())->call($controller);

        sort($ids);

        return $ids;
    }

    public function test_solo_regenera_programas_con_fechas_validas(): void
    {
        DB::table('ReqProgramaTejido')->insert([
            ['Id' => 1, 'FechaInicio' => '2026-10-01 06:30:00', 'FechaFinal' => '2026-10-05 06:30:00'], // válido
            ['Id' => 2, 'FechaInicio' => '1933-01-01 00:00:00', 'FechaFinal' => '2026-10-05 00:00:00'], // año viejo
            ['Id' => 3, 'FechaInicio' => '2026-10-05 00:00:00', 'FechaFinal' => '2026-10-05 00:00:00'], // final = inicio
            ['Id' => 4, 'FechaInicio' => '2026-10-06 00:00:00', 'FechaFinal' => '2026-10-05 00:00:00'], // final < inicio
            ['Id' => 5, 'FechaInicio' => null, 'FechaFinal' => '2026-10-05 00:00:00'],
            ['Id' => 6, 'FechaInicio' => '2026-10-01 00:00:00', 'FechaFinal' => null],
            ['Id' => 7, 'FechaInicio' => '2000-01-01 00:00:00', 'FechaFinal' => '2000-01-01 00:00:01'], // borde válido
            ['Id' => 8, 'FechaInicio' => '2026-10-01 00:00:00', 'FechaFinal' => '1999-12-31 00:00:00'],
        ]);

        $this->assertSame([1, 7], $this->regenerar());
    }

    public function test_parte_en_lotes_de_100_y_no_aborta_si_un_lote_falla(): void
    {
        foreach (array_chunk(range(1, 230), 50) as $lote) {
            DB::table('ReqProgramaTejido')->insert(array_map(fn (int $i) => [
                'Id' => $i, 'FechaInicio' => '2026-10-01 00:00:00', 'FechaFinal' => '2026-10-02 00:00:00',
            ], $lote));
        }

        $lotes = 0;
        DB::connection()->listen(function ($q) use (&$lotes) {
            if (preg_match('/^select \* from "ReqProgramaTejido" where "Id" in/i', $q->sql)) {
                $lotes++;
                $this->assertLessThanOrEqual(100, count($q->bindings));
            }
        });

        // regenerarLineas toca tablas que aquí no existen: cada lote truena y se omite.
        $this->assertSame(range(1, 230), $this->regenerar());
        $this->assertSame(3, $lotes);
    }
}
