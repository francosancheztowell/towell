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

    public function test_plantilla_del_mes_respeta_dias_y_turnos(): void
    {
        // Octubre 2026: 31 días, 4 domingos. De lunes a sábado con 3 turnos = 27 × 3.
        $filas = UrdEngCalMaquina::plantillaMes('2026-10', [
            ['turno' => 1, 'hora' => '06:30', 'minutos' => 480],
            ['turno' => 2, 'hora' => '14:30', 'minutos' => 480],
            ['turno' => 3, 'hora' => '22:30', 'minutos' => 480],
        ], [1, 2, 3, 4, 5, 6]);

        $this->assertCount(81, $filas);
        $this->assertSame('2026-10-01 06:30', $filas[0]['FechaInicio']->format('Y-m-d H:i'));
        $this->assertSame('2026-10-31 22:30', end($filas)['FechaInicio']->format('Y-m-d H:i'));
        $this->assertNotContains('2026-10-04', array_map(fn ($f) => $f['FechaInicio']->format('Y-m-d'), $filas), 'Domingo fuera.');
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

        $this->assertSame(81, UrdEngCalMaquina::count());

        $lw->call('abrirGenerar')->set('generar.Maquina', (string) $this->mcCoy1)->set('generar.mes', '2026-10')->call('generarMes');
        $this->assertSame(81, UrdEngCalMaquina::count(), 'La segunda vez todo se encima: no agrega nada.');
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

        $lw->set('form.FechaInicio', '2026-10-20T06:30')->call('guardar')->assertHasNoErrors();
        $this->assertSame(2, UrdEngCalMaquina::count());

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
