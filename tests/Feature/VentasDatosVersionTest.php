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

    /** Lo que devuelve segundosDesdeUltimaEscritura() del repositorio falso. */
    public ?int $segundosSinEscribir = 3600;

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

            public function segundosDesdeUltimaEscritura(): ?int
            {
                return $this->test->segundosSinEscribir;
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

    public function test_sin_version_publicada_publica_tras_la_calma_aunque_las_marcas_no_cambien(): void
    {
        // Lo que deja `php artisan optimize:clear` en cada deploy: ni versión ni estado en caché.
        $marcas = ['dbo.TwHistoricosPedidos' => '2026-10-19 10:09:45.970'];
        $this->lecturas = [$marcas, $marcas, $marcas];
        $version = app(VentasDatosVersion::class);

        $this->assertNull($version->sincronizar(0, fn () => null));
        $this->assertNull($version->sincronizar(60, fn () => null));
        $this->assertSame('120', $version->sincronizar(VentasDatosVersion::CALMA_SEGUNDOS, fn () => null));
        $this->assertSame('120', $version->actual());
    }

    public function test_con_version_publicada_y_sin_cambios_no_vuelve_a_publicar(): void
    {
        $marcas = ['dbo.TwHistoricosPedidos' => 'a'];
        $this->lecturas = [$marcas, $marcas];
        Cache::forever('ventas:datos:version', '50');
        $version = app(VentasDatosVersion::class);

        $this->assertNull($version->sincronizar(100, fn () => null));
        $this->assertNull($version->sincronizar(100 + VentasDatosVersion::CALMA_SEGUNDOS, fn () => null));
        $this->assertSame('50', $version->actual());
    }

    public function test_actualizar_sin_cambios_responde_al_dia_sin_calentar(): void
    {
        $marcas = ['dbo.TwHistoricosPedidos' => 'a'];
        $this->publicadas('50', $marcas);
        $this->lecturas = [$marcas];

        $resultado = app(VentasDatosVersion::class)->publicarAhora(100, fn () => $this->fail('no debía calentar'));

        $this->assertSame(['estado' => 'al_dia', 'version' => '50'], $resultado);
    }

    public function test_actualizar_con_cambios_publica_sin_esperar_la_calma(): void
    {
        $this->publicadas('50', ['dbo.TwHistoricosPedidos' => 'a']);
        $nuevas = ['dbo.TwHistoricosPedidos' => 'b'];
        $this->lecturas = [$nuevas, $nuevas];
        $this->segundosSinEscribir = 60;
        $version = app(VentasDatosVersion::class);
        $calentadas = [];

        $resultado = $version->publicarAhora(1000, function (string $nueva) use (&$calentadas): void {
            $calentadas[] = $nueva;
        });

        $this->assertSame(['estado' => 'publicada', 'version' => '1000'], $resultado);
        $this->assertSame(['1000'], $calentadas);
        $this->assertSame('1000', $version->actual());
        $this->assertNull($version->sincronizar(1060, fn () => $this->fail('el comando no debe volver a publicar')));
    }

    public function test_actualizar_mientras_la_carga_escribe_no_publica(): void
    {
        $this->publicadas('50', ['dbo.TwHistoricosPedidos' => 'a']);
        $this->lecturas = [['dbo.TwHistoricosPedidos' => 'b']];
        $this->segundosSinEscribir = VentasDatosVersion::CALMA_BOTON_SEGUNDOS - 1;
        $version = app(VentasDatosVersion::class);

        $resultado = $version->publicarAhora(1000, fn () => $this->fail('no debía calentar'));

        $this->assertSame(['estado' => 'cargando', 'version' => '50'], $resultado);
        $this->assertSame('50', $version->actual());
    }

    public function test_actualizar_sin_version_publicada_publica(): void
    {
        $this->lecturas = [['dbo.TwHistoricosPedidos' => 'a']];

        $resultado = app(VentasDatosVersion::class)->publicarAhora(1000, fn () => null);

        $this->assertSame(['estado' => 'publicada', 'version' => '1000'], $resultado);
    }

    public function test_actualizar_en_el_mismo_segundo_que_la_version_vigente_no_la_repite(): void
    {
        $this->publicadas('1000', ['dbo.TwHistoricosPedidos' => 'a']);
        $this->lecturas = [['dbo.TwHistoricosPedidos' => 'b']];

        $resultado = app(VentasDatosVersion::class)->publicarAhora(1000, fn () => null);

        $this->assertSame(['estado' => 'publicada', 'version' => '1001'], $resultado);
    }

    public function test_el_endpoint_actualizar_responde_el_estado(): void
    {
        $marcas = ['dbo.TwHistoricosPedidos' => 'a'];
        $this->publicadas('50', $marcas);
        $this->lecturas = [$marcas];

        $this->actingAs($this->usuario())->postJson(route('ventas.datos.actualizar'))
            ->assertOk()
            ->assertExactJson(['estado' => 'al_dia', 'version' => '50']);
    }

    public function test_sin_acceso_no_se_puede_actualizar(): void
    {
        $this->actingAs($this->usuario(false))->postJson(route('ventas.datos.actualizar'))->assertForbidden();
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

    /**
     * Deja una versión publicada con las marcas que la originaron, como tras una sincronización.
     *
     * @param  array<string, string|null>  $marcas
     */
    private function publicadas(string $version, array $marcas): void
    {
        Cache::forever('ventas:datos:version', $version);
        Cache::forever('ventas:datos:marcas', ['estables' => $marcas, 'vistas' => $marcas, 'desde' => null]);
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
