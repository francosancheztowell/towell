<?php

namespace Tests\Unit;

use App\Http\Controllers\Engomado\CapturaFormulas\EngProduccionFormulacionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class EngProduccionFormulacionControllerTest extends TestCase
{
    use UsesSqlsrvSqlite;

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

        $controller = new EngProduccionFormulacionController();
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

        $controller = new EngProduccionFormulacionController();
        $response = $controller->getFormulacionById(Request::create('/eng-formulacion/by-id', 'GET', ['id' => 1]));
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('00128', $payload['formulacion']['Folio']);
    }
}
