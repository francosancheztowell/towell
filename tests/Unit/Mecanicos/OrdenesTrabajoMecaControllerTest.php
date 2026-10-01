<?php

declare(strict_types=1);

namespace Tests\Unit\Mecanicos;

use App\Http\Controllers\mecanicos\OrdenesTrabajoMecaController;
use App\Models\Mecanicos\MecOrdenTrabajoModel;
use App\Models\Sistema\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
            $table->integer('Calidad')->nullable();
            $table->string('CveAtendio')->nullable();
            $table->string('NomAtendio')->nullable();
        });
        $schema->create('MecOrdenTrabajoTable', function (Blueprint $table): void {
            $table->string('Folio')->primary();
            $table->string('FolioParo')->nullable();
            $table->string('Estatus')->nullable();
            $table->date('Fecha')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('TipoFalla')->nullable();
            $table->string('Falla')->nullable();
            $table->string('Orden')->nullable();
            $table->integer('Turno')->nullable();
        });
        $schema->create('MecOrdenTrabajoLine', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio');
            $table->integer('Calificacion')->nullable();
            $table->string('CveTejedor')->nullable();
            $table->string('NomTejedor')->nullable();
            $table->string('NomOperador')->nullable();
        });
        $schema->create('URDCatalogoMaquinas', function (Blueprint $table): void {
            $table->string('MaquinaId')->primary();
            $table->string('Nombre')->nullable();
            $table->string('Departamento')->nullable();
        });
        $schema->create('dbo.ReqTelares', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
        });

        Carbon::setTestNow(Carbon::parse('2026-09-02 10:00:00', 'America/Mexico_City'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_historial_de_paros_incluye_folios_ya_ligados_a_otra_orden(): void
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

        // Un mismo paro puede originar varias órdenes, así que PARO-201-USADO
        // sigue ofreciéndose aunque ya esté ligado a MEC00001. El paro de otro
        // telar sí queda fuera.
        $this->assertSame(
            ['PARO-201-USADO', 'PARO-201-A'],
            collect($payload['data'])->pluck('Folio')->all()
        );

        $conDatos = collect($payload['data'])->firstWhere('Folio', 'PARO-201-A');
        $this->assertSame('M-01 — Falla mecánica', $conDatos['FallaTexto']);
        $this->assertSame("Observación inicial\nCierre registrado", $conDatos['ComentariosTexto']);
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
            [
                'MaquinaId' => 'RECT10',
                'Nombre' => 'MAQ RECTA 10',
                'Departamento' => 'Costura',
            ],
            [
                'MaquinaId' => 'RECT2',
                'Nombre' => 'MAQ RECTA 2',
                'Departamento' => 'Costura',
            ],
            [
                'MaquinaId' => '299',
                'Nombre' => 'Itema',
                'Departamento' => 'Itema',
            ],
        ]);
        DB::connection('sqlsrv')->table('dbo.ReqTelares')->insert([
            'NoTelarId' => '201',
            'SalonTejidoId' => 'Jacquard',
        ]);

        $method = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'catalogoTelares');
        $catalogo = $method->invoke(new OrdenesTrabajoMecaController);

        // Agrupado por área y en orden natural; el nombre solo aparece si dice algo más que el ID o el área.
        $this->assertSame([
            ['id' => 'RECT2', 'label' => 'RECT2 · MAQ RECTA 2', 'grupo' => 'Costura'],
            ['id' => 'RECT10', 'label' => 'RECT10 · MAQ RECTA 10', 'grupo' => 'Costura'],
            ['id' => 'WestPoint 2', 'label' => 'WestPoint 2 · West Point', 'grupo' => 'Engomado'],
            ['id' => '299', 'label' => '299', 'grupo' => 'Itema'],
            ['id' => '201', 'label' => '201 · Salón Jacquard', 'grupo' => 'Tejido'],
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

    public function test_el_numero_de_orden_capturado_gana_al_del_paro(): void
    {
        DB::connection('sqlsrv')->table('dbo.ManFallasParos')->insert([
            'Folio' => 'PARO-201-A',
            'Estatus' => 'Activo',
            'Fecha' => '2026-09-02',
            'Hora' => '08:30:00',
            'MaquinaId' => '201',
            'OrdenTrabajo' => 'OP-100',
        ]);

        $controller = new OrdenesTrabajoMecaController;
        $origen = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'resolverOrigenCabecera');
        $cabecera = [
            'TelarId' => '201',
            'FolioParo' => 'PARO-201-A',
            'Falla' => 'texto cliente',
        ];

        $corregida = $origen->invoke($controller, [...$cabecera, 'Orden' => 'OP-999'], false);
        $this->assertSame('OP-999', $corregida['Orden']);

        $heredada = $origen->invoke($controller, [...$cabecera, 'Orden' => null], false);
        $this->assertSame('OP-100', $heredada['Orden']);
    }

    public function test_la_orden_queda_calificada_solo_con_notas_dentro_de_la_escala(): void
    {
        $orden = MecOrdenTrabajoModel::create(['Folio' => 'MEC00001', 'Estatus' => 'Terminado']);
        $completas = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'todasLasLineasCalificadas');
        $controller = new OrdenesTrabajoMecaController;

        DB::connection('sqlsrv')->table('MecOrdenTrabajoLine')->insert([
            ['Folio' => 'MEC00001', 'Calificacion' => 5],
            ['Folio' => 'MEC00001', 'Calificacion' => 1],
        ]);
        $orden->load('lineas');
        $this->assertTrue($completas->invoke($controller, $orden));

        DB::connection('sqlsrv')->table('MecOrdenTrabajoLine')
            ->insert(['Folio' => 'MEC00001', 'Calificacion' => null]);
        $orden->load('lineas');
        $this->assertFalse($completas->invoke($controller, $orden));
    }

    public function test_sistemas_elimina_renglones_en_cualquier_estatus_salvo_autorizado(): void
    {
        $user = new User;
        $user->area = 'Sistemas';
        $this->actingAs($user);
        $bloquea = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'estatusBloqueaEliminarLinea');
        $controller = new OrdenesTrabajoMecaController;

        foreach (['', 'Activo', 'Terminado', 'Calificado'] as $estatus) {
            $this->assertFalse($bloquea->invoke($controller, $estatus), "Sistemas deberia eliminar en [{$estatus}].");
        }
        $this->assertTrue($bloquea->invoke($controller, 'Autorizado'));
    }

    public function test_mecanico_solo_elimina_renglones_con_la_orden_activa(): void
    {
        $user = new User;
        $user->area = 'MANTENIMIENTO';
        $this->actingAs($user);
        $bloquea = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'estatusBloqueaEliminarLinea');
        $controller = new OrdenesTrabajoMecaController;

        $this->assertFalse($bloquea->invoke($controller, 'Activo'));
        foreach (['Terminado', 'Calificado', 'Autorizado'] as $estatus) {
            $this->assertTrue($bloquea->invoke($controller, $estatus), "El mecanico no deberia eliminar en [{$estatus}].");
        }
    }

    public function test_eliminar_el_unico_renglon_sin_calificar_pasa_la_orden_a_calificado(): void
    {
        $terminada = MecOrdenTrabajoModel::create(['Folio' => 'MEC00001', 'Estatus' => 'Terminado']);
        $activa = MecOrdenTrabajoModel::create(['Folio' => 'MEC00002', 'Estatus' => 'Activo']);
        DB::connection('sqlsrv')->table('MecOrdenTrabajoLine')->insert([
            ['Folio' => 'MEC00001', 'Calificacion' => 4],
            ['Folio' => 'MEC00002', 'Calificacion' => 4],
        ]);
        $calificar = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'calificarSiQuedaCompleta');
        $controller = new OrdenesTrabajoMecaController;

        $this->assertTrue($calificar->invoke($controller, $terminada));
        $this->assertSame('Calificado', $terminada->fresh()->Estatus);

        $this->assertFalse($calificar->invoke($controller, $activa));
        $this->assertSame('Activo', $activa->fresh()->Estatus);
    }

    public function test_maquina_no_pasa_del_largo_de_la_columna_telar_id(): void
    {
        $controller = new OrdenesTrabajoMecaController;
        $reglas = (new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'reglasCabecera'))->invoke($controller);
        $mensajes = (new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'mensajesCabecera'))->invoke($controller);

        $valida = fn (string $telar) => validator(['TelarId' => $telar, 'Falla' => 'X'], $reglas, $mensajes);

        $this->assertTrue($valida('ELEV RASU')->passes());
        $this->assertTrue($valida('1234567890')->passes());
        $this->assertSame(
            'La máquina no puede pasar de 10 caracteres.',
            $valida('12345678901')->errors()->first('TelarId')
        );
    }

    public function test_falla_es_opcional_y_tipo_de_falla_no_pasa_de_100_caracteres(): void
    {
        $controller = new OrdenesTrabajoMecaController;
        $reglas = (new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'reglasCabecera'))->invoke($controller);
        $mensajes = (new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'mensajesCabecera'))->invoke($controller);

        $this->assertContains('nullable', $reglas['Falla']);
        $this->assertNotContains('required', $reglas['Falla']);
        $this->assertTrue(validator(['TelarId' => '201'], $reglas, $mensajes)->passes());

        $this->assertTrue(validator(['TelarId' => '201', 'TipoFalla' => str_repeat('A', 100)], $reglas, $mensajes)->passes());
        $this->assertSame(
            'El tipo de falla no puede pasar de 100 caracteres.',
            validator(['TelarId' => '201', 'TipoFalla' => str_repeat('A', 101)], $reglas, $mensajes)->errors()->first('TipoFalla')
        );
    }

    public function test_orden_no_vacia_acepta_tipo_o_descripcion_de_falla(): void
    {
        $method = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'validarOrdenNoVacia');
        $controller = new OrdenesTrabajoMecaController;

        // No lanza excepción: basta con uno de los dos campos de falla.
        $method->invoke($controller, ['TelarId' => '201', 'TipoFalla' => 'Mecánica', 'Falla' => null]);
        $method->invoke($controller, ['TelarId' => '201', 'TipoFalla' => null, 'Falla' => 'Se rompió la lanzadera']);
        $this->addToAssertionCount(2);

        foreach ([
            ['TelarId' => '201', 'TipoFalla' => '  ', 'Falla' => null],
            ['TelarId' => '', 'TipoFalla' => 'Mecánica', 'Falla' => 'Rota'],
        ] as $datos) {
            try {
                $method->invoke($controller, $datos);
                $this->fail('Se esperaba ValidationException por orden vacía.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'La orden de trabajo no puede quedar vacía: captura la máquina y el tipo o la descripción de la falla.',
                    $exception->errors()['Falla'][0]
                );
            }
        }
    }

    public function test_normalizar_cabecera_deja_tipo_de_falla_vacio_en_null(): void
    {
        $method = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'normalizarCabecera');
        $controller = new OrdenesTrabajoMecaController;

        $this->assertNull($method->invoke($controller, ['TipoFalla' => '   '])['TipoFalla']);
        $this->assertSame('Eléctrica', $method->invoke($controller, ['TipoFalla' => ' Eléctrica '])['TipoFalla']);
    }

    public function test_reglas_de_linea_exigen_comentarios(): void
    {
        $method = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'reglasLinea');
        $reglas = $method->invoke(new OrdenesTrabajoMecaController);

        $this->assertContains('required', $reglas['comentarios']);
        $this->assertNotContains('nullable', $reglas['comentarios']);
    }

    public function test_linea_completa_rechaza_comentarios_vacios(): void
    {
        $method = new \ReflectionMethod(OrdenesTrabajoMecaController::class, 'validarLineaCompleta');
        $controller = new OrdenesTrabajoMecaController;

        try {
            $method->invoke($controller, [
                'CveOperador' => '123',
                'NomOperador' => 'Juan',
                'Ajusto' => true,
                'HoraInicial' => '08:00:00',
                'HoraFinal' => '09:00:00',
                'comentarios' => '   ',
            ]);
            $this->fail('Se esperaba ValidationException por comentarios vacíos.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('comentarios', $exception->errors());
        }
    }

    public function test_registros_lista_sin_lineas_y_filtra_por_columna(): void
    {
        $user = new User;
        $user->area = 'MANTENIMIENTO';
        $user->numero_empleado = '100';
        $this->actingAs($user);

        DB::connection('sqlsrv')->table('MecOrdenTrabajoTable')->insert([
            [
                'Folio' => 'MEC00001',
                'Fecha' => '2026-09-02',
                'TelarId' => '201',
                'Falla' => 'Rota',
                'Estatus' => 'Activo',
                'FolioParo' => 'P1',
                'Orden' => 'OP1',
                'Turno' => 1,
            ],
            [
                'Folio' => 'MEC00002',
                'Fecha' => '2026-09-02',
                'TelarId' => '305',
                'Falla' => 'Otro',
                'Estatus' => 'Terminado',
                'FolioParo' => 'P2',
                'Orden' => 'OP2',
                'Turno' => 2,
            ],
        ]);
        DB::connection('sqlsrv')->table('MecOrdenTrabajoLine')->insert([
            ['Folio' => 'MEC00001', 'NomOperador' => 'Juan Perez'],
            ['Folio' => 'MEC00002', 'NomOperador' => 'Ana Lopez'],
        ]);

        $response = (new OrdenesTrabajoMecaController)->registros(
            Request::create('/mecanicos/ordenes-trabajo/registros', 'GET', ['telar' => '201'])
        );
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('MEC00001', $payload['data'][0]['Folio']);
        $this->assertSame('Juan Perez', $payload['data'][0]['NomMecanico']);
        $this->assertArrayNotHasKey('lineas', $payload['data'][0]);
    }
}
