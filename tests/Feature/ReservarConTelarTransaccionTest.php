<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Inventario\InvTelasReservadas;
use App\Models\Tejido\TejInventarioTelares;
use App\Services\ProgramaUrdEng\ReservarProgramarActionService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Reservar una pieza escribe en dos tablas. El navegador lo hacia con dos POST
 * seguidos (actualizar-telar y luego reservar-inventario): si el segundo
 * fallaba, el telar quedaba con no_julio y no_orden puestos pero sin fila en
 * InvTelasReservadas, y la pantalla lo daba por reservado para siempre.
 *
 * Ahora es una sola transaccion: o las dos escrituras, o ninguna.
 */
class ReservarConTelarTransaccionTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        $schema = Schema::connection('sqlsrv');

        $schema->create('tej_inventario_telares', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_telar')->nullable();
            $table->string('tipo')->nullable();
            $table->string('status')->nullable();
            $table->string('hilo')->nullable();
            $table->float('metros')->nullable();
            $table->string('no_julio')->nullable();
            $table->string('no_julio2')->nullable();
            $table->string('no_julio3')->nullable();
            $table->string('no_julio4')->nullable();
            $table->string('no_orden')->nullable();
            $table->string('no_orden2')->nullable();
            $table->string('no_orden3')->nullable();
            $table->string('no_orden4')->nullable();
            $table->string('localidad')->nullable();
            $table->string('cuenta')->nullable();
            $table->float('calibre')->nullable();
            $table->boolean('Reservado')->nullable();
            $table->boolean('Programado')->nullable();
            $table->string('ConfigId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('InventColorId')->nullable();
            $table->string('LoteProveedor')->nullable();
            $table->string('NoProveedor')->nullable();
            $table->string('tipo_atado')->nullable();
            $table->string('salon')->nullable();
            $table->date('fecha')->nullable();
            $table->string('turno')->nullable();
            $table->string('horaParo')->nullable();
            $table->timestamps();
        });

        $schema->create('InvTelasReservadas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('ConfigId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('InventColorId')->nullable();
            $table->string('InventLocationId')->nullable();
            $table->string('InventBatchId')->nullable();
            $table->string('WMSLocationId')->nullable();
            $table->string('InventSerialId')->nullable();
            $table->string('Tipo')->nullable();
            $table->float('Metros')->nullable();
            $table->float('InventQty')->nullable();
            $table->date('ProdDate')->nullable();
            $table->date('Fecha')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('Status')->nullable();
            $table->integer('TejInventarioTelaresId')->nullable();
            $table->string('NumeroEmpleado')->nullable();
            $table->string('NombreEmpl')->nullable();
            $table->timestamps();
        });

        // La reserva consulta avisos pendientes del tejedor antes de escribir.
        $schema->create('TejNotificaTejedor', function (Blueprint $table) {
            $table->increments('id');
            $table->string('telar')->nullable();
            $table->string('tipo')->nullable();
            $table->string('no_julio')->nullable();
            $table->string('no_orden')->nullable();
            $table->string('hora')->nullable();
            $table->date('Fecha')->nullable();
            $table->boolean('Reserva')->nullable();
        });

        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            'id' => 1, 'no_telar' => '401', 'tipo' => 'Rizo', 'status' => 'Activo', 'Reservado' => false,
        ]);

        // Barra 1 de Karl Mayer: se alimenta de cuatro julios, no de uno.
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            'id' => 2, 'no_telar' => '401', 'tipo' => '1', 'status' => 'Activo', 'Reservado' => false,
        ]);
    }

    /**
     * Una barra de Karl Mayer acepta cuatro julios, cada uno en su columna, y al
     * quinto se planta. Un rizo sigue con uno solo.
     */
    public function test_una_barra_km_reserva_cuatro_julios_en_columnas_distintas(): void
    {
        $pares = [
            ['01269-K6', '01269'],
            ['01310-K66', '01310'],
            ['01269-K29', '01269'],
            ['01400-K48', '01400'],
        ];

        foreach ($pares as [$julio, $orden]) {
            $this->acciones()->reservarConTelar(
                ['InventSerialId' => $julio, 'InventBatchId' => $orden, 'Tipo' => '1', 'TejInventarioTelaresId' => 2] + $this->reserva(),
                ['no_julio' => $julio, 'no_orden' => $orden]
            );
        }

        $barra = TejInventarioTelares::find(2);

        $this->assertSame(array_column($pares, 0), [
            $barra->no_julio, $barra->no_julio2, $barra->no_julio3, $barra->no_julio4,
        ]);
        $this->assertSame(array_column($pares, 1), [
            $barra->no_orden, $barra->no_orden2, $barra->no_orden3, $barra->no_orden4,
        ]);
        $this->assertSame(4, InvTelasReservadas::where('TejInventarioTelaresId', 2)->count());

        // El quinto ya no cabe.
        try {
            $this->acciones()->reservarConTelar(
                ['InventSerialId' => '01269-K99', 'InventBatchId' => '01269', 'Tipo' => '1', 'TejInventarioTelaresId' => 2] + $this->reserva(),
                ['no_julio' => '01269-K99', 'no_orden' => '01269']
            );
            $this->fail('Se esperaba DomainException: la barra ya tiene cuatro julios.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cuatro julios', $e->getMessage());
        }

        // Liberar la barra suelta los cuatro de golpe.
        $this->acciones()->liberar(2, '401', '1');

        $barra = TejInventarioTelares::find(2);
        $this->assertNull($barra->no_julio);
        $this->assertNull($barra->no_julio4);
        $this->assertNull($barra->no_orden);
        $this->assertNull($barra->no_orden4);
        $this->assertSame(0, InvTelasReservadas::where('TejInventarioTelaresId', 2)->count());
    }

    /**
     * Liberar suelta la reserva, no el material del requerimiento: la fibra (hilo)
     * tiene que seguir ahi. Reservar nunca la escribe, asi que borrarla la perdia.
     */
    public function test_liberar_conserva_la_fibra_del_telar(): void
    {
        DB::connection('sqlsrv')->table('tej_inventario_telares')
            ->where('id', 1)->update(['hilo' => 'ALGODON 20/1', 'cuenta' => '3000', 'calibre' => 20.5]);

        $this->acciones()->reservarConTelar($this->reserva(), [
            'metros' => 1200.0, 'no_julio' => '00061-744', 'no_orden' => '00061',
        ]);

        $this->acciones()->liberar(1, '401', 'Rizo');

        $telar = TejInventarioTelares::find(1);
        $this->assertSame('ALGODON 20/1', $telar->hilo, 'La fibra no se borra al liberar.');
        $this->assertSame('3000', $telar->cuenta);
        $this->assertNull($telar->no_julio, 'La reserva si se suelta.');
        $this->assertNull($telar->metros, 'Los metros si se limpian: vienen de la pieza.');
    }

    /**
     * Un julio puede entrar sin lote (no_orden). En una barra eso dejaba
     * Reservado=true y no_orden null, y liberar se negaba para siempre.
     */
    public function test_liberar_una_barra_sin_no_orden_no_se_traba(): void
    {
        $this->acciones()->reservarConTelar(
            ['InventSerialId' => 'K6', 'InventBatchId' => '', 'Tipo' => '1', 'TejInventarioTelaresId' => 2] + $this->reserva(),
            ['no_julio' => 'K6']
        );

        $barra = TejInventarioTelares::find(2);
        $this->assertSame('K6', $barra->no_julio);
        $this->assertNull($barra->no_orden, 'Sin lote, la columna de orden queda vacia.');

        $this->acciones()->liberar(2, '401', '1');

        $barra = TejInventarioTelares::find(2);
        $this->assertNull($barra->no_julio);
        $this->assertFalse((bool) $barra->Reservado);
        $this->assertSame(0, InvTelasReservadas::where('TejInventarioTelaresId', 2)->count());
    }

    /** Un rizo no gana columnas: el segundo julio no tiene donde ir. */
    public function test_un_rizo_sigue_con_un_solo_julio(): void
    {
        $this->acciones()->reservarConTelar($this->reserva(), ['no_julio' => '00061-744', 'no_orden' => '00061']);

        $this->acciones()->reservarConTelar(
            ['InventSerialId' => '00061-999'] + $this->reserva(),
            ['no_julio' => '00061-999', 'no_orden' => '00099']
        );

        $telar = TejInventarioTelares::find(1);
        $this->assertSame('00061-999', $telar->no_julio, 'Rizo conserva el comportamiento de una sola columna.');
        $this->assertSame('00099', $telar->no_orden, 'Rizo sigue guardando la orden en la única columna.');
        $this->assertNull($telar->no_julio2);
        $this->assertNull($telar->no_orden2);
    }

    public function test_la_reserva_marca_el_telar_y_crea_la_fila(): void
    {
        $resultado = $this->acciones()->reservarConTelar($this->reserva(), [
            'metros' => 1200.0,
            'no_julio' => '00061-744',
            'no_orden' => '00061',
        ]);

        $this->assertTrue($resultado['created']);
        $this->assertSame(1, $resultado['telares_actualizados']);

        $telar = TejInventarioTelares::find(1);
        $this->assertTrue((bool) $telar->Reservado);
        $this->assertSame('00061-744', $telar->no_julio);
        $this->assertSame(1, InvTelasReservadas::count());
    }

    /**
     * El telar que se quiere marcar no existe: no debe quedar la reserva suelta.
     */
    public function test_al_cerrar_el_aviso_guarda_el_julio_de_esta_reserva(): void
    {
        DB::connection('sqlsrv')->table('tej_inventario_telares')->where('id', 2)->update([
            'no_julio' => '01269-K6',
            'no_orden' => '01269',
            'Reservado' => true,
        ]);
        DB::connection('sqlsrv')->table('TejNotificaTejedor')->insert([
            'telar' => '401',
            'tipo' => '1',
            'hora' => '08:15:00',
            'Reserva' => 0,
            'Fecha' => now()->toDateString(),
        ]);

        $this->acciones()->reservarConTelar(
            ['InventSerialId' => '01310-K66', 'InventBatchId' => '01310', 'Tipo' => '1', 'TejInventarioTelaresId' => 2] + $this->reserva(),
            ['no_julio' => '01310-K66', 'no_orden' => '01310']
        );

        $aviso = DB::connection('sqlsrv')->table('TejNotificaTejedor')->first();
        $this->assertSame('01310-K66', $aviso->no_julio);
        $this->assertSame('01310', $aviso->no_orden);

        $barra = TejInventarioTelares::find(2);
        $this->assertSame('01269-K6', $barra->no_julio);
        $this->assertSame('01310-K66', $barra->no_julio2);
        $this->assertSame('08:15:00', $barra->horaParo);
    }

    public function test_si_el_telar_no_existe_no_queda_reserva_huerfana(): void
    {
        $reserva = $this->reserva();
        $reserva['TejInventarioTelaresId'] = 999;

        try {
            $this->acciones()->reservarConTelar($reserva, ['metros' => 1200.0, 'no_julio' => '00061-744']);
            $this->fail('Se esperaba DomainException por telar inexistente.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Telar no encontrado', $e->getMessage());
        }

        $this->assertSame(0, InvTelasReservadas::count(), 'La reserva no debe existir si el telar no se pudo marcar.');
    }

    /**
     * El telar se marca antes de crear la reserva: si la reserva revienta, el
     * telar tiene que volver atras.
     */
    public function test_si_falla_la_reserva_el_telar_vuelve_atras(): void
    {
        $reserva = $this->reserva();
        // El telar existe para el update inicial, pero no para ejecutarReserva,
        // que lo vuelve a buscar por id despues de crear la fila.
        $reserva['TejInventarioTelaresId'] = 1;

        DB::connection('sqlsrv')->table('tej_inventario_telares')->where('id', 1)->update(['status' => 'Activo']);

        // Forzamos el fallo quitando la tabla de reservas.
        Schema::connection('sqlsrv')->drop('InvTelasReservadas');

        try {
            $this->acciones()->reservarConTelar($reserva, ['no_julio' => '00061-744', 'no_orden' => '00061']);
            $this->fail('Se esperaba que fallara la escritura de la reserva.');
        } catch (\Throwable $e) {
            // cualquier fallo de BD sirve: lo que importa es el rollback
        }

        $telar = TejInventarioTelares::find(1);
        $this->assertNull($telar->no_julio, 'El telar no puede quedar con julio si la reserva no se creo.');
        $this->assertFalse((bool) $telar->Reservado);
    }

    private function acciones(): ReservarProgramarActionService
    {
        return app(ReservarProgramarActionService::class);
    }

    /** @return array<string, mixed> */
    private function reserva(): array
    {
        return [
            'NoTelarId' => '401',
            'ItemId' => 'JU-ENG-RI-01',
            'InventSerialId' => '00061-744',
            'InventBatchId' => '00061',
            'Tipo' => 'Rizo',
            'Status' => 'Reservado',
            'TejInventarioTelaresId' => 1,
            'SalonTejidoId' => 'Karl Mayer',
        ];
    }
}
