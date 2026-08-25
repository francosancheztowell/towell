<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ArmaDatosDesarrollador;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Estos calculos los comparten ahora la captura de desarrollador y la de muestras.
 * Antes vivian duplicados y cada correccion se aplicaba solo a una de las dos.
 */
class ArmaDatosDesarrolladorTest extends TestCase
{
    private object $sujeto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sujeto = new class
        {
            use ArmaDatosDesarrollador;

            protected function modeloPrograma(): string
            {
                return ReqProgramaTejido::class;
            }
        };
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function llamar(string $metodo, ...$args)
    {
        $m = (new ReflectionClass($this->sujeto))->getMethod($metodo);
        $m->setAccessible(true);

        return $m->invoke($this->sujeto, ...$args);
    }

    // ── Codigo de dibujo ──────────────────────────────────────────────────

    public function test_el_codigo_se_normaliza_y_recibe_sufijo_segun_el_telar(): void
    {
        // Telares por debajo de 300 llevan sufijo JC5.
        $this->assertSame('AB12.JC5', $this->llamar('normalizeCodigoDibujo', '  ab 12 ', '101'));
        // De 300 en adelante, sin sufijo.
        $this->assertSame('AB12', $this->llamar('normalizeCodigoDibujo', 'ab12', '300'));
    }

    public function test_no_duplica_un_sufijo_que_ya_venia(): void
    {
        $this->assertSame('AB12.JC5', $this->llamar('normalizeCodigoDibujo', 'AB12.JC5', '101'));
        $this->assertSame('AB12.JC5', $this->llamar('normalizeCodigoDibujo', 'ab12.jcs', '101'));
    }

    public function test_un_codigo_vacio_no_genera_sufijo_suelto(): void
    {
        $this->assertSame('', $this->llamar('normalizeCodigoDibujo', '   ', '101'));
        $this->assertSame('', $this->llamar('normalizeCodigoDibujo', null, '101'));
    }

    // ── Minutos de cambio ─────────────────────────────────────────────────

    public function test_los_minutos_de_cambio_cruzan_la_medianoche(): void
    {
        $this->assertSame(40, $this->llamar('calcularMinutosCambio', '23:50', '00:30'));
        $this->assertSame(90, $this->llamar('calcularMinutosCambio', '06:00', '07:30'));
    }

    public function test_sin_alguna_de_las_dos_horas_no_hay_minutos(): void
    {
        $this->assertNull($this->llamar('calcularMinutosCambio', '06:00', null));
        $this->assertNull($this->llamar('calcularMinutosCambio', null, '07:30'));
    }

    // ── Fecha de inicio programada ────────────────────────────────────────

    public function test_la_fecha_programada_respeta_el_turno_de_noche(): void
    {
        // Se guarda a las 00:10 una hora final de las 23:50: pertenece al dia anterior.
        Carbon::setTestNow(Carbon::parse('2026-03-11 00:10:00'));

        $this->assertSame('2026-03-10 23:50:00', $this->llamar('construirFechaInicioProgramada', '23:50'));
    }

    public function test_una_hora_del_mismo_dia_no_se_desplaza(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 14:00:00'));

        $this->assertSame('2026-03-10 15:30:00', $this->llamar('construirFechaInicioProgramada', '15:30'));
    }

    // ── Longitud de lucha ─────────────────────────────────────────────────

    public function test_la_longitud_de_lucha_redondea_y_distingue_vacio_de_cero(): void
    {
        $this->assertSame(3, $this->llamar('normalizarLongitudLucha', '2.6'));
        $this->assertSame(0, $this->llamar('normalizarLongitudLucha', '0'));
        $this->assertNull($this->llamar('normalizarLongitudLucha', ''));
        $this->assertNull($this->llamar('normalizarLongitudLucha', null));
    }
}
