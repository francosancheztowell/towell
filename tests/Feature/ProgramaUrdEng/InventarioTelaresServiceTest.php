<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use App\Services\ProgramaUrdEng\InventarioTelaresService;
use Tests\Feature\ProgramaUrdEng\Concerns\InventarioUrdEngSqlite;
use Tests\TestCase;

/**
 * Caracterización de InventarioTelaresService (listado de telares de Reservar y Programar).
 */
class InventarioTelaresServiceTest extends TestCase
{
    use InventarioUrdEngSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInventario();
    }

    public function test_normalize_tipo(): void
    {
        $s = new InventarioTelaresService;

        foreach (['rizo' => 'Rizo', ' PIE ' => 'Pie', '3' => '3', 'barra 2' => '2', 'B4' => '4', 'Barra1' => '1'] as $in => $out) {
            $this->assertSame($out, $s->normalizeTipo($in), (string) $in);
        }
        $this->assertNull($s->normalizeTipo(null));
        $this->assertNull($s->normalizeTipo('5'));
        $this->assertNull($s->normalizeTipo('otro'));
    }

    public function test_base_query_solo_activos_y_normaliza_filas(): void
    {
        $this->telar(['no_telar' => '0401', 'tipo' => ' Rizo ', 'cuenta' => '3156', 'calibre' => 20.5, 'fecha' => '2026-09-01',
            'turno' => '1', 'metros' => null, 'no_julio' => 'J1', 'no_orden' => 'O1', 'Reservado' => true]);
        $this->telar(['no_telar' => 'KM-1', 'tipo' => '2', 'no_julio' => 'K1', 'no_julio3' => 'K3', 'no_orden3' => 'O3',
            'tipo_atado' => 'Especial', 'fecha' => null]);
        $this->telar(['no_telar' => '999', 'status' => 'Baja']);

        $s = new InventarioTelaresService;
        $filas = null;
        // Ya óptimo: una consulta para todo el listado (antes 1, después 1).
        $this->assertSame(1, $this->contarQueries(function () use ($s, &$filas): void {
            $filas = $s->normalizeTelares($s->baseQuery()->orderBy('id')->get())->all();
        }));

        $this->assertCount(2, $filas);
        [$rizo, $barra] = $filas;

        $this->assertSame(401, $rizo['no_telar']);
        $this->assertSame('Rizo', $rizo['tipo']);
        $this->assertSame(20.5, $rizo['calibre']);
        $this->assertSame(0.0, $rizo['metros']);
        $this->assertSame('2026-09-01', $rizo['fecha']);
        $this->assertSame(['J1'], $rizo['julios']);
        $this->assertSame(['O1'], $rizo['ordenes']);
        $this->assertSame(1, $rizo['max_julios']);
        $this->assertSame('Normal', $rizo['tipo_atado']);
        $this->assertTrue($rizo['reservado']);
        $this->assertFalse($rizo['programado']);
        $this->assertSame(0, $rizo['_index']);

        $this->assertSame('KM-1', $barra['no_telar']);
        $this->assertNull($barra['fecha']);
        $this->assertSame(['K1', 'K3'], $barra['julios']);
        $this->assertSame(['', 'O3'], $barra['ordenes']);
        $this->assertSame(4, $barra['max_julios']);
        $this->assertSame('Especial', $barra['tipo_atado']);
    }

    public function test_apply_filtros_respeta_la_lista_blanca_y_cada_regla(): void
    {
        $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'hilo' => ' Algodon ', 'fecha' => '2026-09-01', 'cuenta' => '3156']);
        $this->telar(['no_telar' => '4010', 'tipo' => 'Pie', 'hilo' => 'Poly', 'fecha' => '2026-09-02', 'cuenta' => '2000']);
        $this->telar(['no_telar' => '402', 'tipo' => 'Rizo', 'hilo' => '', 'fecha' => '2026-09-01', 'cuenta' => '31']);

        $telares = fn (array $filtros): array => (new InventarioTelaresService)
            ->applyFiltros((new InventarioTelaresService)->baseQuery(), $filtros)
            ->orderBy('id')->pluck('no_telar')->all();

        $this->assertSame(['401'], $telares([['columna' => 'no_telar', 'valor' => '401']]), 'no_telar es exacto.');
        $this->assertSame(['401'], $telares([['columna' => 'hilo', 'valor' => 'ALGODON ']]));
        $this->assertSame(['401', '402'], $telares([['columna' => 'fecha', 'valor' => '01/09/2026']]));
        $this->assertSame(['401', '402'], $telares([['columna' => 'fecha', 'valor' => '2026.09.01']]));
        $this->assertSame(['401', '4010', '402'], $telares([['columna' => 'fecha', 'valor' => 'no-es-fecha']]));
        $this->assertSame(['401', '402'], $telares([['columna' => 'cuenta', 'valor' => '31']]), 'El resto va con LIKE.');
        $this->assertSame(['401', '4010', '402'], $telares([
            ['columna' => 'status', 'valor' => 'Baja'],
            ['columna' => 'tipo', 'valor' => ''],
            ['valor' => 'x'],
        ]), 'Columnas fuera de la lista blanca y valores vacíos se ignoran.');
    }

    public function test_filtro_hilo_no_usa_trim_que_no_existe_en_sql_server_2008(): void
    {
        $s = new InventarioTelaresService;
        $sql = $s->applyFiltros($s->baseQuery(), [['columna' => 'hilo', 'valor' => 'x']])->toSql();

        $this->assertDoesNotMatchRegularExpression('/(?<![LR])TRIM\(/', $sql);
        $this->assertStringContainsString('LOWER(LTRIM(RTRIM(hilo))) = LOWER(LTRIM(RTRIM(?)))', $sql);
    }
}
