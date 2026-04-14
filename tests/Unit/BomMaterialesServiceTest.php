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
