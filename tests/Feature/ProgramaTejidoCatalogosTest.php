<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProgramaTejidoCatalogosTest extends TestCase
{
    public function test_get_salon_tejido_options_sin_auth_redirige(): void
    {
        $response = $this->get('/programa-tejido/salon-options');

        $response->assertRedirect();
    }

    public function test_get_salon_tejido_options_ruta_existe(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('programa-tejido.salon-tejido-options');
        $this->assertNotNull($route);
        $this->assertSame('programa-tejido/salon-tejido-options', $route->uri());
    }
}
