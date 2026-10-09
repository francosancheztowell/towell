<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Trazabilidad\Index;
use App\Models\Sistema\Usuario;
use App\Services\Trazabilidad\TrazabilidadFilterOptionsService;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Trazabilidad exige acceso al idrol 190 en el router, no por nombre en cada action.
 */
class TrazabilidadPermisosTest extends TestCase
{
    private const IDROL = 190;

    /** @var array<int, string> */
    private const RUTAS = [
        '/trazabilidad',
        '/trazabilidad/detalles/matriz?flog=F-1',
        '/trazabilidad/detalles/produccion?flog=F-1',
        '/trazabilidad/detalles/flog?flog=F-1',
        '/trazabilidad/detalles/ventas?flog=F-1',
        '/trazabilidad/opciones/flog',
        '/trazabilidad/redbooth?flog=F-1',
        '/trazabilidad/flog-archivo?file=a.jpg',
    ];

    public function test_todas_las_rutas_del_modulo_llevan_el_gate_por_idrol(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta): bool => str_starts_with((string) $ruta->getName(), 'trazabilidad.'));

        $this->assertCount(count(self::RUTAS), $rutas);
        foreach ($rutas as $ruta) {
            $this->assertContains('module.permission:acceso,'.self::IDROL, $ruta->gatherMiddleware(), $ruta->uri());
        }
    }

    public function test_sin_acceso_cada_ruta_responde_403(): void
    {
        $usuario = $this->usuario(acceso: 0);

        foreach (self::RUTAS as $ruta) {
            $this->actingAs($usuario)->getJson($ruta)->assertForbidden();
        }
    }

    public function test_con_acceso_la_ruta_llega_al_controller(): void
    {
        $this->mock(TrazabilidadFilterOptionsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('searchFlogs')->once()->andReturn(collect(['F-100']));
        });

        $this->actingAs($this->usuario(acceso: 1))
            ->getJson('/trazabilidad/opciones/flog?q=F')
            ->assertOk()
            ->assertJson(['results' => [['id' => 'F-100', 'text' => 'F-100']]]);
    }

    public function test_el_componente_livewire_tambien_exige_acceso(): void
    {
        Livewire::actingAs($this->usuario(acceso: 0))
            ->test(Index::class)
            ->assertForbidden();
    }

    private function usuario(int $acceso): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Test Trazabilidad']);
        $usuario->idusuario = 999190;

        app()->instance('permisos.roles', collect([
            'trazabilidad' => (object) ['idrol' => self::IDROL, 'modulo' => 'Trazabilidad'],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect([
            self::IDROL => (object) ['acceso' => $acceso, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
        ]));

        return $usuario;
    }
}
