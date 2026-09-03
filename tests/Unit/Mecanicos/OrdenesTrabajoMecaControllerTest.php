<?php

declare(strict_types=1);

namespace Tests\Unit\Mecanicos;

use App\Http\Controllers\mecanicos\OrdenesTrabajoMecaController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class OrdenesTrabajoMecaControllerTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        $schema = Schema::connection('sqlsrv');
        $schema->create('dbo.ManFallasParos', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('Estatus')->nullable();
            $table->date('Fecha')->nullable();
            $table->time('Hora')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->string('Falla')->nullable();
            $table->string('Descripcion')->nullable();
            $table->string('OrdenTrabajo')->nullable();
            $table->integer('Turno')->nullable();
            $table->string('Obs')->nullable();
            $table->string('ObsCierre')->nullable();
        });
        $schema->create('MecOrdenTrabajoTable', function (Blueprint $table): void {
            $table->string('Folio')->primary();
            $table->string('FolioParo')->nullable();
        });
        $schema->create('URDCatalogoMaquinas', function (Blueprint $table): void {
            $table->string('MaquinaId')->primary();
            $table->string('Nombre')->nullable();
            $table->string('Departamento')->nullable();
        });

        Carbon::setTestNow(Carbon::parse('2026-09-02 10:00:00', 'America/Mexico_City'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_historial_de_paros_solo_devuelve_folios_elegibles_del_telar(): void
    {
        DB::connection('sqlsrv')->table('dbo.ManFallasParos')->insert([
            [
                'Folio' => 'PARO-201-A',
                'Estatus' => 'Terminado',
                'Fecha' => '2026-09-02',
                'Hora' => '08:30:00',
                'MaquinaId' => '201',
                'Falla' => 'M-01',
                'Descripcion' => 'Falla mecánica',
                'OrdenTrabajo' => 'OP-100',
                'Turno' => 2,
                'Obs' => 'Observación inicial',
                'ObsCierre' => 'Cierre registrado',
            ],
            [
                'Folio' => 'PARO-201-USADO',
                'Estatus' => 'Activo',
                'Fecha' => '2026-09-02',
                'Hora' => '09:00:00',
                'MaquinaId' => '201',
                'Falla' => null,
                'Descripcion' => null,
                'OrdenTrabajo' => null,
                'Turno' => null,
                'Obs' => null,
                'ObsCierre' => null,
            ],
            [
                'Folio' => 'PARO-OTRO-TELAR',
                'Estatus' => 'Activo',
                'Fecha' => '2026-09-03',
                'Hora' => '10:00:00',
                'MaquinaId' => '202',
                'Falla' => null,
                'Descripcion' => null,
                'OrdenTrabajo' => null,
                'Turno' => null,
                'Obs' => null,
                'ObsCierre' => null,
            ],
        ]);
        DB::connection('sqlsrv')->table('MecOrdenTrabajoTable')->insert([
            'Folio' => 'MEC00001',
            'FolioParo' => 'PARO-201-USADO',
        ]);

        $response = (new OrdenesTrabajoMecaController)->parosHistorial(
            Request::create('/mecanicos/ordenes-trabajo/paros-historial', 'GET', ['TelarId' => '201'])
        );
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['captura_manual_permitida']);
        $this->assertSame(['PARO-201-A'], collect($payload['data'])->pluck('Folio')->all());
        $this->assertSame('M-01 — Falla mecánica', $payload['data'][0]['FallaTexto']);
        $this->assertSame("Observación inicial\nCierre registrado", $payload['data'][0]['ComentariosTexto']);
    }

    public function test_historial_de_paros_solo_incluye_las_ultimas_12_horas(): void
    {
        DB::connection('sqlsrv')->table('dbo.ManFallasParos')->insert([
            [
                'Folio' => 'PARO-RECIENTE',
                'Estatus' => 'Activo',
                'Fecha' => '2026-09-02',
                'Hora' => '08:00:00',
                'MaquinaId' => '201',
                'Falla' => null,
                'Descripcion' => null,
                'OrdenTrabajo' => null,
                'Turno' => 1,
                'Obs' => null,
                'ObsCierre' => null,
            ],
            [
                'Folio' => 'PARO-LIMITE',
                'Estatus' => 'Activo',
                'Fecha' => '2026-09-01',
                'Hora' => '22:00:00',
                'MaquinaId' => '201',
                'Falla' => null,
                'Descripcion' => null,
                'OrdenTrabajo' => null,
                'Turno' => 3,
                'Obs' => null,
                'ObsCierre' => null,
            ],
            [
                'Folio' => 'PARO-VIEJO',
                'Estatus' => 'Activo',
                'Fecha' => '2026-09-01',
                'Hora' => '21:59:00',
                'MaquinaId' => '201',
                'Falla' => null,
                'Descripcion' => null,
                'OrdenTrabajo' => null,
                'Turno' => 3,
                'Obs' => null,
                'ObsCierre' => null,
            ],
        ]);

        $response = (new OrdenesTrabajoMecaController)->parosHistorial(
            Request::create('/mecanicos/ordenes-trabajo/paros-historial', 'GET', ['TelarId' => '201'])
        );
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['captura_manual_permitida']);
        $this->assertSame(['PARO-RECIENTE', 'PARO-LIMITE'], collect($payload['data'])->pluck('Folio')->all());
    }

    public function test_historial_vacio_en_12_horas_permite_captura_manual(): void
    {
        DB::connection('sqlsrv')->table('dbo.ManFallasParos')->insert([
            'Folio' => 'PARO-VIEJO',
            'Estatus' => 'Activo',
            'Fecha' => '2026-08-20',
            'Hora' => '08:00:00',
            'MaquinaId' => '201',
        ]);

        $response = (new OrdenesTrabajoMecaController)->parosHistorial(
            Request::create('/mecanicos/ordenes-trabajo/paros-historial', 'GET', ['TelarId' => '201'])
        );
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['captura_manual_permitida']);
        $this->assertSame([], $payload['data']);
    }

    public function test_catalogo_de_maquinas_incluye_todo_urd_con_etiquetas_descriptivas(): void
    {
        DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->insert([
            [
                'MaquinaId' => 'WestPoint 2',
                'Nombre' => 'West Point',
                'Departamento' => 'Engomado',
            ],
            [
                'MaquinaId' => '201',
                'Nombre' => 'Jacquard',
                'Departamento' => 'Tejido',
            ],
        ]);

        $method = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'catalogoTelares');
        $catalogo = $method->invoke(new OrdenesTrabajoMecaController);

        $this->assertSame([
            ['id' => 'WestPoint 2', 'label' => 'Engomado · WestPoint 2 — West Point'],
            ['id' => '201', 'label' => 'Tejido · 201 — Jacquard'],
        ], $catalogo);
    }

    public function test_captura_manual_desvincula_el_folio_aun_con_un_paro_elegible(): void
    {
        DB::connection('sqlsrv')->table('dbo.ManFallasParos')->insert([
            'Folio' => 'PARO-201-A',
            'Estatus' => 'Activo',
            'Fecha' => '2026-09-01',
            'Hora' => '08:30:00',
            'MaquinaId' => '201',
        ]);

        $method = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'resolverOrigenCabecera');
        $datos = $method->invoke(new OrdenesTrabajoMecaController, [
            'Fecha' => '2026-09-01',
            'TelarId' => '201',
            'FolioParo' => 'Sin Folio de Paro.',
            'Falla' => 'Captura manual',
            'Comentarios' => 'Comentario manual',
        ], true);

        $this->assertNull($datos['FolioParo']);
        $this->assertSame('Captura manual', $datos['Falla']);
        $this->assertSame('Comentario manual', $datos['Comentarios']);
    }

    public function test_origen_desde_paro_conserva_fecha_de_paro_sin_pisar_la_de_creacion(): void
    {
        DB::connection('sqlsrv')->table('dbo.ManFallasParos')->insert([
            'Folio' => 'PARO-201-A',
            'Estatus' => 'Activo',
            'Fecha' => '2026-08-20',
            'Hora' => '08:30:00',
            'MaquinaId' => '201',
            'Falla' => 'M-01',
            'Descripcion' => 'Falla mecánica',
            'OrdenTrabajo' => 'OP-100',
            'Turno' => 2,
        ]);

        $controller = new OrdenesTrabajoMecaController;
        $origen = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'resolverOrigenCabecera');
        $fechaCreacion = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'fechaCreacionFolio');

        $datos = $origen->invoke($controller, [
            'Fecha' => '2026-01-01',
            'TelarId' => '999',
            'FolioParo' => 'PARO-201-A',
            'Falla' => 'texto cliente',
        ], false);
        $datos['Fecha'] = $fechaCreacion->invoke($controller);

        $this->assertSame('2026-09-02', $datos['Fecha']);
        $this->assertSame('2026-08-20', $datos['FechaParo']);
        $this->assertSame('201', $datos['TelarId']);
        $this->assertSame('M-01 — Falla mecánica', $datos['Falla']);
    }
}
