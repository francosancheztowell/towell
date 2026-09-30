<?php

declare(strict_types=1);

namespace Tests\Feature\ProgramaUrdEng;

use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejido\TejInventarioTelares;
use App\Services\ProgramaUrdEng\InventarioReservasService;
use App\Services\ProgramaUrdEng\ReservarProgramarActionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\ProgramaUrdEng\Concerns\InventarioUrdEngSqlite;
use Tests\TestCase;

/**
 * PERF-08 (19-05 p2.4): consultas por operación de reservar / cancelar / actualizar / liberar.
 * "antes N" es el conteo con el código previo (medido con este mismo escenario); el assert es
 * el después. Cada test comprueba además el resultado en BD, que no cambia.
 */
class ReservarProgramarActionQueriesTest extends TestCase
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

    public function test_reservar_con_aviso_del_tejedor(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'no_julio' => '00061-744', 'no_orden' => '00061']);
        $aviso = $this->aviso(['telar' => '401', 'tipo' => 'Rizo', 'hora' => '07:30', 'Fecha' => '2026-09-01']);

        $n = $this->contarQueries(fn () => (new InventarioReservasService)->ejecutarReserva($this->datos($id)));

        // antes 8: aviso, telar objetivo, save horaParo, save aviso, find (principal), insert,
        // telar activo, save Reservado. El telar se leía 3 veces y se guardaba 2.
        $this->assertSame(5, $n);

        $telar = TejInventarioTelares::find($id);
        $this->assertSame('07:30', $telar->horaParo);
        $this->assertTrue($telar->Reservado);
        $this->assertSame('00061', $telar->LoteProveedor);
        $this->assertSame(1, InvTelasReservadas::count());
        $fila = DB::connection('sqlsrv')->table('TejNotificaTejedor')->where('id', $aviso)->first();
        $this->assertSame([1, '00061-744', '00061'], [(int) $fila->Reserva, $fila->no_julio, $fila->no_orden]);
    }

    public function test_reservar_sin_tipo_ni_aviso(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Pie']);
        $datos = $this->datos($id);
        unset($datos['Tipo']);

        $n = $this->contarQueries(fn () => (new InventarioReservasService)->ejecutarReserva($datos));

        // antes 6: tipo del telar, aviso, find (principal), insert, telar activo, save.
        $this->assertSame(4, $n);
        $this->assertTrue(TejInventarioTelares::find($id)->Reservado);
    }

    public function test_reservar_con_telar_desde_la_pantalla(): void
    {
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo']);
        $this->aviso(['telar' => '401', 'tipo' => 'Rizo', 'hora' => '07:30', 'Fecha' => '2026-09-01']);

        $n = $this->contarQueries(fn () => $this->acciones()->reservarConTelar(
            $this->datos($id),
            ['metros' => 1200.0, 'no_julio' => '00061-744', 'no_orden' => '00061']
        ));

        // antes 10: 2 del telar (first + update) + 8 de ejecutarReserva.
        $this->assertSame(6, $n);

        $telar = TejInventarioTelares::find($id);
        $this->assertSame(['00061-744', '00061', '07:30', true], [$telar->no_julio, $telar->no_orden, $telar->horaParo, $telar->Reservado]);
        $this->assertEquals(1200, $telar->metros);
        $this->assertSame('00061-744', DB::connection('sqlsrv')->table('TejNotificaTejedor')->value('no_julio'));
    }

    public function test_cancelar_por_id_y_liberar_telar(): void
    {
        $telar = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'Reservado' => true]);
        $id = $this->reservaActiva(['NoTelarId' => '401', 'InventSerialId' => 'J']);

        $n = $this->contarQueries(fn () => (new InventarioReservasService)->ejecutarCancelar(['Id' => $id]));

        // antes 4 y ya óptimo: NoTelarId, update Cancelado, ¿quedan activas?, update telar.
        // Sin consultas por fila: cancelar N reservas del mismo telar sigue siendo 4.
        $this->assertSame(4, $n);
        $this->assertSame('Cancelado', InvTelasReservadas::find($id)->Status);
        $this->assertFalse(TejInventarioTelares::find($telar)->Reservado);
    }

    public function test_disponible_no_consulta_por_fila(): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([['Folio' => '01249', 'RizoPie' => '1']]);
        $filas = [];
        for ($i = 1; $i <= 50; $i++) {
            $filas[] = (object) ['ItemId' => 'JULIO-URDIDO', 'InventBatchId' => (string) (1200 + $i), 'InventSerialId' => "K{$i}"];
            $this->reservaActiva(['ItemId' => 'JULIO-URDIDO', 'InventBatchId' => (string) (1200 + $i), 'InventSerialId' => "K{$i}", 'NoTelarId' => '401']);
        }
        $svc = new class($filas) extends InventarioReservasService
        {
            /** @param array<int, object> $filas */
            public function __construct(private array $filas) {}

            protected function queryDisponibleFromTiPro(array $filtros = [], int $limit = 2000): array
            {
                return $this->filas;
            }
        };

        $r = null;
        $n = $this->contarQueries(function () use ($svc, &$r): void {
            $r = $svc->getDisponibleData([]);
        });

        // antes 2 y ya óptimo: reservas activas + programa de urdido, sin importar las 50 filas.
        $this->assertSame(2, $n);
        $this->assertSame(50, $r['total']);
        $this->assertSame('1', $r['data'][48]->Tipo, 'Lote 1249 cruza con el folio 01249.');
        $this->assertSame('401', $r['data'][48]->NoTelarId);
    }

    /**
     * 1 100 lotes de Karl Mayer = 2 200 folios: en una sola consulta pasaba el límite de 2 100
     * parámetros de SQL Server. Ahora va en bloques y el "último Id gana" se conserva entre bloques.
     */
    public function test_disponible_con_muchos_lotes_parte_la_consulta_del_programa(): void
    {
        // '0010001' cae en el primer bloque y '010001' en el segundo: son el mismo folio
        // normalizado y cada uno trae su fila del programa. Gana el Id mayor, que está en el primero.
        $filas = [(object) ['ItemId' => 'JULIO-URDIDO', 'InventBatchId' => '0010001', 'InventSerialId' => 'K0']];
        for ($i = 1; $i <= 1100; $i++) {
            $filas[] = (object) ['ItemId' => 'JULIO-URDIDO', 'InventBatchId' => (string) (20000 + $i), 'InventSerialId' => "K{$i}"];
        }
        $filas[] = (object) ['ItemId' => 'JULIO-URDIDO', 'InventBatchId' => '010001', 'InventSerialId' => 'K9999'];
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            ['Folio' => '010001', 'RizoPie' => '1'],
            ['Folio' => '0010001', 'RizoPie' => '3'],
            ['Folio' => '21100', 'RizoPie' => '4'],
        ]);
        $svc = new class($filas) extends InventarioReservasService
        {
            /** @param array<int, object> $filas */
            public function __construct(private array $filas) {}

            protected function queryDisponibleFromTiPro(array $filtros = [], int $limit = 2000): array
            {
                return $this->filas;
            }
        };

        $r = null;
        $n = $this->contarQueries(function () use ($svc, &$r): void {
            $r = $svc->getDisponibleData([]);
        });

        // antes 2 (y SQL Server rechazaba la consulta con 2 200 parámetros); después 3: reservas + 2 bloques.
        $this->assertSame(3, $n);
        $this->assertSame('3', $r['data'][0]->Tipo, 'Gana el Id mayor aunque venga en otro bloque.');
        $this->assertSame('3', $r['data'][1101]->Tipo);
        $this->assertSame('4', $r['data'][1100]->Tipo);
        $this->assertNull($r['data'][500]->Tipo);
    }

    public function test_actualizar_por_id_con_campos_de_programa(): void
    {
        foreach (['EngProgramaEngomado', 'UrdProgramaUrdido'] as $tabla) {
            Schema::connection('sqlsrv')->dropIfExists($tabla);
            Schema::connection('sqlsrv')->create($tabla, function (Blueprint $t): void {
                $t->increments('Id');
                $t->string('Folio')->nullable();
            });
        }
        $id = $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'no_orden' => '00061']);

        $n = $this->contarQueries(fn () => $this->acciones()->actualizarTelar(
            ['no_telar' => '401', 'id' => $id, 'metros' => 10, 'cuenta' => '3156']
        ));

        // antes 5: telar + update, el mismo telar otra vez para leer su folio, urdido y engomado.
        $this->assertSame(4, $n);
        $this->assertSame('3156', TejInventarioTelares::find($id)->cuenta);
    }

    public function test_actualizar_varios_telares_sin_id(): void
    {
        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'] as $f) {
            $this->telar(['no_telar' => '401', 'tipo' => 'Rizo', 'fecha' => $f]);
        }

        $n = $this->contarQueries(fn () => $this->acciones()->actualizarTelar(
            ['no_telar' => '401', 'tipo' => 'Rizo', 'localidad' => 'L2', 'solo_inventario' => true]
        ));

        // antes 5: 1 lectura + 1 update por telar (N+1). Después: lectura + 1 UPDATE por cambio distinto.
        $this->assertSame(2, $n);
        $this->assertSame(['L2', 'L2', 'L2', 'L2'], TejInventarioTelares::pluck('localidad')->all());
    }

    public function test_liberar_barra_con_cuatro_julios(): void
    {
        $barra = $this->telar(['no_telar' => '401', 'tipo' => '2', 'no_julio' => 'K1', 'no_julio2' => 'K2', 'Reservado' => true]);
        foreach (['K1', 'K2', 'K3', 'K4'] as $k) {
            $this->reservaActiva(['NoTelarId' => '401', 'InventSerialId' => $k, 'TejInventarioTelaresId' => $barra]);
        }

        $r = null;
        $n = $this->contarQueries(function () use ($barra, &$r): void {
            $r = $this->acciones()->liberar($barra, '401', '2');
        });

        // antes 10: 2 para hallar el telar (la barra no tiene no_orden), leer reservas + 1 DELETE
        // por reserva, aviso, update, fresh. Después: telar, DELETE, aviso, update, fresh.
        $this->assertSame(5, $n);
        $this->assertSame(4, $r['reservas_eliminadas']);
        $this->assertSame(0, InvTelasReservadas::count());
        $this->assertFalse(TejInventarioTelares::find($barra)->Reservado);
    }

    private function acciones(): ReservarProgramarActionService
    {
        return app(ReservarProgramarActionService::class);
    }

    /** @return array<string, mixed> */
    private function datos(int $telarId): array
    {
        return [
            'NoTelarId' => '401', 'ItemId' => 'JU-ENG-RI-01', 'InventSerialId' => '00061-744', 'InventBatchId' => '00061',
            'Tipo' => 'Rizo', 'Status' => 'Reservado', 'TejInventarioTelaresId' => $telarId,
        ];
    }
}
