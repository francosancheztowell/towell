<?php

declare(strict_types=1);

namespace Tests\Feature\Urdido;

use App\Livewire\Urdido\CalendarioMaquinas;
use App\Models\UrdEngomado\UrdEngCalMaquina;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class CalendarioMaquinasTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const VENTANAS = [
        ['ventana' => 1, 'hora' => '06:30', 'minutos' => 480],
        ['ventana' => 2, 'hora' => '14:30', 'minutos' => 480],
        ['ventana' => 3, 'hora' => '22:30', 'minutos' => 480],
    ];

    private int $mcCoy1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        Schema::connection('sqlsrv')->create('URDCatalogoMaquinas', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('MaquinaId')->unique();
            $t->string('Nombre')->nullable();
            $t->string('Departamento')->nullable();
            $t->string('Codificacion')->nullable();
            $t->integer('Secuencia')->nullable();
        });
        Schema::connection('sqlsrv')->create('UrdEngCalMaquinas', function (Blueprint $t) {
            $t->increments('Id');
            $t->integer('Maquina');
            $t->dateTime('FechaInicio');
            $t->integer('Minutos');
            $t->tinyInteger('Turno');
        });

        // La llave del modelo es MaquinaId: el Id (IDENTITY) no vuelve en create(), se relee.
        $this->mcCoy1 = URDCatalogoMaquina::create(['MaquinaId' => 'Mc Coy 1', 'Nombre' => 'Mc Coy 1', 'Departamento' => 'Urdido'])->fresh()->Id;
        URDCatalogoMaquina::create(['MaquinaId' => 'WestPoint 2', 'Departamento' => 'Engomado']);
    }

    public function test_sin_acceso_devuelve_403(): void
    {
        $this->actingAs($this->createUsuario(), 'web');

        Livewire::test(CalendarioMaquinas::class)->assertForbidden();
    }

    public function test_la_pagina_monta_el_componente(): void
    {
        $this->autenticarCon(['acceso']);
        $this->withoutVite();

        $this->get('/urdido/configuracion/calendariourdido')->assertOk()->assertSeeLivewire(CalendarioMaquinas::class);
    }

    public function test_plantilla_del_mes_respeta_dias_y_horarios(): void
    {
        // Octubre 2026: 31 días, 4 domingos. De lunes a sábado con 3 horarios = 27 × 3.
        $filas = UrdEngCalMaquina::plantillaMes('2026-10', self::VENTANAS, [1, 2, 3, 4, 5, 6]);

        $this->assertCount(81, $filas);
        $this->assertSame('2026-10-01 06:30', $filas[0]['FechaInicio']->format('Y-m-d H:i'));
        $this->assertSame('2026-10-31 22:30', end($filas)['FechaInicio']->format('Y-m-d H:i'));
        $this->assertNotContains('2026-10-04', array_map(fn ($f) => $f['FechaInicio']->format('Y-m-d'), $filas), 'Domingo fuera.');
    }

    public function test_el_turno_sale_de_la_rotacion_de_la_hoja(): void
    {
        $turnos = collect(UrdEngCalMaquina::plantillaMes('2026-10', self::VENTANAS, [1, 2, 3, 4, 5, 6, 7]))
            ->mapWithKeys(fn ($f) => [$f['FechaInicio']->format('D H:i').' '.$f['FechaInicio']->format('d') => $f['Turno']]);

        // Semana del martes 13 al lunes 19 de octubre, tal cual la hoja impresa.
        $this->assertSame(1, $turnos['Tue 06:30 13']);
        $this->assertSame(3, $turnos['Tue 14:30 13']);
        $this->assertSame(4, $turnos['Tue 22:30 13']);
        $this->assertSame(2, $turnos['Wed 14:30 14']);
        $this->assertSame(3, $turnos['Thu 22:30 15']);
        $this->assertSame(4, $turnos['Fri 22:30 16']);
        $this->assertSame(3, $turnos['Sun 06:30 18'], 'El domingo en la mañana entra el 3.');
        $this->assertSame(2, $turnos['Sun 14:30 18']);
        $this->assertSame(3, $turnos['Mon 14:30 19']);
        $this->assertSame(4, $turnos['Mon 22:30 19']);

        // Cada día trabajan tres turnos distintos y uno descansa.
        foreach (UrdEngCalMaquina::ROTACION_URDIDO as $dia => $porVentana) {
            $this->assertCount(3, array_unique($porVentana), "Día $dia");
        }
    }

    public function test_generar_mes_no_duplica_lo_que_ya_esta(): void
    {
        $this->autenticarCon(['acceso', 'crear']);

        $lw = Livewire::test(CalendarioMaquinas::class)
            ->call('abrirGenerar')
            ->set('generar.Maquina', (string) $this->mcCoy1)
            ->set('generar.mes', '2026-10')
            ->call('generarMes')
            ->assertHasNoErrors()
            ->assertDispatched('aviso');

        // Por default los 7 días con los 3 horarios: 31 × 3.
        $this->assertSame(93, UrdEngCalMaquina::count());
        $this->assertSame(4, UrdEngCalMaquina::where('FechaInicio', '2026-10-20 22:30:00')->value('Turno'), 'Martes noche = turno 4.');

        $lw->call('abrirGenerar')->set('generar.Maquina', (string) $this->mcCoy1)->set('generar.mes', '2026-10')->call('generarMes');
        $this->assertSame(93, UrdEngCalMaquina::count(), 'La segunda vez todo se encima: no agrega nada.');
    }

    public function test_el_calendario_pinta_el_mes_y_permite_agregar_y_borrar_desde_la_casilla(): void
    {
        $this->autenticarCon(['acceso', 'crear', 'modificar', 'eliminar']);
        $domingo = UrdEngCalMaquina::create(['Maquina' => $this->mcCoy1, 'FechaInicio' => '2026-10-18 06:30:00', 'Minutos' => 450, 'Turno' => 3]);

        $lw = Livewire::withQueryParams(['mes' => '2026-10'])->test(CalendarioMaquinas::class)
            ->assertSet('maquinaFiltro', (string) $this->mcCoy1)
            ->assertSee('Octubre 2026')
            ->assertSee('450 min')          // minutos distintos de 480 se muestran
            ->assertSee('+ T2')             // domingo 14:30 vacío: sugiere el turno 2 de la rotación
            ->assertViewHas('resumen', ['turnos' => 1, 'minutos' => 450]);

        // Casilla vacía del domingo en la noche: alta con máquina, inicio y turno 4 ya puestos.
        $lw->call('abrirAltaEn', '2026-10-18T22:30')
            ->assertSet('editando', '')
            ->assertSet('form.Maquina', (string) $this->mcCoy1)
            ->assertSet('form.Turno', '4')
            ->call('guardar')->assertHasNoErrors();
        $this->assertSame(2, UrdEngCalMaquina::count());

        // Clic en el turno → editar → eliminar desde el diálogo.
        $lw->call('abrirEdicion', (string) $domingo->Id)->call('eliminar')->assertSet('editando', null);
        $this->assertNull(UrdEngCalMaquina::find($domingo->Id));

        $lw->call('moverMes', 1)->assertSet('mes', '2026-11')->assertSee('Noviembre 2026');
    }

    public function test_generar_rechaza_maquina_de_otro_departamento(): void
    {
        $this->autenticarCon(['acceso', 'crear']);
        $westPoint = URDCatalogoMaquina::find('WestPoint 2')->Id;

        Livewire::test(CalendarioMaquinas::class)
            ->call('abrirGenerar')->set('generar.Maquina', (string) $westPoint)->call('generarMes')
            ->assertHasErrors(['generar.Maquina']);
    }

    public function test_turno_3_que_cruza_medianoche_bloquea_el_traslape(): void
    {
        $this->autenticarCon(['acceso', 'crear', 'modificar']);
        UrdEngCalMaquina::create(['Maquina' => $this->mcCoy1, 'FechaInicio' => '2026-10-19 22:30:00', 'Minutos' => 480, 'Turno' => 3]);

        $lw = Livewire::test(CalendarioMaquinas::class)
            ->call('abrirAlta')
            ->set('form.Maquina', (string) $this->mcCoy1)
            ->set('form.FechaInicio', '2026-10-20T06:00')
            ->set('form.Turno', '1')
            ->call('guardar')
            ->assertHasErrors(['form.FechaInicio']);

        // A una hora de ventana el turno se propone con la rotación (martes 22:30 → 4); se puede cambiar.
        $lw->set('form.FechaInicio', '2026-10-20T22:30')->assertSet('form.Turno', '4');
        $lw->set('form.FechaInicio', '2026-10-20T06:30')->assertSet('form.Turno', '1')
            ->set('form.Turno', '4')->call('guardar')->assertHasNoErrors();
        $this->assertSame(2, UrdEngCalMaquina::count());
        $this->assertSame(4, UrdEngCalMaquina::where('FechaInicio', '2026-10-20 06:30:00')->value('Turno'), 'Se guarda el turno 4.');

        // Editar un turno no choca consigo mismo.
        $id = (string) UrdEngCalMaquina::where('Turno', 3)->value('Id');
        $lw->call('abrirEdicion', $id)
            ->assertSet('form.FechaInicio', '2026-10-19T22:30')
            ->set('form.Minutos', '470')
            ->call('guardar')
            ->assertHasNoErrors();
        $this->assertSame(470, UrdEngCalMaquina::find($id)->Minutos);
    }

    private function autenticarCon(array $acciones): void
    {
        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Calendario Urdido', $acciones, null, CalendarioMaquinas::MODULO);
    }
}
