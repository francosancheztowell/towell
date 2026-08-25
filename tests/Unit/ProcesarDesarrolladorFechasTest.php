<?php

namespace Tests\Unit;

use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\ProcesarDesarrolladorService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * El turno 3 va de 22:30 a 6:30, o sea cruza la medianoche. Con Carbon::today()
 * la hora capturada se pegaba al dia del servidor en el momento del envio, asi
 * que un arranque de las 23:50 enviado a las 00:10 quedaba sellado en el dia
 * siguiente al que realmente ocurrio.
 */
class ProcesarDesarrolladorFechasTest extends TestCase
{
    private function payload(?string $horaInicio, ?string $horaFinal): array
    {
        $clase = new ReflectionClass(ProcesarDesarrolladorService::class);
        $servicio = $clase->newInstanceWithoutConstructor();
        $metodo = $clase->getMethod('buildFechasArranqueFinalizaPayload');
        $metodo->setAccessible(true);

        return $metodo->invoke($servicio, $horaInicio, $horaFinal);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_turno_de_noche_enviado_pasada_la_medianoche_se_sella_en_el_dia_correcto(): void
    {
        // Se captura un arranque de las 23:50 y se guarda a las 00:10 del dia siguiente.
        Carbon::setTestNow(Carbon::parse('2026-03-11 00:10:00'));

        $payload = $this->payload('23:50', null);

        $this->assertSame('2026-03-10 23:50:00', $payload['FechaArranque']);
    }

    public function test_una_captura_normal_del_dia_no_se_mueve(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 14:00:00'));

        $payload = $this->payload('06:30', null);

        $this->assertSame('2026-03-10 06:30:00', $payload['FechaArranque']);
    }

    public function test_la_hora_final_que_cruza_medianoche_cae_al_dia_siguiente_del_arranque(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-11 00:10:00'));

        $payload = $this->payload('23:50', '00:30');

        $this->assertSame('2026-03-10 23:50:00', $payload['FechaArranque']);
        $this->assertSame('2026-03-11 00:30:00', $payload['FechaFinaliza']);
    }

    public function test_sin_hora_de_inicio_no_inventa_fecha(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 14:00:00'));

        $this->assertNull($this->payload(null, null)['FechaArranque']);
    }
}
