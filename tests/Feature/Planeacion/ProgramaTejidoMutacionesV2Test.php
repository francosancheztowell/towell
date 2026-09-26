<?php

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * PT-05 · PT-MUT-01 / PT-ROL-01 / PT-DOM-01/02: mutaciones simples v2 detrás de flag.
 *
 * - Paridad: con el flag apagado responde el legacy; encendido, el mismo request deja la
 *   misma base y el mismo JSON (salvo las correcciones documentadas).
 * - Fallas inyectadas en derivados: v2 revierte todo y responde error, nunca éxito.
 * - Rollback: apagar el flag vuelve al handler legacy (se ve en la telemetría).
 */
class ProgramaTejidoMutacionesV2Test extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    /** @var list<array<string, mixed>> */
    private array $telemetria = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        // Fila 2 (SMIT 201, Ultimo) con todo lo que calcularHorasProd necesita.
        DB::table('ReqModelosCodificados')->insert(['TamanoClave' => 'MOD1', 'SalonTejidoId' => 'SMIT', 'Total' => 50]);
        DB::table('ReqProgramaTejido')->where('Id', 2)->update([
            'TamanoClave' => 'MOD1', 'VelocidadSTD' => 100, 'EficienciaSTD' => 85, 'NoTiras' => 2, 'Luchaje' => 200,
            'Repeticiones' => 16, 'HorasProd' => 10, 'PesoCrudo' => 450, 'LargoCrudo' => 70,
        ]);

        $this->telemetria = [];
        Log::listen(function ($evento) {
            if ($evento->message === 'programa_tejido.mutacion') {
                $this->telemetria[] = $evento->context;
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function modo(string $familia, string $modo, array $canary = []): void
    {
        config()->set("planeacion.mutaciones_v2.{$familia}", $modo);
        config()->set('planeacion.mutaciones_v2.usuarios_canary', $canary);
    }

    private function como(int $id = 999100)
    {
        return $this->actingAs($this->usuarioConPermisos([2 => ['modificar'], 5 => ['modificar']], $id));
    }

    private function reiniciar(): void
    {
        $this->refreshApplication();
        $this->setUp();
    }

    private function gateSano(): void
    {
        $this->assertSame(0, Artisan::call('planeacion:programa-tejido-health', ['--json' => true]), Artisan::output());
    }

    /** Foto de la base sin la columna que el legacy y v2 escriben con reloj distinto. */
    private function foto(): array
    {
        return collect($this->fotoSuperficies())
            ->map(fn ($filas) => array_map(fn ($f) => array_diff_key($f, ['UpdatedAt' => 1]), $filas))
            ->all();
    }

    /** @return array{0: TestResponse, 1: array} */
    private function correr(string $familia, string $modo, callable $request): array
    {
        $this->modo($familia, $modo);
        $respuesta = $request();

        return [$respuesta, $this->foto()];
    }

    // ------------------------------------------------------------------ actualizar

    public function test_con_flag_apagado_responde_el_legacy(): void
    {
        $this->modo('actualizar', 'off');

        $this->como()->putJson('/planeacion/programa-tejido/2', ['pedido' => 900])->assertOk();

        $this->assertSame('legacy', $this->telemetria[0]['version']);
        $this->assertSame('actualizar', $this->telemetria[0]['familia']);
        $this->assertSame(200, $this->telemetria[0]['status']);
    }

    public function test_actualizar_v2_deja_la_misma_base_y_el_mismo_json_que_el_legacy(): void
    {
        $payloads = [
            ['pedido' => 900],
            ['no_tiras' => 3],
            ['descripcion' => 'PROYECTO X', 'rasurado' => 'SI'],
            ['calendario_id' => ''],
            ['ancho' => 40, 'peso_crudo' => 500],
        ];

        foreach ($payloads as $payload) {
            [$legacy, $fotoLegacy] = $this->correr('actualizar', 'off', fn () => $this->como()->putJson('/planeacion/programa-tejido/2', $payload));
            $this->reiniciar();
            [$v2, $fotoV2] = $this->correr('actualizar', 'on', fn () => $this->como()->putJson('/planeacion/programa-tejido/2', $payload));
            $this->reiniciar();

            $this->assertSame(200, $legacy->status(), json_encode($payload));
            $this->assertSame($legacy->status(), $v2->status(), json_encode($payload));
            $this->assertEquals($legacy->json(), $v2->json(), json_encode($payload));
            $this->assertEquals($fotoLegacy, $fotoV2, 'Base distinta con '.json_encode($payload));
        }
    }

    public function test_actualizar_v2_velocidad_std_recalcula_fecha_final_cr03(): void
    {
        $antes = DB::table('ReqProgramaTejido')->where('Id', 2)->value('FechaFinal');

        [, $fotoLegacy] = $this->correr('actualizar', 'off', fn () => $this->como()->putJson('/planeacion/programa-tejido/2', ['velocidad_std' => 50])->assertOk());
        $this->assertSame($antes, collect($fotoLegacy['ReqProgramaTejido'])->firstWhere('Id', 2)['FechaFinal'], 'El legacy no recalculaba (CR-03)');
        $this->reiniciar();

        $this->modo('actualizar', 'on');
        $this->como()->putJson('/planeacion/programa-tejido/2', ['velocidad_std' => 50])->assertOk()->assertJsonPath('success', true);

        $fila = DB::table('ReqProgramaTejido')->where('Id', 2)->first();
        $this->assertNotSame($antes, $fila->FechaFinal);
        $this->assertEquals(50, $fila->VelocidadSTD);
        $this->gateSano();
    }

    public function test_actualizar_v2_revierte_si_fallan_las_lineas(): void
    {
        $this->modo('actualizar', 'on');
        $antes = $this->foto();
        Schema::connection('sqlsrv')->drop('ReqProgramaTejidoLine');

        $r = $this->como()->putJson('/planeacion/programa-tejido/2', ['pedido' => 900]);

        $r->assertStatus(500)->assertJsonPath('success', false);
        $this->assertStringNotContainsString('SQLSTATE', (string) $r->json('message'));
        $this->assertEquals(500, DB::table('ReqProgramaTejido')->where('Id', 2)->value('TotalPedido'));
        $this->assertEquals($antes['ReqProgramaTejido'], DB::table('ReqProgramaTejido')->orderBy('Id')->get()->map(fn ($r) => array_diff_key((array) $r, ['UpdatedAt' => 1]))->all());
        $this->assertSame(500, $this->telemetria[0]['status']);
    }

    public function test_actualizar_v2_revierte_si_falla_la_aplicacion_en_lineas_y_el_legacy_no_wr08(): void
    {
        // Sin tabla ReqAplicaciones: actualizarAplicacionEnLineas truena.
        [$legacy] = $this->correr('actualizar', 'off', fn () => $this->como()->putJson('/planeacion/programa-tejido/2', ['aplicacion_id' => 'APX']));
        $legacy->assertOk();
        $this->assertSame('APX', DB::table('ReqProgramaTejido')->where('Id', 2)->value('AplicacionId'), 'El legacy confirmaba con el error tragado');
        $this->reiniciar();

        $this->modo('actualizar', 'on');
        $this->como()->putJson('/planeacion/programa-tejido/2', ['aplicacion_id' => 'APX'])->assertStatus(500);
        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 2)->value('AplicacionId'));
    }

    public function test_actualizar_v2_rechazos_de_negocio_y_validacion_iguales_al_legacy(): void
    {
        foreach ([['tamano_clave' => 'NO-EXISTE'], ['pedido' => -1], ['fecha_final' => 'no-es-fecha']] as $payload) {
            [$legacy] = $this->correr('actualizar', 'off', fn () => $this->como()->putJson('/planeacion/programa-tejido/2', $payload));
            $this->reiniciar();
            [$v2, $foto] = $this->correr('actualizar', 'on', fn () => $this->como()->putJson('/planeacion/programa-tejido/2', $payload));

            $this->assertSame(422, $legacy->status(), json_encode($payload));
            $this->assertSame(422, $v2->status(), json_encode($payload));
            $this->assertEquals($legacy->json(), $v2->json(), json_encode($payload));
            $this->reiniciar();
        }
    }

    public function test_actualizar_v2_en_muestras_solo_toca_muestras(): void
    {
        $this->modo('actualizar', 'on');
        $programaAntes = $this->foto()['ReqProgramaTejido'];

        $this->como()->putJson('/planeacion/muestras/1', ['pedido' => 700])->assertOk();

        $this->assertEquals(700, DB::table('MuestrasPrograma')->where('Id', 1)->value('TotalPedido'));
        $this->assertEquals($programaAntes, $this->foto()['ReqProgramaTejido']);
        $this->assertSame('muestras', $this->telemetria[0]['superficie']);
    }

    public function test_canary_solo_para_los_usuarios_de_la_lista(): void
    {
        $this->modo('actualizar', 'canary', [74]);

        $this->como(74)->putJson('/planeacion/programa-tejido/2', ['rasurado' => 'SI'])->assertOk();
        $this->como(75)->putJson('/planeacion/programa-tejido/2', ['rasurado' => 'NO'])->assertOk();

        $this->assertSame(['v2', 'legacy'], array_column($this->telemetria, 'version'));
    }

    // ------------------------------------------------------------------ reprogramar

    public function test_reprogramar_v2_igual_al_legacy_en_los_casos_validos(): void
    {
        $casos = [[1, ['reprogramar' => '2']], [1, ['reprogramar' => null]], [2, ['reprogramar' => '1']], [999, ['reprogramar' => '1']]];

        foreach ($casos as [$id, $payload]) {
            [$legacy, $fotoLegacy] = $this->correr('reprogramar', 'off', fn () => $this->como()->postJson("/planeacion/programa-tejido/{$id}/reprogramar", $payload));
            $this->reiniciar();
            [$v2, $fotoV2] = $this->correr('reprogramar', 'on', fn () => $this->como()->postJson("/planeacion/programa-tejido/{$id}/reprogramar", $payload));
            $this->reiniciar();

            $this->assertSame($legacy->status(), $v2->status(), "Id {$id}");
            $this->assertEquals($legacy->json(), $v2->json(), "Id {$id}");
            $this->assertEquals($fotoLegacy, $fotoV2, "Id {$id}");
        }
    }

    public function test_reprogramar_valor_invalido_422_en_v2_donde_el_legacy_daba_500(): void
    {
        [$legacy] = $this->correr('reprogramar', 'off', fn () => $this->como()->postJson('/planeacion/programa-tejido/1/reprogramar', ['reprogramar' => '3']));
        $this->reiniciar();
        [$v2] = $this->correr('reprogramar', 'on', fn () => $this->como()->postJson('/planeacion/programa-tejido/1/reprogramar', ['reprogramar' => '3']));

        $this->assertSame(500, $legacy->status());
        $this->assertSame(422, $v2->status());
        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 1)->value('Reprogramar'));
    }

    // ------------------------------------------------------------------ calendarios

    private function sembrarCalendario(): void
    {
        $filas = [];
        for ($d = 0; $d < 40; $d++) {
            $dia = Carbon::parse('2026-08-25')->addDays($d);
            $filas[] = ['CalendarioId' => 'CAL2', 'FechaInicio' => $dia->copy()->setTime(6, 0)->format('Y-m-d H:i:s'), 'FechaFin' => $dia->copy()->setTime(22, 0)->format('Y-m-d H:i:s')];
        }
        DB::table('ReqCalendarioLine')->insert($filas);
    }

    public function test_calendarios_v2_deja_la_misma_base_y_el_mismo_json_que_el_legacy(): void
    {
        $this->sembrarCalendario();
        $payload = ['calendario_id' => 'CAL2', 'registros_ids' => [1, 2, 3]];

        [$legacy, $fotoLegacy] = $this->correr('calendarios', 'off', fn () => $this->como()->postJson('/planeacion/programa-tejido/actualizar-calendarios-masivo', $payload));
        $this->reiniciar();
        $this->sembrarCalendario();
        [$v2, $fotoV2] = $this->correr('calendarios', 'on', fn () => $this->como()->postJson('/planeacion/programa-tejido/actualizar-calendarios-masivo', $payload));

        $legacy->assertOk();
        $this->assertEquals($legacy->json('data.actualizados'), $v2->json('data.actualizados'));
        $this->assertEquals($legacy->json('data.errores'), $v2->json('data.errores'));
        $this->assertEquals($fotoLegacy, $fotoV2);
        // EnProceso conserva su FechaInicio (Id 1).
        $this->assertSame('2026-09-01 06:30:00', collect($fotoV2['ReqProgramaTejido'])->firstWhere('Id', 1)['FechaInicio']);
    }

    public function test_calendarios_v2_una_fila_que_falla_revierte_todo_cr02(): void
    {
        $this->sembrarCalendario();
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['FechaInicio' => 'no-es-fecha']);
        $payload = ['calendario_id' => 'CAL2', 'registros_ids' => [2, 3]];

        [$legacy] = $this->correr('calendarios', 'off', fn () => $this->como()->postJson('/planeacion/programa-tejido/actualizar-calendarios-masivo', $payload));
        $legacy->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.errores', 1);
        $this->assertSame('CAL2', DB::table('ReqProgramaTejido')->where('Id', 2)->value('CalendarioId'), 'El legacy confirmaba el resto');
        $this->reiniciar();

        $this->sembrarCalendario();
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['FechaInicio' => 'no-es-fecha']);
        $antes = $this->foto();
        [$v2, $despues] = $this->correr('calendarios', 'on', fn () => $this->como()->postJson('/planeacion/programa-tejido/actualizar-calendarios-masivo', $payload));

        $v2->assertStatus(500)->assertJsonPath('success', false)->assertJsonPath('registro_id', 3);
        $this->assertEquals($antes, $despues, 'v2 debe revertir todo');
    }

    public function test_calendarios_v2_validacion_con_el_cuerpo_legacy(): void
    {
        $this->modo('calendarios', 'on');

        $this->como()->postJson('/planeacion/programa-tejido/actualizar-calendarios-masivo', ['registros_ids' => []])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Error de validación')
            ->assertJsonStructure(['errors' => ['calendario_id', 'registros_ids']]);
    }
}
