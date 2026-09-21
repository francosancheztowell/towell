<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Programa Urd / Eng: 7 de los 9 controllers no tenian ningun userCan y las
 * rutas solo llevaban 'auth'. Cualquier autenticado podia crear una orden de
 * urdido/engomado (6 tablas + auditoria) o cancelar reservas ajenas.
 *
 * La proteccion vive ahora en routes/modules/programa-urd-eng.php via
 * module.permission. Estos tests la anclan, incluidas las rutas futuras.
 */
class ProgramaUrdEngAuthorizationTest extends TestCase
{
    // Por idrol, no por nombre: userPermissions() indexa por nombre y hay repetidos en SYSRoles.
    private const MODULO = '52'; // Programa Urd / Eng

    /** Toda ruta del modulo, sin excepcion, exige 'acceso'. */
    public function test_todas_las_rutas_del_modulo_exigen_acceso(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (IlluminateRoute $r) => str_starts_with((string) $r->getName(), 'programa.urd.eng.'));

        $this->assertGreaterThan(25, $rutas->count(), 'Se esperaban las ~31 rutas del modulo.');

        $desprotegidas = $rutas
            ->reject(fn (IlluminateRoute $r) => in_array(
                'module.permission:acceso,'.self::MODULO,
                $r->gatherMiddleware(),
                true
            ))
            ->map(fn (IlluminateRoute $r) => $r->getName())
            ->values()
            ->all();

        $this->assertSame([], $desprotegidas, 'Rutas del modulo sin permiso de acceso: '.implode(', ', $desprotegidas));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rutasQueMutan(): array
    {
        return [
            'programar telar' => ['programa.urd.eng.programar.telar', 'crear'],
            'crear ordenes urd/eng' => ['programa.urd.eng.crear.ordenes', 'crear'],
            'crear orden karl mayer' => ['programa.urd.eng.crear.orden.karl.mayer', 'crear'],
            'actualizar telar' => ['programa.urd.eng.actualizar.telar', 'modificar'],
            'reservar inventario' => ['programa.urd.eng.reservar.inventario', 'modificar'],
            'liberar telar' => ['programa.urd.eng.liberar.telar', 'eliminar'],
            'cancelar reserva' => ['programa.urd.eng.reservas.cancelar', 'eliminar'],
        ];
    }

    #[DataProvider('rutasQueMutan')]
    public function test_la_mutacion_declara_su_propio_permiso(string $nombre, string $accion): void
    {
        $ruta = Route::getRoutes()->getByName($nombre);

        $this->assertNotNull($ruta, "No se encontro la ruta [{$nombre}].");
        $this->assertContains('auth', $ruta->gatherMiddleware());
        $this->assertContains(
            'module.permission:'.$accion.','.self::MODULO,
            $ruta->gatherMiddleware(),
            "Falta el permiso [{$accion}] en [{$nombre}]."
        );
    }

    #[DataProvider('rutasQueMutan')]
    public function test_invitado_no_muta(string $nombre, string $_accion): void
    {
        $status = $this->postJson(route($nombre))->status();

        $this->assertContains($status, [302, 401], "POST {$nombre} respondio {$status} sin autenticar.");
    }

    #[DataProvider('rutasQueMutan')]
    public function test_autenticado_sin_permiso_recibe_403(string $nombre, string $_accion): void
    {
        $this->actingAs($this->usuarioSinPermisos())
            ->postJson(route($nombre))
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para esta acción.');
    }

    /**
     * Tener 'acceso' no alcanza para mutar: es el caso que hoy estaba abierto.
     */
    #[DataProvider('rutasQueMutan')]
    public function test_solo_con_acceso_no_alcanza_para_mutar(string $nombre, string $_accion): void
    {
        $this->actingAs($this->usuarioCon(['acceso']))
            ->postJson(route($nombre))
            ->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rutasDeLectura(): array
    {
        return [
            'pantalla principal' => ['programa.urd.eng.index'],
            'inventario de telares' => ['programa.urd.eng.inventario.telares'],
            'inventario disponible' => ['programa.urd.eng.inventario.disponible.get'],
            'diagnostico de reservas' => ['programa.urd.eng.reservas.diagnostico'],
            'nucleos' => ['programa.urd.eng.nucleos'],
            'materiales de urdido' => ['programa.urd.eng.materiales.urdido'],
        ];
    }

    #[DataProvider('rutasDeLectura')]
    public function test_lectura_sin_acceso_recibe_403(string $nombre): void
    {
        $this->actingAs($this->usuarioSinPermisos())
            ->getJson(route($nombre))
            ->assertForbidden();
    }

    /**
     * Con el permiso correcto el middleware deja pasar y llega la validacion.
     * Si el string de SYSRoles estuviera mal, userCan seria false para todos y
     * el test de 403 seguiria verde: este lo desmiente.
     */
    public function test_con_permiso_de_crear_llega_a_la_validacion(): void
    {
        $this->actingAs($this->usuarioCon(['acceso', 'crear']))
            ->postJson(route('programa.urd.eng.crear.ordenes'), [])
            ->assertStatus(422);
    }

    /** @param list<string> $acciones */
    private function usuarioCon(array $acciones): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Programa Urd/Eng con permisos']);
        $usuario->idusuario = 999101;

        $idrol = 52;
        app()->instance('permisos.roles', collect([
            mb_strtolower(self::MODULO) => (object) ['idrol' => $idrol, 'modulo' => self::MODULO],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect([
            $idrol => (object) [
                'acceso' => (int) in_array('acceso', $acciones, true),
                'crear' => (int) in_array('crear', $acciones, true),
                'modificar' => (int) in_array('modificar', $acciones, true),
                'eliminar' => (int) in_array('eliminar', $acciones, true),
                'registrar' => 0,
            ],
        ]));

        return $usuario;
    }

    private function usuarioSinPermisos(): Usuario
    {
        $usuario = new Usuario(['nombre' => 'Programa Urd/Eng sin permisos']);
        $usuario->idusuario = 999102;

        app()->instance('permisos.roles', collect([
            mb_strtolower(self::MODULO) => (object) ['idrol' => 52, 'modulo' => self::MODULO],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect());

        return $usuario;
    }
}
