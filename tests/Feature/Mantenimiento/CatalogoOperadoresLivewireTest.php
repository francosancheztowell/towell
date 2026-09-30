<?php

declare(strict_types=1);

namespace Tests\Feature\Mantenimiento;

use App\Livewire\Mantenimiento\CatalogoOperadores;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CatalogoOperadoresLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    /** idrol de Mantenimiento: el mismo que exigían las rutas module.permission:*,53. */
    private const IDROL = 53;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        $this->createTablaDbo('ManOperadoresMantenimiento', [
            'Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'CveEmpl' => 'TEXT', 'NomEmpl' => 'TEXT', 'Turno' => 'INTEGER', 'Depto' => 'TEXT', 'Telefono' => 'TEXT',
        ]);
    }

    /**
     * @param  array<int, string>  $acciones
     */
    private function autenticarCon(array $acciones): void
    {
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Mantenimiento', $acciones, null, self::IDROL);
    }

    private function operador(string $nombre, int $turno = 1, string $depto = 'Mantenimiento'): ManOperadoresMantenimiento
    {
        return ManOperadoresMantenimiento::create(['CveEmpl' => 'C-'.$nombre, 'NomEmpl' => $nombre, 'Turno' => $turno, 'Depto' => $depto]);
    }

    public function test_sin_permiso_de_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(CatalogoOperadores::class)->assertForbidden();
    }

    public function test_la_pantalla_monta_el_componente(): void
    {
        $this->autenticarCon(['acceso']);
        $this->operador('Mecánico Uno');

        $this->get(route('mantenimiento.operadores-mantenimiento.index'))
            ->assertOk()
            ->assertSeeLivewire(CatalogoOperadores::class)
            ->assertSee('Mecánico Uno');
    }

    public function test_busca_filtra_y_ordena(): void
    {
        $this->autenticarCon(['acceso']);
        $this->operador('Zeta', 2, 'Eléctrico');
        $this->operador('Alfa', 1);
        $this->operador('Beta', 4);

        Livewire::test(CatalogoOperadores::class)
            // Sin orden elegido: por nombre.
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 3 && $filas->first()->NomEmpl === 'Alfa')
            ->set('buscar', 'Zet')
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 1)
            ->set('buscar', '')
            ->set('turnoFiltro', '4')
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 1 && $filas->first()->NomEmpl === 'Beta')
            ->set('turnoFiltro', '')
            ->set('deptoFiltro', 'Eléctrico')
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 1)
            ->call('limpiarFiltros')
            ->call('ordenar', 'Turno')
            ->call('ordenar', 'Turno')
            ->assertSet('ordenDir', 'desc')
            ->assertViewHas('filas', fn ($filas) => $filas->first()->NomEmpl === 'Beta');
    }

    public function test_crea_edita_y_elimina_con_permiso(): void
    {
        $this->autenticarCon(['acceso', 'crear', 'modificar', 'eliminar']);

        $componente = Livewire::test(CatalogoOperadores::class)
            ->call('abrirAlta')
            ->set('form.CveEmpl', '3001')
            ->set('form.NomEmpl', 'Mecánico Uno')
            ->set('form.Turno', '2')
            ->set('form.Depto', 'Mantenimiento')
            ->set('form.Telefono', '  ')
            ->call('guardar')
            ->assertSet('editando', null)
            ->assertDispatched('aviso');

        $operador = ManOperadoresMantenimiento::firstOrFail();
        $this->assertSame(2, $operador->Turno);
        $this->assertNull($operador->Telefono);

        $componente->call('abrirEdicion', (string) $operador->Id)
            ->assertSet('form.NomEmpl', 'Mecánico Uno')
            ->set('form.NomEmpl', 'Mecánico Dos')
            ->call('guardar');
        $this->assertSame('Mecánico Dos', $operador->fresh()->NomEmpl);

        $componente->call('confirmarBorrado')
            ->assertSet('confirmandoBorrado', true)
            ->call('eliminar')
            ->assertSet('seleccionado', null);
        $this->assertSame(0, ManOperadoresMantenimiento::count());
    }

    public function test_valida_como_el_controller(): void
    {
        $this->autenticarCon(['acceso', 'crear']);

        Livewire::test(CatalogoOperadores::class)
            ->call('abrirAlta')
            ->set('form.Turno', '5')
            ->call('guardar')
            ->assertHasErrors(['form.CveEmpl', 'form.NomEmpl', 'form.Depto', 'form.Turno']);

        $this->assertSame(0, ManOperadoresMantenimiento::count());
    }

    public function test_cada_accion_exige_su_permiso(): void
    {
        $this->autenticarCon(['acceso']);
        $operador = $this->operador('Alfa');

        Livewire::test(CatalogoOperadores::class)->call('abrirAlta')->assertForbidden();
        Livewire::test(CatalogoOperadores::class)->call('abrirEdicion', (string) $operador->Id)->assertForbidden();
        Livewire::test(CatalogoOperadores::class)
            ->call('seleccionar', (string) $operador->Id)
            ->call('eliminar')
            ->assertForbidden();

        $this->assertSame(1, ManOperadoresMantenimiento::count());
    }

    /** Un solo camino de escritura: las rutas POST/PUT/DELETE del Blade anterior ya no existen. */
    public function test_ya_no_hay_rutas_de_escritura_de_operadores(): void
    {
        foreach (['store', 'update', 'destroy'] as $accion) {
            $this->assertFalse(Route::has('mantenimiento.operadores-mantenimiento.'.$accion), $accion);
        }
    }
}
