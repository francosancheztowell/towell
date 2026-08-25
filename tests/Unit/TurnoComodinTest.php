<?php

namespace Tests\Unit;

use App\Helpers\TurnoHelper;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El turno 4 es comodín: cubre a los turnos 1, 2 o 3 cuando falta gente y no tiene
 * horario propio. Vive sólo en SYSUsuario.turno; en las tablas de producción el
 * registro se guarda con la ventana de reloj que cubrió.
 */
class TurnoComodinTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enHora(string $hora): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-24 '.$hora, 'America/Mexico_City'));
    }

    public static function casosResolucion(): array
    {
        return [
            // caso                        hora     turno empleado  esperado
            'turno 1 no se toca' => ['23:40', 1, 1],
            'turno 3 no se toca' => ['07:00', 3, 3],
            'turno 2 no se toca' => ['02:00', 2, 2],
            'comodín inicio T1' => ['06:30', 4, 1],
            'comodín borde previo a T1' => ['06:29', 4, 3],
            'comodín fin T1' => ['14:29', 4, 1],
            'comodín inicio T2' => ['14:30', 4, 2],
            'comodín fin T2' => ['22:29', 4, 2],
            'comodín inicio T3' => ['22:30', 4, 3],
            'comodín medianoche' => ['00:00', 4, 3],
            'comodín madrugada' => ['03:15', 4, 3],
            'comodín como string' => ['12:00', '4', 1],
            'nulo' => ['12:00', null, 1],
            'vacío' => ['12:00', '', 1],
            'basura' => ['12:00', 'X', 1],
            'fuera de rango' => ['12:00', 9, 1],
            'negativo' => ['12:00', -1, 1],
        ];
    }

    #[DataProvider('casosResolucion')]
    public function test_resolver_turno_operativo(string $hora, mixed $turnoEmpleado, int $esperado): void
    {
        $this->enHora($hora);

        $this->assertSame($esperado, TurnoHelper::resolverTurnoOperativo($turnoEmpleado));
    }

    public function test_resolver_turno_operativo_nunca_devuelve_4(): void
    {
        foreach (['00:00', '06:29', '06:30', '14:29', '14:30', '22:29', '22:30', '23:59'] as $hora) {
            $this->enHora($hora);
            foreach ([1, 2, 3, 4, '4', null, '', 'X', 9] as $turno) {
                $this->assertContains(
                    TurnoHelper::resolverTurnoOperativo($turno),
                    [1, 2, 3],
                    "resolverTurnoOperativo devolvió un turno fuera de 1-3 para hora {$hora}"
                );
            }
        }
    }

    public function test_es_comodin(): void
    {
        $this->assertTrue(TurnoHelper::esComodin(4));
        $this->assertTrue(TurnoHelper::esComodin('4'));

        foreach ([1, 2, 3, '1', '3', null, '', 'X', 0, 5] as $turno) {
            $this->assertFalse(TurnoHelper::esComodin($turno), 'esComodin() marcó como comodín: '.var_export($turno, true));
        }
    }

    public function test_turno_4_tiene_descripcion_valida(): void
    {
        $this->assertNotSame('Turno no válido', TurnoHelper::getDescripcionTurno('4'));
        $this->assertSame('Turno 4', TurnoHelper::getTurnoFormato('4'));

        // Un turno realmente inválido sí debe seguir avisando.
        $this->assertSame('Turno no válido', TurnoHelper::getTurnoFormato('9'));
    }

    public function test_get_turno_actual_sigue_sin_conocer_el_4(): void
    {
        foreach (['00:00', '06:30', '14:30', '22:30'] as $hora) {
            $this->enHora($hora);
            $this->assertContains(TurnoHelper::getTurnoActual(), ['1', '2', '3']);
        }
    }
}
