<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\UrdEng\EdicionOrden;
use App\Models\Sistema\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de editar orden guarda campo por campo sin boton de guardar:
 * el permiso y las reglas de estado tienen que vivir en el servidor.
 */
class EditarOrdenPermisosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.sqlsrv', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'sqlsrv');
        DB::purge('sqlsrv');

        $this->crearTablaPrograma('UrdProgramaUrdido');
        $this->crearTablaPrograma('EngProgramaEngomado');
        $this->crearTablaProduccion('UrdProduccionUrdido');
        $this->crearTablaProduccion('EngProduccionEngomado');

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
            'Id' => 1, 'Folio' => 'ORD-900', 'Status' => 'Programado', 'NoTelarId' => '101', 'Cuenta' => '20',
        ]);
        DB::connection('sqlsrv')->table('EngProgramaEngomado')->insert([
            'Id' => 1, 'Folio' => 'ORD-900', 'Status' => 'Programado', 'NoTelarId' => '101', 'Cuenta' => '20',
        ]);
    }

    public function test_un_no_supervisor_no_puede_guardar_campos(): void
    {
        foreach (['urdido' => 'UrdProgramaUrdido', 'engomado' => 'EngProgramaEngomado'] as $modulo => $tabla) {
            Livewire::actingAs($this->usuario('Auxiliar'))
                ->test(EdicionOrden::class, ['module' => $modulo, 'ordenId' => 1])
                ->set('form.NoTelarId', '999')
                ->assertStatus(403);

            $this->assertSame('101', DB::connection('sqlsrv')->table($tabla)->where('Id', 1)->value('NoTelarId'));
        }
    }

    public function test_un_supervisor_guarda_y_sincroniza_con_engomado(): void
    {
        Livewire::actingAs($this->usuario('Supervisor Urdido'))
            ->test(EdicionOrden::class, ['module' => 'urdido', 'ordenId' => 1])
            ->set('form.NoTelarId', '999')
            ->assertOk();

        $this->assertSame('999', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('NoTelarId'));
        // NoTelarId se copia al folio gemelo de Engomado.
        $this->assertSame('999', DB::connection('sqlsrv')->table('EngProgramaEngomado')->where('Id', 1)->value('NoTelarId'));
    }

    public function test_los_campos_por_estado_se_bloquean_cuando_la_orden_esta_finalizada(): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->update(['Status' => 'Finalizado']);

        // Regla de negocio: no revienta la pantalla, avisa y deja el valor anterior.
        $componente = Livewire::actingAs($this->usuario('Supervisor Urdido'))
            ->test(EdicionOrden::class, ['module' => 'urdido', 'ordenId' => 1])
            ->set('form.Cuenta', '40')
            ->assertOk()
            ->assertDispatched('program-board-notify', type: 'error');

        $this->assertSame('20', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('Cuenta'));
        $this->assertSame('20', $componente->get('form.Cuenta'));
    }

    public function test_con_ax_solo_queda_editable_el_tipo(): void
    {
        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->update(['ax' => 1]);

        $componente = Livewire::actingAs($this->usuario('Supervisor Urdido'))
            ->test(EdicionOrden::class, ['module' => 'urdido', 'ordenId' => 1]);

        $componente->set('form.NoTelarId', '999')->assertDispatched('program-board-notify', type: 'error');
        $this->assertSame('101', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('NoTelarId'));

        $componente->set('form.RizoPie', 'Pie')->assertOk();
        $this->assertSame('Pie', DB::connection('sqlsrv')->table('UrdProgramaUrdido')->where('Id', 1)->value('RizoPie'));
    }

    public function test_los_julios_no_se_pueden_tocar_sin_permiso(): void
    {
        Livewire::actingAs($this->usuario('Auxiliar'))
            ->test(EdicionOrden::class, ['module' => 'urdido', 'ordenId' => 1])
            ->call('guardarJulio', 0)
            ->assertStatus(403);
    }

    private function usuario(string $puesto): Usuario
    {
        $usuario = new Usuario([
            'idusuario' => 10,
            'numero_empleado' => '100',
            'nombre' => 'Usuario prueba',
            'puesto' => $puesto,
        ]);
        $usuario->idusuario = 10;
        $usuario->exists = true;

        return $usuario;
    }

    private function crearTablaPrograma(string $tabla): void
    {
        Schema::connection('sqlsrv')->create($tabla, function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Status')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('RizoPie')->nullable();
            $table->string('Cuenta')->nullable();
            $table->float('Calibre')->nullable();
            $table->float('Metros')->nullable();
            $table->float('Kilos')->nullable();
            $table->string('Fibra')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('SalonTejidoId')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->string('MaquinaEng')->nullable();
            $table->string('MaquinaUrd')->nullable();
            $table->string('BomId')->nullable();
            $table->string('BomUrd')->nullable();
            $table->string('BomEng')->nullable();
            $table->string('BomFormula')->nullable();
            $table->date('FechaProg')->nullable();
            $table->string('TipoAtado')->nullable();
            $table->string('LoteProveedor')->nullable();
            $table->string('FolioConsumo')->nullable();
            $table->integer('NoTelas')->nullable();
            $table->text('Observaciones')->nullable();
            $table->integer('ax')->nullable();
        });
    }

    private function crearTablaProduccion(string $tabla): void
    {
        Schema::connection('sqlsrv')->create($tabla, function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->integer('AX')->nullable();
            $table->integer('Hilos')->nullable();
            $table->string('NoJulio')->nullable();
            $table->string('HoraInicial')->nullable();
            $table->string('HoraFinal')->nullable();
            $table->date('Fecha')->nullable();
            $table->float('Metros1')->nullable();
            $table->float('Metros2')->nullable();
            $table->float('Metros3')->nullable();
            $table->float('Solidos')->nullable();
            $table->string('CveEmpl1')->nullable();
            $table->string('NomEmpl1')->nullable();
            $table->integer('Turno1')->nullable();
            $table->string('TipoAtado')->nullable();
        });
    }
}
