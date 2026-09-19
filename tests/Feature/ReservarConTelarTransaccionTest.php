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
            $table->string('no_orden')->nullable();
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
        ];
    }
}
