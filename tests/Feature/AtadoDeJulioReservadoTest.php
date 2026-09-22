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

    public function test_sin_reserva_no_hay_detalle(): void
    {
        $response = $this->getJson('/tejedores/atadodejulio?no_telar=300&tipo=pie');

        $response->assertOk();
        $response->assertJsonPath('detalles', null);
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
