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
