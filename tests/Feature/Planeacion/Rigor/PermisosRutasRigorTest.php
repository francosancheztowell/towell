<?php

namespace Tests\Feature\Planeacion\Rigor;

use App\Models\Planeacion\ReqModelosCodificados;
use App\Services\Planeacion\ProgramaTejido\CalendarioProduccion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Planeacion\Concerns\ConPermisosPlaneacion;
use Tests\Feature\Planeacion\Concerns\ProgramaTejidoFixtures;
use Tests\TestCase;

/**
 * verificar-cambio-telar y preview-fechas-balanceo devuelven diagnóstico del programa
 * (clave modelo, telares, fechas y saldos simulados): exigen el mismo permiso que su acción
 * hermana (cambiar-telar / actualizar-pedidos-balanceo), modificar del módulo de la superficie
 * (2 Programa, 5 Muestras). El permiso de una superficie no abre la otra.
 */
class PermisosRutasRigorTest extends TestCase
{
    use ConPermisosPlaneacion;
    use ProgramaTejidoFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10 08:00:00');
        CalendarioProduccion::limpiarCache();
        $this->prepararSuperficies();
        $this->sembrarFixtures();
        $this->createTablaDesdeModelo(ReqModelosCodificados::class);
        $this->createTablaDbo('ReqCalendarioLine', ['CalendarioId' => 'text', 'FechaInicio' => 'text', 'FechaFin' => 'text']);

        foreach (['ReqProgramaTejido', 'MuestrasPrograma'] as $tabla) {
            DB::table($tabla)->where('Id', 2)->update(['TamanoClave' => 'MOD1']);
        }
        DB::table('ReqModelosCodificados')->insert([
            ['TamanoClave' => 'MOD1', 'SalonTejidoId' => 'SMIT', 'Total' => 50],
            ['TamanoClave' => 'MOD1', 'SalonTejidoId' => 'JACQUARD', 'Total' => 50],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CalendarioProduccion::limpiarCache();
        parent::tearDown();
    }

    /** @return array<string, array{0: string, 1: int, 2: string}> */
    public static function rutas(): array
    {
        // superficie (prefijo de URI), idrol propio, acción
        return [
            'verificar programa' => ['programa-tejido', 2, 'verificar'],
            'verificar muestras' => ['muestras', 5, 'verificar'],
            'preview programa' => ['programa-tejido', 2, 'preview'],
            'preview muestras' => ['muestras', 5, 'preview'],
        ];
    }

    private function enviar(string $sup, string $accion, array $permisos)
    {
        $this->usarSuperficie($sup === 'muestras' ? 'muestras' : 'programa');
        [$uri, $body] = $accion === 'verificar'
            ? ["/planeacion/{$sup}/2/verificar-cambio-telar", ['nuevo_salon' => 'JACQUARD', 'nuevo_telar' => '210']]
            : ["/planeacion/{$sup}/preview-fechas-balanceo", ['cambios' => [['id' => 3, 'total_pedido' => 700], ['id' => 4, 'total_pedido' => 300]], 'ord_compartida' => 7]];

        return $this->actingAs($this->usuarioConPermisos($permisos))->postJson($uri, $body);
    }

    #[DataProvider('rutas')]
    public function test_sin_permisos_es_403_sin_escribir(string $sup, int $_idrol, string $accion): void
    {
        $antes = $this->fotoSuperficies();

        $this->enviar($sup, $accion, [])
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para esta acción.');

        $this->assertSame($antes, $this->fotoSuperficies());
    }

    #[DataProvider('rutas')]
    public function test_acceso_sin_modificar_es_403(string $sup, int $idrol, string $accion): void
    {
        $this->enviar($sup, $accion, [$idrol => ['acceso', 'crear', 'eliminar', 'registrar']])->assertForbidden();
    }

    #[DataProvider('rutas')]
    public function test_modificar_de_la_otra_superficie_es_403(string $sup, int $idrol, string $accion): void
    {
        $this->enviar($sup, $accion, [($idrol === 2 ? 5 : 2) => ['acceso', 'modificar']])->assertForbidden();
    }

    #[DataProvider('rutas')]
    public function test_con_modificar_del_modulo_responde_200_sin_escribir(string $sup, int $idrol, string $accion): void
    {
        $antes = $this->fotoSuperficies();

        $r = $this->enviar($sup, $accion, [$idrol => ['modificar']])->assertOk();
        $accion === 'verificar'
            ? $r->assertJsonPath('clave_modelo', 'MOD1')->assertJsonPath('telar_destino', '210')
            : $r->assertJsonPath('success', true);

        $this->assertSame($antes, $this->fotoSuperficies());
    }
}
