<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Smoke de las rutas de marbetes (preview / guardar).
 * No pega a SQL ni al kernel HTTP: solo verifica que el contrato de rutas
 * sigue vivo bajo `auth`. El happy-path de preview/guardar está en
 * LiberarOrdenesLiberarTest::test_marbetes_preview_respeta_regla_fel_y_guardado_sincroniza_cat_codificados.
 */
class LiberarMarbetesRoutesTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function marbetesRoutes(): array
    {
        return [
            'preview GET' => ['programa-tejido.marbetes'],
            'guardar POST' => ['programa-tejido.marbetes.guardar'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('marbetesRoutes')]
    public function test_ruta_marbetes_existe_y_exige_auth(string $name): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "No se encontro la ruta [{$name}].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware(), "Middleware auth faltante en [{$name}].");
        $this->assertSame(
            \App\Http\Controllers\Planeacion\ProgramaTejido\LiberarOrdenesController::class,
            $route->getControllerClass()
        );
    }
}
