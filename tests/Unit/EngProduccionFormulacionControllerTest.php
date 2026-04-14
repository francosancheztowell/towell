<?php

namespace Tests\Unit;

use App\Http\Controllers\Engomado\CapturaFormulas\EngProduccionFormulacionController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class EngProduccionFormulacionControllerTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private ?string $tiSqlitePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        $schema = Schema::connection('sqlsrv');

        $schema->create('EngProduccionFormulacion', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('fecha')->nullable();
            $table->string('Hora')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('Tipo')->nullable();
            $table->string('CveEmpl')->nullable();
            $table->string('NomEmpl')->nullable();
            $table->string('Olla')->nullable();
            $table->string('Formula')->nullable();
            $table->float('Kilos')->nullable();
            $table->float('Litros')->nullable();
            $table->string('ProdId')->nullable();
            $table->float('TiempoCocinado')->nullable();
            $table->float('Solidos')->nullable();
            $table->float('Viscocidad')->nullable();
            $table->string('Status')->nullable();
            $table->text('obs_calidad')->nullable();
            $table->integer('OkTiempo')->nullable();
            $table->integer('OkViscosidad')->nullable();
            $table->integer('OkSolidos')->nullable();
            $table->integer('AX')->nullable();
        });

        $schema->create('EngFormulacionLine', function (Blueprint $table) {
            $table->increments('Id');
            $table->unsignedInteger('EngProduccionFormulacionId')->nullable();
            $table->string('Folio')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('ItemName')->nullable();
            $table->string('ConfigId')->nullable();
            $table->float('ConsumoUnit')->nullable();
            $table->float('ConsumoTotal')->nullable();
            $table->string('Unidad')->nullable();
            $table->string('InventLocation')->nullable();
        });

        $schema->create('SYSUsuario', function (Blueprint $table) {
            $table->increments('idusuario');
            $table->string('nombre')->nullable();
        });

        $schema->create('URDCatalogoMaquinas', function (Blueprint $table) {
            $table->string('MaquinaId')->primary();
            $table->string('Nombre')->nullable();
            $table->string('Departamento')->nullable();
        });

        $schema->create('EngProgramaEngomado', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->string('RizoPie')->nullable();
            $table->string('BomFormula')->nullable();
            $table->string('Status')->nullable();
        });

        $tmp = tempnam(sys_get_temp_dir(), 'towell_eng_form_ti_');
        if ($tmp === false) {
            $this->markTestSkipped('No se pudo crear archivo temporal para sqlsrv_ti');
        }
        $this->tiSqlitePath = $tmp;
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
    }

    protected function tearDown(): void
    {
        if ($this->tiSqlitePath !== null && is_file($this->tiSqlitePath)) {
            @unlink($this->tiSqlitePath);
        }
        parent::tearDown();
    }

    public function test_index_resolves_blank_folio_from_production_context(): void
    {
        \DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Folio' => '00128',
            'Cuenta' => '3524',
            'Calibre' => 12,
            'RizoPie' => 'Pie',
            'BomFormula' => 'FORM-01',
            'Status' => 'En Proceso',
        ]);

        \DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert([
            'Folio' => null,
            'ProdId' => '00128',
            'Cuenta' => '3524',
            'Calibre' => 12,
            'Status' => 'Creado',
        ]);

        $controller = $this->app->make(EngProduccionFormulacionController::class);
        $view = $controller->index(Request::create('/engomado/capturadeformula', 'GET', ['folio' => '00128']));
        $items = $view->getData()['items'];

        $this->assertCount(1, $items);
        $this->assertSame('00128', $items->first()->folio_resuelto);
    }

    public function test_get_formulacion_by_id_returns_resolved_folio_when_original_is_blank(): void
    {
        \DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert([
            'Folio' => null,
            'ProdId' => '00128',
            'Cuenta' => '3524',
            'Calibre' => 12,
            'Status' => 'Creado',
        ]);

        $controller = $this->app->make(EngProduccionFormulacionController::class);
        $response = $controller->getFormulacionById(Request::create('/eng-formulacion/by-id', 'GET', ['id' => 1]));
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('00128', $payload['formulacion']['Folio']);
    }

    public function test_get_formulas_disponibles_returns_all_ax_formulas_for_bom(): void
    {
        $bomId = 'BOM-ENG-X';
        DB::connection('sqlsrv_ti')->table('BOM')->insert([
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-Z', 'DATAAREAID' => 'PRO'],
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-Y', 'DATAAREAID' => 'PRO'],
        ]);

        $controller = $this->app->make(EngProduccionFormulacionController::class);
        $response = $controller->getFormulasDisponibles(Request::create('/eng-formulacion/formulas-disponibles', 'GET', ['bomId' => $bomId]));
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame(['TE-PD-ENF-Y', 'TE-PD-ENF-Z'], $payload['formulas']);
    }

    public function test_get_formulas_disponibles_resolves_bom_from_formula_via_bom_version(): void
    {
        $bomId = 'BOM-VIA-FORM';
        DB::connection('sqlsrv_ti')->table('BOMVersion')->insert([
            'BomId' => $bomId,
            'ItemId' => 'TE-PD-ENF-ROOT',
            'DATAAREAID' => 'PRO',
        ]);
        DB::connection('sqlsrv_ti')->table('BOM')->insert([
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-1', 'DATAAREAID' => 'PRO'],
            ['BOMID' => $bomId, 'ITEMID' => 'TE-PD-ENF-2', 'DATAAREAID' => 'PRO'],
        ]);

        $controller = $this->app->make(EngProduccionFormulacionController::class);
        $response = $controller->getFormulasDisponibles(Request::create('/eng-formulacion/formulas-disponibles', 'GET', [
            'formula' => 'TE-PD-ENF-ROOT',
        ]));
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame(['TE-PD-ENF-1', 'TE-PD-ENF-2'], $payload['formulas']);
    }

    public function test_update_segundo_registro_no_sincroniza_bom_formula(): void
    {
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Folio' => 'F-BOM-1',
            'Cuenta' => 'C1',
            'Calibre' => 10,
            'RizoPie' => 'Pie',
            'BomFormula' => 'TE-PD-ENF-ORIG',
            'Status' => 'En Proceso',
        ]);

        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert([
            ['Folio' => 'F-BOM-1', 'Formula' => 'TE-PD-ENF-ORIG', 'Litros' => 10, 'Status' => 'Creado', 'AX' => 0],
            ['Folio' => 'F-BOM-1', 'Formula' => 'TE-PD-ENF-ORIG', 'Litros' => 10, 'Status' => 'Creado', 'AX' => 0],
        ]);

        $request = Request::create('/eng-formulacion/F-BOM-1', 'PUT', [
            'Formula' => 'TE-PD-ENF-OTRA',
            'formulacion_id' => 2,
        ]);
        $request->headers->set('Accept', 'application/json');

        $controller = $this->app->make(EngProduccionFormulacionController::class);
        $response = $controller->update($request, 'F-BOM-1');

        $this->assertTrue($response->getData(true)['success'] ?? false);
        $this->assertSame('TE-PD-ENF-OTRA', DB::connection('sqlsrv')->table('EngProduccionFormulacion')->where('Id', 2)->value('Formula'));
        $this->assertSame('TE-PD-ENF-ORIG', DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Folio', 'F-BOM-1')->value('BomFormula'));
    }

    public function test_update_primer_registro_sincroniza_bom_formula(): void
    {
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Folio' => 'F-BOM-2',
            'Cuenta' => 'C1',
            'Calibre' => 10,
            'RizoPie' => 'Pie',
            'BomFormula' => 'TE-PD-ENF-OLD',
            'Status' => 'En Proceso',
        ]);

        DB::connection('sqlsrv')->table('EngProduccionFormulacion')->insert([
            ['Folio' => 'F-BOM-2', 'Formula' => 'TE-PD-ENF-OLD', 'Litros' => 10, 'Status' => 'Creado', 'AX' => 0],
            ['Folio' => 'F-BOM-2', 'Formula' => 'TE-PD-ENF-OLD', 'Litros' => 10, 'Status' => 'Creado', 'AX' => 0],
        ]);

        $request = Request::create('/eng-formulacion/F-BOM-2', 'PUT', [
            'Formula' => 'TE-PD-ENF-NUEVA',
            'formulacion_id' => 1,
        ]);
        $request->headers->set('Accept', 'application/json');

        $controller = $this->app->make(EngProduccionFormulacionController::class);
        $response = $controller->update($request, 'F-BOM-2');

        $this->assertTrue($response->getData(true)['success'] ?? false);
        $this->assertSame('TE-PD-ENF-NUEVA', DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Folio', 'F-BOM-2')->value('BomFormula'));
    }
}
