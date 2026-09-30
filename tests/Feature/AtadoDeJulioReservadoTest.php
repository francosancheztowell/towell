<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Atado de julio toma la fila reservada del telar. Una fila vieja puede seguir
 * en status Activo despues de liberar; esa ya no se notifica.
 */
class AtadoDeJulioReservadoTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $schema = Schema::connection('sqlsrv');

        $schema->create('TelTelaresOperador', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('numero_empleado')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
        });

        $schema->create('tej_inventario_telares', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_telar')->nullable();
            $table->string('tipo')->nullable();
            $table->string('status')->nullable();
            $table->string('cuenta')->nullable();
            $table->float('calibre')->nullable();
            $table->string('tipo_atado')->nullable();
            $table->string('no_julio')->nullable();
            $table->string('no_orden')->nullable();
            $table->float('metros')->nullable();
            $table->boolean('Reservado')->nullable();
            $table->date('fecha')->nullable();
            $table->string('turno')->nullable();
            $table->string('horaParo')->nullable();
            $table->timestamps();
        });

        $schema->create('TejNotificaTejedor', function (Blueprint $table) {
            $table->increments('id');
            $table->string('telar')->nullable();
            $table->string('tipo')->nullable();
            $table->string('hora')->nullable();
            $table->string('NomEmpleado')->nullable();
            $table->string('NoEmpleado')->nullable();
            $table->boolean('Reserva')->nullable();
            $table->string('no_julio')->nullable();
            $table->string('no_orden')->nullable();
            $table->date('Fecha')->nullable();
        });

        $usuario = $this->createUsuario(['numero_empleado' => '1001']);
        $this->actingAs($usuario, 'web');

        DB::connection('sqlsrv')->table('TelTelaresOperador')->insert([
            'numero_empleado' => '1001',
            'NoTelarId' => '300',
        ]);

        $fila = [
            'no_telar' => '300',
            'tipo' => 'Rizo',
            'status' => 'Activo',
            'cuenta' => null,
            'no_julio' => null,
            'no_orden' => null,
            'metros' => null,
            'Reservado' => 0,
            'fecha' => null,
            'turno' => null,
        ];
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            ['id' => 1, 'no_julio' => 'VIEJO', 'no_orden' => 'ORD-VIEJA', 'fecha' => '2026-09-22', 'turno' => '3'] + $fila,
            ['id' => 2, 'cuenta' => '2500', 'no_julio' => 'RESERVADO', 'no_orden' => 'ORD-OK', 'metros' => 800, 'Reservado' => 1, 'fecha' => '2026-09-01', 'turno' => '1'] + $fila,
        ]);
    }

    public function test_el_detalle_es_la_fila_reservada_aunque_haya_una_activa_mas_nueva(): void
    {
        $response = $this->getJson('/tejedores/atadodejulio?no_telar=300&tipo=rizo');

        $response->assertOk();
        $response->assertJsonPath('detalles.id', 2);
        $response->assertJsonPath('detalles.no_julio', 'RESERVADO');
        $response->assertJsonMissingPath('detalles.registroCompleto');
    }

    public function test_sin_reserva_muestra_el_telar_para_avisar(): void
    {
        $this->getJson('/tejedores/atadodejulio?no_telar=300&tipo=pie')
            ->assertOk()
            ->assertJsonPath('detalles.id', null)
            ->assertJsonPath('detalles.sinReserva', true)
            ->assertJsonPath('detalles.no_telar', '300');

        $this->getJson('/tejedores/atadodejulio?no_telar=300&tipo=otro')->assertJsonPath('detalles', null);
    }

    public function test_aviso_sin_reserva_queda_pendiente_en_tej_notifica_tejedor(): void
    {
        $this->postJson('/tejedores/atadodejulio/notificar', ['id' => null, 'no_telar' => '300', 'tipo' => 'pie', 'horaParo' => '06:40:00'])
            ->assertOk()
            ->assertJsonPath('horaParo', '06:40:00');

        $aviso = DB::connection('sqlsrv')->table('TejNotificaTejedor')->sole();
        $this->assertSame(['300', 'pie', '06:40:00', 0], [$aviso->telar, $aviso->tipo, $aviso->hora, (int) $aviso->Reserva]);
        $this->assertNull(DB::connection('sqlsrv')->table('tej_inventario_telares')->where('id', 1)->value('horaParo'), 'No toca filas sin reserva.');
    }

    public function test_barra_km_sin_reserva_tambien_queda_pendiente(): void
    {
        DB::connection('sqlsrv')->table('TelTelaresOperador')->insert(['numero_empleado' => '1001', 'NoTelarId' => '401']);

        $this->getJson('/tejedores/atadodejulio?no_telar=401&tipo=3')->assertJsonPath('detalles.sinReserva', true);
        $this->postJson('/tejedores/atadodejulio/notificar', ['no_telar' => '401', 'tipo' => '3', 'horaParo' => '10:15:00'])->assertOk();

        $aviso = DB::connection('sqlsrv')->table('TejNotificaTejedor')->sole();
        $this->assertSame(['401', '3', 0], [$aviso->telar, $aviso->tipo, (int) $aviso->Reserva]);
    }

    public function test_aviso_sin_reserva_se_rechaza_si_ya_le_reservaron_o_no_es_su_telar(): void
    {
        // Rizo del 300 ya tiene reserva sin hora (fila 2): hay que notificar esa, no dejar un pendiente.
        $this->postJson('/tejedores/atadodejulio/notificar', ['no_telar' => '300', 'tipo' => 'rizo', 'horaParo' => '06:40:00'])->assertStatus(422);
        $this->postJson('/tejedores/atadodejulio/notificar', ['no_telar' => '999', 'tipo' => 'pie', 'horaParo' => '06:40:00'])->assertStatus(422);
        $this->postJson('/tejedores/atadodejulio/notificar', ['no_telar' => '300', 'tipo' => '7', 'horaParo' => '06:40:00'])->assertStatus(422);

        $this->assertSame(0, DB::connection('sqlsrv')->table('TejNotificaTejedor')->count());
    }

    public function test_un_telar_que_no_es_del_operador_no_devuelve_detalle(): void
    {
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            'id' => 3,
            'no_telar' => '999',
            'tipo' => 'Rizo',
            'status' => 'Activo',
            'no_julio' => 'AJENO',
            'no_orden' => 'ORD',
            'Reservado' => 1,
            'fecha' => '2026-09-22',
        ]);

        $this->getJson('/tejedores/atadodejulio?no_telar=999&tipo=rizo')
            ->assertOk()
            ->assertJsonPath('detalles', null);
    }

    public function test_notificar_guarda_la_hora_que_vio_el_operador(): void
    {
        $response = $this->postJson('/tejedores/atadodejulio/notificar', [
            'id' => 2,
            'horaParo' => '7:05:09',
            'no_telar' => '300',
            'tipo' => 'rizo',
        ]);

        $response->assertOk();
        $response->assertJsonPath('horaParo', '07:05:09');
        $this->assertSame(
            '07:05:09',
            DB::connection('sqlsrv')->table('tej_inventario_telares')->where('id', 2)->value('horaParo')
        );
        $this->assertSame(
            'RESERVADO',
            DB::connection('sqlsrv')->table('TejNotificaTejedor')->value('no_julio')
        );
    }

    public function test_una_barra_reservada_se_consulta_por_su_numero(): void
    {
        DB::connection('sqlsrv')->table('TelTelaresOperador')->insert([
            'numero_empleado' => '1001',
            'NoTelarId' => '401',
        ]);
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            'id' => 4,
            'no_telar' => '401',
            'tipo' => '1',
            'status' => 'Activo',
            'no_julio' => 'BARRA-1',
            'no_orden' => 'ORD-B1',
            'Reservado' => 1,
            'fecha' => '2026-09-20',
            'turno' => '1',
        ]);

        $this->getJson('/tejedores/atadodejulio?no_telar=401&tipo=1')
            ->assertOk()
            ->assertJsonPath('detalles.no_julio', 'BARRA-1');
    }

    public function test_varias_reservas_de_la_misma_barra_saltan_la_que_ya_tiene_hora_paro(): void
    {
        DB::connection('sqlsrv')->table('TelTelaresOperador')->insert([
            'numero_empleado' => '1001',
            'NoTelarId' => '401',
        ]);
        $barra = ['no_telar' => '401', 'tipo' => '1', 'status' => 'Activo', 'no_orden' => 'ORD-B1', 'Reservado' => 1, 'turno' => '1'];
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            ['id' => 10, 'no_julio' => 'J-A', 'fecha' => '2026-09-20', 'horaParo' => '07:00:00'] + $barra,
            ['id' => 11, 'no_julio' => 'J-B', 'fecha' => '2026-09-21', 'horaParo' => null] + $barra,
            ['id' => 12, 'no_julio' => 'J-C', 'fecha' => '2026-09-22', 'horaParo' => null] + $barra,
        ]);

        $this->getJson('/tejedores/atadodejulio?no_telar=401&tipo=1')->assertJsonPath('detalles.id', 11);

        $this->postJson('/tejedores/atadodejulio/notificar', ['id' => 11, 'horaParo' => '08:00:00'])->assertOk();
        $this->getJson('/tejedores/atadodejulio?no_telar=401&tipo=1')->assertJsonPath('detalles.id', 12);

        // La que ya tiene hora no se vuelve a pisar.
        $this->postJson('/tejedores/atadodejulio/notificar', ['id' => 10, 'horaParo' => '09:00:00'])->assertStatus(422);
        $this->assertSame('07:00:00', DB::connection('sqlsrv')->table('tej_inventario_telares')->where('id', 10)->value('horaParo'));
    }

    public function test_la_pantalla_ofrece_barras_en_el_telar_karl_mayer(): void
    {
        DB::connection('sqlsrv')->table('TelTelaresOperador')->insert([
            'numero_empleado' => '1001',
            'NoTelarId' => '401',
        ]);

        $html = $this->get('/tejedores/atadodejulio')->assertOk()->getContent();

        $this->assertStringContainsString('value="401" data-km="1"', $html);
        $this->assertStringContainsString('value="300"', $html);
        $this->assertStringContainsString('Barra 4', $html);
        $this->assertStringContainsString('id="tiposBarras"', $html);
        $this->assertDoesNotMatchRegularExpression('/value="300"[^>]*data-km="1"/', $html);
    }

    public function test_no_notifica_una_fila_que_ya_no_esta_reservada(): void
    {
        $this->postJson('/tejedores/atadodejulio/notificar', [
            'id' => 1,
            'horaParo' => '07:05:09',
        ])->assertStatus(422);

        $this->assertNull(
            DB::connection('sqlsrv')->table('tej_inventario_telares')->where('id', 1)->value('horaParo')
        );
        $this->assertSame(0, DB::connection('sqlsrv')->table('TejNotificaTejedor')->count());
    }
}
