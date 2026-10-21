<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\UsuarioController;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class KardexRouteContractTest extends TestCase
{
    public function test_kardex_route_shows_submodule_grid_and_requires_module_access(): void
    {
        $route = $this->ruta('kardex.index', 'kardex', 209);

        $this->assertSame(UsuarioController::class.'@showSubModulos', $route->getActionName());
        $this->assertSame('kardex', $route->defaults['moduloPrincipal'] ?? null);
    }

    /**
     * SYSRoles: 209 Kardex (1500), 211 Crudo - Acabado (1501), 212 Acabado - Costura P.T. (1502).
     * The dashboard moved from /kardex to /kardex/resumen and keeps the Kardex permission.
     *
     * @return array<string, array{string, string, int}>
     */
    public static function submodulos(): array
    {
        return [
            'resumen' => ['kardex.resumen', 'kardex/resumen', 209],
            'crudo-acabado' => ['kardex.crudo-acabado', 'kardex/crudo-acabado', 211],
            'acabado-costura-pt' => ['kardex.acabado-costura-pt', 'kardex/acabado-costura-pt', 212],
        ];
    }

    #[DataProvider('submodulos')]
    public function test_submodule_route_matches_sysroles_and_requires_its_access(string $nombre, string $uri, int $idrol): void
    {
        $this->ruta($nombre, $uri, $idrol);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        foreach (['/kardex', '/kardex/resumen', '/kardex/crudo-acabado', '/kardex/acabado-costura-pt'] as $url) {
            $this->get($url)->assertRedirect();
        }
    }

    private function ruta(string $nombre, string $uri, int $idrol): IlluminateRoute
    {
        $route = Route::getRoutes()->getByName($nombre);

        $this->assertInstanceOf(IlluminateRoute::class, $route, "Missing route {$nombre}");
        $this->assertSame($uri, $route->uri());
        $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $route->methods());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains("module.permission:acceso,{$idrol}", $route->gatherMiddleware());

        return $route;
    }
}
