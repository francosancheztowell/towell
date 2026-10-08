<?php

namespace Tests\Unit\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use App\Services\Planeacion\ProgramaTejido\SecuenciaFechasTelar;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Caracterización de la cadena de fechas por telar (SecuenciaFechasTelar::recalcular y cascade)
 * y de la fachada DateHelpers. Los golden (tests/fixtures/planeacion/secuencia-fechas) son la
 * salida literal del código original de DateHelpers; si cambian, el refactor cambió comportamiento.
 */
class SecuenciaFechasTelarCharacterizationTest extends TestCase
{
    use ProgramaTejidoFixtures;

    private const CAL = 'CAL SEQ';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);
        CalendarioProduccion::limpiarCache();

        // Turnos de 06:00 a 22:00 (gap nocturno de 8 h) durante 60 días.
        $filas = [];
        for ($d = 0; $d < 60; $d++) {
            $dia = Carbon::parse('2026-09-01')->addDays($d);
            $filas[] = ['CalendarioId' => self::CAL, 'FechaInicio' => $dia->copy()->setTime(6, 0)->format('Y-m-d H:i:s'), 'FechaFin' => $dia->copy()->setTime(22, 0)->format('Y-m-d H:i:s')];
        }
        DB::table('ReqCalendarioLine')->insert($filas);
        DB::table((new ReqModelosCodificados)->getTable())->insert(['TamanoClave' => 'TC-SEQ', 'Total' => 1200]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CalendarioProduccion::limpiarCache();
        parent::tearDown();
    }

    private function registro(array $attrs): ReqProgramaTejido
    {
        $r = new ReqProgramaTejido;
        $r->forceFill(array_merge(['SalonTejidoId' => 'SMIT', 'NoTelarId' => '201'], $attrs));

        return $r;
    }

    /** Cubre: EnProceso (now), snap a calendario, fórmulas por modelo, Karl Mayer, saldo negativo, sin horas (duración previa, repaso, 30 días), CambioHilo. */
    private function secuencia(): Collection
    {
        return collect([
            $this->registro(['Id' => 11, 'EnProceso' => 1, 'FibraRizo' => 'A', 'CalendarioId' => self::CAL, 'TamanoClave' => 'TC-SEQ',
                'VelocidadSTD' => 400, 'EficienciaSTD' => 80, 'NoTiras' => 2, 'Luchaje' => 30, 'Repeticiones' => 3, 'SaldoPedido' => 500,
                'PesoCrudo' => 450, 'LargoToalla' => 70, 'AnchoToalla' => 40, 'AplicacionId' => 'NA',
                'FechaInicio' => '2026-09-01 06:30:00', 'FechaFinal' => '2026-09-03 18:00:00']),
            $this->registro(['Id' => 12, 'EnProceso' => 0, 'FibraRizo' => 'A', 'CalendarioId' => self::CAL, 'HorasProd' => 10,
                'SaldoPedido' => 300, 'AplicacionId' => 'X1', 'EntregaCte' => '2026-10-20 00:00:00',
                'FechaInicio' => '2026-09-03 18:00:00', 'FechaFinal' => '2026-09-04 06:00:00']),
            $this->registro(['Id' => 13, 'EnProceso' => 0, 'FibraRizo' => 'B ', 'SaldoPedido' => -5,
                'FechaInicio' => '2026-09-05 06:00:00', 'FechaFinal' => '2026-09-05 18:00:00']),
            $this->registro(['Id' => 14, 'EnProceso' => 0, 'FibraRizo' => 'B', 'SaldoPedido' => 100,
                'FechaInicio' => '2026-09-05 06:00:00', 'FechaFinal' => '2026-09-06 12:30:00']),
            $this->registro(['Id' => 15, 'EnProceso' => 0, 'FibraRizo' => 'C', 'NombreProducto' => 'REPASO X', 'SaldoPedido' => '1,000']),
            $this->registro(['Id' => 16, 'EnProceso' => 0, 'FibraRizo' => 'C', 'SaldoPedido' => 0]),
            $this->registro(['Id' => 17, 'EnProceso' => 0, 'FibraRizo' => 'C', 'SalonTejidoId' => 'KARL MAYER', 'NoTelarId' => '401',
                'PesoCrudo' => 500, 'SaldoPedido' => 200, 'EficienciaSTD' => 0.7, 'CalendarioId' => self::CAL]),
        ]);
    }

    /** Carbon → 'Y-m-d H:i:s' y llaves ordenadas, para comparar contra el golden en JSON. */
    private function normalizar(mixed $valor): mixed
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }
        if (is_array($valor)) {
            $valor = array_map(fn ($v) => $this->normalizar($v), $valor);
            ksort($valor);
        }

        return $valor;
    }

    /** Golden master: GOLDEN_UPDATE=1 lo regenera (solo con el código original). */
    private function assertGolden(string $nombre, mixed $actual): void
    {
        $ruta = base_path("tests/fixtures/planeacion/secuencia-fechas/{$nombre}.json");
        $actual = $this->normalizar($actual);
        if (getenv('GOLDEN_UPDATE') === '1') {
            @mkdir(dirname($ruta), 0777, true);
            file_put_contents($ruta, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)."\n");
        }

        $this->assertSame(json_decode((string) file_get_contents($ruta), true), json_decode(json_encode($actual, JSON_PRESERVE_ZERO_FRACTION), true));
    }

    public function test_recalcular_fechas_secuencia_conserva_la_salida(): void
    {
        [$updates, $detalles] = SecuenciaFechasTelar::recalcular($this->secuencia(), Carbon::parse('2026-09-02 23:00:00'));

        $this->assertGolden('recalcular', ['updates' => $updates, 'detalles' => $detalles]);
    }

    public function test_recalcular_fechas_secuencia_ajusta_el_inicio_al_calendario(): void
    {
        $secuencia = collect([
            $this->registro(['Id' => 21, 'EnProceso' => 0, 'FibraRizo' => 'A', 'CalendarioId' => self::CAL, 'HorasProd' => 3, 'SaldoPedido' => 50]),
            $this->registro(['Id' => 22, 'EnProceso' => 0, 'FibraRizo' => 'A', 'CalendarioId' => self::CAL, 'HorasProd' => 2, 'SaldoPedido' => 50]),
        ]);

        [$updates, $detalles] = SecuenciaFechasTelar::recalcular($secuencia, Carbon::parse('2026-09-12 23:00:00'));

        $this->assertGolden('recalcular-snap', ['updates' => $updates, 'detalles' => $detalles]);
    }

    public function test_recalcular_fechas_secuencia_vacia(): void
    {
        $this->assertSame([[], []], DateHelpers::recalcularFechasSecuencia(collect(), Carbon::now()));
    }

    public function test_cascade_fechas_conserva_la_salida(): void
    {
        $tabla = ReqProgramaTejido::tableName();
        DB::table($tabla)->where('Id', 2)->update(['Ultimo' => '0', 'FibraRizo' => 'Z', 'CalendarioId' => self::CAL, 'HorasProd' => 20]);
        DB::table($tabla)->insert([
            ['Id' => 6, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 3, 'EnProceso' => 0, 'FibraRizo' => 'Z',
                'TotalPedido' => 300, 'SaldoPedido' => -1, 'FechaInicio' => '2026-09-05 06:00:00', 'FechaFinal' => '2026-09-05 18:00:00', 'Ultimo' => '0'],
            ['Id' => 7, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 4, 'EnProceso' => 0, 'FibraRizo' => 'Y',
                'TotalPedido' => 300, 'SaldoPedido' => 300, 'FechaInicio' => '2026-09-06 06:00:00', 'FechaFinal' => '2026-09-07 08:15:00', 'Ultimo' => '1'],
        ]);

        $actualizado = ReqProgramaTejido::query()->find(1);
        $actualizado->FechaFinal = '2026-09-04 10:00:00';

        $detalles = SecuenciaFechasTelar::cascade($actualizado);
        $filas = DB::table($tabla)->where('NoTelarId', '201')->orderBy('Posicion')
            ->get(['Id', 'FechaInicio', 'FechaFinal', 'Ultimo', 'CambioHilo', 'HorasProd', 'DiasEficiencia', 'StdHrsEfect', 'EntregaCte', 'EntregaPT', 'EntregaProduc', 'PTvsCte'])
            ->map(fn ($r) => (array) $r)->all();

        $this->assertGolden('cascade', ['detalles' => $detalles, 'filas' => $filas]);
    }

    public function test_cascade_fechas_de_un_registro_que_no_esta_en_el_telar_no_hace_nada(): void
    {
        $fantasma = $this->registro(['Id' => 999, 'FechaFinal' => '2026-09-04 10:00:00']);

        $this->assertSame([], DateHelpers::cascadeFechas($fantasma));
    }
}
