<?php

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ProgramaTejidoColumnPresetTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');

        Schema::connection('sqlsrv')->create('ProgramaTejidoColumnPresets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('usuario_id');
            $table->string('tabla', 50);
            $table->string('nombre', 100);
            $table->text('columnas');
            $table->boolean('es_default')->default(false);
            $table->timestamps();
        });

        $this->createAuthTable();
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('ProgramaTejidoColumnPresets');
        parent::tearDown();
    }

    private function usuario(): Usuario
    {
        $u = new Usuario(['idusuario' => 99, 'nombre' => 'Test', 'contrasenia' => 'x', 'numero_empleado' => '99', 'area' => 'X']);
        $u->idusuario = 99;
        return $u;
    }

    public function test_listar_presets_devuelve_json_vacio_cuando_no_hay_presets(): void
    {
        $response = $this->actingAs($this->usuario())
            ->getJson(route('programa-tejido.column-presets.index'));

        $response->assertOk();
        $response->assertJson(['presets' => []]);
    }

    public function test_crear_preset_guarda_en_db(): void
    {
        $response = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.column-presets.store'), [
                'nombre'  => 'Mi vista producción',
                'columnas' => ['visible' => ['SalonTejidoId', 'NoTelarId'], 'pinned' => ['NoTelarId']],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('preset.nombre', 'Mi vista producción');

        $this->assertDatabaseHas('ProgramaTejidoColumnPresets', [
            'usuario_id' => 99,
            'nombre'     => 'Mi vista producción',
            'tabla'      => 'programa-tejido',
        ], 'sqlsrv');
    }

    public function test_crear_preset_valida_nombre_requerido(): void
    {
        $response = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.column-presets.store'), [
                'columnas' => ['visible' => []],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['nombre']);
    }

    public function test_eliminar_preset_propio_funciona(): void
    {
        $create = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.column-presets.store'), [
                'nombre'   => 'Para borrar',
                'columnas' => ['visible' => [], 'pinned' => []],
            ]);
        $presetId = $create->json('preset.id');

        $delete = $this->actingAs($this->usuario())
            ->deleteJson(route('programa-tejido.column-presets.destroy', $presetId));

        $delete->assertOk();
        $this->assertDatabaseMissing('ProgramaTejidoColumnPresets', ['id' => $presetId], 'sqlsrv');
    }

    public function test_no_puede_eliminar_preset_de_otro_usuario(): void
    {
        $create = $this->actingAs($this->usuario())
            ->postJson(route('programa-tejido.column-presets.store'), [
                'nombre'   => 'Ajeno',
                'columnas' => ['visible' => [], 'pinned' => []],
            ]);
        $presetId = $create->json('preset.id');

        $otro = new Usuario(['idusuario' => 88, 'nombre' => 'Otro', 'contrasenia' => 'x', 'numero_empleado' => '88', 'area' => 'X']);
        $otro->idusuario = 88;

        $delete = $this->actingAs($otro)
            ->deleteJson(route('programa-tejido.column-presets.destroy', $presetId));

        $delete->assertForbidden();
        $this->assertDatabaseHas('ProgramaTejidoColumnPresets', ['id' => $presetId], 'sqlsrv');
    }
}
