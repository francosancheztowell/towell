<?php

namespace Tests\Unit\Planeacion;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\EstandaresTelar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Caracterización de la resolución Eficiencia/Velocidad STD al mover un programa de telar
 * (antes QueryHelpers::resolverStdSegunTelar). Busca por telar+fibra+densidad exactos, sin
 * alias de salón ni fallbacks, a diferencia de EstandaresTelar::buscarStd*.
 */
class ResolverStdSegunTelarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlsrv');
        Config::set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv');
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        foreach (['dbo.ReqEficienciaStd' => 'Eficiencia', 'dbo.ReqVelocidadStd' => 'Velocidad'] as $tabla => $valor) {
            Schema::connection('sqlsrv')->create($tabla, function (Blueprint $t) use ($valor) {
                $t->increments('Id');
                $t->string('SalonTejidoId')->nullable();
                $t->string('NoTelarId')->nullable();
                $t->string('FibraId')->nullable();
                $t->string('Densidad')->nullable();
                $t->float($valor)->nullable();
            });
        }

        DB::table('dbo.ReqEficienciaStd')->insert([
            ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FibraId' => 'ALG', 'Densidad' => 'Normal', 'Eficiencia' => 0.8],
            ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FibraId' => 'ALG', 'Densidad' => 'Alta', 'Eficiencia' => 0.7],
            ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '202', 'FibraId' => 'POL', 'Densidad' => null, 'Eficiencia' => 0.9],
        ]);
        DB::table('dbo.ReqVelocidadStd')->insert([
            ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FibraId' => 'ALG', 'Densidad' => 'Normal', 'Velocidad' => 400],
            ['SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FibraId' => 'ALG', 'Densidad' => 'Alta', 'Velocidad' => 350],
        ]);
    }

    private function resolver(array $programa, ?array $modelo, string $telar = '201'): array
    {
        $registro = new ReqProgramaTejido;
        $registro->forceFill($programa);
        $modeloDestino = $modelo === null ? null : (new ReqModelosCodificados)->forceFill($modelo);

        return EstandaresTelar::resolverStdSegunTelar($registro, $modeloDestino, $telar, 'JACQUARD');
    }

    public function test_densidad_normal_con_fibra_rizo_del_programa(): void
    {
        $r = $this->resolver(['FibraRizo' => 'ALG', 'CalibreTrama' => 20, 'EficienciaSTD' => 0.5, 'VelocidadSTD' => 100], null);
        $this->assertEqualsWithDelta(0.8, $r[0], 1e-6);
        $this->assertEqualsWithDelta(400, $r[1], 1e-6);
    }

    public function test_calibre_mayor_a_40_es_densidad_alta(): void
    {
        $r = $this->resolver(['FibraRizo' => 'ALG', 'CalibreTrama' => 41, 'EficienciaSTD' => 0.5, 'VelocidadSTD' => 100], null);
        $this->assertEqualsWithDelta(0.7, $r[0], 1e-6);
        $this->assertEqualsWithDelta(350, $r[1], 1e-6);
    }

    public function test_calibre_40_exacto_sigue_siendo_normal(): void
    {
        $r = $this->resolver(['FibraRizo' => 'ALG', 'CalibreTrama' => 40], null);
        $this->assertEqualsWithDelta(0.8, $r[0], 1e-6);
    }

    public function test_fibra_y_calibre_caen_al_modelo_destino(): void
    {
        $r = $this->resolver([], ['FibraRizo' => 'ALG', 'CalibreTrama' => 50]);
        $this->assertEqualsWithDelta(0.7, $r[0], 1e-6);
        $this->assertEqualsWithDelta(350, $r[1], 1e-6);
    }

    public function test_sin_fila_de_velocidad_usa_velocidad_std_del_modelo(): void
    {
        // Densidad NULL no hace match con 'Normal': sin fallback, la eficiencia queda la del programa.
        $r = $this->resolver(
            ['FibraRizo' => 'POL', 'CalibreTrama' => 20, 'EficienciaSTD' => 0.55, 'VelocidadSTD' => 100],
            ['VelocidadSTD' => '275'],
            '202'
        );
        $this->assertEqualsWithDelta(0.55, $r[0], 1e-6);
        $this->assertSame(275.0, $r[1]);
    }

    public function test_sin_fibra_conserva_los_std_del_programa(): void
    {
        $r = $this->resolver(['CalibreTrama' => 20, 'EficienciaSTD' => 0.6, 'VelocidadSTD' => 120], null);
        $this->assertEqualsWithDelta(0.6, $r[0], 1e-6);
        $this->assertEqualsWithDelta(120, $r[1], 1e-6);
    }

    public function test_telar_sin_estandar_y_sin_modelo_devuelve_null_si_el_programa_no_tiene(): void
    {
        $this->assertSame([null, null], $this->resolver(['FibraRizo' => 'ALG', 'CalibreTrama' => 20], null, '999'));
    }
}
