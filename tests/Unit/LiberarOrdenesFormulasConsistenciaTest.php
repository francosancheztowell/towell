<?php

namespace Tests\Unit;

use App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Garantiza la invariante de las fórmulas de liberación de órdenes:
 *   PzasRollo  = round(Repeticiones × NoTiras)        (piezas por rollo por definición)
 *   TotalRollos = ceil(SaldoPedido / PzasRollo)        (salvo override del usuario)
 *   TotalPzas  = round(PzasRollo × TotalRollos)        (= Repeticiones × NoTiras × TotalRollos)
 *
 * El defecto histórico (p.ej. orden 36643) era que un PzasRollo viejo/almacenado (636) sobrevivía
 * aunque cambiara el peso crudo, corrompiendo TotalRollos y TotalPzas. Estos helpers ya no aceptan
 * ningún valor previo: derivan TODO desde Repeticiones, así que no puede volver a desfasarse.
 */
class LiberarOrdenesFormulasConsistenciaTest extends TestCase
{
    private LiberarOrdenesController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new LiberarOrdenesController;
    }

    private function method(string $name): ReflectionMethod
    {
        $m = (new ReflectionClass(LiberarOrdenesController::class))->getMethod($name);
        $m->setAccessible(true);

        return $m;
    }

    /** Caso exacto reportado: orden 36643 (peso crudo cambió de ~64 a 121). */
    public function test_orden_36643_queda_consistente(): void
    {
        $reps = $this->method('repeticionesDesdePesoRollo');
        $pzasM = $this->method('pzasRolloDesdeRepeticiones');
        $totM = $this->method('derivarTotalRollosTotalPzas');

        // PesoRollo default 41.5, PesoCrudo 121, NoTiras 6 → Repeticiones 57
        $repeticiones = $reps->invoke($this->controller, 41.5, 121, 6);
        $this->assertSame(57, $repeticiones);

        $pzasRollo = $pzasM->invoke($this->controller, $repeticiones, 6);
        $this->assertSame(342.0, $pzasRollo, 'PzasRollo debe ser 57×6=342, NO el 636 viejo.');
        $this->assertNotSame(636.0, $pzasRollo);

        ['totalRollos' => $totalRollos, 'totalPzas' => $totalPzas] =
            $totM->invoke($this->controller, $pzasRollo, 4104, null);

        $this->assertSame(12.0, $totalRollos, 'ceil(4104/342)=12, NO 7.');
        $this->assertSame(4104.0, $totalPzas, '342×12=4104, NO 4452.');
    }

    /** PzasRollo se deriva solo de Repeticiones×NoTiras; no existe forma de inyectar un valor viejo. */
    public function test_pzas_rollo_solo_depende_de_repeticiones_y_tiras(): void
    {
        $pzasM = $this->method('pzasRolloDesdeRepeticiones');

        $this->assertSame(342.0, $pzasM->invoke($this->controller, 57, 6));
        $this->assertSame(636.0, $pzasM->invoke($this->controller, 106, 6)); // si las reps fueran 106
        $this->assertNull($pzasM->invoke($this->controller, 0, 6));
        $this->assertNull($pzasM->invoke($this->controller, 57, 0));
        $this->assertNull($pzasM->invoke($this->controller, null, 6));

        // La firma del método NO admite un parámetro de "valor almacenado" → imposible desfasarse.
        $this->assertSame(2, $this->method('pzasRolloDesdeRepeticiones')->getNumberOfParameters());
    }

    /** Override del usuario en TotalRollos: TotalPzas debe seguir = PzasRollo × TotalRollos. */
    public function test_override_total_rollos_recalcula_total_pzas(): void
    {
        $totM = $this->method('derivarTotalRollosTotalPzas');

        // Usuario fuerza 7 rollos con PzasRollo correcto de 342.
        ['totalRollos' => $tr, 'totalPzas' => $tp] = $totM->invoke($this->controller, 342.0, 4104, 7);
        $this->assertSame(7.0, $tr);
        $this->assertSame(2394.0, $tp, '342×7=2394.');

        // Override con decimal se redondea hacia arriba (techo).
        ['totalRollos' => $tr2] = $totM->invoke($this->controller, 342.0, 4104, 6.2);
        $this->assertSame(7.0, $tr2);
    }

    /** Ajuste FEL: PzasRollo se divide ÷2 y los totales siguen consistentes con el PzasRollo ajustado. */
    public function test_fel_divide_pzas_y_totales_quedan_consistentes(): void
    {
        $pzasM = $this->method('pzasRolloDesdeRepeticiones');
        $felM = $this->method('aplicarAjusteFelMtsYpzas');
        $totM = $this->method('derivarTotalRollosTotalPzas');

        $pzasRollo = $pzasM->invoke($this->controller, 57, 6); // 342
        $mts = 100.0;

        // FEL: pzas 342 → 171
        $felM->invokeArgs($this->controller, ['MODELO-FEL', &$mts, &$pzasRollo]);
        $this->assertSame(171.0, $pzasRollo);

        ['totalRollos' => $tr, 'totalPzas' => $tp] = $totM->invoke($this->controller, $pzasRollo, 4104, null);
        $this->assertSame((float) ceil(4104 / 171), $tr);
        $this->assertSame(round(171.0 * $tr, 0), $tp);
    }

    /** Muchas órdenes: la invariante se cumple para cientos de combinaciones. */
    public function test_invariante_se_cumple_para_muchas_ordenes(): void
    {
        $reps = $this->method('repeticionesDesdePesoRollo');
        $pzasM = $this->method('pzasRolloDesdeRepeticiones');
        $totM = $this->method('derivarTotalRollosTotalPzas');
        $marbM = $this->method('saldoMarbeteDesdeFormula');

        $pesosRollo = [41.5, 50.0, 65.0, 90.0];
        $pesosCrudo = [40, 55, 64, 80, 100, 121, 150, 180, 220];
        $tirasList = [2, 4, 6, 8, 10, 12];
        $pedidos = [300, 500, 1000, 2500, 4104, 7777, 12000];

        $casosVerificados = 0;

        foreach ($pesosRollo as $pesoRollo) {
            foreach ($pesosCrudo as $pesoCrudo) {
                foreach ($tirasList as $tiras) {
                    $repeticiones = $reps->invoke($this->controller, $pesoRollo, $pesoCrudo, $tiras);
                    if ($repeticiones === null || $repeticiones <= 0) {
                        continue;
                    }

                    $pzasRollo = $pzasM->invoke($this->controller, $repeticiones, $tiras);

                    // PzasRollo = round(Repeticiones × NoTiras), exacto.
                    $this->assertSame(round($repeticiones * $tiras, 0), $pzasRollo);

                    foreach ($pedidos as $pedido) {
                        ['totalRollos' => $tr, 'totalPzas' => $tp] =
                            $totM->invoke($this->controller, $pzasRollo, $pedido, null);

                        $ctx = "pesoRollo=$pesoRollo pesoCrudo=$pesoCrudo tiras=$tiras pedido=$pedido rep=$repeticiones pzas=$pzasRollo";

                        // TotalRollos = ceil(pedido / pzas)
                        $this->assertSame((float) ceil($pedido / $pzasRollo), $tr, "TotalRollos: $ctx");

                        // TotalPzas = pzas × rollos = rep × tiras × rollos
                        $this->assertSame(round($pzasRollo * $tr, 0), $tp, "TotalPzas: $ctx");
                        $this->assertEqualsWithDelta($repeticiones * $tiras * $tr, $tp, 0.5, "Rep×Tiras×Rollos: $ctx");

                        // Los rollos alcanzan el pedido, y ni uno de más.
                        $this->assertGreaterThanOrEqual($pedido, $tp, "Cubre pedido: $ctx");
                        $this->assertLessThan($pedido, ($tr - 1) * $pzasRollo, "Sin rollo de sobra: $ctx");

                        // El no. de marbetes nunca es negativo.
                        $this->assertGreaterThanOrEqual(0, $marbM->invoke($this->controller, $pedido, $tiras, $repeticiones));
                    }

                    $casosVerificados++;
                }
            }
        }

        $this->assertGreaterThan(150, $casosVerificados, 'Se esperaban muchas órdenes verificadas.');
    }

    /**
     * Reproduce la MISMA secuencia de helpers privados que ejecuta index()/liberar(),
     * incluido el ajuste FEL y el fallback de TotalRollos, sin tocar la base de datos.
     *
     * @return array{repeticiones: int|null, saldoMarbete: int, mtsRollo: float|null, pzasRollo: float|null, totalRollos: float|null, totalPzas: float|null}
     */
    private function simularCascada(float $pesoRollo, $pesoCrudo, $tiras, $largo, $pedido, ?ReqProgramaTejido $registro = null): array
    {
        $registro = $registro ?? new ReqProgramaTejido;
        $registro->SaldoPedido = $pedido;

        $repeticiones = $this->method('repeticionesDesdePesoRollo')->invoke($this->controller, $pesoRollo, $pesoCrudo, $tiras);
        $saldoMarbete = $this->method('saldoMarbeteDesdeFormula')->invoke($this->controller, $pedido, $tiras, $repeticiones);
        $mtsRollo = ($largo > 0 && $repeticiones !== null && $repeticiones > 0) ? ($largo * $repeticiones) / 100 : null;
        $pzasRollo = $this->method('pzasRolloDesdeRepeticiones')->invoke($this->controller, $repeticiones, $tiras);

        // Ajuste FEL idéntico a index(): saldo×2, mts÷2, pzas÷2 cuando aplica.
        $this->method('aplicarAjusteFelTamanho')->invokeArgs(
            $this->controller,
            [$registro->InventSizeId ?? null, &$saldoMarbete, &$mtsRollo, &$pzasRollo, $registro]
        );

        $tot = $this->method('derivarTotalRollosTotalPzas')->invoke($this->controller, $pzasRollo, $pedido, null);
        $totalRollos = $tot['totalRollos'];
        $totalPzas = $tot['totalPzas'];

        if ($totalRollos === null) {
            if (isset($registro->TotalRollos) && is_numeric($registro->TotalRollos) && $registro->TotalRollos > 0) {
                $totalRollos = (float) ceil((float) $registro->TotalRollos);
            } elseif ($saldoMarbete > 0) {
                $totalRollos = (float) ceil($saldoMarbete);
            }
            if ($totalRollos !== null && $pzasRollo !== null) {
                $totalPzas = round((float) $totalRollos * (float) $pzasRollo, 0);
            }
        }

        return compact('repeticiones', 'saldoMarbete', 'mtsRollo', 'pzasRollo', 'totalRollos', 'totalPzas');
    }

    /** La cascada completa ignora el PzasRollo/TotalRollos/TotalPzas viejos del registro (caso 36643). */
    public function test_cascada_completa_ignora_valores_viejos_del_registro(): void
    {
        $registro = new ReqProgramaTejido;
        $registro->InventSizeId = 'STD';
        $registro->PzasRollo = 636;   // valor viejo (peso crudo anterior ~64)
        $registro->TotalRollos = 7;   // viejo
        $registro->TotalPzas = 4452;  // viejo

        $r = $this->simularCascada(41.5, 121, 6, 50, 4104, $registro);

        $this->assertSame(57, $r['repeticiones']);
        $this->assertSame(342.0, $r['pzasRollo'], 'Ignora el 636 viejo.');
        $this->assertSame(12.0, $r['totalRollos'], 'Ignora el 7 viejo.');
        $this->assertSame(4104.0, $r['totalPzas'], 'Ignora el 4452 viejo.');
        // Consistencia interna: PzasRollo = TotalPzas / TotalRollos.
        $this->assertSame($r['pzasRollo'], $r['totalPzas'] / $r['totalRollos']);
    }

    /** Felpa end-to-end: pzas y mts ÷2, saldo ×2, y los totales siguen cuadrando con el pzas ajustado. */
    public function test_cascada_felpa_completa_queda_consistente(): void
    {
        $registro = new ReqProgramaTejido;
        $registro->TamanoClave = 'FELPA6598';
        $registro->InventSizeId = 'STD';

        $r = $this->simularCascada(90.0, 121, 6, 50, 4104, $registro);

        // PzasRollo = round((Repeticiones × NoTiras) / 2) por el ajuste FEL.
        $this->assertSame(round(($r['repeticiones'] * 6) / 2, 0), $r['pzasRollo']);
        // Totales consistentes con el PzasRollo ya ajustado.
        $this->assertSame((float) ceil(4104 / $r['pzasRollo']), $r['totalRollos']);
        $this->assertSame(round($r['pzasRollo'] * $r['totalRollos'], 0), $r['totalPzas']);
        $this->assertGreaterThanOrEqual(4104, $r['totalPzas']);
    }

    /** Repeticiones TRUNCA hacia cero (no redondea). */
    public function test_repeticiones_trunca_hacia_cero(): void
    {
        $m = $this->method('repeticionesDesdePesoRollo');

        $this->assertSame(57, $m->invoke($this->controller, 41.5, 121, 6));   // 57.16 → 57
        $this->assertSame(100, $m->invoke($this->controller, 50, 100, 5));    // 100.0 exacto
        $this->assertNull($m->invoke($this->controller, 41.5, 0, 6));
        $this->assertNull($m->invoke($this->controller, 41.5, 121, 0));
        $this->assertNull($m->invoke($this->controller, 41.5, null, 6));
    }

    /** SaldoMarbete redondea y devuelve 0 ante datos inválidos (=SI(ESERROR(...),0,...)). */
    public function test_saldo_marbete_redondea_y_maneja_errores(): void
    {
        $m = $this->method('saldoMarbeteDesdeFormula');

        $this->assertSame(12, $m->invoke($this->controller, 4104, 6, 57)); // (4104/6)/57 = 12
        $this->assertSame(0, $m->invoke($this->controller, 1000, 0, 5));   // tiras 0
        $this->assertSame(0, $m->invoke($this->controller, 1000, 5, 0));   // reps 0
        $this->assertSame(0, $m->invoke($this->controller, null, 5, 5));
    }

    /** TotalRollos: techo exacto, +1 unidad, y null sin override cuando no hay PzasRollo. */
    public function test_total_rollos_bordes_off_by_one(): void
    {
        $m = $this->method('derivarTotalRollosTotalPzas');

        $this->assertSame(12.0, $m->invoke($this->controller, 342.0, 4104, null)['totalRollos']); // exacto
        $this->assertSame(13.0, $m->invoke($this->controller, 342.0, 4105, null)['totalRollos']); // +1 pieza
        $this->assertSame(1.0, $m->invoke($this->controller, 342.0, 1, null)['totalRollos']);     // pedido mínimo
        $this->assertNull($m->invoke($this->controller, null, 4104, null)['totalRollos']);        // sin pzas ni override
        $this->assertNull($m->invoke($this->controller, 342.0, 0, null)['totalRollos']);          // pedido 0
    }

    /** Fórmula INN de fecha programada: dentro del rango → HOY; fuera → null. */
    public function test_fecha_programada_inn(): void
    {
        $m = $this->method('calcularFechaProgramada');
        $hoy = Carbon::create(2026, 6, 20)->startOfDay();
        $fechaFormula = $hoy->copy()->addDays(10.999);

        $reg = new ReqProgramaTejido;

        $reg->FechaInicio = Carbon::create(2026, 6, 25); // dentro del rango
        $resultado = $m->invoke($this->controller, $reg, $hoy, $fechaFormula);
        $this->assertNotNull($resultado);
        $this->assertTrue($hoy->equalTo($resultado));

        $reg->FechaInicio = Carbon::create(2026, 8, 1); // fuera del rango
        $this->assertNull($m->invoke($this->controller, $reg, $hoy, $fechaFormula));

        $reg2 = new ReqProgramaTejido; // sin FechaInicio
        $this->assertNull($m->invoke($this->controller, $reg2, $hoy, $fechaFormula));
    }

    /** Estrés con pedidos decimales y valores grandes: la invariante nunca se rompe. */
    public function test_estres_pedidos_decimales_y_grandes(): void
    {
        $pzasM = $this->method('pzasRolloDesdeRepeticiones');
        $totM = $this->method('derivarTotalRollosTotalPzas');

        $reps = [1, 7, 33, 57, 106, 250, 999];
        $tiras = [1, 3, 6, 11];
        $pedidos = [1, 4104.5, 9999.99, 100000, 1234567];

        $verificados = 0;
        foreach ($reps as $rep) {
            foreach ($tiras as $t) {
                $pzas = $pzasM->invoke($this->controller, $rep, $t);
                $this->assertSame((float) ($rep * $t), $pzas);

                foreach ($pedidos as $pedido) {
                    ['totalRollos' => $tr, 'totalPzas' => $tp] = $totM->invoke($this->controller, $pzas, $pedido, null);
                    $this->assertSame((float) ceil($pedido / $pzas), $tr);
                    $this->assertSame(round($pzas * $tr, 0), $tp);
                    $this->assertGreaterThanOrEqual($pedido, $tp);
                    $verificados++;
                }
            }
        }

        $this->assertGreaterThan(100, $verificados);
    }
}
