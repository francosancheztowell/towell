<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Las rutas de Configuración administran usuarios, módulos y permisos: si una queda sin
 * module.permission, cualquier usuario autenticado puede llamarla por URL aunque el menú
 * se la oculte (userCan solo se evaluaba en la vista).
 *
 * POST /configuracion/usuarios/{id}/permisos es el caso critico: sin gate, quien sea se
 * asigna permisos de cualquier modulo.
 */
class ConfiguracionRoutePermissionTest extends TestCase
{
    /** Rutas sin gate a proposito: el 301 no ejecuta nada y no lee la sesion. */
    private const EXENTAS = ['modulo-configuracion'];

    public function test_toda_ruta_de_configuracion_exige_permiso_de_modulo(): void
    {
        $sinGate = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->esRutaDeConfiguracion($route->uri())) {
                continue;
            }

            if (in_array($route->uri(), self::EXENTAS, true)) {
                continue;
            }

            $tieneGate = collect($route->gatherMiddleware())
                ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'module.permission:'));

            if (! $tieneGate) {
                $sinGate[] = implode('|', $route->methods()).' /'.$route->uri();
            }
        }

        $this->assertSame([], $sinGate, "Rutas de Configuración sin module.permission:\n".implode("\n", $sinGate));
    }

    public function test_la_ruta_que_asigna_permisos_exige_modificar_usuarios(): void
    {
        $route = Route::getRoutes()->getByName('configuracion.usuarios.permisos.update');

        $this->assertNotNull($route, 'Desaparecio configuracion.usuarios.permisos.update.');
        $this->assertContains(
            'module.permission:modificar,59', // Usuarios
            $route->gatherMiddleware(),
            'La ruta que asigna permisos quedo sin gate: es escalacion de privilegios directa.'
        );
    }

    /**
     * Los modulos van por SYSRoles.idrol. Ver
     * RutasDestructivasPermisoTest::test_todo_gate_se_referencia_por_idrol para el porque.
     */
    public function test_los_modulos_se_referencian_por_idrol(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! $this->esRutaDeConfiguracion($route->uri())) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'module.permission:')) {
                    continue;
                }

                [, $args] = explode(':', $middleware, 2);
                [, $modulo] = array_pad(explode(',', $args, 2), 2, '');

                $this->assertTrue(
                    ctype_digit(trim($modulo)),
                    "/{$route->uri()} usa el nombre [{$modulo}]; pasar el idrol de SYSRoles."
                );
            }
        }
    }

    private function esRutaDeConfiguracion(string $uri): bool
    {
        return $uri === 'configuracion'
            || str_starts_with($uri, 'configuracion/')
            || str_starts_with($uri, 'modulos/')
            || str_starts_with($uri, 'api/modulos');
    }
}
