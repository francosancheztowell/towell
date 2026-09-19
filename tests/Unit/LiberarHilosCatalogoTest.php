<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Planeacion\Liberar\LiberarHilosCatalogo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Catálogo de hilos extraído de LiberarOrdenesController.
 * Misma entrada → misma salida: ITEMID con '-1', mapa sin sufijo,
 * y select de TwTipoHilo recortado/único/ordenado.
 */
class LiberarHilosCatalogoTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private LiberarHilosCatalogo $catalogo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv_ti');

        Schema::connection('sqlsrv_ti')->create('INVENTTABLE', function (Blueprint $table) {
            $table->string('ITEMID');
            $table->string('TwTipoHiloId')->nullable();
        });
        Schema::connection('sqlsrv_ti')->create('TwTipoHilo', function (Blueprint $table) {
            $table->string('TipoHilo')->nullable();
        });

        $this->catalogo = new LiberarHilosCatalogo;
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv_ti')->dropIfExists('INVENTTABLE');
        Schema::connection('sqlsrv_ti')->dropIfExists('TwTipoHilo');
        parent::tearDown();
    }

    public function test_mapa_vacio_si_no_hay_item_ids(): void
    {
        $this->assertSame([], $this->catalogo->mapaTipoHilo(''));
        $this->assertSame([], $this->catalogo->mapaTipoHilo('  ,  , '));
    }

    public function test_mapa_quita_solo_el_sufijo_menos_uno_final(): void
    {
        DB::connection('sqlsrv_ti')->table('INVENTTABLE')->insert([
            ['ITEMID' => 'IT100-1', 'TwTipoHiloId' => 'ALGODON'],
            ['ITEMID' => '3-100-1', 'TwTipoHiloId' => 'POLIESTER'],
            ['ITEMID' => 'IT200-1', 'TwTipoHiloId' => null],
        ]);

        $map = $this->catalogo->mapaTipoHilo('IT100,3-100,IT200');

        $this->assertSame('ALGODON', $map['IT100']);
        $this->assertSame('POLIESTER', $map['3-100']);
        $this->assertArrayNotHasKey('3100', $map);
        $this->assertArrayHasKey('IT200', $map);
        $this->assertNull($map['IT200']);
    }

    public function test_mapa_omite_items_que_ax_no_tiene(): void
    {
        DB::connection('sqlsrv_ti')->table('INVENTTABLE')->insert([
            ['ITEMID' => 'IT100-1', 'TwTipoHiloId' => 'ALGODON'],
        ]);

        $this->assertSame(
            ['IT100' => 'ALGODON'],
            $this->catalogo->mapaTipoHilo('IT100,IT999')
        );
    }

    public function test_opciones_recorta_deduplica_ordena_y_descarta_vacios(): void
    {
        DB::connection('sqlsrv_ti')->table('TwTipoHilo')->insert([
            ['TipoHilo' => '  VISCOSA  '],
            ['TipoHilo' => 'ALGODON'],
            ['TipoHilo' => 'ALGODON'],
            ['TipoHilo' => ''],
            ['TipoHilo' => '   '],
            ['TipoHilo' => 'POLIESTER'],
        ]);

        $this->assertSame(
            ['ALGODON', 'POLIESTER', 'VISCOSA'],
            $this->catalogo->opciones()
        );
    }

    public function test_opciones_vacio_si_el_catalogo_no_tiene_tipos(): void
    {
        $this->assertSame([], $this->catalogo->opciones());
    }
}
