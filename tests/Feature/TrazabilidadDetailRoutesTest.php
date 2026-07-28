<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Trazabilidad\TrazabilidadDetailController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrazabilidadDetailRoutesTest extends TestCase
{
    public function test_each_detail_has_a_read_only_named_endpoint(): void
    {
        $expected = [
            'trazabilidad.details.matrix' => ['trazabilidad/detalles/matriz', 'matrix'],
            'trazabilidad.details.production' => ['trazabilidad/detalles/produccion', 'production'],
            'trazabilidad.details.flog' => ['trazabilidad/detalles/flog', 'flog'],
        ];

        foreach ($expected as $name => [$uri, $method]) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route);
            $this->assertSame($uri, $route->uri());
            $this->assertContains('GET', $route->methods());
            $this->assertSame(
                TrazabilidadDetailController::class.'@'.$method,
                $route->getActionName(),
            );
        }
    }
}
