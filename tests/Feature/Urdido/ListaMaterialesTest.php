<?php

declare(strict_types=1);

namespace Tests\Feature\Urdido;

use App\Livewire\Urdido\ListaMateriales;
use App\Models\Urdido\Urdbom;
use App\Services\ProgramaUrdEng\BomMaterialesService;
use App\Services\Urdido\CumpKardexService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
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

        $this->kardex = collect();
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
            $table->decimal('cump', 18, 4)->default(0);
            $table->decimal('importe', 18, 4)->default(0);
        });

        Schema::connection('sqlsrv')->create('UrdProduccionUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('Fecha')->nullable();
            $table->float('KgNeto')->nullable();
            $table->decimal('ImporteMP', 18, 4)->nullable();
        });

        // El kardex vive en ProdTowel del .24: se sustituye la lectura, el cálculo es el real.
        $this->partialMock(CumpKardexService::class)->shouldReceive('kardex')->andReturnUsing(fn () => $this->kardex);
    }

    /** @var Collection<int, object> */
    private Collection $kardex;

    public function test_calcular_cump_toma_la_columna_de_la_maquina_y_el_periodo_del_folio(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);

        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('FechaProg')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->decimal('Kilos', 18, 2)->nullable();
        });
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            ['Folio' => '00001', 'MaquinaId' => 'Mc Coy 1', 'FechaProg' => '2026-08-15', 'Kilos' => 247.8],
            ['Folio' => '00002', 'MaquinaId' => 'Karl Mayer', 'FechaProg' => '2026-10-02', 'Kilos' => null],
            ['Folio' => '00003', 'MaquinaId' => 'Mc Coy 3', 'FechaProg' => '2026-09-01', 'Kilos' => 100],
        ]);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'A', 'Calibre' => '10/1', 'Config' => 'Alg-Open', 'Color' => '1000', 'Porcentaje' => 60]);
        Urdbom::create(['Folio' => '00002', 'Lmat' => 'B', 'Calibre' => '10/1', 'Config' => 'Alg-Open', 'Color' => '1000', 'Porcentaje' => 100]);
        Urdbom::create(['Folio' => '00003', 'Lmat' => 'C', 'Calibre' => '99/1', 'Config' => 'X', 'Color' => '1']); // sin kardex

        $k = fn ($mes, $mc1, $km) => (object) ['ITEMID' => '10/1 ', 'CONFIGID' => 'Alg-Open', 'INVENTCOLORID' => '1000',
            'MC1CU' => $mc1, 'MC2CU' => 0, 'MC3CU' => 0, 'KMCU' => $km, 'EXISTENCIACU' => 0, 'YEARDATE' => 2026, 'MONTHDATE' => $mes];
        $this->kardex = collect([$k(8, 55.6, 1), $k(9, 41.25, 2)]);

        Livewire::test(ListaMateriales::class)
            ->call('abrirCostos')
            ->assertSet('calculandoCostos', true)
            ->call('calcularCump')
            ->assertSet('calculandoCostos', false)
            ->assertDispatched('aviso', texto: 'Se actualizaron 2 material(es) y 0 julio(s) de producción.');

        $cump = Urdbom::orderBy('Folio')->pluck('cump', 'Folio')->all();
        $this->assertSame('55.6000', $cump['00001'], 'Mc Coy 1 en agosto → MC1CU de agosto.');
        $this->assertSame('2.0000', $cump['00002'], 'Karl Mayer en octubre → KMCU del último mes que no lo pasa (sep).');
        $this->assertSame('0.0000', $cump['00003'], 'Sin renglón en el kardex se queda como estaba.');
    }

    public function test_importe_mp_por_julio_como_el_excel(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);

        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('FechaProg')->nullable();
            $table->string('MaquinaId')->nullable();
        });
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert(['Folio' => '00001', 'MaquinaId' => 'Mc Coy 2', 'FechaProg' => '2026-09-10']);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'A', 'Calibre' => '12/1', 'Config' => 'Alg', 'Color' => '1000', 'Porcentaje' => 60]);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'A', 'Calibre' => '300/1', 'Config' => 'Pol', 'Color' => '1000', 'Porcentaje' => 40]);
        // Dos julios del mismo folio con distintos kilos: cada uno lleva su propio ImporteMP.
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
            ['Folio' => '00001', 'KgNeto' => 247.8],
            ['Folio' => '00001', 'KgNeto' => 100],
        ]);
        $k = fn ($item, $config, $mc2) => (object) ['ITEMID' => $item, 'CONFIGID' => $config, 'INVENTCOLORID' => '1000',
            'MC1CU' => 0, 'MC2CU' => $mc2, 'MC3CU' => 0, 'KMCU' => 0, 'EXISTENCIACU' => 0, 'YEARDATE' => 2026, 'MONTHDATE' => 9];
        $this->kardex = collect([$k('12/1', 'Alg', 55.6), $k('300/1', 'Pol', 45.87)]);

        Livewire::test(ListaMateriales::class)->call('abrirCostos')->call('calcularCump')
            ->assertDispatched('aviso', texto: 'Se actualizaron 2 material(es) y 2 julio(s) de producción.');

        // Excel fila 5: 55.6 × (60% × 247.8) + 45.87 × (40% × 247.8) = 8,266.61 + 4,546.63 = 12,813.24.
        $importeMp = DB::connection('sqlsrv')->table('UrdProduccionUrdido')->orderBy('Id')->pluck('ImporteMP')->map(fn ($v) => round((float) $v, 2))->all();
        $this->assertSame([12813.24, 5170.8], $importeMp);
        // Urdbom.importe: el material sobre los kilos netos de todo el folio (347.8): cuadra con Σ ImporteMP.
        $importe = Urdbom::orderBy('Calibre')->pluck('importe')->map(fn ($v) => round((float) $v, 2))->all();
        $this->assertSame([11602.61, 6381.43], $importe);
        $this->assertEqualsWithDelta(array_sum($importeMp), array_sum($importe), 0.01);
    }

    public function test_los_switches_eligen_el_mes_del_kardex(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);

        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('FechaProg')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->decimal('Kilos', 18, 2)->nullable();
        });
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            ['Folio' => '00001', 'MaquinaId' => 'Mc Coy 1', 'FechaProg' => '2026-08-15', 'Kilos' => 10],
            ['Folio' => '00002', 'MaquinaId' => 'Mc Coy 1', 'FechaProg' => '2026-10-02', 'Kilos' => 10],
        ]);
        foreach (['00001', '00002'] as $folio) {
            Urdbom::create(['Folio' => $folio, 'Lmat' => 'A', 'Calibre' => '10/1', 'Config' => 'Alg', 'Color' => '1000', 'Porcentaje' => 100]);
        }
        $k = fn ($mes, $mc1) => (object) ['ITEMID' => '10/1', 'CONFIGID' => 'Alg', 'INVENTCOLORID' => '1000',
            'MC1CU' => $mc1, 'MC2CU' => 0, 'MC3CU' => 0, 'KMCU' => 0, 'EXISTENCIACU' => 0, 'YEARDATE' => 2026, 'MONTHDATE' => $mes];
        $this->kardex = collect([$k(8, 10), $k(9, 20)]);
        $cump = fn () => Urdbom::orderBy('Folio')->pluck('cump')->all();

        // Solo mes exacto: octubre no está en el kardex → 00002 no se toca.
        Livewire::test(ListaMateriales::class)->call('abrirCostos')->set('costos.anterior', false)->call('calcularCump');
        $this->assertSame(['10.0000', '0.0000'], $cump());

        // Sin fecha programada: todos con el último mes del kardex (sep).
        Livewire::test(ListaMateriales::class)->call('abrirCostos')->set('costos.porFecha', false)->call('calcularCump');
        $this->assertSame(['20.0000', '20.0000'], $cump());
    }

    public function test_el_mes_es_el_de_produccion_no_el_programado(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);

        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('FechaProg')->nullable();
            $table->string('MaquinaId')->nullable();
        });
        // Caso 00296: programado el 27-mar, urdido el 2-abr. El kardex empieza en abril.
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert(['Folio' => '00296', 'MaquinaId' => 'Mc Coy 3', 'FechaProg' => '2026-03-27']);
        Urdbom::create(['Folio' => '00296', 'Lmat' => 'A', 'Calibre' => '12/1', 'Config' => 'Alg-Open', 'Color' => '1000', 'Porcentaje' => 100]);
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert(['Folio' => '00296', 'Fecha' => '2026-04-02', 'KgNeto' => 100]);
        $k = fn ($mes, $mc3) => (object) ['ITEMID' => '12/1', 'CONFIGID' => 'Alg-Open', 'INVENTCOLORID' => '1000',
            'MC1CU' => 0, 'MC2CU' => 0, 'MC3CU' => $mc3, 'KMCU' => 0, 'EXISTENCIACU' => 0, 'YEARDATE' => 2026, 'MONTHDATE' => $mes];
        $this->kardex = collect([$k(4, 44), $k(5, 46.77)]);

        Livewire::test(ListaMateriales::class)->call('abrirCostos')->call('calcularCump');

        $this->assertSame('44.0000', Urdbom::sole()->cump, 'Abril (producción), no marzo (programado) ni mayo.');
        $this->assertSame(4400.0, round((float) DB::connection('sqlsrv')->table('UrdProduccionUrdido')->value('ImporteMP'), 2));
    }

    public function test_maquina_en_cero_usa_el_costo_de_existencia_del_mes(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);

        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->date('FechaProg')->nullable();
            $table->string('MaquinaId')->nullable();
        });
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            ['Folio' => '00321', 'MaquinaId' => 'Mc Coy 2', 'FechaProg' => '2026-04-01'], // MC2 en 0
            ['Folio' => '00322', 'MaquinaId' => 'Mc Coy 1', 'FechaProg' => '2026-04-01'], // MC1 con costo
            ['Folio' => '00323', 'MaquinaId' => 'Karl Mayer', 'FechaProg' => '2026-04-01'], // KM NULL
        ]);
        foreach (['00321', '00322', '00323'] as $folio) {
            Urdbom::create(['Folio' => $folio, 'Lmat' => 'A', 'Calibre' => '10/1T', 'Config' => 'Poly-Fic-O', 'Color' => '91100', 'Porcentaje' => 100]);
        }
        $this->kardex = collect([(object) ['ITEMID' => '10/1T', 'CONFIGID' => 'Poly-Fic-O', 'INVENTCOLORID' => '91100',
            'MC1CU' => 86.08, 'MC2CU' => 0, 'MC3CU' => 0, 'KMCU' => null, 'EXISTENCIACU' => 88.67, 'YEARDATE' => 2026, 'MONTHDATE' => 4]]);
        $cump = fn () => Urdbom::orderBy('Folio')->pluck('cump')->all();

        Livewire::test(ListaMateriales::class)->call('abrirCostos')->assertSet('costos.existencia', true)->call('calcularCump');
        $this->assertSame(['88.6700', '86.0800', '88.6700'], $cump(), 'Por default: máquina en 0/NULL → EXISTENCIACU; con costo → el de la máquina.');

        Livewire::test(ListaMateriales::class)->call('abrirCostos')->set('costos.existencia', false)->call('calcularCump');
        $this->assertSame(['0.0000', '86.0800', '0.0000'], $cump(), 'Switch apagado: solo el costo de la máquina.');
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
            $table->string('MaquinaId')->nullable();
            $table->decimal('Kilos', 18, 2)->nullable();
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
            $table->string('MaquinaId')->nullable();
            $table->decimal('Kilos', 18, 2)->nullable();
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

    public function test_costos_sin_permiso_de_modificar_da_403(): void
    {
        $this->autenticarCon(['acceso']);

        Livewire::test(ListaMateriales::class)->call('abrirCostos')->assertForbidden();
        Livewire::test(ListaMateriales::class)->call('calcularCump')->assertForbidden();
    }

    public function test_maquina_y_claves_se_normalizan(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);
        // En producción existe "MC Coy 3" junto a "Mc Coy 3"; AX trae espacios al final.
        $this->programa([['Folio' => '00001', 'MaquinaId' => 'MC Coy 3', 'FechaProg' => '2026-09-01']]);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'A', 'Calibre' => ' 12/1', 'Config' => 'alg-open', 'Color' => '1000 ', 'Porcentaje' => 100]);
        $this->kardex = collect([$this->renglon('12/1  ', 'Alg-Open', '1000', 9, ['MC3CU' => 46.5])]);

        Livewire::test(ListaMateriales::class)->call('calcularCump');

        $this->assertSame('46.5000', Urdbom::sole()->cump);
    }

    public function test_sin_maquina_conocida_o_sin_kardex_respeta_el_cump_capturado(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);
        $this->programa([
            ['Folio' => '00001', 'MaquinaId' => 'Otra', 'FechaProg' => '2026-09-01'],
            ['Folio' => '00002', 'MaquinaId' => 'Mc Coy 1', 'FechaProg' => '2026-09-01'],
        ]);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'A', 'Calibre' => '12/1', 'Config' => 'Alg', 'Color' => '1000', 'Porcentaje' => 100, 'cump' => 50]);
        Urdbom::create(['Folio' => '00002', 'Lmat' => 'A', 'Calibre' => 'NO/EXISTE', 'Config' => 'X', 'Color' => '1', 'Porcentaje' => 100, 'cump' => 33]);
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
            ['Folio' => '00001', 'Fecha' => '2026-09-02', 'KgNeto' => 10],
            ['Folio' => '00002', 'Fecha' => '2026-09-02', 'KgNeto' => 10],
        ]);
        $this->kardex = collect([$this->renglon('12/1', 'Alg', '1000', 9, ['MC1CU' => 99])]);

        Livewire::test(ListaMateriales::class)->call('calcularCump');

        // El cump capturado a mano se conserva, y el importe se calcula con él.
        $this->assertSame(['50.0000', '33.0000'], Urdbom::orderBy('Folio')->pluck('cump')->all());
        $this->assertSame([500.0, 330.0], $this->importesMp());
    }

    public function test_sin_produccion_usa_fecha_prog_y_kgneto_nulo_da_cero(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);
        $this->programa([
            ['Folio' => '00001', 'MaquinaId' => 'Mc Coy 1', 'FechaProg' => '2026-08-20'], // sin julios todavía
            ['Folio' => '00002', 'MaquinaId' => 'Mc Coy 1', 'FechaProg' => '2026-08-20'],
        ]);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'A', 'Calibre' => '12/1', 'Config' => 'Alg', 'Color' => '1000', 'Porcentaje' => 100]);
        Urdbom::create(['Folio' => '00002', 'Lmat' => 'A', 'Calibre' => '12/1', 'Config' => 'Alg', 'Color' => '1000', 'Porcentaje' => 100]);
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert([
            ['Folio' => '00002', 'Fecha' => '2026-09-01', 'KgNeto' => null],
            ['Folio' => '00002', 'Fecha' => '2026-09-01', 'KgNeto' => 10],
        ]);
        $this->kardex = collect([
            $this->renglon('12/1', 'Alg', '1000', 8, ['MC1CU' => 40]),
            $this->renglon('12/1', 'Alg', '1000', 9, ['MC1CU' => 45]),
        ]);

        Livewire::test(ListaMateriales::class)->call('calcularCump');

        $this->assertSame(['40.0000', '45.0000'], Urdbom::orderBy('Folio')->pluck('cump')->all(), '00001 por FechaProg (ago); 00002 por producción (sep).');
        $this->assertSame([0.0, 450.0], $this->importesMp(), 'KgNeto NULL → ImporteMP 0, sin romper.');
        $this->assertSame(['0.0000', '450.0000'], Urdbom::orderBy('Folio')->pluck('importe')->all());
    }

    public function test_recalcular_sin_cambios_no_escribe_y_avisa(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);
        $this->programa([['Folio' => '00001', 'MaquinaId' => 'Mc Coy 1', 'FechaProg' => '2026-09-01']]);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'A', 'Calibre' => '12/1', 'Config' => 'Alg', 'Color' => '1000', 'Porcentaje' => 100]);
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert(['Folio' => '00001', 'Fecha' => '2026-09-01', 'KgNeto' => 10]);
        $this->kardex = collect([$this->renglon('12/1', 'Alg', '1000', 9, ['MC1CU' => 45])]);

        Livewire::test(ListaMateriales::class)->call('calcularCump')
            ->assertDispatched('aviso', texto: 'Se actualizaron 1 material(es) y 1 julio(s) de producción.');

        DB::connection('sqlsrv')->enableQueryLog();
        Livewire::test(ListaMateriales::class)->call('calcularCump')
            ->assertDispatched('aviso', texto: 'Los costos ya estaban al día.');
        $updates = collect(DB::connection('sqlsrv')->getQueryLog())->filter(fn ($q) => str_starts_with(strtoupper($q['query']), 'UPDATE'));
        $this->assertCount(0, $updates);
    }

    public function test_muchos_julios_se_actualizan_en_lotes(): void
    {
        $this->autenticarCon(['acceso', 'modificar']);
        $this->programa([['Folio' => '00001', 'MaquinaId' => 'Mc Coy 1', 'FechaProg' => '2026-09-01']]);
        Urdbom::create(['Folio' => '00001', 'Lmat' => 'A', 'Calibre' => '12/1', 'Config' => 'Alg', 'Color' => '1000', 'Porcentaje' => 100]);
        // 650 julios > lote de 300: tres UPDATE, y todos deben quedar con su propio importe.
        $julios = array_map(fn ($i) => ['Folio' => '00001', 'Fecha' => '2026-09-01', 'KgNeto' => $i], range(1, 650));
        foreach (array_chunk($julios, 100) as $lote) {
            DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert($lote);
        }
        $this->kardex = collect([$this->renglon('12/1', 'Alg', '1000', 9, ['MC1CU' => 2])]);

        Livewire::test(ListaMateriales::class)->call('calcularCump');

        $this->assertSame(array_map(fn ($i) => (float) $i * 2, range(1, 650)), $this->importesMp());
    }

    public function test_crear_desde_urdido_ya_trae_costos(): void
    {
        $this->autenticarCon(['acceso', 'crear']);
        $this->programa([['Folio' => '00010', 'MaquinaId' => 'Karl Mayer', 'FechaProg' => '2026-09-10', 'BomId' => 'URD A']]);
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert(['Folio' => '00010', 'Fecha' => '2026-09-11', 'KgNeto' => 100]);
        $this->mock(BomMaterialesService::class)->shouldReceive('lineasBom')
            ->andReturn(collect(['URD A' => collect([
                (object) ['BOMID' => 'URD A', 'ITEMID' => '16/1', 'BOMQTY' => 1, 'CONFIGID' => 'Alg', 'INVENTCOLORID' => '1000'],
                (object) ['BOMID' => 'URD A', 'ITEMID' => '370/1', 'BOMQTY' => 1, 'CONFIGID' => 'VOL', 'INVENTCOLORID' => '1000'],
            ])]));
        $this->kardex = collect([
            $this->renglon('16/1', 'Alg', '1000', 9, ['KMCU' => 48.5]),
            $this->renglon('370/1', 'VOL', '1000', 9, ['KMCU' => 70.8]),
        ]);

        Livewire::test(ListaMateriales::class)->call('abrirImportar')->set('importar.folio', '10')->call('importarDesdeUrdido');

        $this->assertSame(['48.5000', '70.8000'], Urdbom::orderBy('Calibre')->pluck('cump')->all());
        // Dos composiciones al 50%: (48.5 + 70.8) / 2 × 100 kg.
        $this->assertSame([5965.0], $this->importesMp());
    }

    /** @param array<int, array<string, mixed>> $filas */
    private function programa(array $filas): void
    {
        Schema::connection('sqlsrv')->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('BomId')->nullable();
            $table->date('FechaProg')->nullable();
            $table->string('MaquinaId')->nullable();
        });
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert($filas);
    }

    /** @param array<string, float> $costos */
    private function renglon(string $item, string $config, string $color, int $mes, array $costos): object
    {
        return (object) ($costos + [
            'ITEMID' => $item, 'CONFIGID' => $config, 'INVENTCOLORID' => $color,
            'MC1CU' => 0, 'MC2CU' => 0, 'MC3CU' => 0, 'KMCU' => 0, 'EXISTENCIACU' => 0, 'YEARDATE' => 2026, 'MONTHDATE' => $mes,
        ]);
    }

    /** @return array<int, float> */
    private function importesMp(): array
    {
        return DB::connection('sqlsrv')->table('UrdProduccionUrdido')->orderBy('Id')->pluck('ImporteMP')
            ->map(fn ($v) => round((float) $v, 2))->all();
    }

    private function autenticarCon(array $acciones): void
    {
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Lista de Materiales Urd', $acciones, null, ListaMateriales::MODULO);
    }
}
