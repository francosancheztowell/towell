<?php

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT-05 · PT-DUP-01 (suppress/restore) y PT-DUP-03 (Ultimo) con una sola implementación.
 */
class ProgramaTejidoDedupTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
    }

    private function listenersSaved(): int
    {
        $dispatcher = ReqProgramaTejido::getEventDispatcher();

        return $dispatcher ? count($dispatcher->getListeners('eloquent.saved: '.ReqProgramaTejido::class)) : -1;
    }

    public function test_suppress_restore_repetido_no_duplica_el_observer(): void
    {
        $antes = $this->listenersSaved();
        $this->assertGreaterThan(0, $antes);

        for ($i = 0; $i < 3; $i++) {
            $d = ReqProgramaTejido::suppressObservers();
            $this->assertNull(ReqProgramaTejido::getEventDispatcher());
            ReqProgramaTejido::restoreObservers($d);
        }

        // Antes de PT-05 cada restore sumaba un listener: 1 → 4.
        $this->assertSame($antes, $this->listenersSaved());
    }

    public function test_restore_con_null_deja_todo_como_estaba(): void
    {
        $antes = $this->listenersSaved();
        ReqProgramaTejido::restoreObservers(null);
        $this->assertSame($antes, $this->listenersSaved());

        // Anidado: el interno recibe null y no reactiva lo que el externo apagó.
        $externo = ReqProgramaTejido::suppressObservers();
        $interno = ReqProgramaTejido::suppressObservers();
        ReqProgramaTejido::restoreObservers($interno);
        $this->assertNull(ReqProgramaTejido::getEventDispatcher());
        ReqProgramaTejido::restoreObservers($externo);
        $this->assertSame($antes, $this->listenersSaved());
    }

    public function test_dividir_telar_rechazado_restaura_observers_y_cierra_la_transaccion(): void
    {
        $antes = $this->listenersSaved();

        // Telar 204 tiene una sola fila → 422 temprano. Antes dejaba el modelo sin eventos
        // y la transacción abierta.
        $this->actingAs($this->usuarioConPermisos([2 => ['crear']]))
            ->postJson('/planeacion/programa-tejido/dividir-telar', [
                'salon_tejido_id' => 'SMIT', 'no_telar_id' => '204', 'posicion_division' => 0, 'nuevo_telar' => '299',
            ])
            ->assertStatus(422);

        $this->assertSame($antes, $this->listenersSaved());
        $this->assertSame(0, DB::connection('sqlsrv')->transactionLevel());
    }

    public function test_es_ultimo_acepta_1_y_ul(): void
    {
        $r = new ReqProgramaTejido;
        foreach (['1' => true, ' 1 ' => true, 'UL' => true, 'ul' => true, '0' => false, '' => false] as $valor => $esperado) {
            $r->setRawAttributes(['Ultimo' => (string) $valor]);
            $this->assertSame($esperado, $r->esUltimo(), "Ultimo='{$valor}'");
        }
        $r->setRawAttributes(['Ultimo' => null]);
        $this->assertFalse($r->esUltimo());
        $r->setRawAttributes(['Ultimo' => 1]);
        $this->assertTrue($r->esUltimo());
    }

    public function test_ul_se_normaliza_a_1_al_escribir(): void
    {
        $r = ReqProgramaTejido::find(2);
        $r->Ultimo = 'UL';
        $r->save();

        $this->assertSame('1', (string) DB::table('ReqProgramaTejido')->where('Id', 2)->value('Ultimo'));

        $r->Ultimo = 'ul ';
        $this->assertSame('1', $r->getAttributes()['Ultimo']);
        $r->Ultimo = '0';
        $this->assertSame('0', $r->getAttributes()['Ultimo']);
    }

    public function test_consultas_sql_de_ultimo_incluyen_filas_ul_viejas(): void
    {
        // Fila vieja escrita sin pasar por el modelo (como las que ya hay en live).
        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['Ultimo' => 'UL']);

        $ids = ReqProgramaTejido::query()->whereIn('Ultimo', ReqProgramaTejido::VALORES_ULTIMO)->salon('SMIT')->telar('201')->pluck('Id')->all();

        $this->assertSame([2], $ids);
        $this->assertTrue(ReqProgramaTejido::find(2)->esUltimo());
    }
}
