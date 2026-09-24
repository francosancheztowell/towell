<?php

namespace Tests\Feature\Planeacion;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT-01 · 01.5 — planeacion:programa-tejido-health es read-only, estructurado y
 * su exit code sirve de gate antes/después de cada fase.
 */
class ProgramaTejidoHealthCheckTest extends TestCase
{
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function correr(string $superficie = 'todas'): array
    {
        $exit = Artisan::call('planeacion:programa-tejido-health', ['--superficie' => $superficie, '--json' => true]);

        return [$exit, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
    }

    private function valor(array $reporte, string $superficie, string $check): int
    {
        $fila = collect($reporte['superficies'][$superficie]['checks'])->firstWhere('id', $check);
        $this->assertNotNull($fila, "No existe el check {$check}");

        return $fila['valor'];
    }

    public function test_fixtures_sanos_pasan_el_gate_en_ambas_superficies(): void
    {
        [$exit, $reporte] = $this->correr();

        $this->assertSame(0, $exit);
        $this->assertTrue($reporte['ok']);
        $this->assertSame(['programa', 'muestras'], array_keys($reporte['superficies']));
        $this->assertSame('MuestrasPrograma', $reporte['superficies']['muestras']['tabla']);

        foreach (['programa', 'muestras'] as $s) {
            $this->assertSame(5, $this->valor($reporte, $s, 'cabeceras'));
            $this->assertSame(4, $this->valor($reporte, $s, 'lineas'));
        }

        // Aviso, no error: las órdenes de Muestras no están en CatCodificados.
        $this->assertSame(0, $this->valor($reporte, 'programa', 'sin_fila_cat_codificados'));
        $this->assertSame(3, $this->valor($reporte, 'muestras', 'sin_fila_cat_codificados')); // 3 cabeceras (órdenes 90001 y 90003)
    }

    public function test_cada_invariante_rota_se_reporta_solo_en_su_superficie(): void
    {
        DB::table('MuestrasPrograma')->insert([
            // posición duplicada + segundo EnProceso en telar 201
            ['SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 1, 'EnProceso' => 1, 'Ultimo' => '1'],
            // sin posición
            ['SalonTejidoId' => 'SMIT', 'NoTelarId' => '205', 'Posicion' => null, 'EnProceso' => 0, 'Ultimo' => null],
        ]);
        DB::table('MuestrasPrograma')->where('Id', 4)->update(['OrdCompartidaLider' => 1]); // grupo 7 con dos líderes
        DB::table('MuestrasProgramaLine')->insert(['ProgramaId' => 999, 'Fecha' => '2026-09-01']);
        DB::table('MuestrasProgramaLine')->where('ProgramaId', 3)->delete();

        [$exit, $reporte] = $this->correr();

        $this->assertSame(1, $exit);
        $this->assertFalse($reporte['ok']);

        $esperado = [
            'posiciones_duplicadas' => 1,
            'telares_multi_en_proceso' => 1,
            'posiciones_nulas' => 1,
            'lineas_huerfanas' => 1,
            'programas_sin_lineas' => 1,
            'telares_multi_ultimo' => 1,
            'grupos_sin_un_lider' => 1,
        ];
        foreach ($esperado as $check => $valor) {
            $this->assertSame($valor, $this->valor($reporte, 'muestras', $check), $check);
            $this->assertSame(0, $this->valor($reporte, 'programa', $check), "{$check} contaminó Programa");
        }

        [$exitPrograma] = $this->correr('programa');
        $this->assertSame(0, $exitPrograma);
    }

    public function test_los_avisos_no_rompen_el_gate(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 4)->update(['OrdCompartidaLider' => 1]);

        [$exit, $reporte] = $this->correr('programa');

        $this->assertSame(0, $exit);
        $this->assertSame(1, $this->valor($reporte, 'programa', 'grupos_sin_un_lider'));
    }

    public function test_solo_ejecuta_select(): void
    {
        $sql = [];
        DB::listen(function ($query) use (&$sql) {
            $sql[] = $query->sql;
        });
        $antes = $this->fotoSuperficies();

        $this->correr();

        $this->assertNotEmpty($sql);
        foreach ($sql as $sentencia) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $sentencia);
        }
        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_superficie_desconocida_o_tabla_inaccesible_sale_con_2(): void
    {
        $this->assertSame(2, Artisan::call('planeacion:programa-tejido-health', ['--superficie' => 'otra']));

        Schema::drop('MuestrasProgramaLine');
        [$exit, $reporte] = $this->correr();

        $this->assertSame(2, $exit);
        $this->assertArrayHasKey('error', $reporte['superficies']['muestras']);
        $this->assertArrayHasKey('checks', $reporte['superficies']['programa']);
    }

    public function test_sin_superficies_configuradas_no_reporta_sano(): void
    {
        config()->set('planeacion.superficies', []);

        $this->assertSame(2, Artisan::call('planeacion:programa-tejido-health'));
    }
}
