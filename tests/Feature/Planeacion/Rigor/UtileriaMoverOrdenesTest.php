<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqModelosCodificados;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Utilería · Mover órdenes (POST planeacion/utileria/mover/procesar, MoverOrdenesController).
 * Caracterización rigurosa: estado completo de las filas tocadas, aislamiento de otros
 * telares y de Muestras, CatCodificados, rollback total y permiso (idrol 188 + modificar).
 *
 * Telares sembrados (Programa):
 *   201 SMIT  : Id 1 (pos 1, EnProceso, 30001, 24 h) · Id 2 (pos 2, SIN orden, 10 h)
 *               · Id 6 (pos 3, 30006, 12 h) · Id 7 (pos 4, 30007, 6 h, Ultimo)
 *   202 JACQ. : Id 3 (pos 1, EnProceso, líder OrdCompartida 7)
 *   203 JACQ. : Id 4 (miembro OrdCompartida 7)
 *   204 SMIT  : Id 5 (pos 1, fila casi vacía)
 *   205 SMIT  : vacío
 *
 * Diferencias con SQL Server que el setUp corrige SOLO aquí: Posicion se crea INTEGER (el
 * trait la deja TEXT porque el modelo no la castea y el orden saldría lexicográfico: '10002' <
 * '2') con el índice único (NoTelarId, Posicion) que protege live, y la conexión reescribe
 * ISNULL( → IFNULL( (ISNULL es palabra reservada en sqlite).
 */
class UtileriaMoverOrdenesTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const URL = '/planeacion/utileria/mover/procesar';

    private const AHORA = '2026-09-10 08:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::AHORA);
        Connection::resolverFor('sqlite', fn ($pdo, $db, $prefix, $config) => new class($pdo, $db, $prefix, $config) extends SQLiteConnection
        {
            public function affectingStatement($query, $bindings = [])
            {
                return parent::affectingStatement(str_replace('ISNULL(', 'IFNULL(', $query), $bindings);
            }
        });
        $this->prepararSuperficies();
        Schema::table('ReqProgramaTejido', fn (Blueprint $t) => $t->integer('Posicion')->nullable()->change());
        DB::statement('CREATE UNIQUE INDEX ux_pt_telar_posicion ON "ReqProgramaTejido" ("NoTelarId", "Posicion")');

        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        // Sin calendario: las fechas avanzan por horas corridas y el resultado es exacto.
        DB::table('ReqProgramaTejido')->where('Id', 1)->update(['HorasProd' => 24, 'CalendarioId' => null, 'Maquina' => 'SMI 201']);
        DB::table('ReqProgramaTejido')->where('Id', 2)->update(['Ultimo' => '0', 'HorasProd' => 10]);
        DB::table('ReqProgramaTejido')->insert([
            ['Id' => 6, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 3, 'EnProceso' => 0,
                'NoProduccion' => '30006', 'TamanoClave' => 'MOD6', 'TotalPedido' => 300, 'SaldoPedido' => 300, 'HorasProd' => 12,
                'FechaInicio' => '2026-09-05 06:00:00', 'FechaFinal' => '2026-09-05 18:00:00', 'Ultimo' => '0', 'Maquina' => 'SMI 201',
                'CuentaRizo' => '100', 'CuentaPie' => '200', 'Ancho' => 50, 'AnchoToalla' => 50, 'EficienciaSTD' => 0.8, 'VelocidadSTD' => 300],
        ]);
        DB::table('ReqProgramaTejido')->insert([
            ['Id' => 7, 'SalonTejidoId' => 'SMIT', 'NoTelarId' => '201', 'Posicion' => 4, 'EnProceso' => 0,
                'NoProduccion' => '30007', 'TamanoClave' => 'MOD7', 'TotalPedido' => 200, 'SaldoPedido' => 200, 'HorasProd' => 6,
                'FechaInicio' => '2026-09-05 18:00:00', 'FechaFinal' => '2026-09-06 00:00:00', 'Ultimo' => '1', 'Maquina' => 'SMI 201'],
        ]);
        DB::table('CatCodificados')->insert([
            ['OrdenTejido' => '30006', 'TelarId' => 201, 'Departamento' => 'SMIT', 'Pedido' => 300],
            ['OrdenTejido' => '30007', 'TelarId' => 201, 'Departamento' => 'SMIT', 'Pedido' => 200],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
        Connection::resolverFor('sqlite', fn ($pdo, $db, $prefix, $config) => new SQLiteConnection($pdo, $db, $prefix, $config));
    }

    // ---- helpers --------------------------------------------------------------------

    private function como(array $permisos = [188 => ['modificar']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    /** Ids del telar en orden de Posicion. */
    private function idsDe(string $telar): array
    {
        return DB::table('ReqProgramaTejido')->where('NoTelarId', $telar)->orderBy('Posicion')->pluck('Id')->map(fn ($v) => (int) $v)->all();
    }

    /** Estado de planeación de una fila: lo que un mover puede romper. */
    private function estado(int $id): array
    {
        $r = DB::table('ReqProgramaTejido')->where('Id', $id)->first();

        return [
            'NoTelarId' => (string) $r->NoTelarId,
            'SalonTejidoId' => (string) $r->SalonTejidoId,
            'Posicion' => (int) $r->Posicion,
            'FechaInicio' => $r->FechaInicio,
            'FechaFinal' => $r->FechaFinal,
            'EnProceso' => (int) $r->EnProceso,
            'Ultimo' => (string) $r->Ultimo,
        ];
    }

    /** Filas (completas) fuera de los telares indicados. */
    private function filasFuera(array $telares): array
    {
        return DB::table('ReqProgramaTejido')->whereNotIn('NoTelarId', $telares)->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();
    }

    /** Lo que pertenece a Muestras en la foto: Mover solo opera sobre Programa. */
    private function fotoMuestras(): array
    {
        $foto = $this->fotoSuperficies();

        return [$foto['MuestrasPrograma'] ?? null, $foto['MuestrasProgramaLine'] ?? null];
    }

    private function sinCambiosPersistidos(array $antes): void
    {
        $this->assertSame($antes, $this->fotoSuperficies(), 'ninguna tabla de Programa, Muestras ni CatCodificados debe cambiar');
    }

    /** Trigger sqlite que hace fallar una fila concreta, para forzar el rollback a mitad de proceso. */
    private function fallarCuando(string $condicion): void
    {
        DB::statement('CREATE TRIGGER fallo_forzado BEFORE UPDATE ON "ReqProgramaTejido" WHEN '.$condicion
            ." BEGIN SELECT RAISE(ABORT, 'fallo forzado'); END");
    }

    private function reordenarOrigen(array $ordenes, string $telar = '201', string $salon = 'SMIT')
    {
        return $this->como()->postJson(self::URL, [
            'ordenes_origen' => $ordenes, 'origen_salon' => $salon, 'origen_telar' => $telar,
            'solo_reorden_origen' => true,
        ]);
    }

    private function mover(array $origen, array $destino, string $telarDestino, string $salonDestino = 'SMIT')
    {
        return $this->como()->postJson(self::URL, [
            'ordenes_origen' => $origen, 'origen_salon' => 'SMIT', 'origen_telar' => '201',
            'ordenes_destino' => $destino, 'destino_salon' => $salonDestino, 'destino_telar' => $telarDestino,
        ]);
    }

    // ---- reordenar en el mismo telar ------------------------------------------------

    public function test_reordenar_en_el_mismo_telar_compacta_posiciones_encadena_fechas_y_solo_el_primero_queda_en_proceso(): void
    {
        $muestras = $this->fotoMuestras();
        $fuera = $this->filasFuera(['201']);
        $cat = DB::table('CatCodificados')->orderBy('Id')->get()->all();

        // La UI solo manda las filas con NoProduccion (Id 2 no aparece en el panel).
        $this->reordenarOrigen([1, 7, 6])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Se guardaron los cambios correctamente.']);

        $this->assertSame([1, 7, 6, 2], $this->idsDe('201'));
        $this->assertSame(
            ['NoTelarId' => '201', 'SalonTejidoId' => 'SMIT', 'Posicion' => 1, 'FechaInicio' => '2026-09-10 08:00:00', 'FechaFinal' => '2026-09-11 08:00:00', 'EnProceso' => 1, 'Ultimo' => '0'],
            $this->estado(1)
        );
        $this->assertSame(
            ['NoTelarId' => '201', 'SalonTejidoId' => 'SMIT', 'Posicion' => 2, 'FechaInicio' => '2026-09-11 08:00:00', 'FechaFinal' => '2026-09-11 14:00:00', 'EnProceso' => 0, 'Ultimo' => '0'],
            $this->estado(7)
        );
        $this->assertSame(
            ['NoTelarId' => '201', 'SalonTejidoId' => 'SMIT', 'Posicion' => 3, 'FechaInicio' => '2026-09-11 14:00:00', 'FechaFinal' => '2026-09-12 02:00:00', 'EnProceso' => 0, 'Ultimo' => '0'],
            $this->estado(6)
        );
        $this->assertSame(
            ['NoTelarId' => '201', 'SalonTejidoId' => 'SMIT', 'Posicion' => 4, 'FechaInicio' => '2026-09-12 02:00:00', 'FechaFinal' => '2026-09-12 12:00:00', 'EnProceso' => 0, 'Ultimo' => '1'],
            $this->estado(2)
        );

        $this->assertSame($fuera, $this->filasFuera(['201']), 'otros telares intactos');
        $this->assertSame($muestras, $this->fotoMuestras(), 'Muestras intacta');
        $this->assertEquals($cat, DB::table('CatCodificados')->orderBy('Id')->get()->all(), 'un reorden no toca CatCodificados');
    }

    public function test_reordenar_manda_al_final_los_registros_sin_orden_que_la_ui_no_muestra(): void
    {
        // La UI solo lista filas con NoProduccion (getRegistrosByTelar).
        $listado = $this->como()->getJson('/planeacion/utileria/mover/registros?salon=SMIT&telar=201')->assertOk();
        $this->assertSame([1, 6, 7], array_column($listado->json('registros'), 'id'));

        // Se guarda el MISMO orden que mostraba la pantalla.
        $this->reordenarOrigen([1, 6, 7])->assertOk();

        // CARACTERIZA: Id 2 (sin orden, pos 2) no estaba en pantalla y el usuario no movió nada,
        // pero el bump +10000 la deja detrás de todas las enviadas: pasa de pos 2 a pos 4 y su
        // FechaInicio queda detrás de 30006 y 30007. Parece bug: guardar el orden que se veía
        // en pantalla reprograma filas que la pantalla no muestra.
        $this->assertSame([1, 6, 7, 2], $this->idsDe('201'));
        $this->assertSame('1', $this->estado(2)['Ultimo']);
        $this->assertSame('2026-09-12 02:00:00', $this->estado(2)['FechaInicio']);
    }

    public function test_el_registro_en_proceso_del_telar_se_reancla_a_now_al_guardar(): void
    {
        $this->assertSame('2026-09-01 06:30:00', $this->estado(1)['FechaInicio']);

        $this->reordenarOrigen([1, 6, 7])->assertOk();

        // CARACTERIZA: el docblock de recalcularFechasPorTelar dice "usa now() y NO actualiza
        // FechaInicio", pero el update sí la escribe: la orden que lleva tejiendo desde el
        // 01-sep queda con FechaInicio = now() y su FechaFinal se corre 7 días.
        $this->assertSame(self::AHORA, $this->estado(1)['FechaInicio']);
        $this->assertSame('2026-09-11 08:00:00', $this->estado(1)['FechaFinal']);
        $this->assertSame(1, $this->estado(1)['EnProceso']);
    }

    // ---- pasar de un telar a otro ---------------------------------------------------

    public function test_mover_a_un_telar_vacio_queda_en_posicion_1_en_proceso_y_compacta_el_origen(): void
    {
        $muestras = $this->fotoMuestras();
        $fuera = $this->filasFuera(['201', '205']);

        $this->mover([1, 7], [6], '205')
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame([1, 7, 2], $this->idsDe('201'));
        $this->assertSame([6], $this->idsDe('205'));

        // Regla del controller: en cada telar afectado solo Posicion 1 queda EnProceso.
        $this->assertSame(
            ['NoTelarId' => '205', 'SalonTejidoId' => 'SMIT', 'Posicion' => 1, 'FechaInicio' => self::AHORA, 'FechaFinal' => '2026-09-10 20:00:00', 'EnProceso' => 1, 'Ultimo' => '1'],
            $this->estado(6)
        );
        $this->assertSame('SMI 205', DB::table('ReqProgramaTejido')->where('Id', 6)->value('Maquina'));

        $this->assertSame(['201', 1, '2026-09-10 08:00:00', '2026-09-11 08:00:00', 1, '0'], array_values(array_diff_key($this->estado(1), ['SalonTejidoId' => 1])));
        $this->assertSame([2, '2026-09-11 08:00:00', '2026-09-11 14:00:00', 0, '0'], [$this->estado(7)['Posicion'], $this->estado(7)['FechaInicio'], $this->estado(7)['FechaFinal'], $this->estado(7)['EnProceso'], $this->estado(7)['Ultimo']]);
        $this->assertSame([3, '2026-09-11 14:00:00', '2026-09-12 00:00:00', 0, '1'], [$this->estado(2)['Posicion'], $this->estado(2)['FechaInicio'], $this->estado(2)['FechaFinal'], $this->estado(2)['EnProceso'], $this->estado(2)['Ultimo']]);

        $this->assertSame($fuera, $this->filasFuera(['201', '205']));
        $this->assertSame($muestras, $this->fotoMuestras());
    }

    public function test_mover_a_un_telar_con_registros_respeta_el_orden_enviado_y_encadena_desde_el_primero(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 5)->update([
            'EnProceso' => 1, 'HorasProd' => 5, 'NoProduccion' => '30005', 'TotalPedido' => 100, 'SaldoPedido' => 100,
            'FechaInicio' => '2026-09-08 00:00:00', 'FechaFinal' => '2026-09-08 05:00:00', 'Ultimo' => '1',
        ]);

        // Id 7 detrás de Id 5 (el que corre en 204).
        $this->mover([1, 6], [5, 7], '204')->assertOk();

        $this->assertSame([1, 6, 2], $this->idsDe('201'));
        $this->assertSame([5, 7], $this->idsDe('204'));
        $this->assertSame(
            ['NoTelarId' => '204', 'SalonTejidoId' => 'SMIT', 'Posicion' => 1, 'FechaInicio' => self::AHORA, 'FechaFinal' => '2026-09-10 13:00:00', 'EnProceso' => 1, 'Ultimo' => '0'],
            $this->estado(5)
        );
        $this->assertSame(
            ['NoTelarId' => '204', 'SalonTejidoId' => 'SMIT', 'Posicion' => 2, 'FechaInicio' => '2026-09-10 13:00:00', 'FechaFinal' => '2026-09-10 19:00:00', 'EnProceso' => 0, 'Ultimo' => '1'],
            $this->estado(7)
        );
    }

    public function test_mover_antes_del_registro_en_proceso_del_destino_lo_desplaza_sin_rechazo(): void
    {
        // Id 3 corre en el 202 (JACQUARD) y es líder de OrdCompartida 7.
        $grupoAntes = DB::table('ReqProgramaTejido')->whereIn('Id', [3, 4])->orderBy('Id')->get(['Id', 'OrdCompartida', 'OrdCompartidaLider', 'TotalPedido', 'SaldoPedido'])->map(fn ($r) => (array) $r)->all();

        $this->mover([1, 6], [7, 3], '202', 'JACQUARD')->assertOk()->assertJsonPath('success', true);

        // CARACTERIZA: cambiar-telar responde 422 ("posición mínima") si se intenta colocar antes
        // de un EnProceso; Mover lo acepta y le quita EnProceso a la orden que físicamente teje.
        $this->assertSame([7, 3], $this->idsDe('202'));
        $this->assertSame(1, $this->estado(7)['EnProceso']);
        $this->assertSame(0, $this->estado(3)['EnProceso']);
        $this->assertSame('JACQUARD', $this->estado(7)['SalonTejidoId']);
        $this->assertSame('JAC 202', DB::table('ReqProgramaTejido')->where('Id', 7)->value('Maquina'));

        // El grupo compartido conserva líder y cantidades.
        $this->assertSame($grupoAntes, DB::table('ReqProgramaTejido')->whereIn('Id', [3, 4])->orderBy('Id')->get(['Id', 'OrdCompartida', 'OrdCompartidaLider', 'TotalPedido', 'SaldoPedido'])->map(fn ($r) => (array) $r)->all());
        $this->assertSame(1, $this->estado(4)['Posicion'], 'el otro miembro (telar 203) no se toca');
    }

    public function test_mover_un_registro_en_proceso_a_otro_telar_no_se_rechaza(): void
    {
        $this->mover([6, 7], [1], '205')->assertOk()->assertJsonPath('success', true);

        // CARACTERIZA: cambiar-telar rechaza EnProceso con 422 ("Debe finalizar el proceso antes
        // de moverlo"); Mover lo pasa de telar sin aviso y el origen promueve a Id 6 a EnProceso
        // con FechaInicio = now(): el 201 queda "tejiendo" otra orden y el 205 la que se movió.
        $this->assertSame([1], $this->idsDe('205'));
        $this->assertSame(1, $this->estado(1)['EnProceso']);
        $this->assertSame([6, 7, 2], $this->idsDe('201'));
        $this->assertSame(
            ['NoTelarId' => '201', 'SalonTejidoId' => 'SMIT', 'Posicion' => 1, 'FechaInicio' => self::AHORA, 'FechaFinal' => '2026-09-10 20:00:00', 'EnProceso' => 1, 'Ultimo' => '0'],
            $this->estado(6)
        );
    }

    // ---- CatCodificados -------------------------------------------------------------

    public function test_cat_codificados_al_cambiar_de_telar_dentro_del_salon(): void
    {
        $this->mover([1, 7], [6], '205')->assertOk();

        $cat = DB::table('CatCodificados')->where('OrdenTejido', '30006')->get();
        $this->assertCount(1, $cat, 'no se duplica la fila');
        // CARACTERIZA: el sincronizador localiza la fila por OrdenTejido + TelarId usando el telar
        // NUEVO (205) y la fila sigue en el 201, así que no la encuentra: CatCodificados no sigue
        // a la orden. cambiar-telar sí la mueve (sincronizarTelarCatCodificados filtra por el viejo).
        $this->assertSame(201, (int) $cat[0]->TelarId);
        $this->assertSame('SMIT', $cat[0]->Departamento);
        // Las demás órdenes del Cat no se tocan.
        $this->assertSame(201, (int) DB::table('CatCodificados')->where('OrdenTejido', '30007')->value('TelarId'));
    }

    public function test_cat_codificados_al_cambiar_de_salon(): void
    {
        $this->mover([1, 7], [6], '210', 'JACQUARD')->assertOk();

        $this->assertSame(['210', 'JACQUARD'], [$this->estado(6)['NoTelarId'], $this->estado(6)['SalonTejidoId']]);
        $cat = DB::table('CatCodificados')->where('OrdenTejido', '30006')->get();
        $this->assertCount(1, $cat);
        // CARACTERIZA: mismo hueco que dentro del salón; tampoco el paso 5 (MovimientoDesarrollador)
        // lo corrige porque escribe Pedido/Saldos, no TelarId/Departamento.
        $this->assertSame(201, (int) $cat[0]->TelarId);
        $this->assertSame('SMIT', $cat[0]->Departamento);
    }

    // ---- rollback ------------------------------------------------------------------

    public function test_si_falla_una_fila_al_moverla_no_queda_nada_a_medias(): void
    {
        $antes = $this->fotoSuperficies();
        // Falla Id 6 al llegar al destino: el origen ya se reordenó y hay que revertirlo.
        $this->fallarCuando("NEW.\"Id\" = 6 AND NEW.\"NoTelarId\" = '205'");

        $r = $this->mover([7, 1], [6], '205')->assertStatus(500);
        $this->assertSame(['success', 'message', 'trace_id'], array_keys($r->json()));
        $this->assertFalse($r->json('success'));
        $this->assertSame('Error al guardar los cambios', $r->json('message'));

        $this->sinCambiosPersistidos($antes);
    }

    public function test_si_falla_el_recalculo_de_fechas_no_queda_nada_a_medias(): void
    {
        $antes = $this->fotoSuperficies();
        // Solo el paso 2 escribe CambioHilo: el paso 1 ya movió Id 6 y reordenó el origen.
        $this->fallarCuando('NEW."Id" = 2 AND NEW."CambioHilo" IS NOT NULL');

        $this->mover([7, 1], [6], '205')->assertStatus(500)->assertJsonPath('success', false);

        $this->sinCambiosPersistidos($antes);
    }

    // ---- entrada y permisos --------------------------------------------------------

    public function test_sin_permiso_modificar_en_utileria_188_es_403_y_no_toca_nada(): void
    {
        $antes = $this->fotoSuperficies();

        // modificar en Programa Tejido (2) no basta; acceso en 188 tampoco.
        $this->actingAs($this->usuarioConPermisos([2 => ['modificar'], 188 => ['acceso']]))
            ->postJson(self::URL, ['ordenes_origen' => [7, 6, 1], 'origen_salon' => 'SMIT', 'origen_telar' => '201', 'solo_reorden_origen' => true])
            ->assertForbidden()
            ->assertExactJson(['message' => 'No tienes permiso para esta acción.']);

        $this->sinCambiosPersistidos($antes);
    }

    public function test_sin_sesion_es_401_y_no_toca_nada(): void
    {
        $antes = $this->fotoSuperficies();

        $this->postJson(self::URL, ['ordenes_origen' => [7, 6, 1], 'origen_salon' => 'SMIT', 'origen_telar' => '201', 'solo_reorden_origen' => true])
            ->assertUnauthorized();

        $this->sinCambiosPersistidos($antes);
    }

    public function test_ids_no_enteros_es_422_con_errores_y_no_toca_nada(): void
    {
        $antes = $this->fotoSuperficies();

        $r = $this->como()->postJson(self::URL, ['ordenes_origen' => ['abc'], 'origen_salon' => 'SMIT', 'origen_telar' => '201'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Datos de entrada invalidos');
        $this->assertArrayHasKey('ordenes_origen.0', $r->json('errors'));

        $this->sinCambiosPersistidos($antes);
    }

    // ---- divergencia con cambiar-telar ---------------------------------------------

    public function test_divergencia_mover_vs_cambiar_telar_en_los_campos_que_copia_del_modelo_destino(): void
    {
        // Gemelo de Id 6 para mover uno por cada camino al mismo salón destino.
        $gemelo = (array) DB::table('ReqProgramaTejido')->where('Id', 6)->first();
        DB::table('ReqProgramaTejido')->insert(array_merge($gemelo, ['Id' => 8, 'Posicion' => 5, 'NoProduccion' => '30008']));
        DB::table('ReqModelosCodificados')->insert([
            'TamanoClave' => 'MOD6', 'SalonTejidoId' => 'JACQUARD', 'CuentaRizo' => '777', 'CuentaPie' => '888', 'AnchoToalla' => 99,
        ]);
        DB::table('CatCodificados')->insert(['OrdenTejido' => '30008', 'TelarId' => 201, 'Departamento' => 'SMIT', 'Pedido' => 300]);
        $usuario = $this->usuarioConPermisos([188 => ['modificar'], 2 => ['modificar']]);

        $this->actingAs($usuario)->postJson(self::URL, [
            'ordenes_origen' => [1, 7, 8], 'origen_salon' => 'SMIT', 'origen_telar' => '201',
            'ordenes_destino' => [6], 'destino_salon' => 'JACQUARD', 'destino_telar' => '210',
        ])->assertOk();
        $this->actingAs($usuario)->postJson('/planeacion/programa-tejido/8/cambiar-telar', [
            'nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '211', 'target_position' => 0,
        ])->assertOk()->assertJsonPath('success', true);

        $campos = ['NoTelarId', 'SalonTejidoId', 'Maquina', 'CuentaRizo', 'CuentaPie', 'Ancho', 'AnchoToalla', 'EficienciaSTD', 'VelocidadSTD'];
        $porMover = (array) DB::table('ReqProgramaTejido')->where('Id', 6)->first($campos);
        $porCambiar = (array) DB::table('ReqProgramaTejido')->where('Id', 8)->first($campos);

        // Mover copia las cuentas del modelo destino y deja el ancho de origen.
        $this->assertSame(['210', 'JACQUARD', 'JAC 210', '777', '888', 50.0, 50.0], [
            (string) $porMover['NoTelarId'], $porMover['SalonTejidoId'], $porMover['Maquina'], $porMover['CuentaRizo'], $porMover['CuentaPie'],
            (float) $porMover['Ancho'], (float) $porMover['AnchoToalla'],
        ]);
        // cambiar-telar copia el ancho del modelo destino y deja las cuentas de origen.
        $this->assertSame(['211', 'JACQUARD', 'JAC 211', '100', '200', 99.0, 99.0], [
            (string) $porCambiar['NoTelarId'], $porCambiar['SalonTejidoId'], $porCambiar['Maquina'], $porCambiar['CuentaRizo'], $porCambiar['CuentaPie'],
            (float) $porCambiar['Ancho'], (float) $porCambiar['AnchoToalla'],
        ]);
        // Los dos resuelven STD igual (sin tablas STD conservan el de origen).
        $this->assertEquals([0.8, 300.0], [(float) $porMover['EficienciaSTD'], (float) $porMover['VelocidadSTD']]);
        $this->assertEquals([0.8, 300.0], [(float) $porCambiar['EficienciaSTD'], (float) $porCambiar['VelocidadSTD']]);

        // CatCodificados: cambiar-telar mueve la fila al telar nuevo; Mover no (ver test de Cat).
        $this->assertSame(201, (int) DB::table('CatCodificados')->where('OrdenTejido', '30006')->value('TelarId'));
        $this->assertSame([211, 'JACQUARD'], [
            (int) DB::table('CatCodificados')->where('OrdenTejido', '30008')->value('TelarId'),
            DB::table('CatCodificados')->where('OrdenTejido', '30008')->value('Departamento'),
        ]);
    }
}
