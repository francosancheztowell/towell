<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Trazabilidad\TrazabilidadFilterOptionsService;
use App\ValueObjects\Trazabilidad\TrazabilidadFilters;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TrazabilidadBusquedaFlogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.connections.sqlsrv' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        Schema::connection('sqlsrv')->create('TrazaProduccion', function (Blueprint $table): void {
            $table->id('Id');
            $table->string('Flogs', 100)->nullable();
            $table->string('Articulo', 100)->nullable();
            $table->string('Tamano', 100)->nullable();
        });
    }

    /**
     * @param  list<array{0:?string,1?:string,2?:string}>  $filas  [Flogs, Articulo, Tamano]
     */
    private function sembrar(array $filas): void
    {
        DB::connection('sqlsrv')->table('TrazaProduccion')->insert(array_map(
            static fn (array $f): array => ['Flogs' => $f[0], 'Articulo' => $f[1] ?? 'A1', 'Tamano' => $f[2] ?? 'MB'],
            $filas
        ));
    }

    /** @return list<string> */
    private function buscar(string $termino, TrazabilidadFilters $filtros = new TrazabilidadFilters, int $limite = 50): array
    {
        return app(TrazabilidadFilterOptionsService::class)->searchFlogs($filtros, $termino, $limite)->all();
    }

    public function test_los_comodines_del_termino_se_buscan_literal(): void
    {
        $this->sembrar([['RS-100'], ['RS_200'], ['RS%300'], ['RS[4]00'], ['RSX500'], [''], [null]]);

        $this->assertSame(['RS%300'], $this->buscar('%'));
        $this->assertSame(['RS_200'], $this->buscar('_'));
        $this->assertSame(['RS[4]00'], $this->buscar('['));
        $this->assertSame(['RS[4]00'], $this->buscar('[4]'));
        $this->assertSame(['RS_200'], $this->buscar('s_2'), 'sin distinguir mayúsculas');
        $this->assertSame([], $this->buscar('RS_5'), '_ no es comodín de un carácter');
    }

    public function test_sin_termino_devuelve_distintos_ordenados_y_respeta_el_limite(): void
    {
        $this->sembrar([['F03'], ['F01'], ['F02'], ['F01'], ['F04']]);

        $this->assertSame(['F01', 'F02', 'F03', 'F04'], $this->buscar(''));
        $this->assertSame(['F01', 'F02'], $this->buscar('', limite: 2));
        $this->assertSame(['F01', 'F02'], $this->buscar('F0', limite: 2));
    }

    public function test_articulo_y_tamano_acotan_la_lista_y_flog_no(): void
    {
        $this->sembrar([
            ['F-MB-A1', 'A1', 'MB'],
            ['F-MB-A2', 'A2', 'MB'],
            ['F-CH-A1', 'A1', 'CH'],
        ]);

        $this->assertSame(['F-MB-A1', 'F-MB-A2'], $this->buscar('', new TrazabilidadFilters(tamano: 'MB')));
        $this->assertSame(['F-CH-A1', 'F-MB-A1'], $this->buscar('', new TrazabilidadFilters(articulo: 'A1')));
        $this->assertSame(['F-MB-A1'], $this->buscar('F', new TrazabilidadFilters(articulo: 'A1', tamano: 'MB')));
        // El propio flog seleccionado no recorta sus opciones.
        $this->assertCount(3, $this->buscar('', new TrazabilidadFilters(flog: 'F-MB-A1')));
    }
}
