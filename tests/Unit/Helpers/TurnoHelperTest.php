<?php

namespace Tests\Unit\Helpers;

use App\Helpers\TurnoHelper;
use App\Http\Controllers\Tejido\CortesEficiencia\CortesEficienciaController;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Limites de turno (6:30 / 14:30 / 22:30, America/Mexico_City) y la respuesta
 * del endpoint /turno-info de Cortes, que debe seguir igual tras la fase 20-01.
 * (El /turno-info de Trama lo quitó ERP-F0-08 por no tener consumidor.)
 */
class TurnoHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * El reloj se fija en UTC a proposito: el helper debe convertir a
     * America/Mexico_City (UTC-6 todo el año desde 2022, sin horario de verano).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function limites(): array
    {
        return [
            '00:00 turno 3' => ['2026-03-10 06:00:00', '3'],
            '06:29:59 turno 3' => ['2026-03-10 12:29:59', '3'],
            '06:30 turno 1' => ['2026-03-10 12:30:00', '1'],
            '14:29:59 turno 1' => ['2026-03-10 20:29:59', '1'],
            '14:30 turno 2' => ['2026-03-10 20:30:00', '2'],
            '22:29:59 turno 2' => ['2026-03-11 04:29:59', '2'],
            '22:30 turno 3' => ['2026-03-11 04:30:00', '3'],
            '23:59 turno 3' => ['2026-03-11 05:59:00', '3'],
            'julio 06:30 turno 1 (sin horario de verano)' => ['2026-07-10 12:30:00', '1'],
        ];
    }

    #[DataProvider('limites')]
    public function test_turno_actual_en_los_limites(string $utc, string $turno): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));

        $this->assertSame($turno, TurnoHelper::getTurnoActual());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function fechasProduccion(): array
    {
        return [
            'madrugada es de ayer' => ['2026-03-10 06:29', '2026-03-09'],
            'desde 06:30 es de hoy' => ['2026-03-10 06:30', '2026-03-10'],
            'dia 1 antes de 08:30 cierra el mes anterior' => ['2026-04-01 08:29', '2026-03-31'],
            'dia 1 desde 08:30 es del dia 1' => ['2026-04-01 08:30', '2026-04-01'],
        ];
    }

    #[DataProvider('fechasProduccion')]
    public function test_fecha_produccion(string $mexico, string $esperada): void
    {
        Carbon::setTestNow(Carbon::parse($mexico, 'America/Mexico_City'));

        $this->assertSame($esperada, TurnoHelper::getFechaProduccion());
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function turnosInfo(): array
    {
        return [
            'turno 1' => ['2026-03-10 06:30', '1', '6:30 AM - 2:30 PM', 'Turno 1'],
            'turno 2' => ['2026-03-10 14:30', '2', '2:30 PM - 10:30 PM', 'Turno 2'],
            'turno 3' => ['2026-03-10 22:30', '3', '10:30 PM - 6:30 AM', 'Turno 3'],
        ];
    }

    #[DataProvider('turnosInfo')]
    public function test_turno_info_de_cortes_de_eficiencia(string $mexico, string $turno, string $horario, string $formato): void
    {
        Carbon::setTestNow(Carbon::parse($mexico, 'America/Mexico_City'));

        $respuesta = app(CortesEficienciaController::class)->getTurnoInfo();

        $this->assertSame(200, $respuesta->getStatusCode());
        $this->assertSame([
            'success' => true,
            'turno' => $turno,
            'descripcion' => $horario,
        ], $respuesta->getData(true));
    }
}
