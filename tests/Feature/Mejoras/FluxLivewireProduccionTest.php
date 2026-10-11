<?php

declare(strict_types=1);

namespace Tests\Feature\Mejoras;

use App\Livewire\Desarrolladores\Captura;
use App\Livewire\Tejedores\CatalogoCalibres;
use App\Livewire\UrdEng\EdicionOrden;
use App\Models\Sistema\Usuario;
use App\Models\Tejedores\TejCatMatrizDesarrolladores;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Rama claude/mejora-flux-livewire-produccion: los modales de catálogo de calibres,
 * edición de orden Urd/Eng y la alerta de saldo de la captura pasaron de <div fixed> con
 * wire:click a <dialog> nativo cuyo evento `close` llama al método que antes llamaba el
 * botón. Estos tests fijan ese contrato servidor ↔ Blade: el diálogo aparece/desaparece
 * con el estado, su `close` apunta al método que descarta, y los métodos que nombra existen.
 */
class FluxLivewireProduccionTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const MODULO_CALIBRES = 'Catalogo Calibres';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        Cache::flush();
    }

    // ── Catálogo de calibres ─────────────────────────────────────────────

    private function prepararCalibres(): int
    {
        Schema::connection('sqlsrv')->create('TejCatMatrizDesarrolladores', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Codigo', 20);
            $table->string('CodigoInterno', 20);
            $table->float('Divisor');
            $table->string('Nombre', 60);
            $table->boolean('Vigente')->default(true);
            $table->timestamps();
        });

        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo(self::MODULO_CALIBRES, ['acceso', 'crear', 'modificar']);

        return (int) DB::connection('sqlsrv')->table('TejCatMatrizDesarrolladores')->insertGetId([
            'Codigo' => '600/1T', 'CodigoInterno' => '600.1', 'Divisor' => 8.86, 'Nombre' => 'HILO 600 TENIDO', 'Vigente' => 1,
        ]);
    }

    public function test_calibres_el_alta_abre_un_dialog_nativo_cuyo_cierre_llama_a_cerrar(): void
    {
        $this->prepararCalibres();

        $html = Livewire::test(CatalogoCalibres::class)
            ->assertDontSeeHtml('<dialog')
            ->call('abrirAlta')
            ->assertSet('editando', '')
            ->html();

        $this->assertStringContainsString('<dialog wire:key="calibre-"', $html);
        $this->assertStringContainsString('$el.showModal()', $html);
        $this->assertStringContainsString('x-on:close="$wire.cerrar()"', $html);
        // El submit del pie (x-dialogo-botones) apunta a guardar, que es el wire:submit del form.
        $this->assertStringContainsString('wire:submit="guardar"', $html);
        $this->assertMatchesRegularExpression('/type="submit"[^>]*wire:target="guardar"/s', $html);
    }

    public function test_calibres_cerrar_quita_el_dialog(): void
    {
        $this->prepararCalibres();

        Livewire::test(CatalogoCalibres::class)
            ->call('abrirAlta')
            ->call('cerrar')
            ->assertSet('editando', null)
            ->assertDontSeeHtml('<dialog');
    }

    /** flux:input ya no lleva @error a mano: el mensaje lo tiene que pintar flux:error dentro del diálogo. */
    public function test_calibres_los_errores_de_validacion_se_ven_dentro_del_dialog(): void
    {
        $this->prepararCalibres();

        Livewire::test(CatalogoCalibres::class)
            ->call('abrirAlta')
            ->call('guardar')
            ->assertHasErrors(['form.Codigo', 'form.Nombre', 'form.CodigoInterno', 'form.Divisor'])
            // El diálogo sigue abierto (editando = '') y muestra los mensajes propios del componente.
            ->assertSet('editando', '')
            ->assertSeeHtml('<dialog')
            ->assertSee('Escribe el codigo AX del hilo.')
            ->assertSee('Escribe el nombre que vera el operador.')
            ->assertSee('Escribe el calibre que se guarda en la orden.')
            ->assertSee('Escribe el divisor del hilo.');
    }

    /** El maxlength del input no puede ser menor que la regla: cortaría datos válidos sin avisar. */
    public function test_calibres_el_maxlength_de_los_inputs_coincide_con_las_reglas(): void
    {
        $this->prepararCalibres();

        $html = Livewire::test(CatalogoCalibres::class)->call('abrirAlta')->html();

        foreach (['form.Codigo' => 20, 'form.Nombre' => 60, 'form.CodigoInterno' => 20] as $modelo => $max) {
            $this->assertMatchesRegularExpression(
                '/<input[^>]*wire:model="'.preg_quote($modelo, '/').'"[^>]*>/s',
                $html,
                "Falta el input de {$modelo}"
            );
            preg_match('/<input[^>]*wire:model="'.preg_quote($modelo, '/').'"[^>]*>/s', $html, $m);
            $this->assertStringContainsString('maxlength="'.$max.'"', $m[0], "{$modelo} debe aceptar {$max} caracteres");
        }
    }

    public function test_calibres_editar_y_guardar_cierra_el_dialog_y_persiste(): void
    {
        $id = $this->prepararCalibres();

        Livewire::test(CatalogoCalibres::class)
            ->call('abrirEdicion', (string) $id)
            ->assertSeeHtml('wire:key="calibre-'.$id.'"')
            ->set('form.Nombre', 'HILO 600 TEÑIDO')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('editando', null)
            ->assertDontSeeHtml('<dialog');

        $this->assertSame('HILO 600 TEÑIDO', TejCatMatrizDesarrolladores::find($id)->Nombre);
    }

    public function test_calibres_la_baja_se_confirma_en_un_dialog_y_alternar_la_cierra(): void
    {
        $id = $this->prepararCalibres();

        $componente = Livewire::test(CatalogoCalibres::class)
            ->set('seleccionado', (string) $id)
            ->call('confirmarBaja')
            ->assertSet('confirmandoBaja', true)
            ->assertSeeHtml('wire:key="calibre-baja-'.$id.'"')
            ->assertSeeHtml('wire:click="alternarVigencia"')
            ->assertSee('¿Dar de baja');

        $this->assertStringContainsString('x-on:close="$wire.cerrar()"', $componente->html());

        $componente->call('alternarVigencia')
            ->assertSet('confirmandoBaja', false)
            ->assertDontSeeHtml('calibre-baja-');

        $this->assertFalse((bool) TejCatMatrizDesarrolladores::find($id)->Vigente);
    }

    // ── Edición de orden Urd/Eng ─────────────────────────────────────────

    private function prepararEdicionOrden(string $status = 'En Proceso', int $noTelas = 2): void
    {
        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlsrv');

        foreach (['UrdProgramaUrdido', 'EngProgramaEngomado'] as $tabla) {
            Schema::connection('sqlsrv')->create($tabla, function (Blueprint $table): void {
                $table->increments('Id');
                foreach (['Folio', 'Status', 'NoTelarId', 'RizoPie', 'Cuenta', 'Fibra', 'InventSizeId', 'SalonTejidoId',
                    'MaquinaId', 'MaquinaEng', 'MaquinaUrd', 'BomId', 'BomUrd', 'BomEng', 'BomFormula', 'TipoAtado',
                    'LoteProveedor', 'FolioConsumo'] as $col) {
                    $table->string($col)->nullable();
                }
                $table->float('Calibre')->nullable();
                $table->float('Metros')->nullable();
                $table->float('Kilos')->nullable();
                $table->date('FechaProg')->nullable();
                $table->integer('NoTelas')->nullable();
                $table->text('Observaciones')->nullable();
                $table->integer('ax')->nullable();
            });
        }
        foreach (['UrdProduccionUrdido', 'EngProduccionEngomado'] as $tabla) {
            Schema::connection('sqlsrv')->create($tabla, function (Blueprint $table): void {
                $table->increments('Id');
                foreach (['Folio', 'NoJulio', 'HoraInicial', 'HoraFinal', 'CveEmpl1', 'NomEmpl1', 'TipoAtado'] as $col) {
                    $table->string($col)->nullable();
                }
                $table->integer('AX')->nullable();
                $table->integer('Hilos')->nullable();
                $table->date('Fecha')->nullable();
                $table->float('Metros1')->nullable();
                $table->float('Metros2')->nullable();
                $table->float('Metros3')->nullable();
                $table->float('Solidos')->nullable();
                $table->integer('Turno1')->nullable();
            });
        }
        Schema::connection('sqlsrv')->create('UrdJuliosOrden', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->integer('Julios')->nullable();
            $table->integer('Hilos')->nullable();
        });
        Schema::connection('sqlsrv')->create('CatUbicaciones', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Codigo')->nullable();
        });
        Schema::connection('sqlsrv')->create('UrdConsumoHilo', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->dateTime('FechaRequerimiento')->nullable();
        });
        Schema::connection('sqlsrv')->create('URDCatalogoMaquinas', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('MaquinaId')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('Departamento')->nullable();
        });

        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => 1, 'Folio' => 'ORD-900', 'Status' => $status, 'NoTelarId' => '101', 'Cuenta' => '20', 'NoTelas' => $noTelas,
        ]);
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 1, 'Folio' => 'ORD-900', 'Status' => $status, 'NoTelarId' => '101', 'Cuenta' => '20', 'NoTelas' => $noTelas,
        ]);
    }

    private function supervisor(): Usuario
    {
        $usuario = new Usuario(['idusuario' => 10, 'numero_empleado' => '100', 'nombre' => 'Usuario prueba', 'puesto' => 'Supervisor Urdido']);
        $usuario->idusuario = 10;
        $usuario->exists = true;

        return $usuario;
    }

    public function test_edicion_cambiar_notelas_en_proceso_abre_el_dialog_de_confirmacion(): void
    {
        $this->prepararEdicionOrden();

        $componente = Livewire::actingAs($this->supervisor())
            ->test(EdicionOrden::class, ['module' => 'urdido', 'ordenId' => 1])
            ->assertDontSeeHtml('pendiente-notelas')
            ->set('form.NoTelas', '3')
            ->assertSet('pendiente', 'NoTelas')
            ->assertSeeHtml('wire:key="pendiente-notelas"')
            ->assertSee('Se agregarán 1 registro(s) de producción');

        $html = $componente->html();
        // Cancelar/Esc/fondo cierran el <dialog>; su evento close descarta en el servidor.
        $this->assertStringContainsString('x-on:close="$wire.descartarPendiente()"', $html);
        $this->assertStringContainsString('wire:click="confirmarNoTelas"', $html);
    }

    public function test_edicion_cerrar_el_dialog_de_notelas_restaura_el_valor_de_base(): void
    {
        $this->prepararEdicionOrden();

        Livewire::actingAs($this->supervisor())
            ->test(EdicionOrden::class, ['module' => 'urdido', 'ordenId' => 1])
            ->set('form.NoTelas', '5')
            ->call('descartarPendiente')
            ->assertSet('pendiente', null)
            ->assertSet('form.NoTelas', '2')
            ->assertDontSeeHtml('pendiente-notelas');

        $this->assertSame(2, (int) DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('NoTelas'));
    }

    public function test_edicion_el_error_se_muestra_en_dialog_y_cerrarlo_limpia_el_aviso(): void
    {
        // Finalizado: cambiar la cuenta lo rechaza el servidor con aviso de error.
        $this->prepararEdicionOrden('Finalizado');

        $componente = Livewire::actingAs($this->supervisor())
            ->test(EdicionOrden::class, ['module' => 'urdido', 'ordenId' => 1])
            ->set('form.Cuenta', '40')
            ->assertSet('avisoTipo', 'error')
            ->assertSeeHtml('wire:key="aviso-error"')
            ->assertSee('No se guardó');

        $this->assertNotSame('', $componente->get('aviso'));
        // El close del <dialog> hace $wire.set('aviso', ''): la propiedad tiene que seguir llamándose así.
        $this->assertMatchesRegularExpression('/x-on:close="\$wire\.set\((&#039;|\')aviso(&#039;|\'), (&#039;|\')(&#039;|\')\)"/', $componente->html());

        $componente->set('aviso', '')->assertDontSeeHtml('aviso-error');
    }

    public function test_edicion_el_aviso_de_guardando_espera_antes_de_mostrarse(): void
    {
        $this->prepararEdicionOrden('Programado');

        $html = Livewire::actingAs($this->supervisor())
            ->test(EdicionOrden::class, ['module' => 'urdido', 'ordenId' => 1])
            ->html();

        // Sin .delay el aviso parpadearía en cada tecla de NoTelas (wire:model.live).
        $this->assertMatchesRegularExpression('/<p wire:loading\.delay role="status"[^>]*>Guardando/', $html);
    }

    // ── Captura de desarrolladores: alerta de saldo ──────────────────────

    /** Contrato estático: el botón Cancelar cierra el <dialog>; el close llama al método del componente. */
    public function test_captura_la_alerta_de_saldo_cierra_con_el_dialog_y_llama_a_cerrar_alerta(): void
    {
        $vista = (string) file_get_contents(resource_path('views/livewire/desarrolladores/captura.blade.php'));

        $this->assertMatchesRegularExpression('/@if \(\$alertaSaldo\)\s*@php[^\n]*\n\s*\{\{--[^\n]*--\}\}\s*<dialog wire:key="alerta-saldo"/', $vista);
        $this->assertStringContainsString('x-on:close="$wire.cerrarAlertaSaldo()"', $vista);
        $this->assertStringContainsString('$el.showModal()', $vista);
        // El botón con data-cancelar (al que se le da foco) solo cierra; no hace un segundo round-trip.
        $this->assertMatchesRegularExpression('/<button type="button" data-cancelar x-on:click="\$el\.closest\(\'dialog\'\)\.close\(\)"/', $vista);
        $this->assertTrue(method_exists(Captura::class, 'cerrarAlertaSaldo'));
        $this->assertTrue(method_exists(Captura::class, 'elegirAccionSaldo'));
    }
}
