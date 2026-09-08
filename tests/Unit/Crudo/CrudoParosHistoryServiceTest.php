<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Services\Crudo\CrudoParosHistoryService;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CrudoParosHistoryServiceTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = tempnam(sys_get_temp_dir(), 'crudo_paros_').'.sqlite';
        touch($this->database);

        config()->set('database.connections.crudo_test_catalog', [
            'driver' => 'sqlite',
            'database' => $this->database,
            'prefix' => '',
        ]);
        config()->set('crudo.connections.catalog', 'crudo_test_catalog');
        config()->set('crudo.tables.paros', 'ManFallasParos');
        config()->set('crudo.tables.ordenes_trabajo', 'MecOrdenTrabajoTable');
        config()->set('crudo.tables.ordenes_trabajo_lineas', 'MecOrdenTrabajoLine');
        config()->set('crudo.production_day_start_minutes', 390);

        DB::purge('crudo_test_catalog');

        Schema::connection('crudo_test_catalog')->create('ManFallasParos', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Estatus')->nullable();
            $table->date('Fecha')->nullable();
            $table->string('Hora')->nullable();
            $table->date('FechaFin')->nullable();
            $table->string('HoraFin')->nullable();
            $table->string('Depto')->nullable();
            $table->string('TipoFallaId')->nullable();
            $table->string('Falla')->nullable();
            $table->string('Descripcion')->nullable();
            $table->string('NomEmpl')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('NomAtendio')->nullable();
            $table->integer('TurnoAtendio')->nullable();
            $table->string('Obs')->nullable();
            $table->string('ObsCierre')->nullable();
            $table->string('OrdenTrabajo')->nullable();
            $table->string('MaquinaId')->nullable();
        });

        Schema::connection('crudo_test_catalog')->create('MecOrdenTrabajoTable', function (Blueprint $table): void {
            $table->string('Folio')->primary();
            $table->string('FolioParo')->nullable();
            $table->string('Estatus')->nullable();
        });

        Schema::connection('crudo_test_catalog')->create('MecOrdenTrabajoLine', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('CveOperador')->nullable();
            $table->string('NomOperador')->nullable();
            $table->integer('Turno')->nullable();
            $table->date('Fecha')->nullable();
            $table->boolean('Ajusto')->nullable();
            $table->boolean('Reparo')->nullable();
            $table->boolean('Cambio')->nullable();
            $table->boolean('Lubrico')->nullable();
            $table->boolean('FaltaRefacc')->nullable();
            $table->string('HoraInicial')->nullable();
            $table->string('HoraFinal')->nullable();
            $table->integer('TotalMinutos')->nullable();
            $table->string('comentarios')->nullable();
            $table->integer('Calificacion')->nullable();
            $table->string('CveTejedor')->nullable();
            $table->string('NomTejedor')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('crudo_test_catalog');
        @unlink($this->database);

        parent::tearDown();
    }

    public function test_incluye_el_dia_productivo_consultado_y_el_anterior(): void
    {
        $this->insertParo(['Fecha' => '2026-08-13', 'Hora' => '03:00:00', 'Folio' => 'madrugada-hoy']);
        $this->insertParo(['Fecha' => '2026-08-13', 'Hora' => '10:00:00', 'Folio' => 'manana-hoy']);
        $this->insertParo(['Fecha' => '2026-08-12', 'Hora' => '08:00:00', 'Folio' => 'ayer']);
        // 05:00 del 12 pertenece al día productivo del 11: queda fuera de la ventana.
        $this->insertParo(['Fecha' => '2026-08-12', 'Hora' => '05:00:00', 'Folio' => 'antier']);
        $this->insertParo(['Fecha' => '2026-08-13', 'Hora' => '11:00:00', 'Folio' => 'otro-telar', 'MaquinaId' => '999']);

        $paros = $this->service()->forMachine('201', new DateTimeImmutable('2026-08-13'), new DateTimeImmutable('2026-08-13'));

        $this->assertSame(
            ['manana-hoy', 'madrugada-hoy', 'ayer'],
            array_column($paros, 'folio'),
        );
    }

    public function test_la_ventana_se_amplia_a_semana_o_mes(): void
    {
        $this->insertParo(['Fecha' => '2026-08-13', 'Hora' => '10:00:00', 'Folio' => 'hoy']);
        $this->insertParo(['Fecha' => '2026-08-09', 'Hora' => '10:00:00', 'Folio' => 'hace-4-dias']);
        $this->insertParo(['Fecha' => '2026-07-25', 'Hora' => '10:00:00', 'Folio' => 'hace-19-dias']);

        $dia = new DateTimeImmutable('2026-08-13');

        $this->assertSame(['hoy'], array_column($this->service()->forMachine('201', $dia, $dia), 'folio'));
        $this->assertSame(
            ['hoy', 'hace-4-dias'],
            array_column($this->service()->forMachine('201', $dia, $dia, 7), 'folio'),
        );
        $this->assertSame(
            ['hoy', 'hace-4-dias', 'hace-19-dias'],
            array_column($this->service()->forMachine('201', $dia, $dia, 30), 'folio'),
        );
    }

    public function test_calcula_duracion_y_distingue_activos(): void
    {
        $this->insertParo([
            'Folio' => 'cerrado',
            'Estatus' => 'Terminado',
            'Fecha' => '2026-08-13',
            'Hora' => '07:10:00',
            'FechaFin' => '2026-08-13',
            'HoraFin' => '09:25:00',
            'NomAtendio' => 'Mecánico Uno',
            'ObsCierre' => 'Se cambió balero',
        ]);
        $this->insertParo([
            'Folio' => 'abierto',
            'Estatus' => 'Activo',
            'Fecha' => '2026-08-13',
            'Hora' => '08:00:00',
        ]);

        $paros = $this->service()->forMachine('201', new DateTimeImmutable('2026-08-13'), new DateTimeImmutable('2026-08-13'));
        $porFolio = array_column($paros, null, 'folio');

        $this->assertFalse($porFolio['cerrado']['activo']);
        $this->assertSame('Terminado', $porFolio['cerrado']['estatus']);
        $this->assertSame('2h 15m', $porFolio['cerrado']['duracion']);
        $this->assertSame('13/08 09:25', $porFolio['cerrado']['fin']);
        $this->assertSame('Mecánico Uno', $porFolio['cerrado']['atendio']);
        $this->assertSame('Se cambió balero', $porFolio['cerrado']['obsCierre']);

        $this->assertTrue($porFolio['abierto']['activo']);
        $this->assertSame('', $porFolio['abierto']['fin']);
        $this->assertSame([], $porFolio['cerrado']['ordenes']);
        $this->assertSame([], $porFolio['abierto']['ordenes']);
    }

    public function test_cuelga_los_renglones_capturados_de_las_ot_del_paro(): void
    {
        $this->insertParo([
            'Folio' => 'PARO-201',
            'Fecha' => '2026-08-13',
            'Hora' => '08:00:00',
        ]);
        $this->insertParo([
            'Folio' => 'PARO-OTRO',
            'Fecha' => '2026-08-13',
            'Hora' => '09:00:00',
        ]);

        DB::connection('crudo_test_catalog')->table('MecOrdenTrabajoTable')->insert([
            ['Folio' => 'MEC00025', 'FolioParo' => 'PARO-201', 'Estatus' => 'Terminado'],
            ['Folio' => 'MEC00026', 'FolioParo' => 'PARO-201', 'Estatus' => 'Activo'],
            ['Folio' => 'MEC00016', 'FolioParo' => 'PARO-201', 'Estatus' => 'Activo'],
            ['Folio' => 'MEC00999', 'FolioParo' => 'PARO-OTRO', 'Estatus' => 'Activo'],
        ]);

        DB::connection('crudo_test_catalog')->table('MecOrdenTrabajoLine')->insert([
            [
                'Folio' => 'MEC00025',
                'CveOperador' => '1001',
                'NomOperador' => 'Mecánico Uno',
                'Turno' => 1,
                'Fecha' => '2026-08-13',
                'Ajusto' => 1,
                'Reparo' => 1,
                'Cambio' => 0,
                'Lubrico' => 0,
                'FaltaRefacc' => 0,
                'HoraInicial' => '08:10:00',
                'HoraFinal' => '09:40:00',
                'TotalMinutos' => 90,
                'comentarios' => 'Se cambió balero',
                'Calificacion' => 5,
                'CveTejedor' => '2002',
                'NomTejedor' => 'Pedro Tejedor',
            ],
            [
                'Folio' => 'MEC00025',
                'CveOperador' => null,
                'NomOperador' => null,
                'Turno' => null,
                'Fecha' => null,
                'Ajusto' => 0,
                'Reparo' => 0,
                'Cambio' => 0,
                'Lubrico' => 0,
                'FaltaRefacc' => 0,
                'HoraInicial' => null,
                'HoraFinal' => null,
                'TotalMinutos' => null,
                'comentarios' => null,
                'Calificacion' => 5,
                'CveTejedor' => '2002',
                'NomTejedor' => 'Pedro Tejedor',
            ],
            [
                'Folio' => 'MEC00026',
                'CveOperador' => '1003',
                'NomOperador' => 'Mecánico Dos',
                'Turno' => 2,
                'Fecha' => '2026-08-13',
                'Ajusto' => 0,
                'Reparo' => 0,
                'Cambio' => 1,
                'Lubrico' => 0,
                'FaltaRefacc' => 0,
                'HoraInicial' => '10:00',
                'HoraFinal' => '10:20',
                'TotalMinutos' => 20,
                'comentarios' => null,
                'Calificacion' => null,
                'CveTejedor' => null,
                'NomTejedor' => null,
            ],
            [
                'Folio' => 'MEC00999',
                'CveOperador' => '1999',
                'NomOperador' => 'Otro paro',
                'Turno' => 1,
                'Fecha' => '2026-08-13',
                'Ajusto' => 1,
                'Reparo' => 0,
                'Cambio' => 0,
                'Lubrico' => 0,
                'FaltaRefacc' => 0,
                'HoraInicial' => '09:00',
                'HoraFinal' => '09:10',
                'TotalMinutos' => 10,
                'comentarios' => null,
                'Calificacion' => null,
                'CveTejedor' => null,
                'NomTejedor' => null,
            ],
        ]);

        $paros = $this->service()->forMachine('201', new DateTimeImmutable('2026-08-13'), new DateTimeImmutable('2026-08-13'));
        $porFolio = array_column($paros, null, 'folio');

        $this->assertSame(
            ['MEC00025', 'MEC00026'],
            array_column($porFolio['PARO-201']['ordenes'], 'folio'),
        );
        $this->assertSame('MEC00025', $porFolio['PARO-201']['ordenes'][0]['folio']);
        $this->assertSame('Terminado', $porFolio['PARO-201']['ordenes'][0]['estatus']);
        $this->assertCount(1, $porFolio['PARO-201']['ordenes'][0]['renglones']);
        $this->assertSame('Mecánico Uno', $porFolio['PARO-201']['ordenes'][0]['renglones'][0]['nomOperador']);
        $this->assertSame(['Ajustó', 'Reparó'], $porFolio['PARO-201']['ordenes'][0]['renglones'][0]['trabajos']);
        $this->assertSame(
            [true, true, false, false, false],
            array_column($porFolio['PARO-201']['ordenes'][0]['renglones'][0]['checks'], 'on'),
        );
        $this->assertSame('08:10', $porFolio['PARO-201']['ordenes'][0]['renglones'][0]['horaInicial']);
        $this->assertSame('1h 30m', $porFolio['PARO-201']['ordenes'][0]['renglones'][0]['tiempo']);
        $this->assertSame('Se cambió balero', $porFolio['PARO-201']['ordenes'][0]['renglones'][0]['comentarios']);
        $this->assertSame(5, $porFolio['PARO-201']['ordenes'][0]['renglones'][0]['calificacion']);

        $this->assertSame('MEC00026', $porFolio['PARO-201']['ordenes'][1]['folio']);
        $this->assertSame(['Cambió'], $porFolio['PARO-201']['ordenes'][1]['renglones'][0]['trabajos']);

        $this->assertSame(
            ['MEC00999'],
            array_column($porFolio['PARO-OTRO']['ordenes'], 'folio'),
        );
    }

    public function test_no_repite_renglones_identicos_de_la_misma_ot(): void
    {
        $this->insertParo([
            'Folio' => 'PARO-201',
            'Fecha' => '2026-08-13',
            'Hora' => '08:00:00',
        ]);

        DB::connection('crudo_test_catalog')->table('MecOrdenTrabajoTable')->insert([
            'Folio' => 'MEC00015',
            'FolioParo' => 'PARO-201',
            'Estatus' => 'Activo',
        ]);

        $captura = [
            'Folio' => 'MEC00015',
            'CveOperador' => '3517',
            'NomOperador' => 'Angel Galeno Velazquez',
            'Turno' => 1,
            'Fecha' => '2026-08-13',
            'Ajusto' => 0,
            'Reparo' => 1,
            'Cambio' => 1,
            'Lubrico' => 0,
            'FaltaRefacc' => 0,
            'comentarios' => 'a',
            'Calificacion' => null,
            'CveTejedor' => null,
            'NomTejedor' => null,
        ];

        DB::connection('crudo_test_catalog')->table('MecOrdenTrabajoLine')->insert([
            [...$captura, 'HoraInicial' => '07:10:00', 'HoraFinal' => '07:30:00', 'TotalMinutos' => 20],
            [...$captura, 'HoraInicial' => '07:10:00', 'HoraFinal' => '07:30:00', 'TotalMinutos' => 20],
            [...$captura, 'HoraInicial' => '08:10:00', 'HoraFinal' => '08:30:00', 'TotalMinutos' => 20],
        ]);

        $paros = $this->service()->forMachine('201', new DateTimeImmutable('2026-08-13'), new DateTimeImmutable('2026-08-13'));
        $renglones = $paros[0]['ordenes'][0]['renglones'];

        $this->assertCount(2, $renglones);
        $this->assertSame('07:10', $renglones[0]['horaInicial']);
        $this->assertSame('08:10', $renglones[1]['horaInicial']);
    }

    private function service(): CrudoParosHistoryService
    {
        return new CrudoParosHistoryService;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertParo(array $attributes): void
    {
        DB::connection('crudo_test_catalog')->table('ManFallasParos')->insert(array_merge([
            'MaquinaId' => '201',
            'Estatus' => 'Activo',
            'Depto' => 'Jacquard',
            'TipoFallaId' => 'Mecánico',
            'Falla' => 'F-1',
            'Descripcion' => 'Rotura de hilo',
            'NomEmpl' => 'Juan Pérez',
            'Turno' => 1,
        ], $attributes));
    }
}
