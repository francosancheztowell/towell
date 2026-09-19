<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Smoke HTTP de las rutas de marbetes (preview / guardar).
 * No siembra SQL: guest y validación 422 bastan para probar que el router sigue vivo.
 */
class LiberarMarbetesRoutesTest extends TestCase
{
    public function test_rutas_marbetes_exigen_auth(): void
    {
        foreach (['programa-tejido.marbetes', 'programa-tejido.marbetes.guardar'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "No se encontro la ruta [{$name}].");
            $this->assertInstanceOf(IlluminateRoute::class, $route);
            $this->assertContains('auth', $route->gatherMiddleware(), "Middleware auth faltante en [{$name}].");
        }
    }

    public function test_invitado_no_lee_ni_guarda_marbetes(): void
    {
        $get = $this->getJson(route('programa-tejido.marbetes', ['id' => 1]));
        $this->assertContains($get->status(), [302, 401]);

        $post = $this->postJson(route('programa-tejido.marbetes.guardar'), ['id' => 1]);
        $this->assertContains($post->status(), [302, 401]);
    }

    public function test_autenticado_sin_id_recibe_422_en_preview(): void
    {
        $usuario = new Usuario(['nombre' => 'Marbetes smoke']);
        $usuario->idusuario = 999410;

        $this->actingAs($usuario)
            ->getJson(route('programa-tejido.marbetes'))
            ->assertStatus(422);
    }

    public function test_autenticado_sin_id_recibe_422_al_guardar(): void
    {
        $usuario = new Usuario(['nombre' => 'Marbetes smoke']);
        $usuario->idusuario = 999411;

        $this->actingAs($usuario)
            ->postJson(route('programa-tejido.marbetes.guardar'), [])
            ->assertStatus(422);
    }
}
