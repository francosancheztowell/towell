<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * Caracterización rigurosa de POST crear-repaso (RepasoController::createrepaso).
 *
 * Fixtures (ProgramaTejidoFixtures): telar 202 JACQUARD = Id 3 (Posicion 1, Ultimo '1',
 * FechaFinal 2026-09-02 06:30). El salón del repaso NO sale del input sino del número de
 * telar (TelarSalonResolver::salonDesdeTelar): 201-215 JACQUARD, 299-320 SMIT, 401-402 KM.
 * La respuesta usa la llave "ok" (no "success").
 */
class ProgramaTejidoRepasoRigorTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    private const URL_PROGRAMA = '/planeacion/programa-tejido/crear-repaso';

    private const URL_MUESTRAS = '/planeacion/muestras/crear-repaso';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);
        $this->createTablaDbo('ReqEficienciaStd', ['Id' => 'integer primary key', 'SalonTejidoId' => 'text', 'NoTelarId' => 'text', 'FibraId' => 'text', 'Eficiencia' => 'real', 'Densidad' => 'text']);
        $this->createTablaDbo('ReqVelocidadStd', ['Id' => 'integer primary key', 'SalonTejidoId' => 'text', 'NoTelarId' => 'text', 'FibraId' => 'text', 'Velocidad' => 'real', 'Densidad' => 'text']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function como(array $permisos = [2 => ['crear'], 5 => ['crear']])
    {
        return $this->actingAs($this->usuarioConPermisos($permisos));
    }

    private function payload(array $extra = []): array
    {
        return array_merge(['telar' => 'JACQUARD|202', 'ancho' => 100, 'hilo' => 'H1', 'calibre' => 10], $extra);
    }

    private function fila(string $tabla, int $id): array
    {
        return (array) DB::table($tabla)->where('Id', $id)->first();
    }

    private function telar(string $tabla, string $telar): array
    {
        return DB::table($tabla)->where('NoTelarId', $telar)->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();
    }

    /** Foto sin la tabla indicada (y sin sus líneas), para afirmar que nada más cambió. */
    private function fotoSalvo(array $tablas): array
    {
        return array_diff_key($this->fotoSuperficies(), array_flip($tablas));
    }

    public function test_crear_repaso_agrega_fila_al_final_del_telar_y_traspasa_ultimo(): void
    {
        $anteriorAntes = $this->fila('ReqProgramaTejido', 3);

        $r = $this->como()->postJson(self::URL_PROGRAMA, $this->payload())->assertOk();

        $r->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Repaso creado correctamente')
            ->assertJsonMissingPath('success');
        $id = (int) $r->json('id');
        $this->assertSame(6, $id, 'nuevo Id tras los 5 del fixture');
        $this->assertSame([$id], $r->json('registros_ids'));
        $this->assertSame($id, (int) $r->json('registro.Id'));
        $this->assertSame('REPASO1', $r->json('registro.NombreProducto'));

        $nuevo = $this->fila('ReqProgramaTejido', $id);
        $this->assertSame('JACQUARD', $nuevo['SalonTejidoId']);
        $this->assertSame('202', (string) $nuevo['NoTelarId']);
        $this->assertSame(2, (int) $nuevo['Posicion'], 'última posición + 1');
        $this->assertSame('1', (string) $nuevo['Ultimo']);
        $this->assertSame(0, (int) $nuevo['EnProceso']);
        $this->assertSame('REPASO1', $nuevo['NombreProducto']);
        $this->assertSame('REPASO1', $nuevo['NoExisteBase']);
        $this->assertSame('JAC 202', $nuevo['Maquina']);
        $this->assertEquals(1, $nuevo['TotalPedido']);
        $this->assertEquals(1, $nuevo['SaldoPedido']);
        $this->assertNull($nuevo['Produccion']);
        $this->assertNull($nuevo['NoProduccion']);
        $this->assertNull($nuevo['OrdCompartida']);
        $this->assertNull($nuevo['OrdCompartidaLider']);
        $this->assertNull($nuevo['TamanoClave']);
        $this->assertEquals(100, $nuevo['Ancho']);
        $this->assertEquals(10, $nuevo['CalibreRizo']);
        $this->assertSame('H1', $nuevo['FibraRizo']);
        $this->assertSame('2026-09-02 06:30:00', $nuevo['FechaInicio'], 'arranca donde termina el anterior');
        $this->assertSame('2026-09-02 18:30:00', $nuevo['FechaFinal'], 'duración fija de 12 h');

        // El anterior solo cambia Ultimo; todo lo demás (posición, fechas, grupo) intacto.
        $anteriorDespues = $this->fila('ReqProgramaTejido', 3);
        $this->assertSame('0', (string) $anteriorDespues['Ultimo']);
        $this->assertSame(
            array_diff_key($anteriorAntes, ['Ultimo' => 1]),
            array_diff_key($anteriorDespues, ['Ultimo' => 1])
        );
    }

    public function test_crear_repaso_no_toca_otros_telares_ni_la_superficie_muestras(): void
    {
        $otros = fn () => DB::table('ReqProgramaTejido')->where('NoTelarId', '!=', '202')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();
        $antesOtros = $otros();
        $antesResto = $this->fotoSalvo(['ReqProgramaTejido', 'ReqProgramaTejidoLine']);
        $lineasAntes = DB::table('ReqProgramaTejidoLine')->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all();

        $id = (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload())->assertOk()->assertJsonPath('ok', true)->json('id');

        $this->assertSame($antesOtros, $otros());
        $this->assertSame($antesResto, $this->fotoSalvo(['ReqProgramaTejido', 'ReqProgramaTejidoLine']), 'MuestrasPrograma, sus líneas y CatCodificados intactos');
        $this->assertSame($lineasAntes, DB::table('ReqProgramaTejidoLine')->where('ProgramaId', '!=', $id)->orderBy('Id')->get()->map(fn ($r) => (array) $r)->all(), 'solo se agregan las líneas del repaso');
    }

    public function test_crear_repaso_en_muestras_escribe_solo_en_muestras_programa(): void
    {
        $antesPrograma = array_intersect_key($this->fotoSuperficies(), array_flip(['ReqProgramaTejido', 'ReqProgramaTejidoLine', 'CatCodificados']));

        $r = $this->como([5 => ['crear']])->postJson(self::URL_MUESTRAS, $this->payload())
            ->assertOk()->assertJsonPath('ok', true);

        $id = (int) $r->json('id');
        $nuevo = $this->fila('MuestrasPrograma', $id);
        $this->assertSame('REPASO1', $nuevo['NombreProducto']);
        $this->assertSame(2, (int) $nuevo['Posicion']);
        $this->assertSame('1', (string) $nuevo['Ultimo']);
        $this->assertSame('0', (string) $this->fila('MuestrasPrograma', 3)['Ultimo']);

        $this->assertSame(
            $antesPrograma,
            array_intersect_key($this->fotoSuperficies(), array_flip(['ReqProgramaTejido', 'ReqProgramaTejidoLine', 'CatCodificados'])),
            'Programa no se toca desde Muestras (ni el Id 6 ni el Ultimo del Id 3)'
        );
        $this->assertSame('1', (string) $this->fila('ReqProgramaTejido', 3)['Ultimo']);
    }

    public function test_crear_repaso_sin_permiso_crear_de_su_superficie_es_403_y_no_persiste_nada(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como([2 => ['acceso', 'modificar', 'eliminar']])->postJson(self::URL_PROGRAMA, $this->payload())->assertForbidden();
        // El permiso es por superficie: crear en Programa (2) no sirve en Muestras (5) ni al revés.
        $this->como([2 => ['crear']])->postJson(self::URL_MUESTRAS, $this->payload())->assertForbidden();
        $this->como([5 => ['crear']])->postJson(self::URL_PROGRAMA, $this->payload())->assertForbidden();
        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_telar_invalido_o_no_numerico_es_422_y_no_persiste(): void
    {
        $antes = $this->fotoSuperficies();

        foreach (['JACQUARD|abc', 'abc', 'JACQUARD|', '   ', '20a2'] as $telar) {
            $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['telar' => $telar]))
                ->assertStatus(422)->assertJsonValidationErrors('telar');
        }

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_validacion_de_campos_obligatorios_es_422_con_errores_por_campo(): void
    {
        $antes = $this->fotoSuperficies();

        $this->como()->postJson(self::URL_PROGRAMA, [])
            ->assertStatus(422)->assertJsonValidationErrors(['telar', 'ancho', 'hilo']);
        $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['ancho' => -1]))
            ->assertStatus(422)->assertJsonValidationErrors('ancho');
        $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['ancho' => 'x']))
            ->assertStatus(422)->assertJsonValidationErrors('ancho');
        $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['hilo' => str_repeat('H', 51)]))
            ->assertStatus(422)->assertJsonValidationErrors('hilo');
        $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['calibre' => -0.1]))
            ->assertStatus(422)->assertJsonValidationErrors('calibre');

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    public function test_si_falla_aplicar_formulas_se_revierte_todo_y_responde_500_ok_false(): void
    {
        // El UPDATE de aplicarFormulas() es el único que toca una fila REPASO1: el INSERT y el
        // Ultimo='0' del anterior ya corrieron dentro de la transacción cuando revienta.
        DB::statement("CREATE TRIGGER falla_formulas_repaso BEFORE UPDATE ON ReqProgramaTejido
            WHEN NEW.NombreProducto = 'REPASO1' BEGIN SELECT RAISE(ABORT, 'falla formulas'); END");
        $antes = $this->fotoSuperficies();

        $this->como()->postJson(self::URL_PROGRAMA, $this->payload())
            ->assertStatus(500)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Error al crear repaso. Intente de nuevo.');

        $this->assertSame($antes, $this->fotoSuperficies(), 'ni fila nueva ni Ultimo=0 del anterior');
        $this->assertSame('1', (string) $this->fila('ReqProgramaTejido', 3)['Ultimo']);
        $this->assertSame(0, DB::table('ReqProgramaTejido')->where('NombreProducto', 'REPASO1')->count());
        $this->assertSame(0, DB::transactionLevel(), 'no queda transacción abierta');
        $this->assertNotNull(ReqProgramaTejido::getEventDispatcher(), 'suppressObservers se deshace en el catch');
    }

    public function test_dos_repasos_seguidos_encadenan_posicion_fechas_y_un_solo_ultimo(): void
    {
        $id1 = (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload())->assertOk()->json('id');
        $id2 = (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['hilo' => 'H1']))->assertOk()->json('id');

        $filas = DB::table('ReqProgramaTejido')->where('NoTelarId', '202')->orderBy('Posicion')->get();
        $this->assertSame([3, $id1, $id2], $filas->pluck('Id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([1, 2, 3], $filas->pluck('Posicion')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(['0', '0', '1'], $filas->pluck('Ultimo')->map(fn ($v) => (string) $v)->all());

        $segundo = $this->fila('ReqProgramaTejido', $id2);
        $this->assertSame($this->fila('ReqProgramaTejido', $id1)['FechaFinal'], $segundo['FechaInicio']);
        $this->assertSame('2026-09-03 06:30:00', $segundo['FechaFinal']);
        $this->assertSame('0', (string) $segundo['CambioHilo'], 'mismo hilo que el anterior → sin cambio de hilo');
    }

    public function test_cambio_de_hilo_y_herencia_de_calibre_pie_cuenta_rizo_y_calendario(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 3)->update([
            'FibraRizo' => ' H1 ', 'CuentaRizo' => '3456', 'CalibrePie' => null, 'CalibrePie2' => 12.5,
        ]);

        $id = (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['hilo' => '  H1  ']))->assertOk()->json('id');
        $nuevo = $this->fila('ReqProgramaTejido', $id);
        $this->assertSame('0', (string) $nuevo['CambioHilo'], 'compara con trim en ambos lados');
        $this->assertSame('H1', $nuevo['FibraRizo']);
        $this->assertSame('3456', (string) $nuevo['CuentaRizo']);
        $this->assertEquals(12.5, $nuevo['CalibrePie'], 'CalibrePie cae a CalibrePie2');
        $this->assertEquals(12.5, $nuevo['CalibrePie2']);

        $id2 = (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['hilo' => 'H2', 'calibre' => null]))->assertOk()->json('id');
        $segundo = $this->fila('ReqProgramaTejido', $id2);
        $this->assertSame('1', (string) $segundo['CambioHilo']);
        $this->assertNull($segundo['CalibreRizo'], 'calibre vacío queda null');
    }

    public function test_telar_sin_registros_arranca_en_now_posicion_1_y_std_de_catalogo_o_por_defecto(): void
    {
        $r = $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['telar' => 'SMIT|305']))->assertOk();

        $nuevo = $this->fila('ReqProgramaTejido', (int) $r->json('id'));
        $this->assertSame('SMIT', $nuevo['SalonTejidoId']);
        $this->assertSame('SMI 305', $nuevo['Maquina']);
        $this->assertSame(1, (int) $nuevo['Posicion']);
        $this->assertSame('1', (string) $nuevo['Ultimo']);
        $this->assertSame('0', (string) $nuevo['CambioHilo'], 'sin anterior no hay cambio de hilo');
        $this->assertSame('2026-09-10 08:00:00', $nuevo['FechaInicio']);
        $this->assertSame('2026-09-10 20:00:00', $nuevo['FechaFinal']);
        $this->assertEquals(0.78, $nuevo['EficienciaSTD']);
        $this->assertEquals(280, $nuevo['VelocidadSTD']);

        // Con catálogos STD capturados con otro alias del salón (ITEMA/SMITH = SMIT) sí los usa;
        // la velocidad cae a la fila con Densidad NULL.
        DB::table('dbo.ReqEficienciaStd')->insert(['SalonTejidoId' => 'ITEMA', 'NoTelarId' => '305', 'FibraId' => 'H1', 'Eficiencia' => 0.91, 'Densidad' => 'Normal']);
        DB::table('dbo.ReqVelocidadStd')->insert(['SalonTejidoId' => 'SMITH', 'NoTelarId' => '305', 'FibraId' => 'H1', 'Velocidad' => 410, 'Densidad' => null]);
        $segundo = $this->fila('ReqProgramaTejido', (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['telar' => '305']))->assertOk()->json('id'));
        $this->assertEquals(0.91, $segundo['EficienciaSTD']);
        $this->assertEquals(410, $segundo['VelocidadSTD']);
        $this->assertSame(2, (int) $segundo['Posicion']);
        $this->assertSame('0', (string) $this->fila('ReqProgramaTejido', (int) $nuevo['Id'])['Ultimo'], 'el primero cede Ultimo al segundo');
    }

    public function test_formulas_de_eficiencia_quedan_persistidas_en_la_fila_nueva(): void
    {
        $nuevo = $this->fila('ReqProgramaTejido', (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload())->assertOk()->json('id'));

        // 12 h de repaso con TotalPedido=1: medio día de eficiencia y 1/12 pza por hora.
        $this->assertEquals(0.5, $nuevo['DiasEficiencia']);
        $this->assertEqualsWithDelta(0.08, (float) $nuevo['StdHrsEfect'], 0.001);
        $this->assertNotNull($nuevo['EntregaProduc']);
        $this->assertNotNull($nuevo['EntregaPT']);
        $this->assertNotNull($nuevo['EntregaCte']);
    }

    public function test_inicio_se_ajusta_al_calendario_del_anterior(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['CalendarioId' => 'Calendario Tej1']);
        DB::table('dbo.ReqCalendarioLine')->insert([
            ['CalendarioId' => 'Calendario Tej1', 'FechaInicio' => '2026-09-02 00:00:00', 'FechaFin' => '2026-09-02 06:30:00'],
            ['CalendarioId' => 'Calendario Tej1', 'FechaInicio' => '2026-09-03 06:30:00', 'FechaFin' => '2026-09-03 14:30:00'],
        ]);

        $nuevo = $this->fila('ReqProgramaTejido', (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload())->assertOk()->json('id'));

        $this->assertSame('Calendario Tej1', $nuevo['CalendarioId']);
        $this->assertSame('2026-09-03 06:30:00', $nuevo['FechaInicio'], 'salta al siguiente turno del calendario');
        // CARACTERIZA: FechaFinal = inicio + 12 h de reloj, sin respetar el calendario
        // (el turno acaba 14:30 y no hay más líneas): el repaso "trabaja" fuera de turno.
        $this->assertSame('2026-09-03 18:30:00', $nuevo['FechaFinal']);
    }

    public function test_salon_del_input_se_ignora_y_se_deriva_del_numero_de_telar(): void
    {
        // CARACTERIZA: el front manda 'SALON|TELAR' pero el salón se descarta. 'SMIT|201'
        // escribe en JACQUARD 201 y no ve la cadena SMIT 201 (Id 1,2): Id 2 sigue con Ultimo '1'.
        $antesSmit = $this->telar('ReqProgramaTejido', '201');

        $nuevo = $this->fila('ReqProgramaTejido', (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['telar' => 'SMIT|201']))->assertOk()->json('id'));

        $this->assertSame('JACQUARD', $nuevo['SalonTejidoId']);
        $this->assertSame(1, (int) $nuevo['Posicion']);
        $this->assertSame('1', (string) $nuevo['Ultimo']);
        $this->assertSame($antesSmit, array_values(array_filter($this->telar('ReqProgramaTejido', '201'), fn ($f) => $f['Id'] !== $nuevo['Id'])));

        // Telar fuera de rangos conocidos: default histórico JACQUARD.
        $fuera = $this->fila('ReqProgramaTejido', (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload(['telar' => '999']))->assertOk()->json('id'));
        $this->assertSame('JACQUARD', $fuera['SalonTejidoId']);
    }

    public function test_con_hueco_de_posiciones_el_repaso_ocupa_el_hueco_aunque_quede_marcado_ultimo(): void
    {
        DB::table('ReqProgramaTejido')->insert([
            'Id' => 7, 'SalonTejidoId' => 'JACQUARD', 'NoTelarId' => '202', 'Posicion' => 3, 'EnProceso' => 0,
            'TotalPedido' => 100, 'SaldoPedido' => 100,
            'FechaInicio' => '2026-09-04 06:30:00', 'FechaFinal' => '2026-09-05 06:30:00', 'Ultimo' => '1',
        ]);
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['Ultimo' => '0']);

        $nuevo = $this->fila('ReqProgramaTejido', (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload())->assertOk()->json('id'));

        // CARACTERIZA: PosicionesTelar::siguienteDisponible devuelve el primer hueco (2), no
        // "última + 1" (4). El repaso queda ANTES del Id 7 en el orden, pero con fechas
        // posteriores a las suyas y con Ultimo '1', mientras el Id 7 pierde su Ultimo.
        $this->assertSame(2, (int) $nuevo['Posicion']);
        $this->assertSame('1', (string) $nuevo['Ultimo']);
        $this->assertSame('2026-09-05 06:30:00', $nuevo['FechaInicio']);
        $this->assertSame('0', (string) $this->fila('ReqProgramaTejido', 7)['Ultimo']);
        $this->assertSame(3, (int) $this->fila('ReqProgramaTejido', 7)['Posicion']);
    }

    public function test_el_repaso_genera_lineas_diarias(): void
    {
        DB::table('ReqProgramaTejido')->where('Id', 3)->update(['CalendarioId' => 'Calendario Tej1']);
        DB::table('dbo.ReqCalendarioLine')->insert([
            ['CalendarioId' => 'Calendario Tej1', 'FechaInicio' => '2026-09-02 00:00:00', 'FechaFin' => '2026-09-04 00:00:00'],
        ]);

        $id = (int) $this->como()->postJson(self::URL_PROGRAMA, $this->payload())->assertOk()->json('id');

        // createrepaso marca el registro como recién creado antes del observer: genera sus líneas.
        $this->assertSame(1, DB::table('ReqProgramaTejidoLine')->where('ProgramaId', $id)->count(), '06:30-18:30 del 2 de sept = una línea');
    }
}
