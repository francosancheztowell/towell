<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use App\Repositories\Ventas\VentasMarcasEscrituraRepository;
use App\Services\Ventas\VentasDatosVersion;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

/**
 * Versión de los datos de Ventas: se publica cuando las tablas se escribieron y llevan la calma
 * sin cambios, después de calentar la caché; la página la consulta en /ventas/datos/version.
 */
class VentasDatosVersionTest extends TestCase
{
    /** @var list<array<string, string|null>> */
    private array $lecturas = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // SQLite no tiene sys.dm_db_index_usage_stats: cada llamada devuelve la siguiente lectura.
        $this->app->instance(VentasMarcasEscrituraRepository::class, new class($this) extends VentasMarcasEscrituraRepository
        {
            public function __construct(private readonly VentasDatosVersionTest $test) {}

            public function marcas(): array
            {
                return $this->test->siguienteLectura();
            }
        });
    }

    /** @return array<string, string|null> */
    public function siguienteLectura(): array
    {
        return array_shift($this->lecturas) ?? throw new RuntimeException('Sin lecturas preparadas.');
    }

    public function test_publica_la_version_despues_de_la_calma_y_tras_calentar(): void
    {
        $antes = ['dbo.TwHistoricosPedidos' => '2026-10-19 10:09:45.970'];
        $despues = ['dbo.TwHistoricosPedidos' => '2026-10-19 11:00:00.000'];
        $this->lecturas = [$antes, $despues, $despues];
        $version = app(VentasDatosVersion::class);
        $calentadas = [];
        $calentar = function (string $nueva) use (&$calentadas, $version): void {
            $calentadas[] = $nueva;
            $this->assertSame(VentasDatosVersion::INICIAL, $version->actual(), 'se calienta antes de publicar');
        };

        $this->assertNull($version->sincronizar(1000, $calentar));
        $this->assertNull($version->sincronizar(1060, $calentar));
        $this->assertSame('1180', $version->sincronizar(1060 + VentasDatosVersion::CALMA_SEGUNDOS, $calentar));

        $this->assertSame(['1180'], $calentadas);
        $this->assertSame('1180', $version->actual());
    }

    public function test_si_calentar_falla_no_publica_y_lo_reintenta(): void
    {
        $antes = ['dbo.TwHistoricosPedidos' => 'a'];
        $despues = ['dbo.TwHistoricosPedidos' => 'b'];
        $this->lecturas = [$antes, $despues, $despues, $despues];
        $version = app(VentasDatosVersion::class);
        $version->sincronizar(0, fn () => null);
        $version->sincronizar(10, fn () => null);

        try {
            $version->sincronizar(10 + VentasDatosVersion::CALMA_SEGUNDOS, fn () => throw new RuntimeException('BD caída'));
            $this->fail('debía propagar el error');
        } catch (RuntimeException) {
            $this->assertSame(VentasDatosVersion::INICIAL, $version->actual());
        }

        $this->assertSame('200', $version->sincronizar(200, fn () => null));
    }

    public function test_el_endpoint_de_version_no_se_cachea(): void
    {
        Cache::forever('ventas:datos:version', '1760890000');

        $this->actingAs($this->usuario())->getJson(route('ventas.datos.version'))
            ->assertOk()
            ->assertExactJson(['version' => '1760890000'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_sin_acceso_no_hay_version(): void
    {
        $this->actingAs($this->usuario(false))->getJson(route('ventas.datos.version'))->assertForbidden();
    }

    private function usuario(bool $acceso = true): Usuario
    {
        $modulo = (string) config('ventas.permission_module');
        $usuario = new Usuario(['nombre' => 'Ventas Version']);
        $usuario->idusuario = $acceso ? 999221 : 999222;

        app()->instance('permisos.roles', collect([
            mb_strtolower($modulo) => (object) ['idrol' => 77, 'modulo' => $modulo],
        ]));
        app()->instance('permisos.usuario.'.$usuario->idusuario, collect([
            77 => (object) ['acceso' => (int) $acceso, 'crear' => 0, 'modificar' => 0, 'eliminar' => 0, 'registrar' => 0],
        ]));

        return $usuario;
    }
}
