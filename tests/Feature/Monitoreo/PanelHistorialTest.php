<?php

namespace Tests\Feature\Monitoreo;

use App\Livewire\Admin\Accesos;
use App\Livewire\Admin\Navegacion;
use App\Livewire\Admin\Sesiones;
use Livewire\Livewire;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\Feature\Monitoreo\Concerns\SiembraPanel;
use Tests\TestCase;

/** MON-23 (sesiones), MON-24 (navegación) y MON-27 (accesos). */
class PanelHistorialTest extends TestCase
{
    use PreparaMonitoreo;
    use SiembraPanel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararPanel();
    }

    public function test_sesiones_con_duracion_y_filtros(): void
    {
        $ana = $this->crearUsuario(['numero_empleado' => '1111', 'nombre' => 'Ana Tejido']);
        $beto = $this->crearUsuario(['numero_empleado' => '2222', 'nombre' => 'Beto Urdido']);
        $d1 = $this->dispositivo(['Nombre' => 'Tablet 1']);
        $d2 = $this->dispositivo(['Nombre' => 'Tablet 2']);
        $this->sesion($d1, (int) $ana->idusuario, ['Inicio' => now()->subHours(3), 'Fin' => now()->subHour()->subMinutes(55), 'MotivoFin' => 'remoto']);
        $this->sesion($d2, (int) $beto->idusuario, ['Inicio' => now()->subMinutes(20)]);
        $this->sesion($d2, (int) $beto->idusuario, ['Inicio' => now()->subDays(20), 'Fin' => now()->subDays(20), 'MotivoFin' => 'logout']);

        $componente = Livewire::actingAs($this->admin)->test(Sesiones::class)
            ->assertSee('Ana Tejido (#1111)')
            ->assertSee('1 h 05 min')
            ->assertSee('remoto')
            ->assertSee('Abierta')
            ->assertDontSee('logout'); // fuera de los 7 días por defecto

        $componente->set('buscar', '2222')->assertSee('Beto Urdido')->assertDontSee('Ana Tejido');
        $componente->set('buscar', '')->set('abiertas', 'cerradas')->assertSee('Ana Tejido')->assertDontSee('Beto Urdido');
        $componente->set('abiertas', '')->set('dispositivo', (string) $d1)->assertSee('Ana Tejido')->assertDontSee('Beto Urdido');
        $componente->set('dispositivo', '')->set('desde', now()->subDays(30)->toDateString())->set('hasta', now()->subDays(10)->toDateString())
            ->assertSee('logout')->assertDontSee('Ana Tejido');
        // Fecha basura en la URL: se ignora.
        $componente->set('desde', 'no-es-fecha')->set('hasta', '')->assertSee('Ana Tejido')->assertOk();
    }

    public function test_navegacion_pide_filtro_y_muestra_linea_de_tiempo_por_dispositivo(): void
    {
        $ana = $this->crearUsuario(['numero_empleado' => '1111', 'nombre' => 'Ana Tejido']);
        $d1 = $this->dispositivo(['Nombre' => 'Tablet 1']);
        $d2 = $this->dispositivo(['Nombre' => 'Tablet 2']);
        $this->vista($d1, (int) $ana->idusuario, ['Ruta' => 'tejido.inventario', 'VisibleMs' => 125000, 'Inicio' => today()->addHours(8)]);
        $this->vista($d1, (int) $ana->idusuario, ['Ruta' => 'tejido.inventario', 'VisibleMs' => 60000, 'Inicio' => today()->addHours(9)]);
        $this->vista($d1, (int) $ana->idusuario, ['Ruta' => 'urdido.programa', 'VisibleMs' => 5000, 'Inicio' => today()->addHours(10)]);
        $this->vista($d2, (int) $ana->idusuario, ['Ruta' => 'otra.pantalla', 'Inicio' => today()->addHours(8)]);
        $this->vista($d1, (int) $ana->idusuario, ['Ruta' => 'ayer.pantalla', 'Inicio' => today()->subDay()->addHours(8)]);

        $componente = Livewire::actingAs($this->admin)->test(Navegacion::class)
            ->assertSee('Elige un dispositivo')
            ->assertViewHas('filas', null);

        $componente->set('dispositivo', (string) $d1)
            ->assertSee('Tiempo visible por página')
            ->assertSee('tejido.inventario')
            ->assertSee('3 min') // 125 + 60 s
            ->assertSee('2 vistas')
            ->assertSee('08:00:00')
            ->assertDontSee('otra.pantalla')
            ->assertDontSee('ayer.pantalla');

        $componente->set('fecha', today()->subDay()->toDateString())->assertSee('ayer.pantalla')->assertDontSee('urdido.programa');
    }

    public function test_navegacion_por_numero_de_empleado(): void
    {
        $ana = $this->crearUsuario(['numero_empleado' => '1111', 'nombre' => 'Ana Tejido']);
        $beto = $this->crearUsuario(['numero_empleado' => '2222', 'nombre' => 'Beto Urdido']);
        $d1 = $this->dispositivo();
        $this->sesion($d1, (int) $ana->idusuario, ['Inicio' => today()->addHours(7)]);
        $this->vista($d1, (int) $ana->idusuario, ['Ruta' => 'de.ana', 'Inicio' => today()->addHours(8)]);
        $this->vista($d1, (int) $beto->idusuario, ['Ruta' => 'de.beto', 'Inicio' => today()->addHours(8)]);

        Livewire::actingAs($this->admin)->test(Navegacion::class)
            ->set('usuario', '1111')
            ->assertSee('de.ana')
            ->assertDontSee('de.beto')
            ->set('usuario', '0000')
            ->assertSee('No existe ese número de empleado');
    }

    public function test_accesos_con_filtros_y_actor(): void
    {
        $ana = $this->crearUsuario(['numero_empleado' => '1111', 'nombre' => 'Ana Tejido']);
        $base = ['Ip' => '10.0.0.9', 'Fecha' => now()->subHour()];
        $this->mon('SYSMonAcceso')->insert([
            $base + ['Tipo' => 'login', 'NumeroEmpleado' => '1111', 'UsuarioId' => $ana->idusuario, 'Motivo' => null, 'ActorId' => null],
            $base + ['Tipo' => 'login_fallido', 'NumeroEmpleado' => '3333', 'UsuarioId' => null, 'Motivo' => 'contrasena', 'ActorId' => null],
            $base + ['Tipo' => 'admin_accion', 'NumeroEmpleado' => null, 'UsuarioId' => $ana->idusuario, 'Motivo' => 'cierre_remoto', 'ActorId' => $this->admin->idusuario],
        ]);

        $componente = Livewire::actingAs($this->admin)->test(Accesos::class)
            ->assertSee('login fallido')
            ->assertSee('cierre_remoto')
            ->assertSee('Admin Sistemas');

        $componente->set('tipo', 'login_fallido')->assertSee('3333')->assertDontSee('cierre_remoto');
        $componente->set('tipo', 'no_existe')->assertSee('3333')->assertSee('cierre_remoto');
        $componente->set('tipo', '')->set('buscar', 'Ana')->assertSee('cierre_remoto')->assertDontSee('3333');
    }
}
