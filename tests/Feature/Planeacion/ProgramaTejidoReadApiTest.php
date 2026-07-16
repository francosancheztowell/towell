<?php

declare(strict_types=1);

namespace Tests\Feature\Planeacion;

use App\Http\Controllers\Planeacion\ProgramaTejido\ProgramaTejidoController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

final class ProgramaTejidoReadApiTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=');
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');
        $this->createAuthTable();
        $this->createProgramaTable();
        $this->actingAs($this->createUsuario(['nombre' => 'Planeacion QA']));
    }

    public function test_index_filters_searches_and_returns_only_the_read_contract(): void
    {
        $this->insertPrograma([
            'EnProceso' => 1,
            'SalonTejidoId' => 'SMIT',
            'NoTelarId' => '12',
            'Posicion' => 2,
            'NoProduccion' => 'OT-200',
            'NombreProducto' => 'Toalla Hotel',
            'ItemId' => 'AX-200',
            'SaldoPedido' => 750,
        ]);
        $this->insertPrograma([
            'EnProceso' => 0,
            'SalonTejidoId' => 'SMIT',
            'NoTelarId' => '2',
            'NoProduccion' => 'OT-100',
            'NombreProducto' => 'Toalla Programada',
            'ItemId' => 'AX-100',
        ]);
        $this->insertPrograma([
            'EnProceso' => 1,
            'SalonTejidoId' => 'JACQUARD',
            'NoTelarId' => '5',
            'NoProduccion' => 'OT-300',
            'NombreProducto' => 'Tapete',
            'ItemId' => 'BX-300',
        ]);

        $url = route('planeacion.api.v1.programa-tejido.index').'?'.http_build_query([
            'search' => 'Toalla',
            'sort' => 'saldo_pedido',
            'direction' => 'desc',
            'per_page' => 10,
            'filters' => ['salon' => 'SMIT', 'en_proceso' => 1],
        ]);

        DB::connection('sqlsrv')->flushQueryLog();
        DB::connection('sqlsrv')->enableQueryLog();
        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('data.0.orden_produccion', 'OT-200')
            ->assertJsonPath('data.0.saldo_pedido', 750)
            ->assertJsonPath('data.0.en_proceso', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.Observaciones')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $programaQueries = collect(DB::connection('sqlsrv')->getQueryLog())
            ->filter(fn (array $query): bool => str_contains(strtolower((string) $query['query']), 'reqprogramatejido'));

        $this->assertLessThanOrEqual(2, $programaQueries->count(), 'La coleccion no debe introducir consultas N+1.');
    }

    public function test_invalid_collection_parameters_are_rejected_before_querying(): void
    {
        $this->getJson(route('planeacion.api.v1.programa-tejido.index', [
            'per_page' => 101,
            'sort' => 'columna_insegura',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'sort']);
    }

    public function test_default_telar_sort_preserves_the_operational_position(): void
    {
        $this->insertPrograma([
            'SalonTejidoId' => 'SMIT',
            'NoTelarId' => '12',
            'Posicion' => 2,
            'NoProduccion' => 'OT-SEGUNDA',
        ]);
        $this->insertPrograma([
            'SalonTejidoId' => 'SMIT',
            'NoTelarId' => '12',
            'Posicion' => 1,
            'NoProduccion' => 'OT-PRIMERA',
        ]);

        $response = $this->getJson(route('planeacion.api.v1.programa-tejido.index', [
            'filters' => ['salon' => 'SMIT', 'telar' => '12'],
        ]));

        $response->assertOk()
            ->assertJsonPath('data.0.orden_produccion', 'OT-PRIMERA')
            ->assertJsonPath('data.1.orden_produccion', 'OT-SEGUNDA');
    }

    public function test_react_preview_keeps_the_legacy_screen_as_fallback(): void
    {
        $request = Request::create('/planeacion/programa-tejido?react=1', 'GET');
        $view = app(ProgramaTejidoController::class)->index($request);

        $this->assertSame('modulos.programa-tejido.react-index', $view->name());
        $this->assertSame(
            route('catalogos.req-programa-tejido').'?react=1',
            route('catalogos.req-programa-tejido', ['react' => 1]),
        );
    }

    private function createProgramaTable(): void
    {
        Schema::connection('sqlsrv')->create('ReqProgramaTejido', function (Blueprint $table): void {
            $table->bigIncrements('Id');
            $table->boolean('EnProceso')->nullable();
            $table->string('SalonTejidoId', 10)->nullable();
            $table->string('NoTelarId', 10)->nullable();
            $table->integer('Posicion')->nullable();
            $table->string('NoProduccion', 15)->nullable();
            $table->string('NombreProducto', 100)->nullable();
            $table->string('ItemId', 20)->nullable();
            $table->string('InventSizeId', 10)->nullable();
            $table->string('FlogsId', 60)->nullable();
            $table->float('TotalPedido')->nullable();
            $table->float('Produccion')->nullable();
            $table->float('SaldoPedido')->nullable();
            $table->dateTime('FechaInicio')->nullable();
            $table->dateTime('FechaFinal')->nullable();
            $table->string('Prioridad', 150)->nullable();
        });
    }

    /** @param array<string, mixed> $attributes */
    private function insertPrograma(array $attributes): void
    {
        DB::connection('sqlsrv')->table('ReqProgramaTejido')->insert(array_merge([
            'EnProceso' => 0,
            'SalonTejidoId' => null,
            'NoTelarId' => null,
            'Posicion' => null,
            'NoProduccion' => null,
            'NombreProducto' => null,
            'ItemId' => null,
            'InventSizeId' => null,
            'FlogsId' => null,
            'TotalPedido' => 1000,
            'Produccion' => 250,
            'SaldoPedido' => 750,
            'FechaInicio' => '2026-07-16 08:00:00',
            'FechaFinal' => '2026-07-17 08:00:00',
            'Prioridad' => null,
        ], $attributes));
    }
}
