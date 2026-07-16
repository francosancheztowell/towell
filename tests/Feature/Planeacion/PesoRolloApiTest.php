<?php

declare(strict_types=1);

namespace Tests\Feature\Planeacion;

use App\Models\Planeacion\Catalogos\ReqPesosRollosTejido;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

final class PesoRolloApiTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=');
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        Schema::connection('sqlsrv')->create('ReqPesosRolloTejido', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('ItemId', 20);
            $table->string('ItemName', 60);
            $table->string('InventSizeId', 10);
            $table->float('PesoRollo');
            $table->date('FechaCreacion')->nullable();
            $table->time('HoraCreacion')->nullable();
            $table->string('UsuarioCrea')->nullable();
            $table->date('FechaModificacion')->nullable();
            $table->time('HoraModificacion')->nullable();
            $table->string('UsuarioModifica')->nullable();
        });

        $this->actingAs($this->createUsuario(['nombre' => 'Planeacion QA']));
    }

    public function test_index_uses_standard_pagination_filters_and_resource_shape(): void
    {
        $this->createPeso('AX-200', 'Rollo grande', '90X180', 21.5);
        $this->createPeso('AX-100', 'Rollo chico', '50X90', 9.25);
        $this->createPeso('BX-300', 'Otro producto', '70X140', 15);

        $url = route('planeacion.api.v1.pesos-rollos.index').'?'.http_build_query([
            'search' => 'Rollo',
            'sort' => 'peso_rollo',
            'direction' => 'desc',
            'per_page' => 10,
            'filters' => ['peso_min' => 10],
        ]);

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('data.0.item_id', 'AX-200')
            ->assertJsonPath('data.0.peso_rollo', 21.5)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_crud_is_validated_and_audited_in_an_isolated_database(): void
    {
        $created = $this->postJson(route('planeacion.api.v1.pesos-rollos.store'), [
            'item_id' => 'AX-500',
            'item_name' => 'Rollo nuevo',
            'invent_size_id' => '80X160',
            'peso_rollo' => 18.75,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.item_id', 'AX-500')
            ->assertJsonPath('data.usuario_crea', 'Planeacion QA');

        $id = (int) $created->json('data.id');
        $updated = $this->putJson(route('planeacion.api.v1.pesos-rollos.update', ['pesoRollo' => $id]), [
            'item_id' => 'AX-500',
            'item_name' => 'Rollo actualizado',
            'invent_size_id' => '80X160',
            'peso_rollo' => 19,
        ]);

        $updated->assertOk()
            ->assertJsonPath('data.item_name', 'Rollo actualizado')
            ->assertJsonPath('data.usuario_modifica', 'Planeacion QA');

        $this->deleteJson(route('planeacion.api.v1.pesos-rollos.destroy', ['pesoRollo' => $id]))
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('ReqPesosRolloTejido', ['Id' => $id], 'sqlsrv');
    }

    public function test_duplicate_business_key_returns_laravel_validation_errors(): void
    {
        $this->createPeso('AX-100', 'Original', '50X90', 10);

        $response = $this->postJson(route('planeacion.api.v1.pesos-rollos.store'), [
            'item_id' => 'AX-100',
            'item_name' => 'Duplicado',
            'invent_size_id' => '50X90',
            'peso_rollo' => 12,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item_id']);
    }

    public function test_invalid_collection_parameters_do_not_reach_the_database_query(): void
    {
        $this->getJson(route('planeacion.api.v1.pesos-rollos.index', ['per_page' => 101]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    private function createPeso(string $itemId, string $name, string $size, float $weight): ReqPesosRollosTejido
    {
        return ReqPesosRollosTejido::create([
            'ItemId' => $itemId,
            'ItemName' => $name,
            'InventSizeId' => $size,
            'PesoRollo' => $weight,
        ]);
    }
}
