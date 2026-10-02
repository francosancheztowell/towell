<?php

declare(strict_types=1);

namespace Tests\Feature\Urdido;

use App\Livewire\Urdido\ListaMateriales;
use App\Models\Urdido\Urdbom;
use App\Services\ProgramaUrdEng\BomMaterialesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ListaMaterialesTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        Schema::connection('sqlsrv')->create('Urdbom', function (Blueprint $table) {
            $table->increments('Id');
            foreach (['Folio', 'Lmat', 'Calibre', 'Config', 'Color'] as $columna) {
                $table->string($columna)->nullable();
            }
            $table->decimal('Cantidad', 18, 2)->nullable();
            $table->decimal('Porcentaje', 18, 2)->nullable();
        });
    }

    public function test_sin_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(ListaMateriales::class)->assertForbidden();
    }

    public function test_alta_edicion_y_borrado(): void
    {
        $this->autenticarCon(['acceso', 'crear', 'modificar', 'eliminar']);

        $lw = Livewire::test(ListaMateriales::class)
            ->call('abrirAlta')
            ->set('form.Folio', '00123')
            ->set('form.Lmat', 'LM-01')
            ->set('form.Cantidad', '12.5')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('aviso');

        $fila = Urdbom::sole();
        $this->assertSame('00123', $fila->Folio, 'El folio debe conservar los ceros a la izquierda.');
        $this->assertNull($fila->Calibre, 'Un campo vacío se guarda como NULL.');

        $lw->call('abrirEdicion', (string) $fila->Id)
            ->assertSet('form.Lmat', 'LM-01')
            ->set('form.Porcentaje', '40')
            ->call('guardar')
            ->assertHasNoErrors();
        $this->assertSame('40.00', $fila->fresh()->Porcentaje);

        $lw->call('eliminar');
        $this->assertSame(0, Urdbom::count());
    }

    public function test_valida_requeridos_y_rangos(): void
    {
        $this->autenticarCon(['acceso', 'crear']);

        Livewire::test(ListaMateriales::class)
            ->call('abrirAlta')
            ->set('form.Porcentaje', '150')
            ->set('form.Cantidad', 'abc')
            ->call('guardar')
            ->assertHasErrors(['form.Folio', 'form.Lmat', 'form.Porcentaje', 'form.Cantidad']);

        $this->assertSame(0, Urdbom::count());
    }

    public function test_el_filtro_se_muestra_arriba(): void
    {
        $this->autenticarCon(['acceso']);

        Livewire::test(ListaMateriales::class)
            ->assertSee('Filtrar')
            ->assertDontSee('Buscar folio');
    }

    public function test_sin_permiso_de_crear_no_abre_el_alta(): void
    {
        $this->autenticarCon(['acceso']);

        Livewire::test(ListaMateriales::class)->call('abrirAlta')->assertForbidden();
    }

    public function test_crear_desde_urdido_copia_el_bom_de_ax_por_rango(): void
    {
        $this->autenticarCon(['acceso', 'crear']);

        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('BomId')->nullable();
            $table->date('FechaProg')->nullable();
        });
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            ['Folio' => '00010', 'BomId' => 'URD A ', 'FechaProg' => '2026-09-10'],
            ['Folio' => '00011', 'BomId' => 'URD SIN', 'FechaProg' => '2026-09-11'],
            ['Folio' => '00012', 'BomId' => 'URD A', 'FechaProg' => '2026-08-01'], // fuera de rango
            ['Folio' => '00013', 'BomId' => 'URD A', 'FechaProg' => '2026-09-12'], // ya importado
        ]);
        Urdbom::create(['Folio' => '00013', 'Lmat' => 'URD A']);

        $linea = fn ($item, $qty) => (object) ['BOMID' => 'URD A', 'ITEMID' => $item, 'BOMQTY' => $qty, 'CONFIGID' => 'Alg', 'INVENTCOLORID' => '1000'];
        $this->mock(BomMaterialesService::class)->shouldReceive('lineasBom')->once()
            ->withArgs(fn ($ids) => $ids === ['URD A', 'URD SIN'])
            ->andReturn(collect(['URD A' => collect([$linea('20/1', 3), $linea('30/1', 1)])]));

        Livewire::test(ListaMateriales::class)
            ->call('abrirImportar')
            ->set('importar.desde', '2026-09-01')
            ->set('importar.hasta', '2026-09-30')
            ->call('importarDesdeUrdido')
            ->assertHasNoErrors()
            ->assertSet('importando', false)
            ->assertDispatched('aviso', tipo: 'success', texto: 'Se crearon 1 folio(s), 2 material(es). 1 sin BOM en AX.');

        $filas = Urdbom::where('Folio', '00010')->orderBy('Calibre')->get();
        $this->assertSame(['20/1', '30/1'], $filas->pluck('Calibre')->all());
        $this->assertSame(['75.00', '25.00'], $filas->pluck('Porcentaje')->all());
        $this->assertSame('URD A', $filas[0]->Lmat);
        $this->assertSame(3, Urdbom::count());
    }

    public function test_folio_acepta_filtro_estilo_ax(): void
    {
        $this->autenticarCon(['acceso', 'crear']);

        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('BomId')->nullable();
            $table->date('FechaProg')->nullable();
        });
        foreach (['00001', '00002', '00003', '00010', '00015', '00020', '00021', '00500', '00501'] as $folio) {
            DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert(['Folio' => $folio, 'BomId' => 'URD A', 'FechaProg' => '2026-09-01']);
        }

        $this->mock(BomMaterialesService::class)->shouldReceive('lineasBom')
            ->andReturn(collect(['URD A' => collect([(object) ['BOMID' => 'URD A', 'ITEMID' => '20/1', 'BOMQTY' => 1, 'CONFIGID' => 'Alg', 'INVENTCOLORID' => '1000']])]));

        Livewire::test(ListaMateriales::class)
            ->call('abrirImportar')
            ->set('importar.folio', ' 1, 00003 ,10-20, 0050*, !00015 ')
            ->call('importarDesdeUrdido')
            ->assertHasNoErrors();

        $this->assertSame(
            ['00001', '00003', '00010', '00020', '00500', '00501'],
            Urdbom::orderBy('Folio')->pluck('Folio')->all(),
        );
    }

    public function test_la_tabla_agrupa_por_folio(): void
    {
        $this->autenticarCon(['acceso']);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'URD A', 'Calibre' => '12/1', 'Porcentaje' => 55.56]);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'URD A', 'Calibre' => '10/1', 'Porcentaje' => 44.44]);
        Urdbom::create(['Folio' => '00002', 'Lmat' => 'URD B', 'Calibre' => '20/1', 'Porcentaje' => 100]);

        Livewire::test(ListaMateriales::class)
            ->assertViewHas('filas', fn ($p) => $p->total() === 2)
            ->assertViewHas('hijos', fn ($h) => $h['00001']->pluck('Calibre')->all() === ['10/1', '12/1'])
            ->assertSee('2 materiales')
            ->assertDontSee('1 material') // un solo material: sin desplegable, va directo
            ->assertSee('20/1')
            ->assertSee('100.00')
            ->set('filtrosColumna.0', '2')
            ->assertViewHas('filas', fn ($p) => $p->pluck('Folio')->all() === ['00002']);
    }

    private function autenticarCon(array $acciones): void
    {
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Lista de Materiales Urd', $acciones, null, ListaMateriales::MODULO);
    }
}
