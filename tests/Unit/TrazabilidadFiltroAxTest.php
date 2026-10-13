<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Trazabilidad\TrazaProduccion;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Sintaxis de AX y año/mes sobre TrazaProduccion (SQLite en memoria). */
class TrazabilidadFiltroAxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        Schema::connection('sqlsrv')->create('TrazaProduccion', function ($table): void {
            $table->increments('Id');
            $table->string('Flogs')->nullable();
            $table->string('Articulo')->nullable();
            $table->string('Tamano')->nullable();
            $table->string('Cliente')->nullable();
            $table->date('Fecha')->nullable();
        });

        TrazaProduccion::insert([
            ['Flogs' => 'F-1', 'Articulo' => '7160', 'Tamano' => 'MB', 'Cliente' => 'WALMART, S.A.', 'Fecha' => '2026-01-10'],
            ['Flogs' => 'F-1', 'Articulo' => '7166', 'Tamano' => 'CH', 'Cliente' => 'WALMART, S.A.', 'Fecha' => '2026-03-05'],
            ['Flogs' => 'F-2', 'Articulo' => '7170', 'Tamano' => 'MB', 'Cliente' => 'SORIANA', 'Fecha' => '2026-03-20'],
            ['Flogs' => 'F-3', 'Articulo' => '8100', 'Tamano' => 'G_1', 'Cliente' => 'SORIANA', 'Fecha' => '2025-12-30'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    private function ids(array $filtros, ?string $excepto = null): array
    {
        return TrazaProduccion::query()->filtrados($filtros, $excepto)->orderBy('Id')->pluck('Id')->all();
    }

    public function test_lista_rango_y_comodin_se_combinan_con_or(): void
    {
        $this->assertSame([2, 4], $this->ids(['articulo' => '7166,8100']));
        $this->assertSame([1, 2, 3], $this->ids(['articulo' => '7160..7170']));
        $this->assertSame([2, 3, 4], $this->ids(['articulo' => '7166..']));
        $this->assertSame([1, 2], $this->ids(['articulo' => '..7166']));
        $this->assertSame([1, 2, 3], $this->ids(['articulo' => '71*']));
        $this->assertSame([1, 4], $this->ids(['articulo' => '7160,8*']));
        // Campos distintos se combinan con AND.
        $this->assertSame([1, 3], $this->ids(['articulo' => '71*', 'tamano' => 'MB']));
    }

    public function test_el_comodin_busca_literales_el_guion_bajo_y_el_porcentaje(): void
    {
        $this->assertSame([4], $this->ids(['tamano' => 'G_*']));
        $this->assertSame([], $this->ids(['tamano' => 'G%*']));
        $this->assertSame([], $this->ids(['tamano' => 'GX*']));
    }

    public function test_cliente_es_lista_exacta_aunque_el_nombre_traiga_comas(): void
    {
        $this->assertSame([1, 2], $this->ids(['cliente' => ['WALMART, S.A.']]));
        $this->assertSame([1, 2, 3, 4], $this->ids(['cliente' => ['WALMART, S.A.', 'SORIANA']]));
        $this->assertSame([1, 2, 3, 4], $this->ids(['cliente' => ['SORIANA']], 'cliente'));
    }

    public function test_anio_y_mes_traen_los_flogs_completos_con_movimiento_en_el_periodo(): void
    {
        // F-1 se movió en marzo: entra también su renglón de enero (el Flog entero).
        $this->assertSame([1, 2, 3], $this->ids(['anio' => '2026', 'mes' => '3']));
        $this->assertSame([1, 2, 3], $this->ids(['anio' => '2026']));
        $this->assertSame([4], $this->ids(['anio' => '2025']));
        $this->assertSame([], $this->ids(['anio' => '2026', 'mes' => '2']));
        // Sin año, el mes no aplica.
        $this->assertSame([1, 2, 3, 4], $this->ids(['mes' => '2']));
    }
}
