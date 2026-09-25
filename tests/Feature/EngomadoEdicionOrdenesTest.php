<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Engomado\EdicionOrdenes;
use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class EngomadoEdicionOrdenesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        config()->set('database.default', 'sqlsrv');
        DB::purge('sqlsrv');
        Schema::connection('sqlsrv')->create('EngProgramaEngomado', function (Blueprint $table): void {
            $table->increments('Id');
            foreach (['Folio', 'Cuenta', 'Fibra', 'RizoPie', 'MaquinaEng', 'Status'] as $column) {
                $table->string($column)->nullable();
            }
            $table->date('FechaProg')->nullable();
            $table->float('Metros')->nullable();
        });
        $user = new Usuario(['nombre' => 'Prueba', 'puesto' => 'Supervisor Engomado']);
        $user->idusuario = 10;
        $user->exists = true;
        $this->actingAs($user);
        $this->insert(1, 'ENG-001', '2026-01-31', 'Programado', 'West Point 2');
        $this->insert(2, 'ENG-002', '2026-09-19', 'Finalizado', 'West Point 3');
    }

    public function test_page_mounts_the_livewire_table(): void
    {
        $this->get('/engomado/reimpresion-engomado')
            ->assertOk()->assertSeeLivewire(EdicionOrdenes::class)->assertSee('Edición Engomado');
    }

    public function test_dates_are_sorted_chronologically_in_both_directions(): void
    {
        DB::table('EngProgramaEngomado')->where('Id', 2)->update(['MaquinaEng' => 'West Point 2']);
        Livewire::test(EdicionOrdenes::class)
            ->assertViewHas('boards', fn ($boards) => $boards[0]['filas']->pluck('Id')->all() === [2, 1])
            ->call('ordenar', 'FechaProg')
            ->assertViewHas('boards', fn ($boards) => $boards[0]['filas']->pluck('Id')->all() === [1, 2]);
    }

    public function test_url_filters_are_visible_and_clear_recovers_all_orders(): void
    {
        Livewire::withQueryParams(['folio' => 'ENG-002', 'maquina' => 'West Point 3', 'status' => 'Finalizado'])
            ->test(EdicionOrdenes::class)
            ->assertSet('folio', 'ENG-002')
            ->assertViewHas('total', 1)
            ->assertViewHas('boards', fn ($boards) => count($boards) === 2)
            ->call('limpiarFiltros')
            ->assertViewHas('total', 2);
    }

    public function test_removed_url_filters_are_ignored_and_status_still_works(): void
    {
        Livewire::withQueryParams(['q' => 'NO-MATCH', 'maquina' => 'NO-MATCH', 'status' => 'Finalizado', 'ordenPor' => 'invalid', 'ordenDir' => 'invalid'])
            ->test(EdicionOrdenes::class)
            ->assertSet('buscar', '')
            ->assertSet('ordenPor', 'FechaProg')
            ->assertViewHas('boards', fn ($boards) => collect($boards)->flatMap(fn ($b) => $b['filas']->pluck('Id'))->all() === [2]);
    }

    public function test_filtering_resets_a_hidden_selection(): void
    {
        Livewire::test(EdicionOrdenes::class)
            ->call('seleccionar', '1')->assertSet('seleccionado', '1')
            ->set('status', 'Finalizado')->assertSet('seleccionado', null)
            ->assertViewHas('boards', fn ($boards) => collect($boards)->flatMap(fn ($b) => $b['filas']->pluck('Id'))->all() === [2]);
    }

    public function test_pagination_is_bounded_and_clears_selection(): void
    {
        for ($id = 3; $id <= 32; $id++) {
            $this->insert($id, 'ENG-'.$id, '2026-09-19', 'Programado', 'West Point 2');
        }
        Livewire::test(EdicionOrdenes::class)
            ->assertSet('porPagina', 25)
            ->assertViewHas('boards', fn ($boards) => $boards[0]['filas']->count() === 25 && $boards[0]['filas']->total() === 31)
            ->call('seleccionar', '32')
            ->call('gotoPage', 2, 'machine_'.substr(md5('engomadolane_1'), 0, 10))->assertSet('seleccionado', null)
            ->assertViewHas('boards', fn ($boards) => $boards[0]['filas']->count() === 6 && $boards[1]['filas']->currentPage() === 1);
    }

    public function test_edit_keeps_the_return_route(): void
    {
        Livewire::test(EdicionOrdenes::class)->call('editar', '2')
            ->assertRedirect(route('engomado.editar.ordenes.programadas', ['orden_id' => 2, 'from' => 'reimpresion']));
    }

    public function test_qualification_rechecks_the_order_status(): void
    {
        $component = Livewire::test(EdicionOrdenes::class)->call('seleccionar', '2');
        DB::table('EngProgramaEngomado')->where('Id', 2)->update(['Status' => 'Programado']);
        $component->call('calificar')->assertHasErrors('accion')->assertNotDispatched('engomado-calificar-julios');
    }

    public function test_finalized_order_opens_existing_qualification_modal_and_pdf_links(): void
    {
        Livewire::test(EdicionOrdenes::class)->call('seleccionar', '2')
            ->assertSee('Excel simplificado')->call('calificar')
            ->assertDispatched('engomado-calificar-julios', folio: 'ENG-002');
    }

    public function test_zero_meters_are_displayed_and_database_text_is_escaped(): void
    {
        DB::table('EngProgramaEngomado')->where('Id', 1)->update(['Status' => '<script>alert(1)</script>']);
        Livewire::test(EdicionOrdenes::class)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertViewHas('boards', function ($boards): bool {
                $component = new EdicionOrdenes;
                $meters = collect($component->columnas())->firstWhere('campo', 'Metros');

                return $meters['valor']($boards[0]['filas']->first()) === '0';
            });
    }

    public function test_aliases_of_the_same_machine_share_a_table_and_unknown_machines_are_kept(): void
    {
        $this->insert(3, 'ENG-003', '2026-09-19', 'Programado', 'WestPoint 2');
        $this->insert(4, 'ENG-004', '2026-09-19', 'Programado', 'Especial');
        DB::table('EngProgramaEngomado')->where('Id', 4)->update(['MaquinaEng' => null]);
        Livewire::test(EdicionOrdenes::class)
            ->assertViewHas('boards', fn ($boards) => count($boards) === 3 && $boards[0]['filas']->total() === 2)
            ->assertSee('Sin máquina')->assertSee('ENG-004');
    }

    public function test_changing_a_filter_resets_each_machine_page(): void
    {
        $pageName = 'machine_'.substr(md5('engomadolane_1'), 0, 10);
        Livewire::test(EdicionOrdenes::class)->call('gotoPage', 2, $pageName)
            ->set('status', 'Finalizado')->assertSet('paginators.'.$pageName, 1);
    }

    public function test_urdido_uses_the_same_component_and_preserves_edit_and_print_routes(): void
    {
        Schema::create('UrdProgramaUrdido', function (Blueprint $table): void {
            $table->increments('Id');
            foreach (['Folio', 'Cuenta', 'Fibra', 'RizoPie', 'MaquinaId', 'Status'] as $column) {
                $table->string($column)->nullable();
            }
            $table->date('FechaProg')->nullable();
            $table->float('Metros')->nullable();
        });
        foreach (['Mc Coy 1', 'Mc Coy 2', 'Mc Coy 3', 'Karl Mayer'] as $index => $machine) {
            DB::table('UrdProgramaUrdido')->insert(['Id' => $index + 1, 'Folio' => 'URD-'.($index + 1), 'MaquinaId' => $machine, 'Status' => 'Finalizado']);
        }
        $this->get('/urdido/reimpresion-urdido')->assertOk()
            ->assertSeeLivewire(\App\Livewire\UrdEng\EdicionOrdenes::class);
        Livewire::test(\App\Livewire\UrdEng\EdicionOrdenes::class, ['module' => 'urdido'])
            ->assertViewHas('boards', fn ($boards) => count($boards) === 4)
            ->assertSee('Karl Mayer')->call('seleccionar', '1')
            ->assertSee(route('urdido.reimpresion.urdido.ventana.imprimir', ['orden_id' => 1]), false)
            ->assertDontSee('Calificar julios')->assertDontSee('Excel simplificado')
            ->call('editar')->assertRedirect(route('urdido.editar.ordenes.programadas', ['orden_id' => 1, 'from' => 'reimpresion']));
    }

    public function test_guest_cannot_hydrate_the_component(): void
    {
        auth()->logout();
        Livewire::test(EdicionOrdenes::class)->assertForbidden();
    }

    private function insert(int $id, string $folio, string $date, string $status, string $machine): void
    {
        DB::table('EngProgramaEngomado')->insert([
            'Id' => $id, 'Folio' => $folio, 'FechaProg' => $date, 'Status' => $status,
            'MaquinaEng' => $machine, 'Cuenta' => '2500', 'Fibra' => 'ALG', 'RizoPie' => 'Pie', 'Metros' => 0,
        ]);
    }
}
