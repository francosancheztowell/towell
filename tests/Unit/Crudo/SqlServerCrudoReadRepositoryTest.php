<?php

declare(strict_types=1);

namespace Tests\Unit\Crudo;

use App\Repositories\Crudo\SqlServerCrudoReadRepository;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SqlServerCrudoReadRepositoryTest extends TestCase
{
    private string $sourceDatabase;

    private string $catalogDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDatabase = $this->temporaryDatabase();
        $this->catalogDatabase = $this->temporaryDatabase();

        config()->set('database.connections.crudo_test_source', [
            'driver' => 'sqlite',
            'database' => $this->sourceDatabase,
            'prefix' => '',
        ]);
        config()->set('database.connections.crudo_test_catalog', [
            'driver' => 'sqlite',
            'database' => $this->catalogDatabase,
            'prefix' => '',
        ]);
        config()->set('crudo.connections.source', 'crudo_test_source');
        config()->set('crudo.connections.catalog', 'crudo_test_catalog');
        config()->set('crudo.tables.headers', 'TWCRUDOTABLE');
        config()->set('crudo.tables.lines', 'TWCRUDOLINE');
        config()->set('crudo.tables.machines', 'ReqTelares');
        config()->set('crudo.tables.sequence', 'InvSecuenciaTelares');
        config()->set('crudo.tables.paros', 'ManFallasParos');
        config()->set('crudo.catalog_salons', ['Jacquard', 'Smith', 'KM']);
        config()->set('crudo.data_area_id', 'pro');
        config()->set('planeacion.programa_tejido_table', 'ReqProgramaTejido');

        DB::purge('crudo_test_source');
        DB::purge('crudo_test_catalog');
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::disconnect('crudo_test_source');
        DB::disconnect('crudo_test_catalog');

        @unlink($this->sourceDatabase);
        @unlink($this->catalogDatabase);

        parent::tearDown();
    }

    public function test_it_reads_only_the_requested_day_and_columns(): void
    {
        DB::connection('crudo_test_source')->table('TWCRUDOTABLE')->insert([
            $this->header('1', '2026-07-28 08:00:00', '201'),
            $this->header('2', '2026-07-27 08:00:00', '202'),
            [...$this->header('3', '2026-07-28 09:00:00', '203'), 'DATAAREAID' => 'oth'],
        ]);

        $rows = (new SqlServerCrudoReadRepository)
            ->headersForDate(new DateTimeImmutable('2026-07-28'));

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]->RECID);
        $this->assertSame('201', $rows[0]->TELAR);
        $this->assertSame('PB-1', $rows[0]->PURCHBARCODE);
        $this->assertObjectNotHasProperty('UNUSED', $rows[0]);
    }

    public function test_it_aggregates_the_dashboard_totals_by_machine_in_sql(): void
    {
        DB::connection('crudo_test_source')->table('TWCRUDOTABLE')->insert([
            $this->header('1', '2026-07-28 08:00:00', '201'),
            [
                ...$this->header('2', '2026-07-28 09:00:00', '201'),
                'PESO' => 25,
                'PIEZASTOTAL' => 8,
                'SEGUNDASTOTAL' => 2,
            ],
            $this->header('3', '2026-07-28 10:00:00', '202'),
            $this->header('4', '2026-07-27 10:00:00', '201'),
            [...$this->header('5', '2026-07-28 11:00:00', '201'), 'DATAAREAID' => 'oth'],
        ]);

        $rows = (new SqlServerCrudoReadRepository)
            ->aggregateHeadersForRange(
                new DateTimeImmutable('2026-07-28'),
                new DateTimeImmutable('2026-07-28'),
            );

        $this->assertCount(2, $rows);
        $this->assertSame('201', $rows[0]->TELAR);
        $this->assertSame(2, (int) $rows[0]->captureCount);
        $this->assertSame(18.0, (float) $rows[0]->pieces);
        $this->assertSame(3.0, (float) $rows[0]->seconds);
        $this->assertSame(65.0, (float) $rows[0]->kilos);
    }

    public function test_it_loads_defects_by_header_id_instead_of_scanning_by_date(): void
    {
        DB::connection('crudo_test_source')->table('TWCRUDOLINE')->insert([
            [
                'RECID' => 10,
                'REFRECID' => 1,
                'TURNO' => '1',
                'CODDEFECTOID' => '01',
                'CANTIDAD' => 2,
                'DESCRIP' => 'Error',
                'DATAAREAID' => 'pro',
                'UNUSED' => 'not-selected',
            ],
            [
                'RECID' => 11,
                'REFRECID' => 2,
                'TURNO' => '1',
                'CODDEFECTOID' => '09',
                'CANTIDAD' => 5,
                'DESCRIP' => 'Marra',
                'DATAAREAID' => 'pro',
                'UNUSED' => 'not-selected',
            ],
        ]);

        $rows = (new SqlServerCrudoReadRepository)->defectsForHeaders([1]);

        $this->assertCount(1, $rows);
        $this->assertSame(10, (int) $rows[0]->RECID);
        $this->assertSame('1', (string) $rows[0]->REFRECID);
        $this->assertObjectNotHasProperty('UNUSED', $rows[0]);
    }

    public function test_it_combines_machine_catalog_with_visual_sequence(): void
    {
        DB::connection('crudo_test_catalog')->table('ReqTelares')->insert([
            [
                'SalonTejidoId' => 'Jacquard',
                'NoTelarId' => '201',
                'Nombre' => 'JAC 201',
                'Grupo' => 'Jacquard Smith',
            ],
        ]);
        DB::connection('crudo_test_catalog')->table('InvSecuenciaTelares')->insert([
            'NoTelar' => '201',
            'TipoTelar' => 'JACQUARD',
            'Secuencia' => 7,
        ]);

        $machines = (new SqlServerCrudoReadRepository)->machines();

        $this->assertCount(1, $machines);
        $this->assertSame('201', $machines[0]['telar']);
        $this->assertSame('JAC 201', $machines[0]['name']);
        $this->assertSame(7, $machines[0]['sequence']);
    }

    public function test_it_does_not_duplicate_a_machine_when_the_sequence_join_fans_out(): void
    {
        DB::connection('crudo_test_catalog')->table('ReqTelares')->insert([
            [
                'SalonTejidoId' => 'Jacquard',
                'NoTelarId' => '201',
                'Nombre' => 'JAC 201',
                'Grupo' => 'Jacquard Smith',
            ],
        ]);
        DB::connection('crudo_test_catalog')->table('InvSecuenciaTelares')->insert([
            ['NoTelar' => '201', 'TipoTelar' => 'JACQUARD', 'Secuencia' => 7],
            ['NoTelar' => '201', 'TipoTelar' => 'OTRO', 'Secuencia' => 99],
        ]);

        $machines = (new SqlServerCrudoReadRepository)->machines();

        $this->assertCount(1, $machines);
        $this->assertSame('201', $machines[0]['telar']);
    }

    public function test_it_reads_active_stops_from_every_department(): void
    {
        DB::connection('crudo_test_catalog')->table('ManFallasParos')->insert([
            $this->paro('201', 'Calidad', 'Activo'),
            $this->paro('300', 'Tejedores', 'Activo'),
            $this->paro('Mc Coy 3', 'Urdido', 'Activo'),
            $this->paro('306', 'Desarrolladores', 'Terminado'),
        ]);

        $rows = (new SqlServerCrudoReadRepository)->activeParos();

        $this->assertCount(3, $rows);
        $this->assertEqualsCanonicalizing(
            ['Calidad', 'Tejedores', 'Urdido'],
            array_map(static fn (object $row): string => (string) $row->Depto, $rows),
        );
    }

    public function test_it_reads_the_active_program_display_fields_for_a_machine(): void
    {
        DB::connection('crudo_test_catalog')->table('ReqProgramaTejido')->insert([
            [
                'Id' => 10,
                'NoTelarId' => '201',
                'NoProduccion' => 'ORD-PROG-201',
                'TamanoClave' => 'MOD-201-GDE',
                'ItemId' => 'AX-201',
                'NombreProducto' => 'Producto de prueba',
                'FechaInicio' => '2026-08-03 08:00:00',
                'EnProceso' => 1,
            ],
            [
                'Id' => 11,
                'NoTelarId' => '202',
                'NoProduccion' => 'OTRA-ORDEN',
                'TamanoClave' => 'OTRO-MODELO',
                'ItemId' => 'OTRO-AX',
                'NombreProducto' => 'Otro producto',
                'FechaInicio' => '2026-08-03 09:00:00',
                'EnProceso' => 1,
            ],
        ]);

        $rows = (new SqlServerCrudoReadRepository)->activePrograms(['201']);

        $this->assertCount(1, $rows);
        $this->assertSame('ORD-PROG-201', $rows[0]->NoProduccion);
        $this->assertSame('MOD-201-GDE', $rows[0]->TamanoClave);
        $this->assertSame('AX-201', $rows[0]->ItemId);
        $this->assertSame('Producto de prueba', $rows[0]->NombreProducto);
    }

    private function createSchema(): void
    {
        Schema::connection('crudo_test_source')->create('TWCRUDOTABLE', function (Blueprint $table): void {
            $table->integer('RECID');
            $table->string('PRODID');
            $table->string('PURCHBARCODE')->nullable();
            $table->dateTime('TRANSDATE');
            $table->string('TELAR');
            $table->decimal('PESO', 18, 4);
            $table->decimal('PIEZAST1', 18, 4);
            $table->decimal('PIEZAST2', 18, 4);
            $table->decimal('PIEZAST3', 18, 4);
            $table->decimal('PIEZAST4', 18, 4);
            $table->decimal('PIEZASTOTAL', 18, 4);
            $table->decimal('SEGUNDASTOTAL', 18, 4);
            $table->string('EMPLID');
            $table->string('NAMEEMPLE');
            $table->string('OBSERVACIONES');
            $table->dateTime('MODIFIEDDATE');
            $table->integer('MODIFIEDTIME');
            $table->string('DATAAREAID');
            $table->string('UNUSED')->nullable();
        });

        Schema::connection('crudo_test_source')->create('TWCRUDOLINE', function (Blueprint $table): void {
            $table->integer('RECID');
            $table->integer('REFRECID');
            $table->string('TURNO');
            $table->string('CODDEFECTOID');
            $table->decimal('CANTIDAD', 18, 4);
            $table->string('DESCRIP');
            $table->string('DATAAREAID');
            $table->string('UNUSED')->nullable();
        });

        Schema::connection('crudo_test_catalog')->create('ReqTelares', function (Blueprint $table): void {
            $table->string('SalonTejidoId');
            $table->string('NoTelarId');
            $table->string('Nombre');
            $table->string('Grupo');
        });

        Schema::connection('crudo_test_catalog')->create('InvSecuenciaTelares', function (Blueprint $table): void {
            $table->string('NoTelar');
            $table->string('TipoTelar');
            $table->integer('Secuencia');
        });

        Schema::connection('crudo_test_catalog')->create('ManFallasParos', function (Blueprint $table): void {
            $table->string('MaquinaId');
            $table->string('Falla');
            $table->string('Descripcion');
            $table->string('NomEmpl');
            $table->date('Fecha');
            $table->string('Hora');
            $table->string('Depto');
            $table->string('Estatus');
        });

        Schema::connection('crudo_test_catalog')->create('ReqProgramaTejido', function (Blueprint $table): void {
            $table->integer('Id');
            $table->string('NoTelarId');
            $table->string('NoProduccion')->nullable();
            $table->string('TamanoClave')->nullable();
            $table->string('ItemId')->nullable();
            $table->string('NombreProducto')->nullable();
            $table->dateTime('FechaInicio')->nullable();
            $table->boolean('EnProceso');
        });
    }

    /**
     * @return array<string, string>
     */
    private function paro(string $machine, string $department, string $status): array
    {
        return [
            'MaquinaId' => $machine,
            'Falla' => 'Falla de prueba',
            'Descripcion' => 'Descripción de prueba',
            'NomEmpl' => 'Operador',
            'Fecha' => '2026-08-03',
            'Hora' => '08:00:00',
            'Depto' => $department,
            'Estatus' => $status,
        ];
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function header(string $recId, string $date, string $telar): array
    {
        return [
            'RECID' => $recId,
            'PRODID' => 'ORD-'.$recId,
            'PURCHBARCODE' => 'PB-'.$recId,
            'TRANSDATE' => $date,
            'TELAR' => $telar,
            'PESO' => 40,
            'PIEZAST1' => 10,
            'PIEZAST2' => 0,
            'PIEZAST3' => 0,
            'PIEZAST4' => 0,
            'PIEZASTOTAL' => 10,
            'SEGUNDASTOTAL' => 1,
            'EMPLID' => '1',
            'NAMEEMPLE' => 'Operador',
            'OBSERVACIONES' => '',
            'MODIFIEDDATE' => $date,
            'MODIFIEDTIME' => 3600,
            'DATAAREAID' => 'pro',
            'UNUSED' => 'not-selected',
        ];
    }

    private function temporaryDatabase(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crudo-test-');
        $this->assertNotFalse($path);

        return $path;
    }
}
