<?php

namespace Tests\Unit\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * PT-DUP-02 · comparación sombra: TejidoHelpers::resolverFechaFinal() / finDesdeHoras()
 * contra copias LITERALES del código que reemplazan (tal como estaba antes de PT-05).
 * Si alguna combinación diverge, el test lo dice; no se "arregla" la copia.
 */
class ResolverFechaFinalShadowTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        BalancearTejido::clearCalendarioLinesCache();
        $this->createTablaDbo('ReqCalendarioLine', [
            'CalendarioId' => 'text',
            'FechaInicio' => 'text',
            'FechaFin' => 'text',
        ]);

        // CAL: turnos de 06:00 a 22:00 durante 60 días (gap nocturno de 8 h).
        // CORTO: un solo turno de 8 h (se agota → fallback continuo).
        $filas = [];
        for ($d = 0; $d < 60; $d++) {
            $dia = Carbon::parse('2026-01-01')->addDays($d);
            $filas[] = ['CalendarioId' => 'CAL', 'FechaInicio' => $dia->copy()->setTime(6, 0)->format('Y-m-d H:i:s'), 'FechaFin' => $dia->copy()->setTime(22, 0)->format('Y-m-d H:i:s')];
        }
        $filas[] = ['CalendarioId' => 'CORTO', 'FechaInicio' => '2026-01-01 06:00:00', 'FechaFin' => '2026-01-01 14:00:00'];
        DB::table('ReqCalendarioLine')->insert($filas);
    }

    /** Forma A: la copia de UpdateTejido/DividirTejido×4/DuplicarTejido (idénticas). */
    private static function legacyFormaA(Carbon $inicio, float $horasNecesarias, $calendarioId): string
    {
        if ($horasNecesarias <= 0) {
            return $inicio->copy()->addDays(TejidoHelpers::DEFAULT_DURACION_DIAS)->format('Y-m-d H:i:s');
        } else {
            if (! empty($calendarioId)) {
                $fin = BalancearTejido::calcularFechaFinalDesdeInicio($calendarioId, $inicio, $horasNecesarias);
                if (! $fin) {
                    $fin = $inicio->copy()->addSeconds((int) round($horasNecesarias * 3600));
                }

                return $fin->format('Y-m-d H:i:s');
            } else {
                return $inicio->copy()->addSeconds((int) round($horasNecesarias * 3600))->format('Y-m-d H:i:s');
            }
        }
    }

    /** Núcleo de BalancearTejido::resolverInicioFin / DateHelpers / calendario masivo (h > 0). */
    private static function legacyNucleo(Carbon $inicio, float $horas, $calendarioId): string
    {
        $fin = ! empty($calendarioId)
            ? (BalancearTejido::calcularFechaFinalDesdeInicio($calendarioId, $inicio, $horas) ?: $inicio->copy()->addSeconds((int) round($horas * 3600)))
            : $inicio->copy()->addSeconds((int) round($horas * 3600));

        return $fin->format('Y-m-d H:i:s');
    }

    public static function matriz(): array
    {
        $casos = [];
        foreach ([null, '', 'CAL', 'CORTO', 'NOEXISTE'] as $cal) {
            foreach (['2026-01-05 08:00:00', '2026-01-05 23:30:00', '2026-01-05 21:59:59'] as $ini) {
                foreach ([-3.0, 0.0, 0.0001, 0.5, 7.25, 16.0, 30.123456, 1000.0] as $h) {
                    $casos[sprintf('%s|%s|%s', $cal ?? 'null', $ini, $h)] = [$cal, $ini, $h];
                }
            }
        }

        return $casos;
    }

    /** @dataProvider matriz */
    public function test_resolver_fecha_final_es_identico_a_la_forma_a(?string $cal, string $ini, float $h): void
    {
        $inicio = Carbon::parse($ini);

        $this->assertSame(
            self::legacyFormaA($inicio->copy(), $h, $cal),
            TejidoHelpers::resolverFechaFinal($inicio->copy(), $h, $cal)->format('Y-m-d H:i:s')
        );
    }

    /** @dataProvider matriz */
    public function test_fin_desde_horas_es_identico_al_nucleo_de_las_otras_formas(?string $cal, string $ini, float $h): void
    {
        if ($h <= 0) {
            // Las otras formas nunca llegan al núcleo con h <= 0 (cada una tiene su política).
            $this->assertTrue(true);

            return;
        }
        $inicio = Carbon::parse($ini);

        $this->assertSame(
            self::legacyNucleo($inicio->copy(), $h, $cal),
            TejidoHelpers::finDesdeHoras($inicio->copy(), $h, $cal)->format('Y-m-d H:i:s')
        );
    }

    public function test_la_matriz_si_ejercita_el_calendario_y_su_agotamiento(): void
    {
        $inicio = Carbon::parse('2026-01-05 08:00:00');

        // 14 h hasta las 22:00 + 2 h desde las 06:00 del día siguiente (no 00:00 continuo).
        $this->assertSame('2026-01-06 08:00:00', TejidoHelpers::resolverFechaFinal($inicio, 16, 'CAL')->format('Y-m-d H:i:s'));
        // CORTO se agota: fallback continuo.
        $this->assertSame('2026-01-06 00:00:00', TejidoHelpers::resolverFechaFinal($inicio, 16, 'CORTO')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-04 08:00:00', TejidoHelpers::resolverFechaFinal($inicio, 0, 'CAL')->format('Y-m-d H:i:s'));
    }

    public function test_no_muta_el_inicio(): void
    {
        $inicio = Carbon::parse('2026-01-05 08:00:00');
        TejidoHelpers::resolverFechaFinal($inicio, 10, 'CAL');
        TejidoHelpers::resolverFechaFinal($inicio, 0, null);

        $this->assertSame('2026-01-05 08:00:00', $inicio->format('Y-m-d H:i:s'));
    }
}
