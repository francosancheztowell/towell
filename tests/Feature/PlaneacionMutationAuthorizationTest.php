<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * BUG-003 (esqueleto Planeación): un login no basta para mutar.
 *
 * Nombres SYSRoles.modulo verificados en UI:
 * - Liberar: `Programa Tejido` + `crear` (botón Liberar en liberar-ordenes).
 * - L.Mat guardar: `Codificación` + `modificar` (modal L.Mat de Codificación).
 * - Mover / Finalizar: idrol 188 (`Utilería` de Planeación) + `modificar`. Va por idrol y no
 *   por nombre porque `Utilería` esta repetido en SYSRoles (188 Planeación / 67 Configuración)
 *   y userPermissions() indexa por nombre, asi que por nombre gana una fila arbitraria.
 */
class PlaneacionMutationAuthorizationTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function mutationRoutes(): array
    {
        return [
            'liberar programa tejido' => [
                'programa-tejido.liberar-ordenes.procesar',
                'module.permission:crear,2', // Programa Tejido
            ],
            'liberar muestras' => [
                'muestras.liberar-ordenes.procesar',
                'module.permission:crear,2', // Programa Tejido
            ],
            'lmat guardar' => [
                'planeacion.lmat.guardar',
                'module.permission:modificar,169', // Codificación
            ],
            'mover ordenes' => [
                'planeacion.utileria.mover.procesar',
                'module.permission:modificar,188',
            ],
            'finalizar ordenes' => [
                'planeacion.utileria.finalizar.procesar',
                'module.permission:modificar,188',
            ],
        ];
    }

    #[DataProvider('mutationRoutes')]
    public function test_invitado_no_ejecuta_la_mutacion(string $routeName, string $_expectedMiddleware): void
    {
        $uri = route($routeName);

        $response = $this->post($uri);
        $this->assertContains(
            $response->status(),
            [302, 401],
            "POST {$routeName} should redirect or return 401 when unauthenticated, got {$response->status()}"
        );
        if ($response->status() === 302) {
            $response->assertRedirect(route('login'));
        }

        $json = $this->postJson($uri);
        $this->assertContains(
            $json->status(),
            [302, 401],
            "JSON POST {$routeName} should return 401 or 302 when unauthenticated, got {$json->status()}"
        );
    }

    #[DataProvider('mutationRoutes')]
    public function test_autenticado_sin_permiso_de_modulo_recibe_403(string $routeName, string $_expectedMiddleware): void
    {
        $usuario = $this->actuandoComoSinPermisos();

        $this->actingAs($usuario)
            ->postJson(route($routeName))
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para esta acción.');
    }

    #[DataProvider('mutationRoutes')]
    public function test_mutacion_declara_middleware_de_modulo(string $routeName, string $expectedMiddleware): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "No se encontro la ruta [{$routeName}].");
        $this->assertInstanceOf(IlluminateRoute::class, $route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains(
            $expectedMiddleware,
            $route->gatherMiddleware(),
            "Middleware [{$expectedMiddleware}] faltante en [{$routeName}]."
        );
    }

    /**
     * Si el string de SYSRoles estuviera mal, userCan sería false para todos
     * y el test de 403 seguiría verde. Validación 422 prueba que el middleware dejó pasar.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function mutationsThatValidateBeforeDb(): array
    {
        return [
            'liberar programa tejido' => ['programa-tejido.liberar-ordenes.procesar', '2', 'crear'],
            'lmat guardar' => ['planeacion.lmat.guardar', '169', 'modificar'],
            'finalizar ordenes' => ['planeacion.utileria.finalizar.procesar', '188', 'modificar'],
        ];
    }

    #[DataProvider('mutationsThatValidateBeforeDb')]
    public function test_autenticado_con_permiso_llega_a_validacion(string $routeName, string $modulo, string $accion): void
    {
        $usuario = $this->actuandoComo($modulo, $accion);

        $this->actingAs($usuario)
            ->postJson(route($routeName), [])
            ->assertStatus(422);
    }

    /**
     * Sembrar la memoización de userPermissions() evita tocar SQL Server.
     */
    private function actuandoComo(string $modulo, string $accion): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Con permiso Planeación']);
        $usuario->idusuario = 999003;

        // Si la ruta referencia el modulo por idrol, userPermissions() lo usa tal cual.
        $idrol = is_numeric($modulo) ? (int) $modulo : 31;
        app()->instance('permisos.roles', collect([
            mb_strtolower($modulo) => (object) ['idrol' => $idrol, 'modulo' => $modulo],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect([
            $idrol => (object) [
                'acceso' => 1,
                'crear' => (int) ($accion === 'crear'),
                'modificar' => (int) ($accion === 'modificar'),
                'eliminar' => 0,
                'registrar' => 0,
            ],
        ]));

        return $usuario;
    }

    /**
     * Simula un usuario autenticado sin filas en SYSUsuariosRoles.
     */
    private function actuandoComoSinPermisos(): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Sin permiso Planeación']);
        $usuario->idusuario = 999001;

        app()->instance('permisos.roles', collect([
            'programa tejido' => (object) ['idrol' => 11, 'modulo' => 'Programa Tejido'],
            'codificación' => (object) ['idrol' => 12, 'modulo' => 'Codificación'],
            'utilería' => (object) ['idrol' => 13, 'modulo' => 'Utilería'],
            '188' => (object) ['idrol' => 188, 'modulo' => 'Utilería'],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect());

        return $usuario;
    }
}
