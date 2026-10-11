<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Livewire\InventarioTrama\ConsultarRequerimiento;
use App\Livewire\Mantenimiento\CatalogoFallas;
use App\Livewire\Mantenimiento\CatalogoOperadores;
use App\Models\Mantenimiento\CatParosFallas;
use App\Models\Mantenimiento\ManOperadoresMantenimiento;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Rama claude/mejora-flux-livewire-operacion: catálogos de Mantenimiento con <dialog> nativo,
 * flux:input/flux:select y borrado confirmado con notify.confirm (ya no pasa por
 * confirmarBorrado); Inventario trama con flux:button + notify.confirm en lugar de wire:confirm.
 */
class FluxLivewireOperacionTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
    }

    // ── Catálogo de fallas ───────────────────────────────────────────────

    private function prepararFallas(array $acciones): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->create('CatParosFallas', function (Blueprint $table) {
            $table->increments('Id');
            foreach (['TipoFallaId', 'Departamento', 'Falla', 'Descripcion', 'Abreviado', 'Seccion'] as $col) {
                $table->string($col)->nullable();
            }
        });
        $schema->create('CatTipoFalla', function (Blueprint $table) {
            $table->string('TipoFallaId')->primary();
        });
        DB::connection('sqlsrv')->table('CatTipoFalla')->insert([['TipoFallaId' => 'ELECTRICO'], ['TipoFallaId' => 'MECANICO']]);

        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Catalogo de Fallas', $acciones);
    }

    /** El botón Eliminar confirma en el cliente y llama directo a eliminar(); sin paso por confirmarBorrado. */
    public function test_fallas_el_boton_eliminar_confirma_con_notify_y_llama_a_eliminar(): void
    {
        $this->prepararFallas(['acceso', 'eliminar']);
        $falla = CatParosFallas::create(['TipoFallaId' => 'MECANICO', 'Departamento' => 'Tejido', 'Falla' => 'Rotura']);

        $html = Livewire::test(CatalogoFallas::class)->call('seleccionar', (string) $falla->Id)->html();

        $this->assertStringNotContainsString('wire:click="confirmarBorrado"', $html);
        $this->assertMatchesRegularExpression('/x-on:click="notify\.confirm\(\{[^"]*\}\)\.then\(ok (=&gt;|=>) ok (&amp;&amp;|&&) \$wire\.eliminar\(\)\)"/', $html);
    }

    public function test_fallas_eliminar_directo_borra_la_seleccion_sin_confirmar_en_servidor(): void
    {
        $this->prepararFallas(['acceso', 'eliminar']);
        $falla = CatParosFallas::create(['TipoFallaId' => 'MECANICO', 'Departamento' => 'Tejido', 'Falla' => 'Rotura']);

        Livewire::test(CatalogoFallas::class)
            ->call('seleccionar', (string) $falla->Id)
            ->call('eliminar')
            ->assertSet('seleccionado', null)
            ->assertDispatched('aviso', tipo: 'success');

        $this->assertSame(0, CatParosFallas::count());
    }

    public function test_fallas_eliminar_sin_seleccion_no_borra_nada(): void
    {
        $this->prepararFallas(['acceso', 'eliminar']);
        CatParosFallas::create(['TipoFallaId' => 'MECANICO', 'Departamento' => 'Tejido', 'Falla' => 'Rotura']);

        Livewire::test(CatalogoFallas::class)->call('eliminar')->assertNotDispatched('aviso');

        $this->assertSame(1, CatParosFallas::count());
    }

    public function test_fallas_el_alta_es_un_dialog_con_errores_de_flux_y_cerrar_lo_quita(): void
    {
        $this->prepararFallas(['acceso', 'crear']);

        $componente = Livewire::test(CatalogoFallas::class)
            ->call('abrirAlta')
            ->assertSeeHtml('wire:key="falla-"')
            ->assertSee('Nueva falla')
            ->call('guardar')
            ->assertHasErrors(['form.TipoFallaId', 'form.Departamento', 'form.Falla'])
            // flux:input pinta el error del wire:model; antes era un @error a mano por campo.
            ->assertSeeHtml('data-flux-error');

        $this->assertStringContainsString('x-on:close="$wire.cerrar()"', $componente->html());

        $componente->call('cerrar')->assertSet('editando', null)->assertDontSeeHtml('<dialog');
    }

    public function test_fallas_los_filtros_flux_select_listan_tipos_y_departamentos(): void
    {
        $this->prepararFallas(['acceso']);
        CatParosFallas::create(['TipoFallaId' => 'MECANICO', 'Departamento' => 'Urdido', 'Falla' => 'Fuga']);

        Livewire::test(CatalogoFallas::class)
            ->assertSeeHtml('wire:model.live="tipoFallaFiltro"')
            ->assertSeeHtml('wire:model.live="departamentoFiltro"')
            ->assertSeeHtml('value="ELECTRICO"')
            ->assertSeeHtml('value="Urdido"')
            ->set('tipoFallaFiltro', 'ELECTRICO')
            ->assertViewHas('filas', fn ($filas) => $filas->total() === 0)
            ->assertSeeHtml('wire:click="limpiarFiltros"');
    }

    // ── Catálogo de operadores ───────────────────────────────────────────

    private function prepararOperadores(array $acciones): void
    {
        $this->createTablaDbo('ManOperadoresMantenimiento', [
            'Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'CveEmpl' => 'TEXT', 'NomEmpl' => 'TEXT', 'Turno' => 'INTEGER', 'Depto' => 'TEXT', 'Telefono' => 'TEXT',
        ]);
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Mantenimiento', $acciones, null, 53);
    }

    public function test_operadores_el_boton_eliminar_llama_a_eliminar_tras_notify_confirm(): void
    {
        $this->prepararOperadores(['acceso', 'eliminar']);
        $op = ManOperadoresMantenimiento::create(['CveEmpl' => 'C1', 'NomEmpl' => 'Alfa', 'Turno' => 1, 'Depto' => 'Mtto']);

        $componente = Livewire::test(CatalogoOperadores::class)->call('seleccionar', (string) $op->Id);
        $html = $componente->html();

        $this->assertStringNotContainsString('wire:click="confirmarBorrado"', $html);
        $this->assertStringContainsString('$wire.eliminar()', $html);

        $componente->call('eliminar')->assertSet('seleccionado', null);
        $this->assertSame(0, ManOperadoresMantenimiento::count());
    }

    /** El select de turno (flux:select) tiene una opción por cada TURNOS y su placeholder vacío. */
    public function test_operadores_el_select_de_turno_ofrece_los_turnos_y_conserva_el_de_la_edicion(): void
    {
        $this->prepararOperadores(['acceso', 'crear', 'modificar']);
        $op = ManOperadoresMantenimiento::create(['CveEmpl' => 'C1', 'NomEmpl' => 'Alfa', 'Turno' => 3, 'Depto' => 'Mtto']);

        $componente = Livewire::test(CatalogoOperadores::class)
            ->call('abrirEdicion', (string) $op->Id)
            ->assertSet('form.Turno', '3')
            ->assertSeeHtml('wire:key="operador-'.$op->Id.'"');

        $html = $componente->html();
        $this->assertMatchesRegularExpression('/<select[^>]*wire:model="form\.Turno"/s', $html);
        preg_match('/<select[^>]*wire:model="form\.Turno".*?<\/select>/s', $html, $m);
        foreach (CatalogoOperadores::TURNOS as $turno) {
            $this->assertStringContainsString('value="'.$turno.'"', $m[0]);
        }
        $this->assertStringContainsString('Seleccione un turno', $m[0]);

        $componente->set('form.NomEmpl', 'Alfa Dos')->call('guardar')->assertHasNoErrors()->assertSet('editando', null);
        $this->assertSame(3, (int) $op->fresh()->Turno);
    }

    public function test_operadores_sin_turno_el_error_se_ve_en_el_dialog(): void
    {
        $this->prepararOperadores(['acceso', 'crear']);

        Livewire::test(CatalogoOperadores::class)
            ->call('abrirAlta')
            ->set('form.CveEmpl', 'C9')->set('form.NomEmpl', 'Nuevo')->set('form.Depto', 'Mtto')
            ->call('guardar')
            ->assertHasErrors(['form.Turno'])
            ->assertSet('editando', '')
            ->assertSeeHtml('data-flux-error');

        $this->assertSame(0, ManOperadoresMantenimiento::count());
    }

    // ── Inventario trama: consultar requerimiento ────────────────────────

    private function prepararTrama(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->create('TejTrama', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->unique();
            $table->date('Fecha')->nullable();
            foreach (['Status', 'Turno', 'numero_empleado', 'nombreEmpl'] as $col) {
                $table->string($col)->nullable();
            }
        });
        $schema->create('TejTramaConsumos', function (Blueprint $table) {
            $table->increments('Id');
            foreach (['Folio', 'NoTelarId', 'SalonTejidoId', 'NoProduccion', 'NombreProducto', 'FibraTrama', 'CodColorTrama', 'ColorTrama'] as $col) {
                $table->string($col)->nullable();
            }
            $table->float('CalibreTrama')->nullable();
            $table->float('Cantidad')->nullable();
        });
        $this->actingAs($this->createUsuario(), 'web');
    }

    /** Los dos botones de status mandan exactamente 'Solicitado' y 'Cancelado' (los que acepta el servicio). */
    public function test_consultar_en_proceso_los_botones_confirman_y_mandan_el_status_correcto(): void
    {
        $this->prepararTrama();
        DB::connection('sqlsrv')->table('TejTrama')->insert(['Folio' => 'TR00001', 'Fecha' => '2026-01-01', 'Status' => 'En Proceso', 'Turno' => '1']);

        $html = Livewire::test(ConsultarRequerimiento::class)
            ->assertSet('statusSeleccionado', 'En Proceso')
            ->html();

        $this->assertStringNotContainsString('wire:confirm', $html);
        $this->assertMatchesRegularExpression('/\$wire\.cambiarStatus\((&#039;|\')Solicitado(&#039;|\')\)/', $html);
        $this->assertMatchesRegularExpression('/\$wire\.cambiarStatus\((&#039;|\')Cancelado(&#039;|\')\)/', $html);
        // flux:button con :loading y wire:target: el spinner sale mientras corre cambiarStatus.
        $this->assertSame(2, preg_match_all('/wire:loading\.attr="data-flux-loading"[^>]*wire:target="cambiarStatus\([^"]*\)"|wire:target="cambiarStatus\([^"]*\)"[^>]*wire:loading\.attr="data-flux-loading"/s', $html));
    }

    public function test_consultar_folio_sin_consumos_pinta_la_fila_vacia(): void
    {
        $this->prepararTrama();
        DB::connection('sqlsrv')->table('TejTrama')->insert(['Folio' => 'TR00001', 'Fecha' => '2026-01-01', 'Status' => 'Solicitado', 'Turno' => '1']);

        Livewire::test(ConsultarRequerimiento::class)
            ->assertSee('No hay consumos registrados')
            ->assertSeeHtml('colspan="8"')
            ->assertDontSeeHtml('$wire.cambiarStatus');
    }

    public function test_consultar_sin_folios_muestra_el_empty_state(): void
    {
        $this->prepararTrama();

        Livewire::test(ConsultarRequerimiento::class)
            ->assertSee('No hay requerimientos disponibles')
            ->assertSee('No se encontraron requerimientos guardados en el sistema')
            ->assertSeeHtml('fa-inbox');
    }

    public function test_consultar_cambiar_status_a_solicitado_persiste(): void
    {
        $this->prepararTrama();
        DB::connection('sqlsrv')->table('TejTrama')->insert(['Folio' => 'TR00001', 'Fecha' => '2026-01-01', 'Status' => 'En Proceso', 'Turno' => '1']);

        Livewire::test(ConsultarRequerimiento::class)
            ->call('cambiarStatus', 'Solicitado')
            ->assertSet('statusSeleccionado', 'Solicitado')
            // Ya no es En Proceso: los botones de acción desaparecen.
            ->assertDontSeeHtml('$wire.cambiarStatus');

        $this->assertSame('Solicitado', DB::connection('sqlsrv')->table('TejTrama')->where('Folio', 'TR00001')->value('Status'));
    }
}
