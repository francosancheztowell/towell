<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use App\Repositories\UsuarioRepository;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Mockery;
use Tests\Concerns\SiembraPermisos;
use Tests\TestCase;

/**
 * Borrar un usuario que ya no existe vuelve al listado con "Usuario no encontrado".
 *
 * La rama usaba route('usuarios.select'), que no existe (el nombre real lleva el prefijo
 * configuracion.): la RouteNotFoundException caia en el catch generico y el usuario veia
 * "Verifica que no tenga registros relacionados", un mensaje falso (ERP-F0-11).
 */
final class UsuarioDestroyRedirectTest extends TestCase
{
    use SiembraPermisos;

    public function test_borrar_usuario_inexistente_redirige_al_listado_con_error(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $repo = Mockery::mock(UsuarioRepository::class);
        $repo->shouldReceive('findById')->with(999)->once()->andReturn(null);
        $this->app->instance(UsuarioRepository::class, $repo);

        $usuario = new Usuario([
            'idusuario' => 1,
            'nombre' => 'Admin',
            'contrasenia' => 'x',
            'numero_empleado' => '1',
            'area' => 'Sistemas',
        ]);
        $usuario->idusuario = 1;
        $this->sembrarPermisos(1, [59 => 'Usuarios']);

        $this->actingAs($usuario)
            ->delete(route('configuracion.usuarios.destroy', 999))
            ->assertRedirect(route('configuracion.usuarios.select'))
            ->assertSessionHas('error', 'Usuario no encontrado');
    }
}
