<?php

namespace Tests\Feature\CatalogosPlaneacion;

use App\Models\Planeacion\ReqAplicaciones;
use App\Models\Planeacion\ReqEficienciaStd;
use App\Models\Planeacion\ReqMatrizHilos;
use App\Models\Planeacion\ReqProgramaTejidoLine;
use App\Models\Planeacion\ReqVelocidadStd;
use Illuminate\Support\Facades\DB;
use Tests\Feature\CatalogosPlaneacion\Concerns\CatalogosFixtures;
use Tests\TestCase;

/**
 * PERF (19-06b): consultas de la request completa (incluye auth y permisos) al editar un estándar
 * o un catálogo que recalcula el programa de tejido. 10 programas del telar (5 de densidad Normal,
 * 5 Alta), 5 líneas por programa. Antes medido sobre 43e0f09 con el mismo escenario.
 */
class ConsultasCatalogosTest extends TestCase
{
    use CatalogosFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCatalogos();
        for ($i = 1; $i <= 10; $i++) {
            $this->programa(['Id' => $i, 'NoTelarId' => '300', 'FibraRizo' => 'H', 'FibraTrama' => 'H', 'CalibreTrama' => $i <= 5 ? 20 : 50,
                'EficienciaSTD' => 0.78, 'VelocidadSTD' => 850, 'AplicacionId' => 'BOR', 'CuentaRizo' => '100']);
        }
        $n = 1;
        for ($p = 1; $p <= 10; $p++) {
            for ($l = 0; $l < 5; $l++) {
                ReqProgramaTejidoLine::query()->insert(['Id' => $n++, 'ProgramaId' => $p, 'Kilos' => 10, 'Rizo' => 2, 'Aplicacion' => 1]);
            }
        }
    }

    private function consultas(callable $accion): int
    {
        $db = DB::connection('sqlsrv');
        $db->flushQueryLog();
        $db->enableQueryLog();
        $accion();
        $total = count($db->getQueryLog());
        $db->disableQueryLog();

        return $total;
    }

    public function test_editar_eficiencia_21_a_11(): void
    {
        $e = ReqEficienciaStd::create(['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Eficiencia' => 0.78, 'Densidad' => 'Normal']);
        $n = $this->consultas(fn () => $this->putJson("/planeacion/eficiencia/{$e->Id}", ['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Eficiencia' => 0.9, 'Densidad' => 'Normal'])->assertOk());
        $this->assertLessThanOrEqual(11, $n, "antes 21 (refresh antes y después de cada save), ahora $n");
    }

    public function test_editar_velocidad_21_a_16(): void
    {
        $v = ReqVelocidadStd::create(['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Velocidad' => 850, 'Densidad' => 'Normal']);
        $n = $this->consultas(fn () => $this->putJson("/planeacion/velocidad/{$v->Id}", ['SalonTejidoId' => 'SMITH', 'NoTelarId' => '300', 'FibraId' => 'H', 'Velocidad' => 900, 'Densidad' => 'Normal'])->assertOk());
        $this->assertLessThanOrEqual(16, $n, "antes 21 (save de cada programa aunque no cambiara), ahora $n");
    }

    public function test_cambiar_factor_de_aplicacion_57_a_8(): void
    {
        $a = ReqAplicaciones::create(['AplicacionId' => 'BOR', 'Nombre' => 'B', 'Factor' => 1]);
        $n = $this->consultas(fn () => $this->putJson("/planeacion/aplicaciones/{$a->Id}", ['AplicacionId' => 'BOR', 'Nombre' => 'B', 'Factor' => 2])->assertOk());
        $this->assertLessThanOrEqual(8, $n, "antes 57 (un UPDATE por línea), ahora $n");
        $this->assertSame(50, ReqProgramaTejidoLine::where('Aplicacion', '20')->count());
    }

    public function test_cambiar_n1_de_hilo_en_uso_67_a_8(): void
    {
        $h = ReqMatrizHilos::create(['Hilo' => 'H', 'N1' => 10, 'N2' => 20]);
        $n = $this->consultas(fn () => $this->putJson("/planeacion/catalogos/matriz-hilos/{$h->Id}", ['Hilo' => 'H', 'N1' => 30, 'N2' => 20])->assertOk());
        $this->assertLessThanOrEqual(8, $n, "antes 67 (una consulta por programa y un UPDATE por línea), ahora $n");
        $this->assertSame(0, ReqProgramaTejidoLine::where('MtsRizo', 0)->orWhereNull('MtsRizo')->count());
    }
}
