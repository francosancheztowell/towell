<?php

namespace Tests\Unit;

use App\Services\ProgramaUrdEng\BomMaterialesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BomMaterialesServiceTest extends TestCase
{
    private string $tiSqlitePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tiSqlitePath = tempnam(sys_get_temp_dir(), 'towell_bom_ti_');
        if ($this->tiSqlitePath === false) {
            $this->markTestSkipped('No se pudo crear archivo temporal para sqlsrv_ti');
        }

        config()->set('database.connections.sqlsrv_ti', [
            'driver' => 'sqlite',
            'database' => $this->tiSqlitePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv_ti');
        DB::connection('sqlsrv_ti')->getPdo();

        Schema::connection('sqlsrv_ti')->create('BOM', function (Blueprint $table) {
            $table->string('BOMID', 80);
            $table->string('ITEMID', 80);
            $table->string('DATAAREAID', 10);
        });

        Schema::connection('sqlsrv_ti')->create('BOMVersion', function (Blueprint $table) {
            $table->string('BomId', 80);
            $table->string('ItemId', 80);
            $table->string('DATAAREAID', 10);
        });

        Schema::connection('sqlsrv_ti')->create('BOMTABLE', function (Blueprint $table) {
            $table->string('BOMID', 80);
            $table->string('DATAAREAID', 10);
            $table->string('ITEMGROUPID', 20);
            $table->boolean('Vigente')->default(true);
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->tiSqlitePath) && is_file($this->tiSqlitePath)) {
            @unlink($this->tiSqlitePath);
        }
        parent::tearDown();
    }

    public function test_get_bom_formulas_returns_distinct_ordered_tepd_enf_items_for_bom(): void
    {
        $bomId = 'ENG-BOM-1';
        DB::connection('sqlsrv_ti')->table('BOM')->insert([
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-B', 'DATAAREAID' => 'PRO'],
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-A', 'DATAAREAID' => 'PRO'],
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-A', 'DATAAREAID' => 'PRO'],
            ['BOMID' => $bomId, 'ITEMID' => 'OTHER-ITEM', 'DATAAREAID' => 'PRO'],
            ['BOMID' => 'OTHER-BOM', 'ITEMID' => 'TE-PD-ENF-Z', 'DATAAREAID' => 'PRO'],
        ]);

        $service = new BomMaterialesService;
        $formulas = $service->getBomFormulas($bomId);

        $this->assertSame(['TE-PD-ENF-A', 'TE-PD-ENF-B'], $formulas);
    }

    public function test_get_bom_formula_returns_first_of_get_bom_formulas(): void
    {
        $bomId = 'ENG-BOM-2';
        DB::connection('sqlsrv_ti')->table('BOM')->insert([
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-M', 'DATAAREAID' => 'PRO'],
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-N', 'DATAAREAID' => 'PRO'],
        ]);

        $service = new BomMaterialesService;

        $this->assertSame('TE-PD-ENF-M', $service->getBomFormula($bomId));
        $this->assertSame('TE-PD-ENF-M', $service->getBomFormulas($bomId)[0]);
    }

    public function test_get_bom_formulas_empty_for_blank_bom_id(): void
    {
        $service = new BomMaterialesService;

        $this->assertSame([], $service->getBomFormulas(null));
        $this->assertSame([], $service->getBomFormulas('   '));
        $this->assertNull($service->getBomFormula(''));
    }

    public function test_get_bom_formulas_with_fallback_resolves_bom_via_bom_version(): void
    {
        $bomId = 'ENG-BOM-R';
        DB::connection('sqlsrv_ti')->table('BOMVersion')->insert([
            'BomId' => $bomId,
            'ItemId' => 'TE-PD-ENF-SEED',
            'DATAAREAID' => 'PRO',
        ]);
        DB::connection('sqlsrv_ti')->table('BOM')->insert([
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-X', 'DATAAREAID' => 'PRO'],
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-Y', 'DATAAREAID' => 'PRO'],
        ]);

        $service = new BomMaterialesService;

        $this->assertSame(
            ['TE-PD-ENF-X', 'TE-PD-ENF-Y'],
            $service->getBomFormulasWithFallback(null, 'TE-PD-ENF-SEED')
        );
    }

    public function test_buscar_bom_formula_returns_distinct_ordered_and_filters_by_query(): void
    {
        DB::connection('sqlsrv_ti')->table('BOM')->insert([
            ['BOMID' => 'ENG A', 'ITEMID' => 'TE-PD-ENF-0025', 'DATAAREAID' => 'PRO'],
            ['BOMID' => 'ENG B', 'ITEMID' => 'TE-PD-ENF-0025', 'DATAAREAID' => 'PRO'],
            ['BOMID' => 'ENG A', 'ITEMID' => 'TE-PD-ENF-0032', 'DATAAREAID' => 'PRO'],
            ['BOMID' => 'ENG C', 'ITEMID' => 'TE-PD-ENF-9999', 'DATAAREAID' => 'PRO'],
            ['BOMID' => 'ENG D', 'ITEMID' => 'OTRO-ITEM', 'DATAAREAID' => 'PRO'],
        ]);

        $service = new BomMaterialesService;

        $this->assertSame(
            [['BomFormula' => 'TE-PD-ENF-0025'], ['BomFormula' => 'TE-PD-ENF-0032'], ['BomFormula' => 'TE-PD-ENF-9999']],
            $service->buscarBomFormula('')
        );

        $this->assertSame(
            [['BomFormula' => 'TE-PD-ENF-0025'], ['BomFormula' => 'TE-PD-ENF-0032']],
            $service->buscarBomFormula('00')
        );
    }

    public function test_buscar_lote_proveedor_returns_distinct_batches_with_stock_in_raw_material_warehouses(): void
    {
        Schema::connection('sqlsrv_ti')->create('InventSum', function (Blueprint $table) {
            $table->string('ITEMID', 80);
            $table->string('INVENTDIMID', 80);
            $table->string('DATAAREAID', 10);
            $table->decimal('PhysicalInvent', 18, 6)->default(0);
        });
        Schema::connection('sqlsrv_ti')->create('InventDim', function (Blueprint $table) {
            $table->string('INVENTDIMID', 80);
            $table->string('DATAAREAID', 10);
            $table->string('INVENTLOCATIONID', 20)->nullable();
            $table->string('INVENTBATCHID', 80)->nullable();
        });

        DB::connection('sqlsrv_ti')->table('InventDim')->insert([
            ['INVENTDIMID' => 'D1', 'DATAAREAID' => 'PRO', 'INVENTLOCATIONID' => 'A-MP', 'INVENTBATCHID' => '00061'],
            ['INVENTDIMID' => 'D2', 'DATAAREAID' => 'PRO', 'INVENTLOCATIONID' => 'A-MPBB', 'INVENTBATCHID' => '00061'],
            ['INVENTDIMID' => 'D3', 'DATAAREAID' => 'PRO', 'INVENTLOCATIONID' => 'A-MP', 'INVENTBATCHID' => '00074'],
            ['INVENTDIMID' => 'D4', 'DATAAREAID' => 'PRO', 'INVENTLOCATIONID' => 'A-MP', 'INVENTBATCHID' => ''],
            ['INVENTDIMID' => 'D5', 'DATAAREAID' => 'PRO', 'INVENTLOCATIONID' => 'OTRO', 'INVENTBATCHID' => '00099'],
            ['INVENTDIMID' => 'D6', 'DATAAREAID' => 'PRO', 'INVENTLOCATIONID' => 'A-MP', 'INVENTBATCHID' => '00050'],
        ]);
        DB::connection('sqlsrv_ti')->table('InventSum')->insert([
            ['ITEMID' => 'JULIO-URDIDO', 'INVENTDIMID' => 'D1', 'DATAAREAID' => 'PRO', 'PhysicalInvent' => 10],
            ['ITEMID' => 'JULIO-URDIDO', 'INVENTDIMID' => 'D2', 'DATAAREAID' => 'PRO', 'PhysicalInvent' => 5],
            ['ITEMID' => 'JULIO-URDIDO', 'INVENTDIMID' => 'D3', 'DATAAREAID' => 'PRO', 'PhysicalInvent' => 7],
            ['ITEMID' => 'JULIO-URDIDO', 'INVENTDIMID' => 'D4', 'DATAAREAID' => 'PRO', 'PhysicalInvent' => 7],
            ['ITEMID' => 'JULIO-URDIDO', 'INVENTDIMID' => 'D5', 'DATAAREAID' => 'PRO', 'PhysicalInvent' => 7],
            ['ITEMID' => 'JULIO-URDIDO', 'INVENTDIMID' => 'D6', 'DATAAREAID' => 'PRO', 'PhysicalInvent' => 0],
        ]);

        $service = new BomMaterialesService;

        $this->assertSame(
            [['LoteProveedor' => '00061'], ['LoteProveedor' => '00074']],
            $service->buscarLoteProveedor('')
        );

        $this->assertSame(
            [['LoteProveedor' => '00074']],
            $service->buscarLoteProveedor('74')
        );
    }

    public function test_get_bom_formulas_aggregated_merges_tepd_enf_from_all_eng_boms_same_bom_version_item(): void
    {
        $engA = 'ENG ALT-A';
        $engB = 'ENG ALT-B';
        $parent = 'TEJIDO-PADRE-1';

        DB::connection('sqlsrv_ti')->table('BOMTABLE')->insert([
            ['BOMID' => $engA, 'DATAAREAID' => 'PRO', 'ITEMGROUPID' => 'JUL-ENG'],
            ['BOMID' => $engB, 'DATAAREAID' => 'PRO', 'ITEMGROUPID' => 'JUL-ENG'],
        ]);
        DB::connection('sqlsrv_ti')->table('BOMVersion')->insert([
            ['BomId' => $engA, 'ItemId' => $parent, 'DATAAREAID' => 'PRO'],
            ['BomId' => $engB, 'ItemId' => $parent, 'DATAAREAID' => 'PRO'],
        ]);
        DB::connection('sqlsrv_ti')->table('BOM')->insert([
            ['BOMID' => $engA, 'ITEMID' => 'TE-PD-ENF-111', 'DATAAREAID' => 'PRO'],
            ['BOMID' => $engB, 'ITEMID' => 'TE-PD-ENF-222', 'DATAAREAID' => 'PRO'],
        ]);

        $service = new BomMaterialesService;

        $this->assertSame(
            ['TE-PD-ENF-111', 'TE-PD-ENF-222'],
            $service->getBomFormulasAggregatedForEngProgram($engA)
        );
        $this->assertSame(
            ['TE-PD-ENF-111', 'TE-PD-ENF-222'],
            $service->getBomFormulasWithFallback($engA, '')
        );
    }
}
