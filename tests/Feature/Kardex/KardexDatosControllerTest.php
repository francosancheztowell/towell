<?php

declare(strict_types=1);

namespace Tests\Feature\Kardex;

use App\Http\Controllers\Kardex\KardexDatosController;
use App\Repositories\Kardex\KardexLibroRepository;
use App\Services\Kardex\KardexLibroBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class KardexDatosControllerTest extends TestCase
{
    private KardexLibroRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->repo = new class extends KardexLibroRepository
        {
            public int $lecturas = 0;

            public function periodos(): array
            {
                return [['anio' => 2026, 'mes' => 8], ['anio' => 2026, 'mes' => 7]];
            }

            public function filas(int $anio, int $mes): array
            {
                $this->lecturas++;

                return [
                    'urdido' => [['WMSLOCATIONID' => 'MC1', 'INICIALKG' => '8211.78', 'SALDOKG' => '10479.45']],
                    'julurd' => [], 'jultela' => [], 'tejido' => [],
                    'crudo' => [['ENTJACQUARDKG' => '278200.82']],
                ];
            }
        };
    }

    private function pedir(string $query): array
    {
        $respuesta = (new KardexDatosController)->libro(Request::create('/kardex/datos/libro?'.$query), $this->repo, new KardexLibroBuilder);

        return $respuesta->getData(true);
    }

    public function test_libro_de_un_mes_disponible_trae_secciones_kpis_y_flujo(): void
    {
        $libro = $this->pedir('anio=2026&mes=8');

        $this->assertSame('Agosto 2026', $libro['periodo']['nombre']);
        $this->assertSame(278200.82, $libro['kpis']['crudoKg']);
        $this->assertSame(['inicial' => 8211.78, 'final' => 10479.45], $libro['flujo']['nodos']['A-EP-URDID']);
        $this->assertSame(['urdido', 'julurd', 'jultela', 'tejido'], array_column($libro['secciones'], 'clave'));
    }

    public function test_el_libro_se_cachea_por_mes(): void
    {
        $this->pedir('anio=2026&mes=8');
        $this->pedir('anio=2026&mes=8');
        $this->pedir('anio=2026&mes=7');

        $this->assertSame(2, $this->repo->lecturas);
    }

    /** @return array<string, array{string}> */
    public static function mesesInvalidos(): array
    {
        return [
            'mes sin libro' => ['anio=2026&mes=9'],
            'sin parámetros' => [''],
            'texto' => ['anio=dos&mes=8'],
        ];
    }

    #[DataProvider('mesesInvalidos')]
    public function test_rechaza_meses_que_no_tienen_libro(string $query): void
    {
        try {
            $this->pedir($query);
            $this->fail('Debió responder 422');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(0, $this->repo->lecturas);
    }

    public function test_periodos_lista_los_meses_con_libro(): void
    {
        $this->assertSame(
            ['periodos' => [['anio' => 2026, 'mes' => 8], ['anio' => 2026, 'mes' => 7]]],
            (new KardexDatosController)->periodos($this->repo)->getData(true),
        );
    }

    public function test_rutas_de_datos_piden_el_permiso_del_modulo(): void
    {
        foreach (['kardex.datos.periodos' => 'kardex/datos/periodos', 'kardex.datos.libro' => 'kardex/datos/libro'] as $nombre => $uri) {
            $route = Route::getRoutes()->getByName($nombre);
            $this->assertNotNull($route, "Falta {$nombre}");
            $this->assertSame($uri, $route->uri());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('module.permission:acceso,209', $route->gatherMiddleware());
        }
    }
}
