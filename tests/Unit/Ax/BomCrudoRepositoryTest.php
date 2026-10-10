<?php

declare(strict_types=1);

namespace Tests\Unit\Ax;

use App\Repositories\Ax\BomCrudoRepository;
use App\Services\Planeacion\Liberar\LiberarBomCrudoResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class BomCrudoRepositoryTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private BomCrudoRepository $repo;

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
        Schema::connection('sqlsrv_ti')->create('BOMTABLE', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('NAME')->nullable();
            $table->string('ITEMGROUPID')->nullable();
            $table->string('TWINVENTSIZEID')->nullable();
            $table->string('TWSALON')->nullable();
            $table->integer('Vigente')->default(1);
        });
        Schema::connection('sqlsrv_ti')->create('BOMVERSION', function (Blueprint $table) {
            $table->string('BOMID');
            $table->string('ITEMID');
        });
        $this->repo = new BomCrudoRepository;
    }

    protected function tearDown(): void
    {
        foreach (['BOMTABLE', 'BOMVERSION'] as $tabla) {
            Schema::connection('sqlsrv_ti')->dropIfExists($tabla);
        }
        parent::tearDown();
    }

    public function test_varias_versiones_de_ax_cuentan_como_una_lista(): void
    {
        $this->sembrar('BOM-A', 'IT1', 'STD', 'JACQUARD', versiones: 3);
        $this->sembrar('BOM-B', 'IT1', 'STD', 'ITEMA');
        $this->sembrar('BOM-KM', 'IT1', 'STD', 'KM');
        $this->sembrar('BOM-AJENO', 'IT9', 'STD', 'JACQUARD');
        $this->sembrar('BOM-BAJA', 'IT1', 'STD', 'JACQUARD', vigente: 0);
        $this->sembrar('BOM-OTRO', 'IT1', 'STD', 'JACQUARD', grupo: 'HILO');

        $lista = $this->repo->listarParaCodificacion('IT1');
        $ids = array_column($lista, 'bomId');

        $this->assertSame(['BOM-A', 'BOM-B', 'BOM-KM'], $ids);
        $this->assertSame('LISTA BOM-A', $lista[0]['bomName']);
    }

    public function test_filtra_talla_respeta_el_tope_y_el_item_vacio_no_consulta(): void
    {
        $this->sembrar('BOM-1', 'IT1', 'STD', 'SMIT');
        $this->sembrar('BOM-2', 'IT1', 'FEL', 'SMIT');
        $this->sembrar('BOM-3', 'IT1', 'STD', 'JACQUARD');

        $this->assertSame(['BOM-1', 'BOM-3'], array_column($this->repo->listarParaCodificacion('IT1', 'STD'), 'bomId'));
        $this->assertCount(1, $this->repo->listarParaCodificacion('IT1', null, 1));
        $this->assertSame([], $this->repo->listarParaCodificacion('  '));
    }

    public function test_liberar_y_codificacion_ven_el_mismo_bom_sin_repetir(): void
    {
        $this->sembrar('BOM-A', 'IT1', 'STD', 'JACUARD', versiones: 2);

        $liberar = (new LiberarBomCrudoResolver)->query('IT1', 'STD', 'JACQUARD')->get();

        $this->assertCount(1, $liberar);
        $this->assertSame('BOM-A', $liberar[0]->bomId);
        $this->assertSame(
            ['BOM-A'],
            array_column($this->repo->listarParaCodificacion('IT1', 'STD'), 'bomId')
        );
    }

    public function test_si_ax_no_responde_devuelve_vacio(): void
    {
        Schema::connection('sqlsrv_ti')->drop('BOMTABLE');
        Log::spy();

        $this->assertSame([], $this->repo->listarParaCodificacion('IT1'));
        Log::shouldHaveReceived('warning')->once();
    }

    private function sembrar(
        string $bomId,
        string $itemId,
        string $talla,
        string $salon,
        int $versiones = 1,
        int $vigente = 1,
        string $grupo = 'CRUDO',
    ): void {
        DB::connection('sqlsrv_ti')->table('BOMTABLE')->insert([
            'BOMID' => $bomId,
            'NAME' => 'LISTA '.$bomId,
            'ITEMGROUPID' => $grupo,
            'TWINVENTSIZEID' => $talla,
            'TWSALON' => $salon,
            'Vigente' => $vigente,
        ]);
        for ($i = 0; $i < $versiones; $i++) {
            DB::connection('sqlsrv_ti')->table('BOMVERSION')->insert([
                'BOMID' => $bomId,
                'ITEMID' => $itemId.'-1',
            ]);
        }
    }
}
