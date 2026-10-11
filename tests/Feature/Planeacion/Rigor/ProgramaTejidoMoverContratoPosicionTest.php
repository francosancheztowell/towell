<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Contrato de prioridad/mover (DragAndDropTejido) cuando Posicion NO es contigua.
 *
 * Telar 301 (SMIT), sin EnProceso: Id 10, 11, 12, 13 con Posicion 1, 2, 5, 6. El servidor
 * interpreta new_position como ÍNDICE base 0 en la lista ordenada por Posicion (no como
 * valor de Posicion) y al guardar renumera todo el telar a 1..n.
 *
 * El cliente (calcTargetPosition en resources/js/programa-tejido/index.ts) deriva
 * new_position del data-posicion de la fila destino, o sea del valor de Posicion: con
 * huecos, los dos no coinciden (ver test_new_position_derivado_del_valor_de_posicion_...).
 */
class ProgramaTejidoMoverContratoPosicionTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    /** Claves que updateTableAfterDragDrop sabe pintar en la grilla tras mover. */
    private const CLAVES_PINTABLES = ['Posicion', 'FechaInicio', 'FechaFinal', 'EnProceso', 'Ultimo', 'CambioHilo', 'HorasProd'];

    /** Fórmulas que SecuenciaFechasTelar recalcula y viajan también en updates. */
    private const CLAVES_FORMULAS = [
        'StdToaHra', 'PesoGRM2', 'DiasEficiencia', 'StdDia', 'ProdKgDia', 'StdHrsEfect', 'ProdKgDia2',
        'HorasProd', 'DiasJornada', 'EntregaCte', 'EntregaPT', 'EntregaProduc', 'PTvsCte',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        foreach (['ReqProgramaTejido', 'MuestrasPrograma'] as $tabla) {
            DB::table($tabla)->insert([
                ['Id' => 10, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '301', 'Posicion' => 1, 'EnProceso' => 0, 'NoProduccion' => '31010',
                    'TotalPedido' => 100, 'SaldoPedido' => 100, 'HorasProd' => 10, 'FibraRizo' => 'A', 'Ultimo' => '0',
                    'FechaInicio' => '2026-09-11 06:00:00', 'FechaFinal' => '2026-09-11 16:00:00'],
                ['Id' => 11, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '301', 'Posicion' => 2, 'EnProceso' => 0, 'NoProduccion' => null,
                    'TotalPedido' => 200, 'SaldoPedido' => 200, 'HorasProd' => 20, 'FibraRizo' => 'A', 'Ultimo' => '0',
                    'FechaInicio' => '2026-09-11 16:00:00', 'FechaFinal' => '2026-09-12 12:00:00'],
                ['Id' => 12, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '301', 'Posicion' => 5, 'EnProceso' => 0, 'NoProduccion' => null,
                    'TotalPedido' => 300, 'SaldoPedido' => 300, 'HorasProd' => 30, 'FibraRizo' => 'B', 'Ultimo' => '0',
                    'FechaInicio' => '2026-09-12 12:00:00', 'FechaFinal' => '2026-09-13 18:00:00'],
                ['Id' => 13, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '301', 'Posicion' => 6, 'EnProceso' => 0, 'NoProduccion' => null,
                    'TotalPedido' => 400, 'SaldoPedido' => 400, 'HorasProd' => 40, 'FibraRizo' => 'A', 'Ultimo' => '1',
                    'FechaInicio' => '2026-09-13 18:00:00', 'FechaFinal' => '2026-09-15 10:00:00'],
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mover(int $id, int $nuevaPosicion)
    {
        return $this->actingAs($this->usuarioConPermisos([2 => ['modificar']]))
            ->postJson("/planeacion/programa-tejido/{$id}/prioridad/mover", ['new_position' => $nuevaPosicion]);
    }

    private function local(string $iso): string
    {
        return Carbon::parse($iso)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    private function orden(string $tabla = 'ReqProgramaTejido'): array
    {
        return DB::table($tabla)->where('NoTelarId', '301')->orderBy('Posicion')->pluck('Id')->map(fn ($v) => (int) $v)->all();
    }

    private function valoresPosicion(string $tabla = 'ReqProgramaTejido'): array
    {
        return DB::table($tabla)->where('NoTelarId', '301')->orderBy('Posicion')->pluck('Posicion')->map(fn ($v) => (int) $v)->all();
    }

    public function test_new_position_es_indice_base_cero_no_valor_de_posicion(): void
    {
        // Índice 2 = donde hoy está Id 12 (Posicion 5): Id 13 cae tercero.
        $this->mover(13, 2)->assertOk()->assertJsonPath('success', true);

        $this->assertSame([10, 11, 13, 12], $this->orden());
    }

    public function test_mover_con_huecos_renumera_todo_el_telar_a_uno_n(): void
    {
        $this->mover(13, 2)->assertOk();

        $this->assertSame([1, 2, 3, 4], $this->valoresPosicion());
    }

    public function test_new_position_derivado_del_valor_de_posicion_con_hueco_cae_fuera_de_rango(): void
    {
        // CARACTERIZA: el cliente calcula new_position = Posicion destino - 1 (calcTargetPosition).
        // Soltar Id 10 "antes" de la fila con Posicion 6 manda 6-1-1 = 4, que para el servidor
        // es un índice fuera de rango en un telar de 4: el drag falla y nada se mueve.
        $antes = $this->fotoSuperficies();

        $this->mover(10, 4)
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'La nueva posición está fuera del rango válido.']);

        $this->assertSame($antes, $this->fotoSuperficies());
        $this->assertSame([1, 2, 5, 6], $this->valoresPosicion(), 'los huecos siguen: no se renumera en un rechazo');
    }

    public function test_new_position_derivado_del_valor_de_posicion_con_hueco_aterriza_en_otro_lugar(): void
    {
        // CARACTERIZA: soltar Id 10 (Posicion 1) "antes" de Id 12 (Posicion 5) hace que
        // calcTargetPosition mande (5-1)-1 = 3. El usuario esperaba [11, 10, 12, 13] (índice 1);
        // el servidor lo pone en el índice 3: ÚLTIMO, después de Id 13.
        $this->mover(10, 3)->assertOk();

        $this->assertSame([11, 12, 13, 10], $this->orden());
    }

    public function test_mover_al_mismo_indice_se_rechaza_aunque_posicion_sea_distinta(): void
    {
        // Id 12 está en el índice 2 aunque su Posicion valga 5.
        $antes = $this->fotoSuperficies();

        $this->mover(12, 2)
            ->assertStatus(422)
            ->assertExactJson(['success' => false, 'message' => 'El registro ya está en esa posición.']);

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_fallo_de_bd_al_mover_es_500_con_mensaje_fijo_y_revierte(): void
    {
        DB::statement("CREATE TRIGGER no_mover_10 BEFORE UPDATE ON ReqProgramaTejido WHEN OLD.Id = 10 BEGIN SELECT RAISE(ABORT, 'falla simulada'); END");
        $antes = $this->fotoSuperficies();

        $r = $this->mover(10, 2)->assertStatus(500)->assertJsonPath('success', false);

        $this->assertSame('No se pudo mover el registro. Intente de nuevo.', $r->json('message'));
        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_respuesta_de_mover_tiene_forma_estable(): void
    {
        $r = $this->mover(13, 2)->assertOk();

        $this->assertSame(
            ['success', 'message', 'cascaded_records', 'detalles', 'updates', 'registro_id', 'deseleccionar'],
            array_keys($r->json())
        );
        $this->assertSame('Prioridad actualizada correctamente', $r->json('message'));
        $this->assertSame(13, $r->json('registro_id'));
        $this->assertTrue($r->json('deseleccionar'));
        $this->assertSame(4, $r->json('cascaded_records'));
        $this->assertCount(4, $r->json('detalles'));
        $this->assertSame(['10', '11', '12', '13'], collect(array_keys($r->json('updates')))->map(fn ($k) => (string) $k)->sort()->values()->all());
        $this->assertSame([10, 11, 13, 12], array_column($r->json('detalles'), 'Id'));
        $this->assertSame([1, 2, 3, 4], array_column($r->json('detalles'), 'Posicion'));
    }

    public function test_updates_trae_siempre_las_claves_que_el_cliente_pinta(): void
    {
        $r = $this->mover(13, 2)->assertOk();

        foreach ($r->json('updates') as $id => $u) {
            foreach (['Posicion', 'FechaInicio', 'FechaFinal', 'EnProceso', 'Ultimo', 'CambioHilo'] as $clave) {
                $this->assertArrayHasKey($clave, $u, "updates[{$id}] debe traer {$clave}");
            }
            // CARACTERIZA: HorasProd solo viaja si FormulasSecuenciaTelar pudo calcular HorasProdRaw
            // (aquí no: faltan velocidad/eficiencia). Entonces detalles trae HorasProd_calc = null
            // y updateTableAfterDragDrop lo copia como HorasProd = null: la celda queda en blanco
            // aunque la BD conserva las horas guardadas.
            $this->assertArrayNotHasKey('HorasProd', $u);
        }
        foreach ($r->json('detalles') as $d) {
            $this->assertArrayHasKey('HorasProd_calc', $d);
            $this->assertNull($d['HorasProd_calc']);
        }
        $this->assertSame([10.0, 20.0, 30.0, 40.0], DB::table('ReqProgramaTejido')->whereIn('Id', [10, 11, 12, 13])->orderBy('Id')->pluck('HorasProd')->map(fn ($h) => (float) $h)->all());
    }

    public function test_updates_solo_trae_claves_pintables_o_formulas_conocidas(): void
    {
        $updates = $this->mover(13, 2)->assertOk()->json('updates');

        $permitidas = [...self::CLAVES_PINTABLES, ...self::CLAVES_FORMULAS, 'UpdatedAt'];
        foreach ($updates as $id => $u) {
            $this->assertSame([], array_values(array_diff(array_keys($u), $permitidas)), "updates[{$id}] trae claves que el cliente no conoce");
            // CARACTERIZA: UpdatedAt viaja al cliente aunque no es columna de la grilla
            // (ddApplyUpdatesToRow lo ignora por no tener td[data-column]); es ruido en el payload.
            $this->assertArrayHasKey('UpdatedAt', $u);
        }
    }

    public function test_updates_coincide_con_lo_que_quedo_en_la_bd(): void
    {
        $updates = $this->mover(13, 2)->assertOk()->json('updates');

        foreach ($updates as $id => $u) {
            $fila = DB::table('ReqProgramaTejido')->where('Id', (int) $id)->first();
            $this->assertSame((int) $u['Posicion'], (int) $fila->Posicion, "Posicion de {$id}");
            // CARACTERIZA: las fechas de updates salen como ISO-8601 en UTC
            // ("2026-09-11T12:00:00.000000Z" = 06:00 en America/Mexico_City), no como el
            // "Y-m-d H:i:s" local que guarda la BD y que trae el resto de la grilla: el cliente
            // depende de new Date() y de la zona del navegador para pintarlas bien.
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $u['FechaInicio']);
            $this->assertSame($this->local($u['FechaInicio']), $fila->FechaInicio, "FechaInicio de {$id}");
            $this->assertSame($this->local($u['FechaFinal']), $fila->FechaFinal, "FechaFinal de {$id}");
            $this->assertSame((int) $u['EnProceso'], (int) $fila->EnProceso, "EnProceso de {$id}");
            $this->assertSame((string) $u['Ultimo'], (string) $fila->Ultimo, "Ultimo de {$id}");
            $this->assertSame((string) $u['CambioHilo'], (string) $fila->CambioHilo, "CambioHilo de {$id}");
        }
    }

    public function test_mover_encadena_fechas_y_marca_ultimo_y_cambio_hilo(): void
    {
        $this->mover(13, 2)->assertOk();

        $filas = DB::table('ReqProgramaTejido')->where('NoTelarId', '301')->orderBy('Posicion')->get()->keyBy('Id');
        $this->assertSame('2026-09-11 06:00:00', $filas[10]->FechaInicio, 'el primero conserva el inicio original del telar');
        $orden = [10, 11, 13, 12];
        for ($i = 1; $i < 4; $i++) {
            $this->assertSame($filas[$orden[$i - 1]]->FechaFinal, $filas[$orden[$i]]->FechaInicio, 'sin huecos ni solapes entre consecutivos');
        }
        $this->assertSame(['0', '0', '0', '1'], array_map(fn ($id) => (string) $filas[$id]->Ultimo, $orden));
        // Fibras A, A, A, B → solo cambia al pasar a Id 12.
        $this->assertSame(['0', '0', '0', '1'], array_map(fn ($id) => (string) $filas[$id]->CambioHilo, $orden));
        // Los pedidos no se tocan.
        $this->assertSame([100.0, 200.0, 400.0, 300.0], array_map(fn ($id) => (float) $filas[$id]->TotalPedido, $orden));
        $this->assertSame([100.0, 200.0, 400.0, 300.0], array_map(fn ($id) => (float) $filas[$id]->SaldoPedido, $orden));
    }

    public function test_mover_al_indice_cero_en_telar_sin_en_proceso_marca_en_proceso_al_movido(): void
    {
        // CARACTERIZA: SecuenciaFechasTelar::recalcular pone EnProceso = 1 al índice 0 sin mirar
        // si el telar tenía alguno en proceso. Reordenar un telar en espera "arranca" el
        // registro arrastrado al primer lugar y le quita EnProceso al que estaba primero.
        // Las fechas, en cambio, se calculan con el EnProceso VIEJO (0): arranca en el inicio
        // original del telar, no en now() como un EnProceso de verdad.
        $this->mover(13, 0)->assertOk();

        $filas = DB::table('ReqProgramaTejido')->where('NoTelarId', '301')->orderBy('Posicion')->get()->keyBy('Id');
        $this->assertSame([13, 10, 11, 12], $this->orden());
        $this->assertSame(1, (int) $filas[13]->EnProceso);
        $this->assertSame([0, 0, 0], [(int) $filas[10]->EnProceso, (int) $filas[11]->EnProceso, (int) $filas[12]->EnProceso]);
        $this->assertSame('2026-09-11 06:00:00', $filas[13]->FechaInicio);
    }

    public function test_mover_con_huecos_no_toca_otros_telares_ni_muestras(): void
    {
        $antes = $this->fotoSuperficies();

        $this->mover(13, 2)->assertOk();

        $despues = $this->fotoSuperficies();
        $sinTelar301 = fn (array $filas) => array_values(array_filter($filas, fn ($f) => ! in_array((int) $f['Id'], [10, 11, 12, 13], true)));
        $this->assertSame($sinTelar301($antes['ReqProgramaTejido']), $sinTelar301($despues['ReqProgramaTejido']));
        foreach (['MuestrasPrograma', 'MuestrasProgramaLine', 'CatCodificados'] as $tabla) {
            $this->assertSame($antes[$tabla], $despues[$tabla], "{$tabla} no debe cambiar");
        }
    }

    public function test_el_mismo_contrato_de_indice_aplica_en_muestras(): void
    {
        $programaAntes = DB::table('ReqProgramaTejido')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();

        $this->actingAs($this->usuarioConPermisos([5 => ['modificar']]))
            ->postJson('/planeacion/muestras/13/prioridad/mover', ['new_position' => 2])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame([10, 11, 13, 12], $this->orden('MuestrasPrograma'));
        $this->assertSame([1, 2, 3, 4], $this->valoresPosicion('MuestrasPrograma'));
        $this->assertSame($programaAntes, DB::table('ReqProgramaTejido')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all());
    }
}
