<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DesarrolladoresRouteContractTest extends TestCase
{
    /**
     * GET /desarrolladores without authentication must redirect (302) or return 401.
     */
    public function test_index_sin_auth_redirige(): void
    {
        $response = $this->get('/desarrolladores');

        $this->assertContains(
            $response->status(),
            [302, 401],
            "GET /desarrolladores should redirect or return 401 when unauthenticated, got {$response->status()}"
        );
    }

    /**
     * POST /desarrolladores without authentication must redirect (302) or return 401.
     */
    public function test_store_sin_auth_redirige(): void
    {
        $response = $this->post('/desarrolladores');

        $this->assertContains(
            $response->status(),
            [302, 401],
            "POST /desarrolladores should redirect or return 401 when unauthenticated, got {$response->status()}"
        );
    }

    /**
     * GET /desarrolladores/telar/101/producciones-html without authentication must redirect or return 401.
     */
    public function test_producciones_html_sin_auth_redirige(): void
    {
        $response = $this->get('/desarrolladores/telar/101/producciones-html');

        $this->assertContains(
            $response->status(),
            [302, 401],
            "GET producciones-html should redirect or return 401 when unauthenticated, got {$response->status()}"
        );
    }

    /**
     * GET /desarrolladores/telar/101/orden-en-proceso without authentication must redirect or return 401.
     */
    public function test_orden_en_proceso_sin_auth_redirige(): void
    {
        $response = $this->get('/desarrolladores/telar/101/orden-en-proceso');

        $this->assertContains(
            $response->status(),
            [302, 401],
            "GET orden-en-proceso should redirect or return 401 when unauthenticated, got {$response->status()}"
        );
    }

    /**
     * GET /desarrolladores/verificar-orden without authentication must redirect or return 401.
     */
    public function test_verificar_orden_sin_auth_redirige(): void
    {
        $response = $this->get('/desarrolladores/verificar-orden');

        $this->assertContains(
            $response->status(),
            [302, 401],
            "GET verificar-orden should redirect or return 401 when unauthenticated, got {$response->status()}"
        );
    }

    /**
     * All expected desarrolladores named routes must be registered.
     */
    public function test_rutas_desarrolladores_existen(): void
    {
        $routes = Route::getRoutes();

        $this->assertNotNull(
            $routes->getByName('desarrolladores'),
            "Named route 'desarrolladores' must exist"
        );

        $this->assertNotNull(
            $routes->getByName('desarrolladores.store'),
            "Named route 'desarrolladores.store' must exist"
        );

        $this->assertNotNull(
            $routes->getByName('desarrolladores.obtener-producciones-html'),
            "Named route 'desarrolladores.obtener-producciones-html' must exist"
        );

        $this->assertNotNull(
            $routes->getByName('desarrolladores.orden-en-proceso'),
            "Named route 'desarrolladores.orden-en-proceso' must exist"
        );

        $this->assertNotNull(
            $routes->getByName('desarrolladores.verificar-orden'),
            "Named route 'desarrolladores.verificar-orden' must exist"
        );
    }
}
