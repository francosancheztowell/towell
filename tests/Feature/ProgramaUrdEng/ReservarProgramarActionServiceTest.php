<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejido\TejInventarioTelares;
use App\Services\ProgramaUrdEng\ReservarProgramarActionService;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\ProgramaUrdEng\Concerns\InventarioUrdEngSqlite;
use Tests\TestCase;

/**
 * Caracterización de ReservarProgramarActionService (actualizar / liberar) previa a PERF-08.
 * La reserva con telar ya la cubre tests/Feature/ReservarConTelarTransaccionTest.php.
 */
class ReservarProgramarActionServiceTest extends TestCase
{
    use InventarioUrdEngSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararInventario();
    }

    protected function tearDown(): void
    {
        $this->tearDownInventario();
        parent::tearDown();
    }

    public function test_campos_de_inventario_y_de_programas(): void
    {
        $this->assertSame([
            'metros' => 12.5, 'no_julio' => 'J1', 'no_orden' => 'O1', 'LoteProveedor' => 'LP',
            'cuenta' => '', 'calibre' => null, 'NoProveedor' => 'NP',
        ], ReservarProgramarActionService::camposDeInventario([
            'metros' => '12.5', 'no_julio' => 'J1', 'no_orden' => 'O1', 'localidad' => '  ',
            'cuenta' => null, 'calibre' => '', 'lote_proveedor' => 'LP', 'no_proveedor' => 'NP', 'hilo' => null,
        ]));

        $this->assertSame(
            ['hilo' => 'H', 'cuenta' => '3156', 'calibre' => 20.0, 'tipo' => 'Pie'],
            ReservarProgramarActionService::camposDeProgramas(['hilo' => 'H', 'cuenta' => '3156', 'calibre' => '20', 'tipo' => 'pie'], 'Pie')
        );
        $this->assertSame([], ReservarProgramarActionService::camposDeProgramas(['cuenta' => ''], null));

        $this->assertTrue(ReservarProgramarActionService::esBarraKm(' 3 '));
        $this->assertFalse(ReservarProgramarActionService::esBarraKm('5'));
        $this->assertFalse(ReservarProgramarActionService::esBarraKm(null));
    }

    public function test_mensaje_de_actualizacion(): void
    {
        $s = $this->acciones();

        $this->assertSame(
            'Telar 401 actualizado (no se encontraron registros para actualizar)',
            $s->mensajeDeActualizacion('401', ['tej_inventario_telares' => 0])
        );
        $this->assertSame(
            'Telar 401 actualizado: 2 registro(s) en TejInventarioTelares, 1 registro(s) en EngProgramaEngomado',
            $s->mensajeDeActualizacion('401', ['tej_inventario_telares' => 2, 'urd_programa_urdido' => 0, 'eng_programa_engomado' => 1])
        );
    }

    public function test_actualizar_por_id_escribe_solo_ese_telar(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'cuenta' => '3000', 'calibre' => 20.5]);
        $otro = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo']);

        $r = $this->acciones()->actualizarTelar([
            'no_telar' => '401', 'id' => $id, 'metros' => '900', 'no_orden' => '00061', 'cuenta' => '', 'calibre' => null,
            'solo_inventario' => true, 'hilo' => 'ALG',
        ]);

        $this->assertSame(['tej_inventario_telares' => 1, 'urd_programa_urdido' => 0, 'eng_programa_engomado' => 0], $r);
        $t = TejInventarioTelares::find($id);
        $this->assertEquals(900, $t->metros);
        $this->assertSame('00061', $t->no_orden);
        $this->assertSame('00061', $t->LoteProveedor);
        $this->assertSame('', $t->cuenta);
        $this->assertNull($t->calibre);
        $this->assertSame('ALG', $t->hilo);
        $this->assertNull(TejInventarioTelares::find($otro)->metros);
    }

    public function test_actualizar_sin_id_acota_por_tipo_fecha_y_turno(): void
    {
        $a = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'fecha' => '2026-09-01', 'turno' => '1']);
        $b = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'fecha' => '2026-09-01', 'turno' => '2']);
        $c = $this->telar(['no_telar' => '401', 'tipo' => 'Pie', 'fecha' => '2026-09-01', 'turno' => '1']);

        $r = $this->acciones()->actualizarTelar([
            'no_telar' => '401', 'tipo' => 'rizo', 'fecha' => '2026-09-01T00:00:00', 'turno' => 1, 'localidad' => 'L1',
        ]);

        $this->assertSame(1, $r['tej_inventario_telares']);
        $this->assertSame(
            [$a => 'L1', $b => null, $c => null],
            TejInventarioTelares::orderBy('id')->pluck('localidad', 'id')->all()
        );
    }

    public function test_actualizar_varios_telares_sin_id_los_escribe_todos(): void
    {
        $ids = [
            $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'fecha' => '2026-09-01']),
            $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'fecha' => '2026-09-02']),
            $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'fecha' => '2026-09-03']),
        ];

        $r = $this->acciones()->actualizarTelar(['no_telar' => '401', 'tipo' => 'Rizo', 'no_julio' => 'J9', 'metros' => 10]);

        $this->assertSame(3, $r['tej_inventario_telares']);
        $this->assertSame(
            array_fill_keys($ids, 'J9'),
            TejInventarioTelares::orderBy('id')->pluck('no_julio', 'id')->all()
        );
    }

    public function test_actualizar_barras_sin_id_acomoda_el_julio_en_la_columna_libre_de_cada_una(): void
    {
        $llena = $this->telar(['no_telar' => '401', 'tipo' => '1', 'fecha' => '2026-09-01', 'no_julio' => 'K1', 'no_orden' => 'O1']);
        $vacia = $this->telar(['no_telar' => '401', 'tipo' => '1', 'fecha' => '2026-09-02']);
        $conEse = $this->telar(['no_telar' => '401', 'tipo' => '1', 'fecha' => '2026-09-03', 'no_julio' => 'K5', 'no_orden' => 'O5']);

        $r = $this->acciones()->actualizarTelar(['no_telar' => '401', 'tipo' => 'B1', 'no_julio' => 'K5', 'no_orden' => 'O5', 'metros' => 7]);

        $this->assertSame(3, $r['tej_inventario_telares']);
        $t = TejInventarioTelares::find($llena);
        $this->assertSame(['K1', 'K5', 'O1', 'O5'], [$t->no_julio, $t->no_julio2, $t->no_orden, $t->no_orden2]);
        $t = TejInventarioTelares::find($vacia);
        $this->assertSame(['K5', null, 'O5'], [$t->no_julio, $t->no_julio2, $t->no_orden]);
        $t = TejInventarioTelares::find($conEse);
        $this->assertSame(['K5', null], [$t->no_julio, $t->no_julio2], 'El mismo julio no gasta otra columna.');
        $this->assertEquals([7, 7, 7], TejInventarioTelares::orderBy('id')->pluck('metros')->map(fn ($m) => (float) $m)->all());
    }

    public function test_actualizar_sin_campos_o_sin_telar_falla(): void
    {
        try {
            $this->acciones()->actualizarTelar(['no_telar' => '401', 'localidad' => '']);
            $this->fail('Se esperaba DomainException');
        } catch (DomainException $e) {
            $this->assertSame('No hay campos para actualizar', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Telar no encontrado o no está activo');
        $this->acciones()->actualizarTelar(['no_telar' => '999', 'metros' => 1]);
    }

    public function test_liberar_rizo_borra_su_reserva_resetea_el_aviso_y_limpia_el_telar(): void
    {
        $id = $this->telar([
            'no_telar' => '401', 'tipo' => 'Rizo', 'no_julio' => '00061-744', 'no_orden' => '00061', 'Reservado' => true,
            'Programado' => true, 'metros' => 1200, 'hilo' => 'ALG', 'localidad' => 'L', 'ConfigId' => 'H1',
        ]);
        $this->reservaActiva(['NoTelarId' => '401', 'InventSerialId' => '00061-744', 'InventBatchId' => '00061', 'Tipo' => 'Rizo']);
        $pie = $this->reservaActiva(['NoTelarId' => '401', 'InventSerialId' => '00044-454', 'InventBatchId' => '00044', 'Tipo' => 'Pie']);
        $aviso = $this->aviso(['telar' => '401', 'tipo' => 'rizo', 'no_julio' => '00061-744', 'no_orden' => '00061', 'Reserva' => 1, 'Fecha' => '2026-09-01']);

        $r = $this->acciones()->liberar($id, '401', 'Rizo');

        $this->assertSame(1, $r['reservas_eliminadas']);
        $this->assertSame([$pie], InvTelasReservadas::pluck('Id')->all(), 'La de Pie del mismo telar se queda.');
        $this->assertInstanceOf(TejInventarioTelares::class, $r['telar']);
        $this->assertNull($r['telar']->no_julio);
        $this->assertFalse($r['telar']->Reservado);
        $this->assertFalse($r['telar']->Programado);
        $this->assertNull($r['telar']->metros);
        $this->assertNull($r['telar']->ConfigId);
        $this->assertSame('ALG', $r['telar']->hilo);

        $fila = DB::connection('sqlsrv')->table('TejNotificaTejedor')->where('id', $aviso)->first();
        $this->assertSame([null, null, 0], [$fila->no_julio, $fila->no_orden, (int) $fila->Reserva]);
    }

    public function test_liberar_sin_id_prefiere_el_telar_reservado(): void
    {
        $libre = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo']);
        $reservado = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'no_julio' => 'J', 'no_orden' => 'O', 'Reservado' => true]);

        $r = $this->acciones()->liberar(null, '401', 'Rizo');

        $this->assertSame($reservado, $r['telar']->id);
        $this->assertNotSame($libre, $r['telar']->id);
        $this->assertSame(0, $r['reservas_eliminadas']);
    }

    public function test_liberar_barra_suelta_sus_cuatro_julios(): void
    {
        $barra = $this->telar(['no_telar' => '401', 'tipo' => '2', 'no_julio' => 'K1', 'no_julio2' => 'K2', 'Reservado' => true]);
        foreach (['K1', 'K2', 'K3', 'K4'] as $k) {
            $this->reservaActiva(['NoTelarId' => '401', 'InventSerialId' => $k, 'TejInventarioTelaresId' => $barra]);
        }
        $ajena = $this->reservaActiva(['NoTelarId' => '401', 'InventSerialId' => 'K9', 'TejInventarioTelaresId' => $barra + 100]);

        $r = $this->acciones()->liberar($barra, '401', '2');

        $this->assertSame(4, $r['reservas_eliminadas']);
        $this->assertSame([$ajena], InvTelasReservadas::pluck('Id')->all());
        $this->assertNull($r['telar']->no_julio2);
    }

    public function test_liberar_telar_no_reservado_o_inexistente_falla(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'no_julio' => 'J']);

        try {
            $this->acciones()->liberar($id, '401', 'Rizo');
            $this->fail('Se esperaba DomainException');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no esta reservado', $e->getMessage());
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Telar no encontrado');
        $this->acciones()->liberar(999, '401', 'Rizo');
    }

    private function acciones(): ReservarProgramarActionService
    {
        return app(ReservarProgramarActionService::class);
    }
}
