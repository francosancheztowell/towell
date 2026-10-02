<?php

namespace Tests\Feature\Planeacion;

use App\Livewire\Planeacion\ProgramaTejido\DuplicarDividir;
use App\Models\Planeacion\ReqModelosCodificados;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Reglas de Dividir/Duplicar (owner):
 * - Dividir reparte SOLO el saldo: Σ saldos capturados == SaldoPedido del original (±0.5), si no 422.
 * - TotalPedido de cada fila se deriva del saldo: (saldo + Produccion) / (1 + %seg/100).
 * - A otro salón solo si la clave modelo existe en ReqModelosCodificados para ese salón.
 *
 * Fixture: Id 1 = SMIT/201, TotalPedido 1000, SaldoPedido 800, Produccion 200, NoProduccion 30001.
 * Grupo 7 = Ids 3 (saldo 600) y 4 (saldo 400) en JACQUARD.
 */
class ProgramaTejidoDividirSaldoTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        // DividirTejido hace DB::reconnect() tras el commit: se reconecta al mismo PDO en memoria.
        $pdo = DB::connection('sqlsrv')->getPdo();
        DB::extend('sqlsrv', fn (array $config) => new SQLiteConnection($pdo, ':memory:', '', $config));

        DB::table('ReqProgramaTejido')->whereIn('Id', [1, 3, 4])->update(['TamanoClave' => 'CLV-1']);
    }

    /** La misma lógica que corre el modal (ya no hay endpoint HTTP); validación fallida = 422. */
    private function enviar(string $ruta, array $payload): TestResponse
    {
        $this->actingAs($this->usuarioConPermisos([2 => ['crear']]));
        try {
            $respuesta = DuplicarDividir::correr($ruta === 'dividir-saldo', $payload);
        } catch (ValidationException $e) {
            $respuesta = response()->json(['errors' => $e->errors()], 422);
        }

        return TestResponse::fromBaseResponse($respuesta);
    }

    private function dividir(array $destinos)
    {
        return $this->enviar('dividir-saldo', [
            'salon_tejido_id' => 'SMIT', 'no_telar_id' => '201', 'registro_id_original' => 1, 'destinos' => $destinos,
        ]);
    }

    private function fila(int $id): object
    {
        return DB::table('ReqProgramaTejido')->where('Id', $id)->first();
    }

    private function filasNuevas(): array
    {
        return DB::table('ReqProgramaTejido')->where('Id', '>', 5)->orderBy('Id')->get()->all();
    }

    public function test_cuadre_exacto_divide_y_responde_el_json_de_la_grilla(): void
    {
        $this->dividir([['telar' => '201', 'saldo' => '500'], ['telar' => '210', 'saldo' => '300']])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('modo', 'dividir')
            ->assertJsonPath('ord_compartida', 30001)
            ->assertJsonPath('registro_id_original', 1)
            ->assertJsonStructure(['registros_ids', 'registros_datos', 'registro_original' => ['TotalPedido', 'SaldoPedido']]);

        $original = $this->fila(1);
        $this->assertEqualsWithDelta(500, $original->SaldoPedido, 0.01);
        $this->assertEqualsWithDelta(700, $original->TotalPedido, 0.01, 'TotalPedido = saldo + lo producido');
        $this->assertEqualsWithDelta(200, $original->Produccion, 0.01, 'lo producido se queda en el original');

        [$nuevo] = $this->filasNuevas();
        $this->assertSame('210', (string) $nuevo->NoTelarId);
        $this->assertEqualsWithDelta(300, $nuevo->SaldoPedido, 0.01);
        $this->assertEqualsWithDelta(300, $nuevo->TotalPedido, 0.01);
        $this->assertNull($nuevo->NoProduccion);
        $this->assertSame(30001, (int) $nuevo->OrdCompartida);
    }

    public function test_separador_de_miles_no_trunca(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['SaldoPedido' => 1800]);

        $this->dividir([['telar' => '201', 'saldo' => '300'], ['telar' => '210', 'saldo' => '1,500']])->assertOk();

        $this->assertEqualsWithDelta(1500, $this->filasNuevas()[0]->SaldoPedido, 0.01);
    }

    public function test_descuadre_responde_422_sin_tocar_nada(): void
    {
        $antes = DB::table('ReqProgramaTejido')->orderBy('Id')->get()->toArray();

        // Σ = 700 contra 800: antes se escalaba o quedaba un faltante silencioso.
        $this->dividir([['telar' => '201', 'saldo' => '400'], ['telar' => '210', 'saldo' => '300']])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEquals($antes, DB::table('ReqProgramaTejido')->orderBy('Id')->get()->toArray());
    }

    public function test_dividir_sin_telar_nuevo_o_con_saldo_cero_responde_422_sin_tocar_nada(): void
    {
        $antes = DB::table('ReqProgramaTejido')->orderBy('Id')->get()->toArray();

        // Solo el original: cuadra, pero no hay a quién pasarle saldo.
        $this->dividir([['telar' => '201', 'saldo' => '800']])->assertStatus(422);
        // Fila nueva con saldo 0: cuadra, pero crearía un registro vacío.
        $this->dividir([['telar' => '201', 'saldo' => '800'], ['telar' => '210', 'saldo' => '0']])->assertStatus(422);

        $this->assertEquals($antes, DB::table('ReqProgramaTejido')->orderBy('Id')->get()->toArray());
    }

    public function test_saldo_negativo_responde_422(): void
    {
        $this->dividir([['telar' => '201', 'saldo' => '900'], ['telar' => '210', 'saldo' => '-100']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['destinos.1.saldo']);

        $this->assertSame([], $this->filasNuevas());
    }

    public function test_saldo_con_porcentaje_de_segundas_y_produccion(): void
    {
        $this->dividir([
            ['telar' => '201', 'saldo' => '520', 'porcentaje_segundos' => 5],
            ['telar' => '210', 'saldo' => '280', 'porcentaje_segundos' => 5],
        ])->assertOk();

        // Saldo = Pedido × 1.05 − Producción  =>  Pedido = (Saldo + Producción) / 1.05
        $original = $this->fila(1);
        $this->assertEqualsWithDelta(520, $original->SaldoPedido, 0.01);
        $this->assertEqualsWithDelta((520 + 200) / 1.05, $original->TotalPedido, 0.01);

        [$nuevo] = $this->filasNuevas();
        $this->assertEqualsWithDelta(280, $nuevo->SaldoPedido, 0.01);
        $this->assertEqualsWithDelta(280 / 1.05, $nuevo->TotalPedido, 0.01);
    }

    public function test_pedido_tempo_del_original_es_el_de_su_fila_no_el_del_ultimo_destino(): void
    {
        $this->dividir([
            ['telar' => '201', 'saldo' => '500', 'pedido_tempo' => '500'],
            ['telar' => '210', 'saldo' => '300', 'pedido_tempo' => '300'],
        ])->assertOk();

        $this->assertEqualsWithDelta(500, (float) $this->fila(1)->PedidoTempo, 0.01);
    }

    public function test_salon_destino_sin_clave_modelo_responde_422(): void
    {
        $this->dividir([
            ['telar' => '201', 'saldo' => '500'],
            ['telar' => '205', 'saldo' => '300', 'salon_destino' => 'JACQUARD'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'CLV-1') && str_contains($m, 'JACQUARD'));

        $this->assertSame([], $this->filasNuevas());
    }

    public function test_salon_destino_con_clave_modelo_divide(): void
    {
        DB::table('ReqModelosCodificados')->insert(['TamanoClave' => 'CLV-1', 'SalonTejidoId' => 'JACQUARD']);

        $this->dividir([
            ['telar' => '201', 'saldo' => '500'],
            ['telar' => '205', 'saldo' => '300', 'salon_destino' => 'JAC'],
        ])->assertOk();

        $this->assertSame('JACQUARD', $this->filasNuevas()[0]->SalonTejidoId);
    }

    public function test_redistribuir_grupo_existente_conserva_el_saldo_del_grupo(): void
    {
        $payload = fn (array $saldos) => [
            'salon_tejido_id' => 'JACQUARD', 'no_telar_id' => '202', 'registro_id_original' => 3, 'ord_compartida_existente' => 7,
            'destinos' => [
                ['telar' => '202', 'registro_id' => 3, 'es_existente' => true, 'saldo' => $saldos[0]],
                ['telar' => '203', 'registro_id' => 4, 'es_existente' => true, 'saldo' => $saldos[1]],
                ['telar' => '205', 'es_nuevo' => true, 'saldo' => $saldos[2]],
            ],
        ];

        // 600 + 400 = 1000 antes; 500 + 300 + 100 = 900 después.
        $this->enviar('dividir-saldo', $payload(['500', '300', '100']))->assertStatus(422);
        $this->assertSame([], $this->filasNuevas());

        $this->enviar('dividir-saldo', $payload(['500', '300', '200']))->assertOk()->assertJsonPath('ord_compartida', 7);

        $this->assertEqualsWithDelta(500, $this->fila(3)->SaldoPedido, 0.01);
        $this->assertEqualsWithDelta(300, $this->fila(4)->SaldoPedido, 0.01);
        [$nuevo] = $this->filasNuevas();
        $this->assertEqualsWithDelta(200, $nuevo->SaldoPedido, 0.01);
        $this->assertSame(7, (int) $nuevo->OrdCompartida);
    }

    public function test_duplicar_respeta_salon_destino_por_fila_y_encadena_fechas_en_el_mismo_telar(): void
    {
        DB::table('ReqModelosCodificados')->insert(['TamanoClave' => 'CLV-1', 'SalonTejidoId' => 'JACQUARD']);

        $this->enviar('duplicar-telar', [
            'salon_tejido_id' => 'SMIT', 'no_telar_id' => '201', 'registro_id_original' => 1, 'salon_destino' => 'SMIT',
            'destinos' => [
                ['telar' => '205', 'salon_destino' => 'JACQUARD', 'pedido' => '1,500'],
                ['telar' => '210', 'pedido' => '50'],
                ['telar' => '210', 'pedido' => '60'],
            ],
        ])->assertOk()->assertJsonPath('modo', 'duplicar')->assertJsonCount(3, 'registros_ids');

        [$jac, $smit1, $smit2] = $this->filasNuevas();
        $this->assertSame(['JACQUARD', '205'], [$jac->SalonTejidoId, (string) $jac->NoTelarId]);
        $this->assertEqualsWithDelta(1500, $jac->TotalPedido, 0.01);
        $this->assertSame('SMIT', $smit1->SalonTejidoId);
        $this->assertNotEquals($smit1->FechaInicio, $smit2->FechaInicio, 'dos copias al mismo telar no arrancan juntas');
        $this->assertEquals($smit1->FechaFinal, $smit2->FechaInicio);
    }

    public function test_duplicar_a_salon_sin_clave_modelo_responde_422(): void
    {
        $this->enviar('duplicar-telar', [
            'salon_tejido_id' => 'SMIT', 'no_telar_id' => '201', 'registro_id_original' => 1,
            'destinos' => [['telar' => '205', 'salon_destino' => 'JACQUARD', 'pedido' => '100']],
        ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'CLV-1') && str_contains($m, 'JACQUARD'));

        $this->assertSame([], $this->filasNuevas());
    }
}
