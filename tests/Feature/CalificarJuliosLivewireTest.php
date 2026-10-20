<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Tejedores\CalificarJulios;
use App\Models\Tejedores\TejCalificacionJulioMontados;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CalificarJuliosLivewireTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        Cache::flush();

        $schema = Schema::connection('sqlsrv');
        $schema->create('TelTelaresOperador', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('numero_empleado')->nullable();
            $t->string('NoTelarId')->nullable();
            $t->string('SalonTejidoId')->nullable();
        });
        $schema->create('AtaMontadoTelas', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('Estatus')->nullable();
            $t->date('Fecha')->nullable();
            $t->string('Turno')->nullable();
            $t->string('NoJulio')->nullable();
            $t->string('NoProduccion')->nullable();
            $t->string('Tipo')->nullable();
            $t->float('Metros')->nullable();
            $t->string('NoTelarId')->nullable();
            $t->string('NomTejedor')->nullable();
        });
        $schema->create('CatDefectosUrdEng', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('Clave');
            $t->decimal('Penalizacion', 10, 2);
            $t->string('Defecto');
            $t->boolean('Activo')->default(true);
        });
        $schema->create('TejCalificacionJulioMontados', function (Blueprint $t) {
            $t->increments('Id');
            $t->integer('AtaMontadoTelasId');
            $t->integer('DefectoId');
            $t->decimal('Penalizacion', 10, 2);
            $t->dateTime('Fecha');
            $t->string('NoEmpleado');
            $t->string('NomEmpleado')->nullable();
        });

        $db = DB::connection('sqlsrv');
        $db->table('TelTelaresOperador')->insert([
            ['numero_empleado' => '1001', 'NoTelarId' => '205', 'SalonTejidoId' => 'Jacquard'],
            ['numero_empleado' => '1001', 'NoTelarId' => '402', 'SalonTejidoId' => 'KARL MAYER'],
            ['numero_empleado' => '9999', 'NoTelarId' => '300', 'SalonTejidoId' => 'Jacquard'],
        ]);
        $montado = fn (string $telar, string $tipo, string $julio, string $fecha, string $estatus = 'Autorizado') => [
            'NoTelarId' => $telar, 'Tipo' => $tipo, 'NoJulio' => $julio, 'Fecha' => $fecha,
            'Estatus' => $estatus, 'NoProduccion' => '100', 'Turno' => '1',
        ];
        $db->table('AtaMontadoTelas')->insert([
            $montado('205', 'Rizo', 'R-viejo', '2026-10-01'),
            $montado('205', 'Rizo', 'R-ant', '2026-10-05'),
            $montado('205', 'Rizo', 'R-act', '2026-10-10'),
            $montado('205', 'Rizo', 'R-proceso', '2026-10-15', 'En Proceso'),
            $montado('205', 'Pie', 'P-act', '2026-10-09'),
            $montado('300', 'Rizo', 'ajeno', '2026-10-10'),
            $montado('402', '3', 'KM-b3', '2026-10-08', 'En Proceso'),
        ]);
        $db->table('CatDefectosUrdEng')->insert([
            ['Clave' => 'RHC', 'Penalizacion' => 1, 'Defecto' => 'HILOS CRUZADOS', 'Activo' => 1],
        ]);
    }

    private function autenticar(array $acciones = ['acceso', 'crear']): void
    {
        $this->actingAs($this->createUsuario(['numero_empleado' => '1001']), 'web');
        $this->grantModulo(CalificarJulios::MODULO, $acciones);
    }

    private function idJulio(string $noJulio): int
    {
        return (int) DB::connection('sqlsrv')->table('AtaMontadoTelas')->where('NoJulio', $noJulio)->value('Id');
    }

    public function test_sin_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(CalificarJulios::class)->assertForbidden();
    }

    public function test_muestra_los_2_ultimos_del_telar_y_tipo_en_cualquier_estatus(): void
    {
        $this->autenticar();

        $lw = Livewire::test(CalificarJulios::class)
            ->assertSet('telar', '205')
            ->assertSet('montadoId', $this->idJulio('R-proceso'));

        // El 'En Proceso' cuenta: es el más reciente aunque el atado no esté cerrado.
        $this->assertSame(['R-proceso', 'R-act'], $lw->instance()->julios->pluck('NoJulio')->all());

        $lw->set('tipo', 'Pie')->assertSet('montadoId', $this->idJulio('P-act'));
    }

    public function test_karl_mayer_usa_barras_1_a_4(): void
    {
        $this->autenticar();

        $lw = Livewire::test(CalificarJulios::class)
            ->set('telar', '402')
            ->assertSet('tipo', '1')
            ->assertSee('Barra 4')
            ->set('tipo', '3')
            ->assertSet('montadoId', $this->idJulio('KM-b3'));

        $this->assertSame(CalificarJulios::TIPOS_KM, $lw->instance()->tipos);

        $lw->set('telar', '205')->assertSet('tipo', 'Rizo');

        // Un tipo que no es del telar (Pie en un KM) no se queda pegado.
        $lw->set('telar', '402')->set('tipo', 'Pie')->assertSet('tipo', '1');
    }

    public function test_telar_y_tipo_de_la_url_sobreviven_al_recargar(): void
    {
        $this->autenticar();

        Livewire::withQueryParams(['telar' => '402', 'tipo' => '3'])->test(CalificarJulios::class)
            ->assertSet('telar', '402')->assertSet('tipo', '3')
            ->assertSet('montadoId', $this->idJulio('KM-b3'));

        // Telar ajeno o tipo de otra familia en la URL: se cae al primero válido.
        Livewire::withQueryParams(['telar' => '300', 'tipo' => 'Pie'])->test(CalificarJulios::class)
            ->assertSet('telar', '205')->assertSet('tipo', 'Pie');
        Livewire::withQueryParams(['telar' => '402', 'tipo' => 'Pie'])->test(CalificarJulios::class)
            ->assertSet('tipo', '1');
    }

    public function test_califica_borra_y_rechaza_julio_ajeno(): void
    {
        $this->autenticar();

        $lw = Livewire::test(CalificarJulios::class)
            ->call('abrirCalificar')
            ->assertSet('calificando', true)
            ->set('defectoId', 1)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('calificando', false);

        $cal = TejCalificacionJulioMontados::sole();
        $this->assertSame($this->idJulio('R-proceso'), $cal->AtaMontadoTelasId);
        $this->assertSame('1001', $cal->NoEmpleado);
        $this->assertSame(1.0, $cal->Penalizacion);
        $this->assertCount(1, $lw->instance()->historial);

        $lw->set('montadoId', $this->idJulio('ajeno'))->set('defectoId', 1)->call('guardar')->assertHasErrors('montadoId');
        $this->assertSame(1, TejCalificacionJulioMontados::count());

        $lw->call('confirmarBorrar', $cal->Id)->assertSet('borrando', $cal->Id)->call('borrar')->assertSet('borrando', null);
        $this->assertSame(0, TejCalificacionJulioMontados::count());
    }

    public function test_historial_2_dias_por_defecto_y_ultima_semana(): void
    {
        $this->autenticar();
        foreach ([1, 5] as $haceDias) {
            TejCalificacionJulioMontados::create([
                'AtaMontadoTelasId' => $this->idJulio('R-proceso'), 'DefectoId' => 1, 'Penalizacion' => 1,
                'Fecha' => now()->subDays($haceDias), 'NoEmpleado' => '1001',
            ]);
        }

        $lw = Livewire::test(CalificarJulios::class)->assertSet('dias', 2);
        $this->assertCount(1, $lw->instance()->historial);

        $lw->set('dias', 7);
        $this->assertCount(2, $lw->instance()->historial);
    }

    public function test_no_borra_defectos_de_otro_oficial(): void
    {
        $this->autenticar();
        $otro = TejCalificacionJulioMontados::create([
            'AtaMontadoTelasId' => $this->idJulio('R-act'), 'DefectoId' => 1, 'Penalizacion' => 1,
            'Fecha' => now(), 'NoEmpleado' => '9999', 'NomEmpleado' => 'Otro',
        ]);

        Livewire::test(CalificarJulios::class)->call('confirmarBorrar', $otro->Id)->assertNotFound();
        $this->assertSame(1, TejCalificacionJulioMontados::count());
    }

    public function test_sin_permiso_de_crear_no_califica(): void
    {
        $this->autenticar(['acceso']);

        Livewire::test(CalificarJulios::class)->call('abrirCalificar')->assertForbidden();
        Livewire::test(CalificarJulios::class)->set('defectoId', 1)->call('guardar')->assertForbidden();
    }
}
