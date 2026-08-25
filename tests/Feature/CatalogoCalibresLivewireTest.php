<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Tejedores\CatalogoCalibres;
use App\Models\Tejedores\TejCatMatrizDesarrolladores;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CatalogoCalibresLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const MODULO = 'Catalogo Calibres';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        Cache::flush();

        Schema::connection('sqlsrv')->create('TejCatMatrizDesarrolladores', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Codigo', 20);
            $table->string('CodigoInterno', 20);
            $table->float('Divisor');
            $table->string('Nombre', 60);
            $table->boolean('Vigente')->default(true);
            $table->timestamps();
        });

        DB::connection('sqlsrv')->table('TejCatMatrizDesarrolladores')->insert([
            ['Codigo' => '10/1', 'CodigoInterno' => '10.1', 'Divisor' => 10, 'Nombre' => 'HILO 10/1', 'Vigente' => 1],
            ['Codigo' => '600/1T', 'CodigoInterno' => '600.1', 'Divisor' => 8.86, 'Nombre' => 'HILO 600 TENIDO', 'Vigente' => 1],
            ['Codigo' => '8/1', 'CodigoInterno' => '8.1', 'Divisor' => 8, 'Nombre' => 'HILO 8/1', 'Vigente' => 0],
        ]);
    }

    private function autenticar(array $acciones = ['acceso', 'crear', 'modificar']): void
    {
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo(self::MODULO, $acciones);
    }

    // ── Permisos ──────────────────────────────────────────────────────────

    public function test_sin_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(CatalogoCalibres::class)->assertForbidden();
    }

    public function test_sin_permiso_de_crear_no_se_abre_el_alta(): void
    {
        $this->autenticar(['acceso']);

        Livewire::test(CatalogoCalibres::class)->call('abrirAlta')->assertForbidden();
    }

    public function test_sin_permiso_de_modificar_no_se_cambia_la_vigencia(): void
    {
        $this->autenticar(['acceso']);

        $id = TejCatMatrizDesarrolladores::where('Codigo', '10/1')->value('Id');

        Livewire::test(CatalogoCalibres::class)
            ->set('seleccionado', (string) $id)
            ->call('alternarVigencia')
            ->assertForbidden();

        $this->assertTrue((bool) TejCatMatrizDesarrolladores::find($id)->Vigente);
    }

    // ── Listado ───────────────────────────────────────────────────────────

    public function test_lista_todos_los_calibres_y_filtra_por_vigencia(): void
    {
        $this->autenticar();

        Livewire::test(CatalogoCalibres::class)
            ->assertSee('HILO 10/1')
            ->assertSee('HILO 8/1')
            ->set('vigenciaFiltro', 'vigentes')
            ->assertSee('HILO 10/1')
            ->assertDontSee('HILO 8/1')
            ->set('vigenciaFiltro', 'baja')
            ->assertSee('HILO 8/1')
            ->assertDontSee('HILO 10/1');
    }

    public function test_busca_por_codigo_calibre_o_nombre(): void
    {
        $this->autenticar();

        Livewire::test(CatalogoCalibres::class)
            ->set('buscar', '600')
            ->assertSee('HILO 600 TENIDO')
            ->assertDontSee('HILO 10/1');
    }

    // ── Alta ──────────────────────────────────────────────────────────────

    public function test_crea_un_calibre(): void
    {
        $this->autenticar();

        Livewire::test(CatalogoCalibres::class)
            ->call('abrirAlta')
            ->set('form.Codigo', '450/1t')
            ->set('form.CodigoInterno', '450.1')
            ->set('form.Divisor', '11.81')
            ->set('form.Nombre', 'HILO 450 TENIDO')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('aviso', tipo: 'success');

        $nuevo = TejCatMatrizDesarrolladores::where('CodigoInterno', '450.1')->first();

        // El codigo es el ItemId de AX: se guarda en mayusculas o no empata el catalogo.
        $this->assertSame('450/1T', $nuevo->Codigo);
        $this->assertSame(11.81, round((float) $nuevo->Divisor, 2));
        $this->assertTrue((bool) $nuevo->Vigente);
    }

    public function test_no_admite_un_codigo_ax_repetido(): void
    {
        $this->autenticar();

        Livewire::test(CatalogoCalibres::class)
            ->call('abrirAlta')
            ->set('form.Codigo', '10/1')
            ->set('form.CodigoInterno', '10.1')
            ->set('form.Divisor', '10')
            ->set('form.Nombre', 'DUPLICADO')
            ->call('guardar')
            ->assertHasErrors('form.Codigo');

        $this->assertSame(1, TejCatMatrizDesarrolladores::where('Codigo', '10/1')->count());
    }

    /** Divisor 0 no es un dato: es el denominador con el que L.Mat calcula el peso. */
    public function test_no_admite_divisor_cero(): void
    {
        $this->autenticar();

        Livewire::test(CatalogoCalibres::class)
            ->call('abrirAlta')
            ->set('form.Codigo', 'X/1')
            ->set('form.CodigoInterno', 'X.1')
            ->set('form.Divisor', '0')
            ->set('form.Nombre', 'CERO')
            ->call('guardar')
            ->assertHasErrors('form.Divisor');
    }

    // ── Edicion ───────────────────────────────────────────────────────────

    public function test_edita_un_calibre_existente(): void
    {
        $this->autenticar();

        $id = TejCatMatrizDesarrolladores::where('Codigo', '600/1T')->value('Id');

        Livewire::test(CatalogoCalibres::class)
            ->call('abrirEdicion', (string) $id)
            ->assertSet('form.Codigo', '600/1T')
            ->assertSet('form.Divisor', '8.86')
            ->set('form.Divisor', '9.1')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(9.1, round((float) TejCatMatrizDesarrolladores::find($id)->Divisor, 2));
    }

    /** Editandose a si mismo, su propio codigo no cuenta como duplicado. */
    public function test_editar_sin_cambiar_el_codigo_no_choca_consigo_mismo(): void
    {
        $this->autenticar();

        $id = TejCatMatrizDesarrolladores::where('Codigo', '10/1')->value('Id');

        Livewire::test(CatalogoCalibres::class)
            ->call('abrirEdicion', (string) $id)
            ->set('form.Nombre', 'HILO 10/1 CORREGIDO')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('HILO 10/1 CORREGIDO', TejCatMatrizDesarrolladores::find($id)->Nombre);
    }

    // ── Vigencia ──────────────────────────────────────────────────────────

    public function test_da_de_baja_y_reactiva_sin_borrar_el_renglon(): void
    {
        $this->autenticar();

        $id = (string) TejCatMatrizDesarrolladores::where('Codigo', '10/1')->value('Id');

        $componente = Livewire::test(CatalogoCalibres::class)->set('seleccionado', $id);

        $componente->call('confirmarBaja')->assertSet('confirmandoBaja', true);
        $componente->call('alternarVigencia')->assertSet('confirmandoBaja', false);

        $this->assertFalse((bool) TejCatMatrizDesarrolladores::find($id)->Vigente);

        $componente->call('alternarVigencia');

        $this->assertTrue((bool) TejCatMatrizDesarrolladores::find($id)->Vigente);
        // Nunca se borra: las ordenes viejas seguirian apuntando a este codigo.
        $this->assertSame(3, TejCatMatrizDesarrolladores::count());
    }

    /**
     * La captura memoiza los calibres vigentes cinco minutos. Sin soltar esa llave, un
     * alta no aparece y una baja se sigue pudiendo elegir hasta que el cache expire.
     */
    public function test_cada_escritura_suelta_el_cache_de_la_captura(): void
    {
        $this->autenticar();

        $id = (string) TejCatMatrizDesarrolladores::where('Codigo', '10/1')->value('Id');

        Cache::put('desarrolladores.calibres.vigentes', 'viejo', now()->addMinutes(5));
        Livewire::test(CatalogoCalibres::class)->set('seleccionado', $id)->call('alternarVigencia');
        $this->assertFalse(Cache::has('desarrolladores.calibres.vigentes'), 'La baja debe soltar el cache.');

        Cache::put('desarrolladores.calibres.vigentes', 'viejo', now()->addMinutes(5));
        Livewire::test(CatalogoCalibres::class)
            ->call('abrirAlta')
            ->set('form.Codigo', 'Z/1')
            ->set('form.CodigoInterno', 'Z.1')
            ->set('form.Divisor', '3')
            ->set('form.Nombre', 'HILO Z')
            ->call('guardar');
        $this->assertFalse(Cache::has('desarrolladores.calibres.vigentes'), 'El alta debe soltar el cache.');
    }
}
