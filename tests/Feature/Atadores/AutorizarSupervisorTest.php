<?php

declare(strict_types=1);

namespace Tests\Feature\Atadores;

use App\Services\Monitoreo\AccesoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * 19-03: "Autoriza Supervisor" (POST atadores/save, action=supervisor).
 *  - AuthZ en modo auditar (20-03-MAPA-AUTHZ §Huecos): sin registrar,45 queda un authz_denegaria y pasa igual.
 *  - SEC-07: si falla, 500 con trace_id y sin el error de SQL (antes 200 + ok:false + getMessage()).
 */
class AutorizarSupervisorTest extends TestCase
{
    use EsquemaAtadores;

    /** @var list<string> */
    private array $motivos = [];

    private function preparar(array $acciones): void
    {
        $this->prepararAtadores($acciones);

        $acceso = Mockery::mock(AccesoService::class);
        $acceso->shouldReceive('registrar')->andReturnUsing(function (string $tipo, array $datos) {
            if ($tipo === 'authz_denegaria') {
                $this->motivos[] = (string) ($datos['Motivo'] ?? '');
            }
        });
        $this->app->instance(AccesoService::class, $acceso);

        $this->inventarioJacquard();
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'Calificado', 'NoJulio' => '00010-1', 'NoProduccion' => '00010',
            'Tipo' => 'Rizo', 'NoTelarId' => '300', 'Fecha' => '2026-09-24', 'Turno' => '1',
        ]);
    }

    private function autorizar()
    {
        return $this->postJson('/atadores/save', ['action' => 'supervisor', 'no_julio' => '00010-1', 'no_orden' => '00010']);
    }

    public function test_sin_registrar_se_audita_y_no_se_bloquea(): void
    {
        $this->preparar(['acceso', 'crear', 'modificar']);

        $this->autorizar()->assertOk()->assertJson(['ok' => true]);

        $this->assertSame('Autorizado', DB::connection('sqlsrv')->table('AtaMontadoTelas')->value('Estatus'));
        $this->assertCount(1, $this->motivos);
        $this->assertStringStartsWith('registrar · 45 · POST', $this->motivos[0]);
    }

    public function test_con_registrar_no_hay_denegacion(): void
    {
        $this->preparar(['acceso', 'crear', 'modificar', 'registrar']);

        $this->autorizar()->assertOk();

        $this->assertSame([], $this->motivos);
    }

    public function test_otras_acciones_no_auditan_registrar(): void
    {
        $this->preparar(['acceso', 'crear', 'modificar']);
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->update(['Estatus' => 'En Proceso']);

        $this->postJson('/atadores/save', ['action' => 'observaciones', 'observaciones' => 'x', 'no_julio' => '00010-1', 'no_orden' => '00010'])
            ->assertOk();

        $this->assertSame([], $this->motivos);
    }

    public function test_si_falla_no_manda_el_error_de_sql_y_no_autoriza(): void
    {
        $this->preparar(['acceso', 'crear', 'modificar', 'registrar']);
        Schema::connection('sqlsrv')->drop('TejHistorialInventarioTelares');

        $respuesta = $this->autorizar()->assertStatus(500)->assertJson(['success' => false]);

        $this->assertNotEmpty($respuesta->json('trace_id'));
        $this->assertStringStartsWith('No se pudo autorizar el atado', (string) $respuesta->json('message'));
        $this->assertStringNotContainsString('TejHistorialInventarioTelares', (string) $respuesta->getContent());
        $this->assertSame('Calificado', DB::connection('sqlsrv')->table('AtaMontadoTelas')->value('Estatus'));
    }

    public function test_fecha_km_invalida_responde_el_mensaje_fijo(): void
    {
        $this->preparar(['acceso', 'crear', 'modificar']);
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->update(['Estatus' => 'En Proceso', 'Tipo' => '3', 'NoTelarId' => '401']);

        $this->postJson('/atadores/save', [
            'action' => 'km_montado', 'no_julio' => '00010-1', 'no_orden' => '00010', 'fecha_inicio' => 'mañana',
        ])->assertStatus(422)->assertJson(['ok' => false, 'message' => 'La fecha debe ir como aaaa-mm-dd hh:mm']);
    }
}
