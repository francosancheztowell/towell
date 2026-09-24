<?php

namespace Tests\Feature\Planeacion;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\TestCase;

/**
 * PT-01.1 — Autorización en servidor de las escrituras de routes/modules/planeacion.php.
 *
 * Complementa tests/Feature/PlaneacionMutationAuthorizationTest (BUG-003): cubre las rutas
 * que PT-01.1 cerró y deja un inventario que rompe si aparece una ruta de escritura sin
 * module.permission que no esté justificada como de solo lectura.
 */
class PlaneacionEscrituraAutorizacionTest extends TestCase
{
    use ConPermisosPlaneacion;

    /**
     * POST/PUT/PATCH/DELETE sin module.permission revisados uno por uno: no escriben en BD
     * (búsquedas y cálculos por POST por tamaño de payload), son preferencias propias del
     * usuario, o son redirects 301 legacy. Clave = métodos + URI del snapshot.
     */
    private const SIN_PERMISO_DE_MODULO = [
        'POST planeacion/catalogos/codificacion-modelos/buscar' => 'búsqueda (SELECT)',
        'POST planeacion/lmat/api/matriz-calibre/lote' => 'lookup en lote (SELECT)',
        'GET|POST programa-tejido/datos-relacionados' => 'catálogo (SELECT)',
        'GET|POST muestras/datos-relacionados' => 'catálogo (SELECT)',
        'POST planeacion/programa-tejido/preview-fechas-balanceo' => 'preview de fechas, no persiste',
        'POST planeacion/muestras/preview-fechas-balanceo' => 'preview de fechas, no persiste',
        'POST planeacion/programa-tejido/{id}/verificar-cambio-telar' => 'validación previa, no persiste',
        'POST planeacion/muestras/{id}/verificar-cambio-telar' => 'validación previa, no persiste',
        'POST programa-tejido/columnas' => 'preferencia del propio usuario (usuario_id ajeno → 403)',
        'POST muestras/columnas' => 'preferencia del propio usuario (usuario_id ajeno → 403)',
    ];

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: int, 4: list<string>}>
     */
    public static function rutasCerradas(): array
    {
        // ruta, método, middleware esperado, idrol correcto, acciones
        return [
            'liberar muestras exige crear de Muestras' => ['muestras.liberar-ordenes.procesar', 'POST', 'module.permission:crear,5', 5, ['crear']],
            'descargar programa exige registrar de Programa' => ['programa-tejido.descargar-programa', 'POST', 'module.permission:registrar,2', 2, ['registrar']],
            'descargar muestras exige registrar de Muestras' => ['muestras.descargar-programa', 'POST', 'module.permission:registrar,5', 5, ['registrar']],
            'lectura v2 programa exige acceso de Programa' => ['programa-tejido.v2.registros', 'GET', 'module.permission:acceso,2', 2, ['acceso']],
            'lectura v2 muestras exige acceso de Muestras' => ['muestras.v2.registros', 'GET', 'module.permission:acceso,5', 5, ['acceso']],
        ];
    }

    #[DataProvider('rutasCerradas')]
    public function test_la_ruta_declara_su_permiso_de_modulo(string $ruta, string $_metodo, string $middleware): void
    {
        $route = Route::getRoutes()->getByName($ruta);

        $this->assertNotNull($route, "No existe la ruta [{$ruta}]");
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains($middleware, $route->gatherMiddleware());
    }

    #[DataProvider('rutasCerradas')]
    public function test_sin_permiso_responde_403(string $ruta, string $metodo): void
    {
        $this->actingAs($this->usuarioConPermisos([]))
            ->json($metodo, route($ruta))
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para esta acción.');
    }

    #[DataProvider('rutasCerradas')]
    public function test_el_permiso_del_otro_modulo_no_basta(string $ruta, string $metodo, string $_m, int $idrol, array $acciones): void
    {
        // Programa ↔ Muestras: tener la acción en la otra superficie no autoriza esta.
        $otro = $idrol === 5 ? 2 : 5;

        $this->actingAs($this->usuarioConPermisos([$otro => $acciones]))
            ->json($metodo, route($ruta))
            ->assertForbidden();
    }

    #[DataProvider('rutasCerradas')]
    public function test_con_el_permiso_correcto_pasa_el_middleware(string $ruta, string $metodo, string $_m, int $idrol, array $acciones): void
    {
        $status = $this->actingAs($this->usuarioConPermisos([$idrol => $acciones]))
            ->json($metodo, route($ruta), [])
            ->status();

        // Pasó el router: validación (422), guard de capacidad B (422) o flag v2 apagado (404).
        $this->assertContains($status, [404, 422], "{$ruta} respondió {$status}");
    }

    public function test_toda_escritura_de_planeacion_tiene_permiso_de_modulo_o_esta_justificada(): void
    {
        $snapshot = json_decode((string) file_get_contents(base_path('tests/fixtures/planeacion/programa-tejido/rutas.json')), true, flags: JSON_THROW_ON_ERROR);
        $sinPermiso = [];

        foreach ($snapshot['rutas'] as $r) {
            $escribe = array_intersect(explode('|', $r['metodos']), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [];
            $conPermiso = preg_grep('/^module\.permission:/', $r['middleware']) !== [];
            $redirect = $r['accion'] === '\\Illuminate\\Routing\\RedirectController';
            if ($escribe && ! $conPermiso && ! $redirect) {
                $sinPermiso[] = $r['metodos'].' '.$r['uri'];
            }
        }
        sort($sinPermiso);
        $justificadas = array_keys(self::SIN_PERMISO_DE_MODULO);
        sort($justificadas);

        $this->assertSame($justificadas, $sinPermiso, 'Ruta de escritura sin module.permission: agregar el middleware o justificarla aquí');
    }

    public function test_columnas_rechaza_escribir_las_preferencias_de_otro_usuario(): void
    {
        $usuario = $this->usuarioConPermisos([]);

        foreach (['/programa-tejido/columnas', '/muestras/columnas'] as $uri) {
            $this->actingAs($usuario)
                ->postJson($uri, ['usuario_id' => 1, 'columnas' => ['NoTelarId' => true]])
                ->assertForbidden();
            // El propio id (o ninguno) sigue funcionando; sin columnas no toca BD.
            $this->actingAs($usuario)
                ->postJson($uri, ['usuario_id' => $usuario->idusuario, 'columnas' => []])
                ->assertOk()
                ->assertJsonPath('success', true);
        }
    }
}
