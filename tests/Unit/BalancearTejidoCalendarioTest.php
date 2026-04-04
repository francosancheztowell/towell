<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Tests unitarios para los métodos de calendario extraídos de BalancearTejido:
 *  - resolverInicioFin()
 *  - iterarLineasActivas()
 *  - calcularFechaFinalDesdeInicio()
 *  - calcularHorasDisponiblesHastaFecha() (vía calcularFechaFinalDesdeInicio inverso)
 *
 * No requieren BD: operan sobre Carbon puro y arrays de líneas en memoria.
 */
class BalancearTejidoCalendarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BalancearTejido::clearCalendarioLinesCache();
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * Construye un ReqProgramaTejido en memoria (sin persistir) con atributos mínimos
     * suficientes para calcularHorasProd y resolverInicioFin.
     */
    private function makeReg(array $attrs = []): ReqProgramaTejido
    {
        $defaults = [
            'SaldoPedido'  => 5000,
            'TotalPedido'  => 5000,
            'Produccion'   => 0,
            'VelocidadSTD' => 100.0,
            'EficienciaSTD'=> 0.85,
            'NoTiras'      => 4.0,
            'Luchaje'      => 200.0,
            'Repeticiones' => 16.0,
            'CalendarioId' => null,
            'EnProceso'    => 0,
            'FechaInicio'  => '2026-01-01 06:00:00',
            'NombreProducto' => 'TOALLA STD',
        ];
        $reg = new ReqProgramaTejido(array_merge($defaults, $attrs));
        // TamanoClave vacío → obtenerModeloParams retorna total=0 →
        // en estos tests seteamos Total a través del modelo directamente
        if (isset($attrs['Total'])) {
            $reg->Total = $attrs['Total'];
        }
        return $reg;
    }

    /**
     * Construye un array de líneas de calendario en el formato que usa BalancearTejido
     * (getCalendarioLines): [{ini, fin, ini_ts, fin_ts}, ...]
     *
     * @param  array<array{0:string,1:string}>  $periodos  [['Y-m-d H:i:s', 'Y-m-d H:i:s'], ...]
     */
    private function makeLines(array $periodos): array
    {
        return array_map(function (array $p): array {
            $ini = Carbon::parse($p[0]);
            $fin = Carbon::parse($p[1]);
            return [
                'ini'    => $ini,
                'fin'    => $fin,
                'ini_ts' => $ini->getTimestamp(),
                'fin_ts' => $fin->getTimestamp(),
            ];
        }, $periodos);
    }

    // =========================================================
    // resolverInicioFin
    // =========================================================

    public function test_resolver_inicio_fin_sin_calendario_retorna_inicio_sin_snap(): void
    {
        $reg    = $this->makeReg(['CalendarioId' => null, 'SaldoPedido' => 0]);
        $inicio = Carbon::parse('2026-01-05 08:00:00');

        [$ini, $fin, $horas] = BalancearTejido::resolverInicioFin($inicio->copy(), $reg);

        // Sin horas (SaldoPedido=0 → calcularHorasProd=0) → fallback de duración
        $this->assertSame('2026-01-05 08:00:00', $ini->format('Y-m-d H:i:s'));
        $this->assertSame(0.0, $horas);
        // Fallback no-repaso = 30 días
        $this->assertSame('2026-02-04 08:00:00', $fin->format('Y-m-d H:i:s'));
    }

    public function test_resolver_inicio_fin_repaso_fallback_usa_12_horas(): void
    {
        $reg    = $this->makeReg(['CalendarioId' => null, 'SaldoPedido' => 0, 'NombreProducto' => 'REPASO ESPECIAL']);
        $inicio = Carbon::parse('2026-01-05 08:00:00');

        [, $fin, $horas] = BalancearTejido::resolverInicioFin($inicio->copy(), $reg);

        $this->assertSame(0.0, $horas);
        $this->assertSame('2026-01-05 20:00:00', $fin->format('Y-m-d H:i:s'));
    }

    public function test_resolver_inicio_fin_aplicar_snap_false_no_modifica_inicio(): void
    {
        // Inicio cae en GAP (fuera de cualquier línea de calendario)
        // Pero con aplicarSnap=false, el inicio no debe moverse
        $reg    = $this->makeReg(['CalendarioId' => null, 'SaldoPedido' => 0]);
        $inicio = Carbon::parse('2026-01-05 03:00:00'); // hora de madrugada

        [$ini] = BalancearTejido::resolverInicioFin($inicio->copy(), $reg, false);

        $this->assertSame('2026-01-05 03:00:00', $ini->format('Y-m-d H:i:s'));
    }

    // =========================================================
    // iterarLineasActivas
    // =========================================================

    public function test_iterar_lineas_activas_consume_segmento_completo(): void
    {
        $lines = $this->makeLines([
            ['2026-01-01 06:00:00', '2026-01-01 22:00:00'], // 16 h
        ]);

        $consumidos = 0;
        $cursor     = Carbon::parse('2026-01-01 06:00:00');

        [$cursorFinal, $exhausted] = BalancearTejido::iterarLineasActivas(
            $lines,
            $cursor,
            function (int $disponibles) use (&$consumidos): array {
                $consumidos += $disponibles;
                return [$disponibles, false]; // consumir todo y parar
            }
        );

        $this->assertFalse($exhausted);
        $this->assertSame(16 * 3600, $consumidos);
        $this->assertSame('2026-01-01 22:00:00', $cursorFinal->format('Y-m-d H:i:s'));
    }

    public function test_iterar_lineas_activas_salta_gap_y_avanza_a_siguiente_linea(): void
    {
        $lines = $this->makeLines([
            ['2026-01-01 08:00:00', '2026-01-01 14:00:00'], // 6 h
            ['2026-01-01 16:00:00', '2026-01-01 22:00:00'], // 6 h (con gap)
        ]);

        $segmentos = 0;
        $cursor    = Carbon::parse('2026-01-01 06:00:00'); // antes de la primera línea

        BalancearTejido::iterarLineasActivas(
            $lines,
            $cursor,
            function (int $disponibles) use (&$segmentos): array {
                $segmentos++;
                return [$disponibles, true]; // consumir y continuar
            }
        );

        $this->assertSame(2, $segmentos, 'Debe procesar exactamente 2 segmentos');
    }

    public function test_iterar_lineas_activas_retorna_exhausted_true_cuando_no_hay_lineas(): void
    {
        $cursor = Carbon::parse('2026-01-01 06:00:00');

        [, $exhausted] = BalancearTejido::iterarLineasActivas(
            [],
            $cursor,
            fn ($d) => [$d, true]
        );

        $this->assertTrue($exhausted);
    }

    public function test_iterar_lineas_activas_retorna_exhausted_true_cuando_lineas_insuficientes(): void
    {
        $lines = $this->makeLines([
            ['2026-01-01 06:00:00', '2026-01-01 10:00:00'], // solo 4 h
        ]);

        $cursor = Carbon::parse('2026-01-01 06:00:00');

        // Callback nunca detiene → se agota
        [, $exhausted] = BalancearTejido::iterarLineasActivas(
            $lines,
            $cursor,
            fn ($d) => [$d, true]
        );

        $this->assertTrue($exhausted);
    }

    // =========================================================
    // calcularFechaFinalDesdeInicio
    // =========================================================

    public function test_calcular_fecha_final_sin_lineas_retorna_null(): void
    {
        // El calendario ID no existe → getCalendarioLines retorna []
        // → iterarLineasActivas → exhausted=true → null
        $result = BalancearTejido::calcularFechaFinalDesdeInicio(
            'CAL_NO_EXISTE',
            Carbon::parse('2026-01-01 08:00:00'),
            4.0
        );

        $this->assertNull($result);
    }

    public function test_calcular_fecha_final_cero_horas_retorna_mismo_inicio(): void
    {
        $inicio = Carbon::parse('2026-01-15 10:00:00');
        $result = BalancearTejido::calcularFechaFinalDesdeInicio('CAL_X', $inicio, 0.0);

        $this->assertNotNull($result);
        $this->assertSame($inicio->format('Y-m-d H:i:s'), $result->format('Y-m-d H:i:s'));
    }

    // =========================================================
    // calcularFechaFinalDesdeInicio con líneas reales (vía cache inyectado)
    // Verificamos la aritmética del motor de iteración usando líneas simples.
    // =========================================================

    /**
     * Inyecta líneas en el cache privado para poder probar sin BD.
     */
    private function inyectarLineasCache(string $calId, array $lines): void
    {
        // getCalendarioLines usa un static array $calLinesCache.
        // Lo poblamos llamando al método público clearCalendarioLinesCache primero,
        // luego inyectando vía Reflection.
        BalancearTejido::clearCalendarioLinesCache();

        $ref  = new \ReflectionClass(BalancearTejido::class);
        $prop = $ref->getProperty('calLinesCache');
        $prop->setAccessible(true);
        $prop->setValue(null, [$calId => $lines]);
    }

    public function test_calcular_fecha_final_consume_horas_en_linea_continua(): void
    {
        $calId = 'TEST_CAL';
        $this->inyectarLineasCache($calId, $this->makeLines([
            ['2026-01-01 06:00:00', '2026-01-02 06:00:00'], // 24 h
        ]));

        $inicio = Carbon::parse('2026-01-01 06:00:00');
        $result = BalancearTejido::calcularFechaFinalDesdeInicio($calId, $inicio, 8.0);

        $this->assertNotNull($result);
        $this->assertSame('2026-01-01 14:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_calcular_fecha_final_cruza_gap_entre_lineas(): void
    {
        $calId = 'TEST_CAL_GAP';
        $this->inyectarLineasCache($calId, $this->makeLines([
            ['2026-01-01 06:00:00', '2026-01-01 14:00:00'], // 8 h
            ['2026-01-01 16:00:00', '2026-01-02 06:00:00'], // 14 h (tras 2 h de gap)
        ]));

        // Necesita 10 h: consume 8 h en línea 1, salta gap, consume 2 h en línea 2
        $inicio = Carbon::parse('2026-01-01 06:00:00');
        $result = BalancearTejido::calcularFechaFinalDesdeInicio($calId, $inicio, 10.0);

        $this->assertNotNull($result);
        $this->assertSame('2026-01-01 18:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_calcular_fecha_final_inicio_dentro_de_linea(): void
    {
        $calId = 'TEST_CAL_MID';
        $this->inyectarLineasCache($calId, $this->makeLines([
            ['2026-01-01 06:00:00', '2026-01-01 22:00:00'], // 16 h
        ]));

        // Inicio en mitad de la línea: quedan 12 h disponibles
        $inicio = Carbon::parse('2026-01-01 10:00:00');
        $result = BalancearTejido::calcularFechaFinalDesdeInicio($calId, $inicio, 6.0);

        $this->assertNotNull($result);
        $this->assertSame('2026-01-01 16:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function test_calcular_fecha_final_retorna_null_cuando_lineas_insuficientes(): void
    {
        $calId = 'TEST_CAL_SHORT';
        $this->inyectarLineasCache($calId, $this->makeLines([
            ['2026-01-01 06:00:00', '2026-01-01 10:00:00'], // solo 4 h
        ]));

        // Pedimos 10 h pero solo hay 4 → null
        $result = BalancearTejido::calcularFechaFinalDesdeInicio($calId, Carbon::parse('2026-01-01 06:00:00'), 10.0);

        $this->assertNull($result);
    }

    // =========================================================
    // Simetría: calcularFechaFinalDesdeInicio inverso de calcularHorasDisponibles
    // (ambos usan iterarLineasActivas; verificamos consistencia)
    // =========================================================

    public function test_horas_disponibles_entre_inicio_y_fin_calculado_son_consistentes(): void
    {
        $calId = 'TEST_SIM';
        $this->inyectarLineasCache($calId, $this->makeLines([
            ['2026-01-01 06:00:00', '2026-01-01 14:00:00'], // 8 h
            ['2026-01-01 16:00:00', '2026-01-02 00:00:00'], // 8 h
        ]));

        $inicio = Carbon::parse('2026-01-01 06:00:00');
        $horas  = 12.0;

        $fin = BalancearTejido::calcularFechaFinalDesdeInicio($calId, $inicio, $horas);
        $this->assertNotNull($fin, 'Debe haber suficientes horas en el calendario');

        // Ahora usamos calcularHorasDisponiblesHastaFecha para verificar la inversa
        // El método es private, lo invocamos vía Reflection
        $ref    = new \ReflectionClass(BalancearTejido::class);
        $method = $ref->getMethod('calcularHorasDisponiblesHastaFecha');
        $method->setAccessible(true);

        $horasCalculadas = $method->invoke(null, $calId, $inicio, $fin);

        $this->assertEqualsWithDelta($horas, $horasCalculadas, 0.01,
            'Las horas disponibles hasta el fin calculado deben coincidir con las horas solicitadas');
    }
}
