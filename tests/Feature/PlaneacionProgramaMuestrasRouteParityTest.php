<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PlaneacionProgramaMuestrasRouteParityTest extends TestCase
{
    public function test_programa_tejido_and_muestras_core_routes_keep_http_parity(): void
    {
        $pairs = [
            ['programa-tejido.balancear', 'muestras.balancear'],
            ['programa-tejido.duplicar-telar', 'muestras.duplicar-telar'],
            ['programa-tejido.dividir-telar', 'muestras.dividir-telar'],
            ['programa-tejido.preview-fechas-balanceo', 'muestras.preview-fechas-balanceo'],
            ['verdetallesgrupobalanceo', 'muestras.verdetallesgrupobalanceo'],
            ['programa-tejido.cambiar-telar', 'muestras.cambiar-telar'],
            ['programa-tejido.update', 'muestras.update'],
            ['programa-tejido.destroy', 'muestras.destroy'],
        ];

        foreach ($pairs as [$programaRouteName, $muestrasRouteName]) {
            $programa = $this->getNamedRoute($programaRouteName);
            $muestras = $this->getNamedRoute($muestrasRouteName);

            $this->assertEqualsCanonicalizing(
                $programa->methods(),
                $muestras->methods(),
                "Metodos HTTP distintos entre [$programaRouteName] y [$muestrasRouteName]."
            );
        }
    }

    private function getNamedRoute(string $routeName): IlluminateRoute
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "No se encontro la ruta [$routeName].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);

        return $route;
    }
}
