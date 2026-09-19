<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\Liberar\LiberarProgramaScheduling;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Prioridad anterior y fecha INN extraídas de LiberarOrdenesController.
 * Misma entrada → misma salida: SALDAR + producto del renglón previo
 * (mismo salón+telar); Programado = HOY si FechaInicio ≤ (HOY + días).
 */
class LiberarProgramaSchedulingTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private LiberarProgramaScheduling $scheduling;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NombreProducto')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->dateTime('FechaInicio')->nullable();
        });

        $this->scheduling = new LiberarProgramaScheduling;
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('ReqProgramaTejido');
        parent::tearDown();
    }

    private function registro(array $attrs = []): ReqProgramaTejido
    {
        $r = new ReqProgramaTejido;
        $r->Id = $attrs['Id'] ?? 10;
        $r->SalonTejidoId = $attrs['SalonTejidoId'] ?? 'JACQUARD';
        $r->NoTelarId = $attrs['NoTelarId'] ?? '201';
        $r->NombreProducto = $attrs['NombreProducto'] ?? 'TOALLA ACTUAL';
        $r->FechaInicio = array_key_exists('FechaInicio', $attrs)
            ? $attrs['FechaInicio']
            : Carbon::create(2026, 6, 20);

        return $r;
    }

    private function candidato(int $id, string $producto, ?string $fecha): object
    {
        return (object) [
            'Id' => $id,
            'NombreProducto' => $producto,
            'FechaInicio' => $fecha,
        ];
    }

    public function test_fecha_programada_inn_dentro_del_rango_es_hoy(): void
    {
        $hoy = Carbon::create(2026, 6, 20)->startOfDay();
        $fechaFormula = $hoy->copy()->addDays(10.999);

        $reg = $this->registro(['FechaInicio' => Carbon::create(2026, 6, 25)]);
        $resultado = $this->scheduling->calcularFechaProgramada($reg, $hoy, $fechaFormula);

        $this->assertNotNull($resultado);
        $this->assertTrue($hoy->equalTo($resultado));
    }

    public function test_fecha_programada_inn_acepta_string_y_el_borde_igual_a_formula(): void
    {
        $hoy = Carbon::create(2026, 6, 20)->startOfDay();
        $fechaFormula = $hoy->copy()->addDays(10.999);

        $reg = $this->registro(['FechaInicio' => '2026-06-30 15:40:00']);
        $resultado = $this->scheduling->calcularFechaProgramada($reg, $hoy, $fechaFormula);

        $this->assertNotNull($resultado);
        $this->assertTrue($hoy->equalTo($resultado));
        $this->assertNotSame($hoy, $resultado, 'Debe devolver copia, no la misma instancia de HOY.');
    }

    public function test_fecha_programada_inn_fuera_del_rango_o_sin_inicio_es_null(): void
    {
        $hoy = Carbon::create(2026, 6, 20)->startOfDay();
        $fechaFormula = $hoy->copy()->addDays(10.999);

        $fuera = $this->registro(['FechaInicio' => Carbon::create(2026, 8, 1)]);
        $this->assertNull($this->scheduling->calcularFechaProgramada($fuera, $hoy, $fechaFormula));

        $sinFecha = new ReqProgramaTejido;
        $this->assertNull($this->scheduling->calcularFechaProgramada($sinFecha, $hoy, $fechaFormula));
    }

    public function test_aplicar_programado_calculado_loguea_y_anula_fecha_invalida(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $mensaje, array $contexto): bool {
                return $mensaje === 'Error al procesar fecha'
                    && ($contexto['registro_id'] ?? null) === 77;
            });

        $reg = new ReqProgramaTejido;
        $reg->setRawAttributes(['Id' => 77, 'FechaInicio' => 'no-es-fecha']);
        $hoy = Carbon::create(2026, 6, 20)->startOfDay();

        $this->scheduling->aplicarProgramadoCalculado(collect([$reg]), $hoy, $hoy->copy()->addDays(10));

        $this->assertNull($reg->ProgramadoCalculado);
    }

    public function test_prioridad_anterior_elige_el_mas_cercano_del_mismo_salon_telar(): void
    {
        $candidatos = [
            LiberarProgramaScheduling::clavePrioridad('JACQUARD', '201') => [
                $this->candidato(1, 'TOALLA VIEJA', '2026-06-01 00:00:00'),
                $this->candidato(2, 'TOALLA MEDIA', '2026-06-10 00:00:00'),
                $this->candidato(3, 'TOALLA ACTUAL', '2026-06-20 00:00:00'),
                $this->candidato(4, 'TOALLA FUTURA', '2026-06-25 00:00:00'),
            ],
            LiberarProgramaScheduling::clavePrioridad('SMIT', '201') => [
                $this->candidato(99, 'OTRO SALON', '2026-06-19 00:00:00'),
            ],
        ];

        $actual = $this->registro([
            'Id' => 3,
            'FechaInicio' => '2026-06-20 00:00:00',
        ]);

        $this->assertSame(
            'SALDAR TOALLA MEDIA',
            $this->scheduling->prioridadAnterior($actual, $candidatos)
        );
    }

    public function test_prioridad_anterior_en_mismo_dia_usa_id_menor(): void
    {
        $clave = LiberarProgramaScheduling::clavePrioridad('JACQUARD', '201');
        $candidatos = [
            $clave => [
                $this->candidato(4, 'PRIMERA', '2026-06-20 00:00:00'),
                $this->candidato(5, 'SEGUNDA', '2026-06-20 00:00:00'),
                $this->candidato(6, 'TERCERA', '2026-06-20 00:00:00'),
            ],
        ];

        $actual = $this->registro([
            'Id' => 6,
            'FechaInicio' => '2026-06-20 00:00:00',
        ]);

        $this->assertSame(
            'SALDAR SEGUNDA',
            $this->scheduling->prioridadAnterior($actual, $candidatos)
        );
    }

    public function test_prioridad_anterior_sin_fecha_compara_solo_id(): void
    {
        $clave = LiberarProgramaScheduling::clavePrioridad('JACQUARD', '201');
        $candidatos = [
            $clave => [
                $this->candidato(1, 'ID UNO', null),
                $this->candidato(8, 'ID OCHO', null),
                $this->candidato(12, 'ID DOCE', null),
            ],
        ];

        $actual = $this->registro(['Id' => 12, 'FechaInicio' => null]);

        $this->assertSame(
            'SALDAR ID OCHO',
            $this->scheduling->prioridadAnterior($actual, $candidatos)
        );
    }

    public function test_prioridad_anterior_vacia_si_falta_telar_id_o_nombre(): void
    {
        $clave = LiberarProgramaScheduling::clavePrioridad('JACQUARD', '201');
        $candidatos = [
            $clave => [
                $this->candidato(1, '', '2026-06-01 00:00:00'),
            ],
        ];

        $sinTelar = $this->registro(['NoTelarId' => '   ']);
        $sinId = new ReqProgramaTejido;
        $sinId->NoTelarId = '201';
        $sinId->SalonTejidoId = 'JACQUARD';
        $sinId->FechaInicio = '2026-06-20 00:00:00';

        $conNombreVacio = $this->registro(['Id' => 2, 'FechaInicio' => '2026-06-20 00:00:00']);

        $this->assertSame('', $this->scheduling->prioridadAnterior($sinTelar, $candidatos));
        $this->assertSame('', $this->scheduling->prioridadAnterior($sinId, $candidatos));
        $this->assertSame('', $this->scheduling->prioridadAnterior($conNombreVacio, $candidatos));
    }

    public function test_candidatos_prioridad_agrupa_por_salon_y_telar(): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            ['NombreProducto' => 'A', 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FechaInicio' => '2026-06-01 00:00:00'],
            ['NombreProducto' => 'B', 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FechaInicio' => '2026-06-10 00:00:00'],
            ['NombreProducto' => 'C', 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'FechaInicio' => '2026-06-05 00:00:00'],
            ['NombreProducto' => 'D', 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '305', 'FechaInicio' => '2026-06-05 00:00:00'],
        ]);

        $lote = collect([
            $this->registro(['Id' => 2, 'NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD']),
            $this->registro(['Id' => 3, 'NoTelarId' => '201', 'SalonTejidoId' => 'SMIT']),
        ]);

        $grupos = $this->scheduling->candidatosPrioridadAnterior($lote);

        $this->assertCount(2, $grupos['JACQUARD|201']);
        $this->assertCount(1, $grupos['SMIT|201']);
        $this->assertArrayNotHasKey('SMIT|305', $grupos);
        $this->assertSame('A', $grupos['JACQUARD|201'][0]->NombreProducto);
        $this->assertSame('B', $grupos['JACQUARD|201'][1]->NombreProducto);
    }

    public function test_candidatos_prioridad_sin_telares_devuelve_vacio(): void
    {
        $lote = collect([
            $this->registro(['NoTelarId' => '']),
            $this->registro(['NoTelarId' => '   ']),
        ]);

        $this->assertSame([], $this->scheduling->candidatosPrioridadAnterior($lote));
    }

    public function test_aplicar_prioridad_anterior_asigna_saldar_del_previo(): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert([
            ['NombreProducto' => 'PREVIA', 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FechaInicio' => '2026-06-01 00:00:00'],
            ['NombreProducto' => 'ACTUAL', 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '201', 'FechaInicio' => '2026-06-20 00:00:00'],
        ]);

        $actual = ReqProgramaTejido::query()->where('NombreProducto', 'ACTUAL')->first();
        $this->assertNotNull($actual);

        $this->scheduling->aplicarPrioridadAnterior(collect([$actual]));

        $this->assertSame('SALDAR PREVIA', $actual->PrioridadAnterior);
    }

    public function test_clave_prioridad_recorta_salon_y_telar(): void
    {
        $this->assertSame('JACQUARD|201', LiberarProgramaScheduling::clavePrioridad('  JACQUARD  ', ' 201 '));
        $this->assertSame('|', LiberarProgramaScheduling::clavePrioridad(null, null));
    }
}
