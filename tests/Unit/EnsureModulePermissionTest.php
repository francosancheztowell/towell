<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\EnsureModulePermission;
use App\Models\Sistema\Usuario;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureModulePermissionTest extends TestCase
{
    public function test_permite_cuando_user_can_es_true(): void
    {
        $usuario = $this->usuarioConPermiso('Programa Tejido', 21, ['crear' => 1]);
        $this->actingAs($usuario);

        $called = false;
        $response = (new EnsureModulePermission)->handle(
            Request::create('/planeacion/programa-tejido/liberar-ordenes/procesar', 'POST'),
            function () use (&$called): Response {
                $called = true;

                return response('ok');
            },
            'crear',
            'Programa Tejido',
        );

        $this->assertTrue($called);
        $this->assertSame('ok', $response->getContent());
    }

    public function test_json_recibe_403_cuando_falta_el_permiso(): void
    {
        $usuario = $this->usuarioConPermiso('Programa Tejido', 21, ['acceso' => 1]);
        $this->actingAs($usuario);

        $request = Request::create(
            '/planeacion/programa-tejido/liberar-ordenes/procesar',
            'POST',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        $response = (new EnsureModulePermission)->handle(
            $request,
            fn (): Response => response('no debe pasar'),
            'crear',
            'Programa Tejido',
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertSame(
            'No tienes permiso para esta acción.',
            $response->getData(true)['message'] ?? null,
        );
    }

    /**
     * @param  array<string, int>  $permisos
     */
    private function usuarioConPermiso(string $modulo, int $idrol, array $permisos): Usuario
    {
        $usuario = new Usuario(['nombre' => 'AuthZ unit']);
        $usuario->idusuario = 999002;

        app()->instance('permisos.roles', collect([
            mb_strtolower($modulo) => (object) ['idrol' => $idrol, 'modulo' => $modulo],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect([
            $idrol => (object) array_merge(
                ['acceso' => 0, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
                $permisos,
            ),
        ]));

        return $usuario;
    }
}
