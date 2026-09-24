<?php

namespace Tests\Feature\Monitoreo;

use Illuminate\Support\Facades\Gate;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class AdminAccesoTest extends TestCase
{
    use PreparaMonitoreo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
    }

    public function test_gate_admin_normaliza_el_area(): void
    {
        foreach (['Sistemas', ' sistemas ', 'SISTEMAS', 'Sístemas'] as $i => $area) {
            $usuario = $this->crearUsuario(['numero_empleado' => 'G'.$i, 'area' => $area]);
            $this->assertTrue(Gate::forUser($usuario)->allows('admin'), "'{$area}' debe pasar el Gate");
        }

        foreach (['Planeación', '', null] as $i => $area) {
            $usuario = $this->crearUsuario(['numero_empleado' => 'N'.$i, 'area' => $area]);
            $this->assertFalse(Gate::forUser($usuario)->allows('admin'));
        }
    }

    public function test_gate_admin_respeta_areas_configuradas(): void
    {
        config()->set('monitoreo.areas_admin', ['Sistemas', 'Dirección']);

        $usuario = $this->crearUsuario(['area' => 'DIRECCION']);

        $this->assertTrue(Gate::forUser($usuario)->allows('admin'));
    }

    public function test_panel_admin_solo_para_sistemas(): void
    {
        $sistemas = $this->crearUsuario(['numero_empleado' => '1', 'area' => 'Sistemas']);
        $tejido = $this->crearUsuario(['numero_empleado' => '2', 'area' => 'Tejido']);

        $this->actingAs($sistemas)->get('/admin')->assertOk()->assertSee('Panel de administración');

        $this->nuevoProceso();
        $this->actingAs($tejido)->get('/admin')->assertForbidden();
    }

    public function test_invitado_va_al_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }
}
