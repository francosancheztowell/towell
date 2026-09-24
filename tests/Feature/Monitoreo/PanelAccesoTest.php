<?php

namespace Tests\Feature\Monitoreo;

use App\Livewire\Admin\Accesos;
use App\Livewire\Admin\EnLinea;
use App\Livewire\Admin\ErrorDetalle;
use App\Livewire\Admin\Errores;
use App\Livewire\Admin\Navegacion;
use App\Livewire\Admin\Rendimiento;
use App\Livewire\Admin\Sesiones;
use Livewire\Livewire;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\Feature\Monitoreo\Concerns\SiembraPanel;
use Tests\TestCase;

/** MON-21: todo /admin/* exige el Gate admin, en la ruta y en cada llamada Livewire. */
class PanelAccesoTest extends TestCase
{
    use PreparaMonitoreo;
    use SiembraPanel;

    private const RUTAS = ['/admin', '/admin/sesiones', '/admin/navegacion', '/admin/rendimiento', '/admin/errores', '/admin/accesos'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
    }

    public function test_sistemas_abre_todas_las_pantallas(): void
    {
        $idError = $this->error();

        foreach ([...self::RUTAS, '/admin/errores/'.$idError] as $ruta) {
            $this->nuevoProceso();
            $this->actingAs($this->admin)->get($ruta)->assertOk()->assertSee('Panel de administración');
        }
    }

    public function test_otra_area_recibe_403_en_todas(): void
    {
        $tejido = $this->crearUsuario(['area' => 'Tejido']);
        $idError = $this->error();

        foreach ([...self::RUTAS, '/admin/errores/'.$idError] as $ruta) {
            $this->nuevoProceso();
            $this->actingAs($tejido)->get($ruta)->assertForbidden();
        }
    }

    public function test_invitado_va_al_login(): void
    {
        foreach (self::RUTAS as $ruta) {
            $this->get($ruta)->assertRedirect('/login');
        }
    }

    public function test_componentes_rechazan_otra_area_aunque_se_llamen_directo(): void
    {
        $tejido = $this->crearUsuario(['area' => 'Tejido']);

        foreach ([EnLinea::class, Sesiones::class, Navegacion::class, Rendimiento::class, Errores::class, Accesos::class] as $componente) {
            Livewire::actingAs($tejido)->test($componente)->assertForbidden();
        }
        Livewire::actingAs($tejido)->test(ErrorDetalle::class, ['errorId' => $this->error()])->assertForbidden();
    }

    public function test_una_accion_de_un_componente_montado_vuelve_a_exigir_el_gate(): void
    {
        $componente = Livewire::actingAs($this->admin)->test(EnLinea::class)->assertOk();

        // El usuario cambia de área a media sesión: la siguiente llamada ya no pasa.
        $this->admin->forceFill(['area' => 'Tejido'])->save();
        $componente->call('cerrarSesion', (string) $this->dispositivo())->assertForbidden();
        $this->assertSame(0, $this->mon('SYSMonAcceso')->where('Tipo', 'admin_accion')->count());
    }

    public function test_detalle_de_error_inexistente_da_404(): void
    {
        $this->actingAs($this->admin)->get('/admin/errores/999999')->assertNotFound();
        $this->actingAs($this->admin)->get('/admin/errores/abc')->assertNotFound();
    }
}
