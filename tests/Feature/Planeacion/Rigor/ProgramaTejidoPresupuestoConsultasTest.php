<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Services\Planeacion\ProgramaTejido\HorasProduccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Presupuestos de rendimiento de Programa Tejido: consultas por request (DB::listen) y
 * bytes del HTML del index. Cada techo es el número medido hoy (2026-10-10) más un
 * margen chico; si sube, alguien metió una consulta (o un N+1) nuevo.
 *
 * La pendiente "por registro" se mide con el mismo request sobre un telar de 3 y de 9
 * registros (Posicion no tiene cast int: en sqlite es TEXT y con 10+ por telar el ORDER BY
 * Posicion sería alfabético, así que el máximo es 9 por telar).
 *
 * Telares sembrados: SMIT 301..(300+telares), Id = 100 + t*100 + posicion (101.., 201..),
 * NoProduccion = 50000 + t*100 + posicion. Los fixtures base (Id 1..5) y Muestras quedan
 * como testigos: ninguna mutación debe tocarlos.
 */
class ProgramaTejidoPresupuestoConsultasTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    // Index (shell legacy): una sola consulta de la grilla, sin importar N.
    private const MAX_CONSULTAS_INDEX = 2;          // medido: 1 (frío y caliente)

    private const MAX_BYTES_INDEX_45 = 540_000;     // medido: 504 525 (45 filas: 40 + 5 fixtures)

    private const MAX_BYTES_POR_REGISTRO = 9_500;   // medido: ~8 900

    // Mutaciones: techo a 9 registros por telar y pendiente por registro de la cascada.
    // Bajados tras quitar el N+1 del observer (StdToaHra + modelo memoizado) y de Vincular.
    private const MAX_MOVER_9 = 45;                 // medido: 3 → 20, 9 → 43 (antes 60)

    private const MAX_CAMBIAR_9 = 88;               // medido: 3 → 37, 9 → 85 (antes 120)

    private const MAX_ELIMINAR_9 = 56;              // medido: 3 → 24, 9 → 54 (antes 69)

    private const MAX_UPDATE_9 = 48;                // medido: 3 → 22, 9 → 46 (antes 63)

    private const MAX_VINCULAR_5 = 31;              // medido: 2 → 22, 5 → 31 (antes 47); +3 fijas: OrdPrincipal ya se escribe (el fixture sin ItemId lo saltaba)

    // Lo que queda por registro son escrituras propias: UPDATE de eficiencia + DELETE/INSERT de
    // líneas (3), más el UPDATE de fechas de la cascada donde lo hay.
    private const MAX_PENDIENTE_CASCADA = 5.5;      // medido: 3.0 – 5.0 (antes ~6 – 7)

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);
        // Todo en legacy y sin muestreo shadow: el presupuesto es el de producción hoy.
        config()->set('planeacion.shell_v2', ['modo' => 'off', 'usuarios_canary' => []]);
        config()->set('planeacion.read_v2.shadow_sample', 0);
        config()->set('planeacion.mutaciones_v2.actualizar', 'off');
        DB::table('ReqModelosCodificados')->insert(['TamanoClave' => 'MOD1', 'SalonTejidoId' => 'SMIT', 'Total' => 50]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    private function sembrarTelares(int $porTelar, int $telares, string $tabla = 'ReqProgramaTejido'): void
    {
        for ($t = 0; $t < $telares; $t++) {
            $inicio = Carbon::parse('2026-09-10 06:00:00');
            for ($p = 1; $p <= $porTelar; $p++) {
                $fin = $inicio->copy()->addHours(12);
                DB::table($tabla)->insert([
                    'Id' => 100 + $t * 100 + $p, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => (string) (301 + $t), 'Posicion' => $p,
                    'EnProceso' => $p === 1 ? 1 : 0, 'Ultimo' => $p === $porTelar ? '1' : '0',
                    'NoProduccion' => (string) (50000 + $t * 100 + $p), 'TamanoClave' => 'MOD1',
                    'TotalPedido' => 500, 'SaldoPedido' => 500, 'Produccion' => 0, 'HorasProd' => 12,
                    'VelocidadSTD' => 100, 'EficienciaSTD' => 85, 'NoTiras' => 2, 'Luchaje' => 200,
                    'Repeticiones' => 16, 'PesoCrudo' => 450, 'LargoCrudo' => 70,
                    'FechaInicio' => $inicio->format('Y-m-d H:i:s'), 'FechaFinal' => $fin->format('Y-m-d H:i:s'),
                ]);
                $inicio = $fin;
            }
        }
    }

    /** @return array{0: int, 1: mixed, 2: list<string>} consultas, resultado y SQL de cada una */
    private function contar(callable $fn): array
    {
        $sql = [];
        DB::listen(function ($q) use (&$sql) {
            $sql[] = (string) $q->sql;
        });
        $r = $fn();

        return [count($sql), $r, $sql];
    }

    private function como(array $permisos = [2 => ['acceso', 'crear', 'modificar', 'eliminar', 'registrar']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    /** App limpia (sin listeners ni memoria del request anterior) con N por telar. */
    private function escenario(int $porTelar, int $telares): void
    {
        $this->refreshApplication();
        $this->setUp();
        $this->sembrarTelares($porTelar, $telares);
    }

    /** Programa fuera de los telares tocados + Muestras completa (cabecera y líneas) + CatCodificados. */
    private function testigos(array $telaresTocados): array
    {
        $foto = $this->fotoSuperficies();
        $foto['ReqProgramaTejido'] = array_values(array_filter(
            $foto['ReqProgramaTejido'],
            fn ($f) => ! in_array((string) $f['NoTelarId'], $telaresTocados, true)
        ));
        unset($foto['ReqProgramaTejidoLine']); // el observer regenera líneas de los tocados

        return $foto;
    }

    /** Estado completo de un telar tras una cascada: posiciones 1..n, un EnProceso al frente, Ultimo al final, fechas encadenadas. */
    private function assertTelarCoherente(string $telar, array $idsEsperados): void
    {
        $filas = DB::table('ReqProgramaTejido')->where('NoTelarId', $telar)->orderByRaw('CAST(Posicion AS INTEGER)')->get();
        $this->assertSame($idsEsperados, $filas->pluck('Id')->map(fn ($v) => (int) $v)->all(), "orden del telar {$telar}");
        $this->assertSame(range(1, count($idsEsperados)), $filas->pluck('Posicion')->map(fn ($v) => (int) $v)->all(), "posiciones contiguas en {$telar}");
        $this->assertSame([1], $filas->filter(fn ($f) => (int) $f->EnProceso === 1)->keys()->map(fn ($k) => $k + 1)->values()->all(), "solo el primero de {$telar} está EnProceso");
        $this->assertSame([count($idsEsperados)], $filas->filter(fn ($f) => (string) $f->Ultimo === '1')->keys()->map(fn ($k) => $k + 1)->values()->all(), "solo el último de {$telar} es Ultimo");
        for ($i = 1; $i < $filas->count(); $i++) {
            $this->assertGreaterThanOrEqual(
                Carbon::parse($filas[$i - 1]->FechaFinal)->timestamp,
                Carbon::parse($filas[$i]->FechaInicio)->timestamp,
                "{$telar}: la posición ".($i + 1).' empieza antes de que termine la anterior'
            );
            $this->assertGreaterThan(Carbon::parse($filas[$i]->FechaInicio)->timestamp, Carbon::parse($filas[$i]->FechaFinal)->timestamp);
        }
    }

    // ------------------------------------------------------------------ index

    public function test_index_con_40_registros_en_10_telares_hace_a_lo_sumo_dos_consultas_en_frio(): void
    {
        $this->sembrarTelares(4, 10);

        [$consultas, $r, $sql] = $this->contar(fn () => $this->como([2 => ['acceso']])->get(route('catalogos.req-programa-tejido')));

        $r->assertOk();
        $html = (string) $r->getContent();
        $this->assertStringNotContainsString('No se pudieron cargar los registros', $html, 'el index cayó al estado de error: el conteo no valdría');
        for ($t = 0; $t < 10; $t++) {
            for ($p = 1; $p <= 4; $p++) {
                $this->assertStringContainsString((string) (50000 + $t * 100 + $p), $html, 'cada registro sembrado se pinta');
            }
        }
        $this->assertLessThanOrEqual(self::MAX_CONSULTAS_INDEX, $consultas, "index en frío:\n".implode("\n", $sql));
        $this->assertSame(1, count(array_filter($sql, fn ($s) => str_contains($s, 'from "ReqProgramaTejido"'))), 'la grilla se lee en una sola consulta');
    }

    public function test_index_no_crece_en_consultas_de_10_a_40_registros(): void
    {
        $this->sembrarTelares(1, 10);
        $this->como([2 => ['acceso']])->get(route('catalogos.req-programa-tejido'))->assertOk(); // calienta memoizaciones
        [$con10] = $this->contar(fn () => $this->como([2 => ['acceso']])->get(route('catalogos.req-programa-tejido'))->assertOk());

        $this->escenario(4, 10);
        $this->como([2 => ['acceso']])->get(route('catalogos.req-programa-tejido'))->assertOk();
        [$con40] = $this->contar(fn () => $this->como([2 => ['acceso']])->get(route('catalogos.req-programa-tejido'))->assertOk());

        fwrite(STDERR, sprintf("\n[PT-PERF] index: 10 registros=%d consultas, 40=%d\n", $con10, $con40));
        $this->assertSame($con10, $con40, 'el index hace las mismas consultas con 10 que con 40 registros (sin N+1)');
        $this->assertLessThanOrEqual(self::MAX_CONSULTAS_INDEX, $con40);
    }

    public function test_index_html_con_40_registros_cabe_en_el_presupuesto_de_bytes(): void
    {
        $this->sembrarTelares(1, 10);
        $bytes10 = strlen((string) $this->como([2 => ['acceso']])->get(route('catalogos.req-programa-tejido'))->assertOk()->getContent());

        $this->escenario(4, 10);
        $bytes40 = strlen((string) $this->como([2 => ['acceso']])->get(route('catalogos.req-programa-tejido'))->assertOk()->getContent());

        $porRegistro = ($bytes40 - $bytes10) / 30;
        fwrite(STDERR, sprintf("\n[PT-PERF] index HTML: 15 filas=%d B, 45 filas=%d B, %.0f B por registro\n", $bytes10, $bytes40, $porRegistro));

        $this->assertLessThanOrEqual(self::MAX_BYTES_INDEX_45, $bytes40, 'HTML del index con 45 filas');
        $this->assertLessThanOrEqual(self::MAX_BYTES_POR_REGISTRO, $porRegistro, 'bytes de HTML por registro de la grilla');
        $this->assertGreaterThan(0, $porRegistro, 'más registros, más HTML (sanidad de la medición)');
    }

    public function test_index_de_muestras_tiene_el_mismo_presupuesto_y_no_lee_programa(): void
    {
        $this->sembrarTelares(4, 10, 'MuestrasPrograma');
        $this->usarSuperficie('muestras');
        $this->como([5 => ['acceso']])->get(route('muestras.index'))->assertOk();

        [$consultas, $r, $sql] = $this->contar(fn () => $this->como([5 => ['acceso']])->get(route('muestras.index')));

        $r->assertOk();
        $html = (string) $r->getContent();
        $this->assertStringNotContainsString('No se pudieron cargar los registros', $html);
        $this->assertStringContainsString('90001', $html, 'pinta la fila de Muestras del fixture');
        $this->assertStringContainsString('50304', $html);
        $this->assertLessThanOrEqual(self::MAX_CONSULTAS_INDEX, $consultas, implode("\n", $sql));
        $this->assertSame([], array_values(array_filter($sql, fn ($s) => str_contains($s, '"ReqProgramaTejido"'))), 'Muestras no lee la tabla de Programa');
    }

    // ------------------------------------------------------------------ mutaciones

    /** @return array{0: int, 1: TestResponse, 2: list<string>} */
    private function mover(int $porTelar): array
    {
        $this->escenario($porTelar, 3);
        $ultimo = 100 + $porTelar;

        return $this->contar(fn () => $this->como()->postJson("/planeacion/programa-tejido/{$ultimo}/prioridad/mover", ['new_position' => 1]));
    }

    public function test_mover_prioridad_respeta_techo_y_pendiente_por_registro_de_la_cascada(): void
    {
        [$con3, $r3] = $this->mover(3);
        $r3->assertOk();
        [$con9, $r9, $sql] = $this->mover(9);

        $pendiente = ($con9 - $con3) / 6;
        fwrite(STDERR, sprintf("\n[PT-PERF] mover: 3=%d, 9=%d, %.1f por registro\n", $con3, $con9, $pendiente));

        $r9->assertOk()->assertJsonPath('success', true)->assertJsonPath('registro_id', 109)->assertJsonPath('cascaded_records', 9);
        $this->assertLessThanOrEqual(self::MAX_MOVER_9, $con9, implode("\n", $sql));
        $this->assertLessThanOrEqual(self::MAX_PENDIENTE_CASCADA, $pendiente);
    }

    public function test_mover_prioridad_deja_el_telar_coherente_sin_tocar_otros_telares_ni_muestras(): void
    {
        $this->escenario(9, 3);
        $antes = $this->testigos(['301']);

        $this->como()->postJson('/planeacion/programa-tejido/109/prioridad/mover', ['new_position' => 1])->assertOk();

        $this->assertTelarCoherente('301', [101, 109, 102, 103, 104, 105, 106, 107, 108]);
        $this->assertSame('2026-09-10 08:00:00', DB::table('ReqProgramaTejido')->where('Id', 101)->value('FechaInicio'), 'el EnProceso arranca en now()');
        $this->assertEquals($antes, $this->testigos(['301']));
    }

    /** @return array{0: int, 1: TestResponse, 2: list<string>} */
    private function cambiarTelar(int $porTelar): array
    {
        $this->escenario($porTelar, 3);

        return $this->contar(fn () => $this->como()->postJson('/planeacion/programa-tejido/102/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '302', 'target_position' => 1,
        ]));
    }

    public function test_cambiar_telar_respeta_techo_y_pendiente_por_registro(): void
    {
        [$con3, $r3] = $this->cambiarTelar(3);
        $r3->assertOk();
        [$con9, $r9, $sql] = $this->cambiarTelar(9);

        // Crecen los dos telares (origen y destino): 12 registros más en la cascada.
        $pendiente = ($con9 - $con3) / 12;
        fwrite(STDERR, sprintf("\n[PT-PERF] cambiar-telar: 3=%d, 9=%d, %.1f por registro\n", $con3, $con9, $pendiente));

        $r9->assertOk()->assertJsonPath('success', true)->assertJsonPath('registro_id', 102)->assertJsonPath('registros_afectados', 18);
        $this->assertLessThanOrEqual(self::MAX_CAMBIAR_9, $con9, implode("\n", $sql));
        $this->assertLessThanOrEqual(self::MAX_PENDIENTE_CASCADA, $pendiente);

        $this->assertTelarCoherente('301', [101, 103, 104, 105, 106, 107, 108, 109]);
        $this->assertTelarCoherente('302', [201, 102, 202, 203, 204, 205, 206, 207, 208, 209]);
    }

    public function test_cambiar_telar_no_toca_un_tercer_telar_ni_muestras(): void
    {
        $this->escenario(9, 3);
        $antes = $this->testigos(['301', '302']);

        $this->cambiarTelarSinMedir();

        $this->assertEquals($antes, $this->testigos(['301', '302']));
    }

    private function cambiarTelarSinMedir(): void
    {
        $this->como()->postJson('/planeacion/programa-tejido/102/cambiar-telar', [
            'nuevo_salon' => 'SMIT', 'nuevo_telar' => '302', 'target_position' => 1,
        ])->assertOk();
    }

    /** @return array{0: int, 1: TestResponse, 2: list<string>} vincula la posición 2 de los primeros $cuantos telares */
    private function vincular(int $cuantos): array
    {
        $this->escenario(3, 5);
        $ids = array_map(fn ($t) => 100 + $t * 100 + 2, range(0, $cuantos - 1));

        return $this->contar(fn () => $this->como()->postJson('/planeacion/programa-tejido/vincular-registros-existentes', ['registros_ids' => $ids]));
    }

    public function test_vincular_5_registros_respeta_techo_y_deja_un_solo_lider(): void
    {
        [$con2, $r2] = $this->vincular(2);
        $r2->assertOk();
        [$con5, $r5, $sql] = $this->vincular(5);
        $pendiente = ($con5 - $con2) / 3;
        fwrite(STDERR, sprintf("\n[PT-PERF] vincular: 2=%d, 5=%d, %.1f por registro\n", $con2, $con5, $pendiente));

        $r5->assertOk()->assertJsonPath('success', true)->assertJsonPath('ord_compartida', '50002')->assertJsonPath('registros_vinculados', 5);
        $this->assertLessThanOrEqual(self::MAX_VINCULAR_5, $con5, implode("\n", $sql));
        $this->assertLessThanOrEqual(self::MAX_PENDIENTE_CASCADA, $pendiente);

        $grupo = DB::table('ReqProgramaTejido')->whereIn('Id', [102, 202, 302, 402, 502])->orderBy('Id')->get();
        $this->assertSame([50002, 50002, 50002, 50002, 50002], $grupo->pluck('OrdCompartida')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(1, $grupo->sum(fn ($f) => (int) $f->OrdCompartidaLider), 'exactamente un líder');
        $this->assertSame(0, DB::table('ReqProgramaTejido')->whereNotIn('Id', [102, 202, 302, 402, 502])->where('OrdCompartida', 50002)->count());
    }

    /** @return array{0: int, 1: TestResponse, 2: list<string>} */
    private function eliminar(int $porTelar): array
    {
        $this->escenario($porTelar, 3);

        return $this->contar(fn () => $this->como()->deleteJson('/planeacion/programa-tejido/102'));
    }

    public function test_eliminar_respeta_techo_y_pendiente_y_compacta_el_telar(): void
    {
        [$con3, $r3] = $this->eliminar(3);
        $r3->assertOk();
        [$con9, $r9, $sql] = $this->eliminar(9);
        $pendiente = ($con9 - $con3) / 6;
        fwrite(STDERR, sprintf("\n[PT-PERF] eliminar: 3=%d, 9=%d, %.1f por registro\n", $con3, $con9, $pendiente));

        $r9->assertOk()->assertJsonPath('success', true);
        $this->assertLessThanOrEqual(self::MAX_ELIMINAR_9, $con9, implode("\n", $sql));
        $this->assertLessThanOrEqual(self::MAX_PENDIENTE_CASCADA, $pendiente);

        $this->assertNull(DB::table('ReqProgramaTejido')->where('Id', 102)->value('Id'));
        $this->assertSame(0, DB::table('ReqProgramaTejidoLine')->where('ProgramaId', 102)->count(), 'no quedan líneas huérfanas del borrado');
        $this->assertTelarCoherente('301', [101, 103, 104, 105, 106, 107, 108, 109]);
    }

    /** @return array{0: int, 1: TestResponse, 2: list<string>} */
    private function actualizarPedido(int $porTelar): array
    {
        $this->escenario($porTelar, 3);

        return $this->contar(fn () => $this->como()->putJson('/planeacion/programa-tejido/102', ['pedido' => 900]));
    }

    public function test_update_de_pedido_respeta_techo_y_pendiente_y_encadena_el_telar(): void
    {
        [$con3, $r3] = $this->actualizarPedido(3);
        $r3->assertOk();
        [$con9, $r9, $sql] = $this->actualizarPedido(9);
        $pendiente = ($con9 - $con3) / 6;
        fwrite(STDERR, sprintf("\n[PT-PERF] update pedido: 3=%d, 9=%d, %.1f por registro\n", $con3, $con9, $pendiente));

        $r9->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.Id', 102);
        $this->assertLessThanOrEqual(self::MAX_UPDATE_9, $con9, implode("\n", $sql));
        $this->assertLessThanOrEqual(self::MAX_PENDIENTE_CASCADA, $pendiente);

        $fila = DB::table('ReqProgramaTejido')->where('Id', 102)->first();
        $this->assertEquals(900, (float) $fila->TotalPedido);
        $this->assertEquals(900, (float) $fila->SaldoPedido, 'Saldo = Pedido×(1+0%)−0');
        $this->assertTelarCoherente('301', range(101, 109));
    }

    // ------------------------------------------------------------------ guardas baratas

    public function test_sin_permiso_las_mutaciones_responden_403_sin_leer_ni_escribir_la_tabla(): void
    {
        $this->sembrarTelares(3, 3);
        $antes = $this->fotoSuperficies();
        $soloAcceso = [2 => ['acceso']];

        $peticiones = [
            fn () => $this->como($soloAcceso)->postJson('/planeacion/programa-tejido/103/prioridad/mover', ['new_position' => 1]),
            fn () => $this->como($soloAcceso)->postJson('/planeacion/programa-tejido/102/cambiar-telar', ['nuevo_salon' => 'SMIT', 'nuevo_telar' => '302', 'target_position' => 1]),
            fn () => $this->como($soloAcceso)->postJson('/planeacion/programa-tejido/vincular-registros-existentes', ['registros_ids' => [102, 202]]),
            fn () => $this->como($soloAcceso)->deleteJson('/planeacion/programa-tejido/102'),
            fn () => $this->como($soloAcceso)->putJson('/planeacion/programa-tejido/102', ['pedido' => 900]),
        ];

        foreach ($peticiones as $i => $peticion) {
            [, $r, $sql] = $this->contar($peticion);
            $r->assertForbidden();
            $this->assertSame([], array_values(array_filter($sql, fn ($s) => str_contains($s, 'ReqProgramaTejido'))), "petición {$i}: el 403 no debe tocar Programa");
        }
        $this->assertEquals($antes, $this->fotoSuperficies());
    }

    public function test_la_cascada_no_relee_stdtoahra_y_busca_el_modelo_una_sola_vez(): void
    {
        [, $r, $sql] = $this->mover(9);
        $r->assertOk();

        $releeStd = count(array_filter($sql, fn ($s) => str_starts_with($s, 'select "StdToaHra" from "ReqProgramaTejido"')));
        $releeModelo = count(array_filter($sql, fn ($s) => str_contains($s, "REPLACE(UPPER(LTRIM(RTRIM(TamanoClave))), '  ', ' ') = ?")));

        // StdToaHra sale del modelo ya cargado; ReqModelosCodificados se memoiza por (TamanoClave, salón).
        $this->assertSame(0, $releeStd, 'el observer no relee StdToaHra de un registro ya cargado');
        $this->assertSame(1, $releeModelo, 'el mismo modelo/salón se busca una sola vez en toda la cascada');
    }

    public function test_el_modelo_sin_callback_prefiere_el_del_salon_del_registro_y_es_determinista(): void
    {
        DB::table('ReqModelosCodificados')->insert([
            ['TamanoClave' => 'MOD2', 'SalonTejidoId' => 'JACQUARD', 'Total' => 10, 'FechaTejido' => '2026-09-01 00:00:00'],
            ['TamanoClave' => 'MOD2', 'SalonTejidoId' => 'SMIT', 'Total' => 20, 'FechaTejido' => '2026-08-01 00:00:00'],
            ['TamanoClave' => 'MOD2', 'SalonTejidoId' => 'SMIT', 'Total' => 30, 'FechaTejido' => '2026-09-05 00:00:00'],
        ]);
        $total = fn (string $salon) => HorasProduccion::obtenerModeloParams(new ReqProgramaTejido(['TamanoClave' => 'MOD2', 'SalonTejidoId' => $salon]))['total'];

        $this->assertSame(30.0, $total('SMIT'), 'del salón del registro, el más reciente');
        $this->assertSame(10.0, $total('JACQUARD'), 'no el de otro salón');
        $this->assertSame(30.0, $total('ITEMA'), 'ITEMA es alias de SMIT: el más reciente de ese salón');
        $this->assertSame(0.0, $total('KARL MAYER'), 'sin modelo en su salón no se toma el de otro');

        [$consultas] = $this->contar(fn () => $total('SMIT'));
        $this->assertSame(0, $consultas, 'memoizado por (TamanoClave, salón)');
        ReqProgramaTejidoObserver::flushCaches();
        [$consultas] = $this->contar(fn () => $total('SMIT'));
        $this->assertSame(1, $consultas, 'flushCaches del observer vacía la memoria del modelo');
    }

    public function test_vincular_no_crece_en_consultas_salvo_las_escrituras_propias_de_cada_registro(): void
    {
        $esEscrituraPorRegistro = fn (string $s) => str_contains($s, '"ReqProgramaTejidoLine"') || str_contains($s, 'set "StdToaHra"');
        $resto = fn (array $sql) => count(array_filter($sql, fn ($s) => ! $esEscrituraPorRegistro($s)));

        [, $r2, $sql2] = $this->vincular(2);
        $r2->assertOk();
        [, $r5, $sql5] = $this->vincular(5);
        $r5->assertOk()->assertJsonPath('registros_vinculados', 5);

        // Sin Rule::exists (un count por Id) ni fresh() por fila: validar, vincular y sincronizar
        // CatCodificados cuesta lo mismo con 2 que con 5 registros.
        $this->assertSame($resto($sql2), $resto($sql5), implode("\n", $sql5));
        $this->assertSame([], array_values(array_filter($sql5, fn ($s) => str_contains($s, 'count(*)'))), 'sin exists por elemento');
        $this->assertSame(5 * 3, count($sql5) - $resto($sql5), 'por registro solo UPDATE de eficiencia + DELETE/INSERT de líneas');
    }

    public function test_vincular_con_un_id_inexistente_responde_422_sin_escribir(): void
    {
        $this->sembrarTelares(3, 2);
        $antes = $this->fotoSuperficies();

        $this->como()->postJson('/planeacion/programa-tejido/vincular-registros-existentes', ['registros_ids' => [102, 999999]])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertEquals($antes, $this->fotoSuperficies());
    }
}
