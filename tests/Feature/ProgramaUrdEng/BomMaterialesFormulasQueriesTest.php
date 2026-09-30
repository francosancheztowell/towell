<?php

namespace Tests\Feature\ProgramaUrdEng;

use App\Services\ProgramaUrdEng\BomMaterialesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PERF-08 (19-05): las fórmulas del programa de engomado se pedían con una consulta por cada
 * BOM hermano (mismo artículo en BOMVersion). Ahora es una sola con whereIn.
 */
class BomMaterialesFormulasQueriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.sqlsrv_ti', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlsrv_ti');

        $schema = Schema::connection('sqlsrv_ti');
        $schema->create('BOMVersion', function (Blueprint $t): void {
            $t->string('BomId');
            $t->string('ItemId');
            $t->string('DATAAREAID');
        });
        $schema->create('BOMTABLE', function (Blueprint $t): void {
            $t->string('BOMID');
            $t->string('DATAAREAID');
            $t->string('ITEMGROUPID');
            $t->integer('Vigente');
        });
        $schema->create('BOM', function (Blueprint $t): void {
            $t->string('BOMID');
            $t->string('ITEMID');
            $t->string('DATAAREAID');
        });

        $db = DB::connection('sqlsrv_ti');
        // Cinco listas ENG del mismo artículo; una no vigente y una de otra empresa no cuentan.
        foreach (['ENG 1', 'ENG 2', 'ENG 3', 'ENG 4', 'ENG 5'] as $i => $bom) {
            $db->table('BOMVersion')->insert(['BomId' => $bom, 'ItemId' => 'PROD-1', 'DATAAREAID' => 'PRO']);
            $db->table('BOMTABLE')->insert(['BOMID' => $bom, 'DATAAREAID' => 'PRO', 'ITEMGROUPID' => 'JUL-ENG', 'Vigente' => (int) ($i !== 4)]);
        }
        $db->table('BOMVersion')->insert(['BomId' => 'ENG 9', 'ItemId' => 'PROD-1', 'DATAAREAID' => 'OTR']);

        $formulas = [
            ['ENG 1', 'TE-PD-ENF3 '], ['ENG 1', 'TE-PD-ENF1'], ['ENG 1', 'HIL-1'],
            ['ENG 2', 'TE-PD-ENF1'], ['ENG 2', 'TE-PD-ENF2'],
            ['ENG 3', 'TE-PD-ENF4'], ['ENG 4 ', 'TE-PD-ENF5'],
            ['ENG 5', 'TE-PD-ENF9'], // no vigente
        ];
        foreach ($formulas as [$bom, $item]) {
            $db->table('BOM')->insert(['BOMID' => $bom, 'ITEMID' => $item, 'DATAAREAID' => 'PRO']);
        }
        $db->table('BOM')->insert(['BOMID' => 'ENG 2', 'ITEMID' => 'TE-PD-ENF8', 'DATAAREAID' => 'OTR']);
    }

    public function test_formulas_agregadas_en_una_consulta_con_el_mismo_resultado(): void
    {
        $db = DB::connection('sqlsrv_ti');
        $db->flushQueryLog();
        $db->enableQueryLog();

        $formulas = app(BomMaterialesService::class)->getBomFormulasAggregatedForEngProgram('ENG 1');

        // Antes 6: artículos del BOM, BOMs hermanos y una consulta de fórmulas por cada uno de los 4.
        // Ahora 3.
        $this->assertCount(3, $db->getQueryLog());
        $this->assertSame(['TE-PD-ENF1', 'TE-PD-ENF2', 'TE-PD-ENF3', 'TE-PD-ENF4', 'TE-PD-ENF5'], $formulas);
    }

    public function test_sin_hermanos_usa_las_formulas_del_propio_bom(): void
    {
        DB::connection('sqlsrv_ti')->table('BOM')->insert(['BOMID' => 'ENG X', 'ITEMID' => 'TE-PD-ENF7', 'DATAAREAID' => 'PRO']);

        $this->assertSame(['TE-PD-ENF7'], app(BomMaterialesService::class)->getBomFormulasAggregatedForEngProgram('ENG X'));
        $this->assertSame([], app(BomMaterialesService::class)->getBomFormulasAggregatedForEngProgram(' '));
    }
}
