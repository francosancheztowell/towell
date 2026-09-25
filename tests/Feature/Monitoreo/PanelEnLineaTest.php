<?php

namespace Tests\Feature\Monitoreo;

use App\Livewire\Admin\EnLinea;
use App\Services\Monitoreo\CierreRemotoService;
use App\Services\Monitoreo\PanelConsultas;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\Feature\Monitoreo\Concerns\SiembraPanel;
use Tests\TestCase;

/** MON-22 (En línea) y MON-28 (acciones auditadas). */
class PanelEnLineaTest extends TestCase
{
    use PreparaMonitoreo;
    use SiembraPanel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
    }

    public function test_estados_segun_el_contrato(): void
    {
        $this->assertSame(PanelConsultas::EN_LINEA, PanelConsultas::estado(now()->subSeconds(30), true, 0));
        $this->assertSame(PanelConsultas::INACTIVO, PanelConsultas::estado(now()->subSeconds(30), true, 601));
        $this->assertSame(PanelConsultas::INACTIVO, PanelConsultas::estado(now()->subSeconds(30), false, 0));
        // Oculta: el latido llega cada 300 s, así que a los 200 s sigue "reciente".
        $this->assertSame(PanelConsultas::INACTIVO, PanelConsultas::estado(now()->subSeconds(200), false, 0));
        $this->assertSame(PanelConsultas::DESCONECTADO, PanelConsultas::estado(now()->subSeconds(200), true, 0));
        $this->assertSame(PanelConsultas::DESCONECTADO, PanelConsultas::estado(now()->subSeconds(500), false, 0));
        $this->assertSame(PanelConsultas::DESCONECTADO, PanelConsultas::estado(null, true, 0));
    }

    public function test_filtro_y_conteos_coinciden_con_el_estado(): void
    {
        $tejido = $this->crearUsuario(['numero_empleado' => '7702', 'nombre' => 'Operador Telar', 'area' => 'Tejido']);
        $enLinea = $this->dispositivo(['Nombre' => 'Tablet telar 12', 'UltimoUsuarioId' => $tejido->idusuario]);
        $this->mon('SYSMonDispositivo')->where('Id', $enLinea)->update(['UltimaSesionId' => $this->sesion($enLinea, (int) $tejido->idusuario)]);
        $this->dispositivo(['Nombre' => 'Tablet inactiva', 'InactivoSeg' => 900]);
        $this->dispositivo(['Nombre' => 'Tablet apagada', 'UltimaActividad' => now()->subHours(2)]);
        $this->dispositivo(['Nombre' => 'Tablet vieja', 'UltimaActividad' => now()->subDays(3)]);

        $componente = Livewire::actingAs($this->admin)->test(EnLinea::class)
            ->assertViewHas('conteos', ['en_linea' => 1, 'inactivo' => 1, 'desconectado' => 1])
            ->assertSee('Tablet telar 12')
            ->assertSee('Operador Telar (#7702)')
            ->assertSee('Tejido')
            ->assertSee('1 h')
            ->assertSee('Tablet inactiva')
            ->assertDontSee('Tablet apagada');

        $componente->set('estado', 'desconectado')
            ->assertSee('Tablet apagada')
            ->assertSee('Tablet vieja')
            ->assertDontSee('Tablet telar 12');

        $componente->set('estado', 'en_linea')->assertSee('Tablet telar 12')->assertDontSee('Tablet inactiva');
        $componente->set('estado', '')->set('buscar', '7702')->assertSee('Tablet telar 12')->assertDontSee('Tablet inactiva');
    }

    public function test_se_refresca_con_poll_visible_de_al_menos_10_segundos(): void
    {
        config()->set('monitoreo.poll_seconds', 3);

        Livewire::actingAs($this->admin)->test(EnLinea::class)->assertSeeHtml('wire:poll.visible.10s');
    }

    public function test_no_consulta_sysmonvista(): void
    {
        $consultas = [];
        DB::connection('sqlsrv')->listen(function ($q) use (&$consultas) {
            $consultas[] = $q->sql;
        });
        $this->dispositivo();

        Livewire::actingAs($this->admin)->test(EnLinea::class)->assertOk();

        $this->assertNotEmpty($consultas);
        $this->assertEmpty(array_filter($consultas, fn ($sql) => str_contains($sql, 'SYSMonVista')));
    }

    public function test_cerrar_sesion_usa_el_cierre_remoto_y_queda_auditado(): void
    {
        $id = $this->dispositivo(['Nombre' => 'Tablet A', 'UltimoUsuarioId' => 55]);
        $uuid = $this->mon('SYSMonDispositivo')->where('Id', $id)->value('Uuid');

        Livewire::actingAs($this->admin)->test(EnLinea::class)
            ->call('seleccionar', (string) $id)
            ->call('cerrarSesion')
            ->assertDispatched('aviso', tipo: 'success')
            ->assertSee('cierre pendiente');

        $this->assertTrue(Cache::has(CierreRemotoService::llave($uuid)));
        $this->assertSame((int) $this->admin->idusuario, (int) $this->mon('SYSMonDispositivo')->where('Id', $id)->value('CierreSolicitadoPor'));

        $acceso = $this->mon('SYSMonAcceso')->where('Tipo', 'admin_accion')->first();
        $this->assertSame('cierre_remoto', $acceso->Motivo);
        $this->assertSame((int) $this->admin->idusuario, (int) $acceso->ActorId);
        $this->assertSame($id, (int) $acceso->DispositivoId);
        $this->assertSame(55, (int) $acceso->UsuarioId);
    }

    public function test_cerrar_sesion_sin_seleccion_no_hace_nada(): void
    {
        Livewire::actingAs($this->admin)->test(EnLinea::class)->call('cerrarSesion')->assertNotDispatched('aviso');

        $this->assertSame(0, $this->mon('SYSMonAcceso')->count());
    }

    public function test_renombrar_valida_guarda_y_audita(): void
    {
        $id = $this->dispositivo(['Nombre' => 'Viejo']);

        Livewire::actingAs($this->admin)->test(EnLinea::class)
            ->call('abrirRenombrar', (string) $id)
            ->assertSet('nombre', 'Viejo')
            ->assertDontSeeHtml('wire:poll')
            ->set('nombre', str_repeat('x', 81))
            ->call('guardarNombre')
            ->assertHasErrors(['nombre' => 'max'])
            ->set('nombre', '  Tablet telar 7  ')
            ->call('guardarNombre')
            ->assertHasNoErrors()
            ->assertSet('renombrando', null)
            ->assertDispatched('aviso');

        $this->assertSame('Tablet telar 7', $this->mon('SYSMonDispositivo')->where('Id', $id)->value('Nombre'));
        $acceso = $this->mon('SYSMonAcceso')->where('Tipo', 'admin_accion')->first();
        $this->assertSame('renombrar_dispositivo: "Viejo" → "Tablet telar 7"', $acceso->Motivo);
        $this->assertSame((int) $this->admin->idusuario, (int) $acceso->ActorId);
    }

    public function test_front_desactualizado(): void
    {
        $this->dispositivo(['Nombre' => 'Con front viejo', 'VersionFront' => 'aaaaaaaaaaaa']);

        $version = \App\Services\Monitoreo\Monitoreo::versionFront();
        $html = Livewire::actingAs($this->admin)->test(EnLinea::class)->html();

        $this->assertStringContainsString($version === '' ? 'Al día' : 'Desactualizado', $html);
    }
}
