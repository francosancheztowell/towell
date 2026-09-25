<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Karl Mayer no usa el checklist de atadoras. Abrir o iniciar ese atado
 * no muestra ni copia AtaMaquinas / AtaActividades.
 */
class CalificarAtadoKarlMayerTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $schema = Schema::connection('sqlsrv');
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");

        $schema->create('dbo.ReqTelares', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoTelarId')->nullable();
        });

        $schema->create('AtaMontadoTelas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Estatus')->nullable();
            $table->date('Fecha')->nullable();
            $table->string('Turno')->nullable();
            $table->string('NoJulio')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('Tipo')->nullable();
            $table->float('Metros')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('LoteProveedor')->nullable();
            $table->string('NoProveedor')->nullable();
            $table->float('MergaKg')->nullable();
            $table->string('HoraParo')->nullable();
            $table->string('HoraArranque')->nullable();
            $table->string('Calidad')->nullable();
            $table->string('Limpieza')->nullable();
            $table->string('CveSupervisor')->nullable();
            $table->string('NomSupervisor')->nullable();
            $table->string('Obs')->nullable();
            $table->string('CveTejedor')->nullable();
            $table->string('NomTejedor')->nullable();
            $table->dateTime('FechaSupervisor')->nullable();
            $table->string('comments_sup')->nullable();
            $table->string('comments_ata')->nullable();
            $table->string('comments_tej')->nullable();
            $table->string('HrInicio')->nullable();
            $table->date('FechaInicio')->nullable();
            $table->date('FechaParo')->nullable();
            $table->date('FechaArranque')->nullable();
            $table->string('FolioParo')->nullable();
            $table->string('ConfigId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('InventColorId')->nullable();
        });

        $schema->create('AtaDevoluciones', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('RefId')->nullable();
            $table->string('NoJulio')->nullable();
            $table->float('Kilos')->nullable();
            $table->string('Ubicacion')->nullable();
            $table->float('Metros')->nullable();
            $table->date('FechaDevol')->nullable();
            $table->string('Cuenta')->nullable();
            $table->string('Calibre')->nullable();
            $table->string('Hilo')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('Tipo')->nullable();
            $table->string('Obs')->nullable();
            $table->string('ConfigId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('InventColorId')->nullable();
            $table->string('Estatus')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('CveOperador')->nullable();
            $table->string('LoteOriginal')->nullable();
            $table->integer('InvTelasReservadaId')->nullable();
            $table->integer('AX')->nullable();
        });

        $schema->create('AtaComentarios', function (Blueprint $table) {
            $table->string('Nota1')->nullable();
            $table->string('Nota2')->nullable();
        });

        $schema->create('AtaMaquinas', function (Blueprint $table) {
            $table->string('MaquinaId')->primary();
        });

        $schema->create('AtaActividades', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('ActividadId')->nullable();
            $table->float('Porcentaje')->nullable();
        });

        $schema->create('AtaMontadoMaquinas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoJulio')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->integer('Estado')->nullable();
        });

        $schema->create('AtaMontadoActividades', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoJulio')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('ActividadId')->nullable();
            $table->float('Porcentaje')->nullable();
            $table->integer('Estado')->nullable();
            $table->string('CveEmpl')->nullable();
            $table->string('NomEmpl')->nullable();
            $table->string('Turno')->nullable();
        });

        foreach (['AtaKmMontado', 'AtaKmEnhebrado'] as $tabla) {
            $schema->create($tabla, function (Blueprint $table) {
                $table->increments('Id');
                $table->string('NoJulio')->nullable();
                $table->string('NoProduccion')->nullable();
                $table->string('CveEmpl1')->nullable();
                $table->string('NomEmpl1')->nullable();
                $table->string('CveEmpl2')->nullable();
                $table->string('NomEmpl2')->nullable();
                $table->string('CveEmpl3')->nullable();
                $table->string('NomEmpl3')->nullable();
                $table->dateTime('FechaInicio')->nullable();
                $table->dateTime('FechaFin')->nullable();
            });
        }

        $schema->create('tej_inventario_telares', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_telar')->nullable();
            $table->string('tipo')->nullable();
            $table->string('status')->nullable();
            $table->string('no_julio')->nullable();
            $table->string('no_julio2')->nullable();
            $table->string('no_julio3')->nullable();
            $table->string('no_julio4')->nullable();
            $table->string('no_orden')->nullable();
            $table->string('no_orden2')->nullable();
            $table->string('no_orden3')->nullable();
            $table->string('no_orden4')->nullable();
            $table->string('cuenta')->nullable();
            $table->string('calibre')->nullable();
            $table->string('hilo')->nullable();
            $table->date('fecha')->nullable();
            $table->string('turno')->nullable();
            $table->float('metros')->nullable();
            $table->string('horaParo')->nullable();
            $table->timestamps();
        });

        DB::connection('sqlsrv')->table('AtaMaquinas')->insert(['MaquinaId' => 'Atadora STAUBLI']);
        DB::connection('sqlsrv')->table('AtaActividades')->insert([
            'ActividadId' => 'Tendido y Atado',
            'Porcentaje' => 30,
        ]);

        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Programa Atadores', ['acceso', 'crear', 'modificar'], null, 45);
    }

    public function test_calificar_una_barra_no_muestra_ni_copia_el_checklist(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'En Proceso',
            'NoJulio' => '00667-63',
            'NoProduccion' => '00667',
            'Tipo' => '3',
            'NoTelarId' => '401',
            'Fecha' => '2026-09-24',
            'Turno' => '3',
        ]);

        $html = $this->get('/atadores/calificar?no_julio=00667-63&no_orden=00667')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Atado de barra', $html);
        $this->assertStringContainsString('Barra 3', $html);
        // Montado y Enhebrado se muestran directo en sus tarjetas (sin modal).
        $this->assertStringNotContainsString('id="modal-montado"', $html);
        $this->assertStringNotContainsString('id="modal-enhebrado"', $html);
        $this->assertStringNotContainsString('abrirProcesoKm(\'montado\')', $html);
        $this->assertStringNotContainsString('abrirProcesoKm(\'enhebrado\')', $html);
        $this->assertStringContainsString('id="montado_cve1"', $html);
        $this->assertStringContainsString('id="enhebrado_cve1"', $html);
        $this->assertStringNotContainsString('/atadores/calificar/montado', $html);
        $this->assertStringNotContainsString('/atadores/calificar/enhebrado', $html);
        $this->assertStringNotContainsString('Atadora STAUBLI', $html);
        $this->assertStringNotContainsString('No hay notas configuradas.', $html);
        $this->assertStringNotContainsString('Nota 1', $html);
        $this->assertStringNotContainsString('>Máquinas<', $html);
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaMontadoActividades')->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaMontadoMaquinas')->count());
    }

    public function test_calificar_rizo_sigue_pidiendo_el_checklist(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'En Proceso',
            'NoJulio' => '00010-1',
            'NoProduccion' => '00010',
            'Tipo' => 'Rizo',
            'NoTelarId' => '300',
            'Fecha' => '2026-09-24',
            'Turno' => '1',
        ]);

        $this->get('/atadores/calificar?no_julio=00010-1&no_orden=00010')
            ->assertOk()
            ->assertSee('Máquinas', false)
            ->assertSee('Atadora STAUBLI', false)
            ->assertSee('Tendido y Atado', false)
            ->assertSee('No hay notas configuradas.', false);

        $this->assertSame(1, DB::connection('sqlsrv')->table('AtaMontadoActividades')->count());
    }

    public function test_iniciar_una_barra_no_siembra_maquinas_ni_actividades(): void
    {
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            'id' => 1,
            'no_telar' => '401',
            'tipo' => '3',
            'status' => 'Activo',
            'no_julio' => '00667-63',
            'no_orden' => '00667',
            'fecha' => '2026-09-24',
            'turno' => '3',
            'horaParo' => '10:18:33',
        ]);

        $this->get('/atadores/iniciar?id=1&no_julio=00667-63&no_orden=00667')
            ->assertRedirect('/atadores/calificar?no_julio=00667-63&no_orden=00667');

        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaMontadoMaquinas')->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaMontadoActividades')->count());
        $this->assertSame(1, DB::connection('sqlsrv')->table('AtaMontadoTelas')->count());
    }

    public function test_devolucion_km_sin_atado_anterior_no_lista_los_julios_actuales(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'En Proceso',
            'NoJulio' => '00667-63',
            'NoProduccion' => '00667',
            'Tipo' => '3',
            'NoTelarId' => '401',
            'Fecha' => now()->toDateString(),
            'Turno' => '3',
        ]);

        $html = $this->get('/atadores/calificar?no_julio=00667-63&no_orden=00667')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('No hay un atado anterior de esta barra.', $html);
        $this->assertStringNotContainsString('data-julio="00667-63"', $html);
    }

    public function test_devolucion_km_usa_el_atado_anterior_y_no_los_julios_en_proceso(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            [
                'Estatus' => 'Autorizado',
                'NoJulio' => '00667-63',
                'NoProduccion' => '00667',
                'Tipo' => '3',
                'NoTelarId' => '401',
                'Fecha' => now()->subDays(2)->toDateString(),
                'Turno' => '1',
            ],
            [
                'Estatus' => 'En Proceso',
                'NoJulio' => 'K12',
                'NoProduccion' => '4072',
                'Tipo' => '3',
                'NoTelarId' => '401',
                'Fecha' => now()->toDateString(),
                'Turno' => '2',
            ],
        ]);
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            'no_telar' => '401',
            'tipo' => '3',
            'status' => 'En Proceso',
            'no_julio' => 'K12',
            'no_julio2' => 'K38',
            'no_julio3' => 'K34',
            'no_julio4' => 'K14',
            'no_orden' => '4072',
            'no_orden2' => '4072',
            'no_orden3' => '4072',
            'no_orden4' => '4072',
            'cuenta' => '2139',
            'calibre' => '75.00',
            'hilo' => 'FIL. 370 VOLUMINIZADO',
        ]);

        $html = $this->get('/atadores/calificar?no_julio=K12&no_orden=4072')
            ->assertOk()
            ->getContent();

        // Igual que Jacquard/Smit: se devuelve lo que se quita del telar, no lo que se está atando.
        $this->assertStringContainsString('data-julio="00667-63"', $html);
        foreach (['K12', 'K38', 'K34', 'K14'] as $julio) {
            $this->assertStringNotContainsString('data-julio="'.$julio.'"', $html);
        }
    }

    public function test_devolucion_km_con_dos_atados_anteriores_se_elige_la_orden(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            ['Estatus' => 'Autorizado', 'NoJulio' => 'A-1', 'NoProduccion' => '00100', 'Tipo' => '3',
                'NoTelarId' => '401', 'Fecha' => now()->subDays(5)->toDateString(), 'Turno' => '1'],
            ['Estatus' => 'Autorizado', 'NoJulio' => 'B-1', 'NoProduccion' => '00200', 'Tipo' => '3',
                'NoTelarId' => '401', 'Fecha' => now()->subDays(2)->toDateString(), 'Turno' => '1'],
            ['Estatus' => 'En Proceso', 'NoJulio' => 'C-1', 'NoProduccion' => '00300', 'Tipo' => '3',
                'NoTelarId' => '401', 'Fecha' => now()->toDateString(), 'Turno' => '1'],
            // Otra barra del mismo telar: no debe aparecer en el select.
            ['Estatus' => 'Autorizado', 'NoJulio' => 'Z-1', 'NoProduccion' => '00900', 'Tipo' => '2',
                'NoTelarId' => '401', 'Fecha' => now()->subDay()->toDateString(), 'Turno' => '1'],
        ]);
        foreach ([['A', '00100'], ['B', '00200']] as [$letra, $orden]) {
            DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
                'no_telar' => '401', 'tipo' => '3', 'cuenta' => '2139',
                'no_julio' => "{$letra}-1", 'no_julio2' => "{$letra}-2",
                'no_orden' => $orden, 'no_orden2' => $orden,
            ]);
        }
        $ids = DB::connection('sqlsrv')->table('AtaMontadoTelas')->pluck('Id', 'NoJulio');

        // Por defecto, la orden más reciente.
        $html = $this->get('/atadores/calificar?no_julio=C-1&no_orden=00300')->assertOk()->getContent();
        $this->assertStringContainsString('id="dev_anterior_km_select"', $html);
        $this->assertStringContainsString('Orden 00100', $html);
        $this->assertStringNotContainsString('Orden 00900', $html);
        $this->assertStringContainsString('data-julio="B-2"', $html);
        $this->assertStringNotContainsString('data-julio="A-1"', $html);

        // Eligiendo la orden anterior del select.
        $html = $this->get('/atadores/calificar?no_julio=C-1&no_orden=00300&anterior='.$ids['A-1'])->assertOk()->getContent();
        $this->assertStringContainsString('data-julio="A-2"', $html);
        $this->assertStringNotContainsString('data-julio="B-1"', $html);

        // Guardar con la orden 00200 y luego cambiar a 00100: las del atado anterior viejo se quitan.
        $this->postJson('/atadores/devoluciones', [
            'ref_id' => $ids['C-1'],
            'anterior_id' => $ids['B-1'],
            'filas' => [['no_julio' => 'B-1', 'no_produccion' => '00200', 'metros' => 5]],
        ])->assertOk();
        // Un julio de otro atado anterior se rechaza.
        $this->postJson('/atadores/devoluciones', [
            'ref_id' => $ids['C-1'],
            'anterior_id' => $ids['B-1'],
            'filas' => [['no_julio' => 'A-1', 'no_produccion' => '00100', 'metros' => 5]],
        ])->assertStatus(422);

        // Al reabrir sin elegir, queda la orden que ya tiene devolución.
        $html = $this->get('/atadores/calificar?no_julio=C-1&no_orden=00300')->assertOk()->getContent();
        $this->assertStringContainsString('data-julio="B-1"', $html);

        $this->postJson('/atadores/devoluciones', [
            'ref_id' => $ids['C-1'],
            'anterior_id' => $ids['A-1'],
            'filas' => [
                ['no_julio' => 'A-1', 'no_produccion' => '00100', 'metros' => 7],
                ['no_julio' => 'A-2', 'no_produccion' => '00100', 'kilos' => 1],
            ],
        ])->assertOk();
        $this->assertSame(
            ['A-1', 'A-2'],
            DB::connection('sqlsrv')->table('AtaDevoluciones')->orderBy('NoJulio')->pluck('NoJulio')->all()
        );
    }

    public function test_devolucion_km_toma_los_julios_del_atado_anterior(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            [
                'Estatus' => 'Autorizado',
                'NoJulio' => '00500-01',
                'NoProduccion' => '00500',
                'Tipo' => '3',
                'NoTelarId' => '401',
                'Fecha' => now()->subDays(2)->toDateString(),
                'Turno' => '1',
            ],
            [
                'Estatus' => 'En Proceso',
                'NoJulio' => '00667-63',
                'NoProduccion' => '00667',
                'Tipo' => '3',
                'NoTelarId' => '401',
                'Fecha' => now()->toDateString(),
                'Turno' => '3',
            ],
        ]);
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            'no_telar' => '401',
            'tipo' => '3',
            'no_julio' => '00500-01',
            'no_julio2' => '00500-02',
            'no_julio3' => '00500-03',
            'no_julio4' => '00500-04',
            'no_orden' => '00500',
            'no_orden2' => '00500',
            'no_orden3' => '00500',
            'no_orden4' => '00500',
            'cuenta' => '2139',
            'calibre' => '75.00',
            'hilo' => 'FIL. 370 VOLUMINIZADO',
        ]);

        $html = $this->get('/atadores/calificar?no_julio=00667-63&no_orden=00667')
            ->assertOk()
            ->getContent();

        foreach (['00500-01', '00500-02', '00500-03', '00500-04'] as $julio) {
            $this->assertStringContainsString('data-julio="'.$julio.'"', $html);
        }
        $this->assertStringNotContainsString('data-julio="00667-63"', $html);
        $this->assertStringContainsString('>Barra 3<', $html);
        $this->assertSame(2, substr_count($html, '>KM1<'));

        $montadoId = DB::connection('sqlsrv')->table('AtaMontadoTelas')->where('Estatus', 'En Proceso')->value('Id');
        $this->postJson('/atadores/devoluciones', [
            'ref_id' => $montadoId,
            'fecha_devol' => now()->toDateString(),
            'filas' => [
                ['no_julio' => '00667-63', 'no_produccion' => '00667', 'metros' => 10, 'kilos' => 1],
            ],
        ])->assertStatus(422);

        $this->postJson('/atadores/devoluciones', [
            'ref_id' => $montadoId,
            'fecha_devol' => now()->toDateString(),
            'filas' => [
                ['no_julio' => '00500-01', 'no_produccion' => '00500', 'metros' => 10, 'kilos' => 1, 'cuenta' => '2139'],
                ['no_julio' => '00500-02', 'no_produccion' => '00500', 'metros' => 11, 'kilos' => 2, 'cuenta' => '2139'],
                ['no_julio' => '00500-03', 'no_produccion' => '00500', 'metros' => 12, 'kilos' => 3, 'cuenta' => '2139'],
                ['no_julio' => '00500-04', 'no_produccion' => '00500', 'metros' => 13, 'kilos' => 4, 'cuenta' => '2139'],
            ],
        ])->assertOk();

        $guardadas = DB::connection('sqlsrv')->table('AtaDevoluciones')->orderBy('Id')->get();
        $this->assertSame(
            ['00500-01', '00500-02', '00500-03', '00500-04'],
            $guardadas->pluck('NoJulio')->all()
        );
        $this->assertSame(['KM1', 'KM1', 'KM1', 'KM1'], $guardadas->pluck('Ubicacion')->all());
        $this->assertSame(['DEV00500'], $guardadas->pluck('NoProduccion')->unique()->values()->all());
        $this->assertSame(['00500'], $guardadas->pluck('LoteOriginal')->unique()->values()->all());

        // Solo sale lo que el operador capturó: los julios sin metros ni kilos se quitan.
        $this->postJson('/atadores/devoluciones', [
            'ref_id' => $montadoId,
            'fecha_devol' => now()->toDateString(),
            'filas' => [
                ['no_julio' => '00500-01', 'no_produccion' => '00500', 'metros' => 10, 'kilos' => 1],
                ['no_julio' => '00500-02', 'no_produccion' => '00500', 'metros' => null, 'kilos' => 2],
                ['no_julio' => '00500-03', 'no_produccion' => '00500', 'metros' => null, 'kilos' => null],
                ['no_julio' => '00500-04', 'no_produccion' => '00500', 'metros' => 0, 'kilos' => 0],
            ],
        ])->assertOk();
        $this->assertSame(
            ['00500-01', '00500-02'],
            DB::connection('sqlsrv')->table('AtaDevoluciones')->orderBy('Id')->pluck('NoJulio')->all()
        );

        // Al marcar el check la tabla va vacía: no se crea nada y desmarcar no falla.
        $filasVacias = collect(['00500-01', '00500-02', '00500-03', '00500-04'])
            ->map(fn ($julio) => ['no_julio' => $julio, 'no_produccion' => '00500'])->all();
        $this->postJson('/atadores/devoluciones', [
            'ref_id' => $montadoId,
            'filas' => $filasVacias,
        ])->assertOk();
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaDevoluciones')->count());
        $this->deleteJson('/atadores/devoluciones', ['ref_id' => $montadoId])->assertOk();
    }

    public function test_autorizar_barra_guarda_los_cuatro_julios_y_el_siguiente_atado_los_devuelve(): void
    {
        Schema::connection('sqlsrv')->create('TejHistorialInventarioTelares', function (Blueprint $table) {
            foreach (['NoTelarId', 'Status', 'Tipo', 'Cuenta', 'Calibre', 'Turno', 'Fibra', 'NoJulio', 'NoProduccion',
                'TipoAtado', 'Localidad', 'LoteProveedor', 'NoProveedor', 'HoraParo'] as $columna) {
                $table->string($columna)->nullable();
            }
            $table->float('Metros')->nullable();
            $table->date('FechaAtado')->nullable();
            $table->date('FechaRequerimiento')->nullable();
        });
        Schema::connection('sqlsrv')->table('tej_inventario_telares', function (Blueprint $table) {
            $table->string('tipo_atado')->nullable();
            $table->string('localidad')->nullable();
        });

        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'Calificado',
            'NoJulio' => '00500-01',
            'NoProduccion' => '00500',
            'Tipo' => '3',
            'NoTelarId' => '401',
            'Fecha' => now()->subDay()->toDateString(),
            'Turno' => '1',
        ]);
        DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
            'no_telar' => '401',
            'tipo' => '3',
            'status' => 'Calificado',
            'no_julio' => '00500-01',
            'no_julio2' => '00500-02',
            'no_julio3' => '00500-03',
            'no_julio4' => '00500-04',
            'no_orden' => '00500',
            'no_orden2' => '00500',
            'no_orden3' => '00500',
            'no_orden4' => '00500',
            'cuenta' => '2139',
            'calibre' => '75.00',
            'hilo' => 'FIL. 370 VOLUMINIZADO',
        ]);

        $this->postJson('/atadores/save', [
            'action' => 'supervisor',
            'no_julio' => '00500-01',
            'no_orden' => '00500',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaMontadoMaquinas')->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaMontadoActividades')->count());

        $historial = DB::connection('sqlsrv')->table('TejHistorialInventarioTelares')->orderBy('NoJulio')->get();
        $this->assertSame(['00500-01', '00500-02', '00500-03', '00500-04'], $historial->pluck('NoJulio')->all());
        $this->assertSame(['3'], $historial->pluck('Tipo')->unique()->values()->all());
        $this->assertSame(0, DB::connection('sqlsrv')->table('tej_inventario_telares')->count());

        // Siguiente atado de la barra 3: el inventario anterior ya no existe, sale del historial.
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'En Proceso',
            'NoJulio' => '00667-63',
            'NoProduccion' => '00667',
            'Tipo' => '3',
            'NoTelarId' => '401',
            'Fecha' => now()->toDateString(),
            'Turno' => '3',
        ]);

        $html = $this->get('/atadores/calificar?no_julio=00667-63&no_orden=00667')
            ->assertOk()
            ->getContent();

        foreach (['00500-01', '00500-02', '00500-03', '00500-04'] as $julio) {
            $this->assertStringContainsString('data-julio="'.$julio.'"', $html);
        }
    }

    public function test_devolucion_km_recupera_los_julios_de_la_barra_desde_las_reservas(): void
    {
        Schema::connection('sqlsrv')->create('InvTelasReservadas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('NoTelarId')->nullable();
            $table->string('Tipo')->nullable();
            $table->string('InventSerialId')->nullable();
            $table->string('InventBatchId')->nullable();
            $table->integer('TejInventarioTelaresId')->nullable();
        });
        // Atado anterior ya autorizado: su fila de inventario (4263) ya no existe.
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            ['Estatus' => 'Autorizado', 'NoJulio' => '00667-63', 'NoProduccion' => '00667', 'Tipo' => '3',
                'NoTelarId' => '401', 'Fecha' => now()->subDay()->toDateString(), 'Turno' => '3'],
            ['Estatus' => 'En Proceso', 'NoJulio' => 'K12', 'NoProduccion' => '4072', 'Tipo' => '3',
                'NoTelarId' => '401', 'Fecha' => now()->toDateString(), 'Turno' => '2'],
        ]);
        foreach (['00667-63', '00667-60', '00667-70', '00667-31'] as $julio) {
            DB::connection('sqlsrv')->table('InvTelasReservadas')->insert([
                'NoTelarId' => '401', 'Tipo' => '3', 'InventSerialId' => $julio,
                'InventBatchId' => '00667', 'TejInventarioTelaresId' => 4263,
            ]);
        }
        // Reservas de la barra nueva (otra fila de inventario): no deben mezclarse.
        DB::connection('sqlsrv')->table('InvTelasReservadas')->insert([
            'NoTelarId' => '401', 'Tipo' => '3', 'InventSerialId' => 'K12',
            'InventBatchId' => '4072', 'TejInventarioTelaresId' => 4262,
        ]);

        $html = $this->get('/atadores/calificar?no_julio=K12&no_orden=4072')->assertOk()->getContent();

        foreach (['00667-63', '00667-60', '00667-70', '00667-31'] as $julio) {
            $this->assertStringContainsString('data-julio="'.$julio.'"', $html);
        }
        $this->assertStringNotContainsString('data-julio="K12"', $html);
    }

    public function test_montado_y_enhebrado_son_pantallas_distintas(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'En Proceso',
            'NoJulio' => '00667-63',
            'NoProduccion' => '00667',
            'Tipo' => '3',
            'NoTelarId' => '401',
        ]);

        $montado = $this->get('/atadores/calificar/montado?no_julio=00667-63&no_orden=00667')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="montado_cve1"', $montado);
        $this->assertStringNotContainsString('id="enhebrado_cve1"', $montado);

        $enhebrado = $this->get('/atadores/calificar/enhebrado?no_julio=00667-63&no_orden=00667')
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="enhebrado_cve1"', $enhebrado);
        $this->assertStringNotContainsString('id="montado_cve1"', $enhebrado);
    }

    public function test_guarda_montado_en_su_tabla_y_no_en_actividades(): void
    {
        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'En Proceso',
            'NoJulio' => '00667-63',
            'NoProduccion' => '00667',
            'Tipo' => '3',
            'NoTelarId' => '401',
        ]);

        $this->postJson('/atadores/save', [
            'action' => 'km_montado',
            'no_julio' => '00667-63',
            'no_orden' => '00667',
            'cve1' => '2229',
            'nombre1' => 'Franco Sanchez',
            'cve2' => '1002',
            'nombre2' => 'Ana',
            'fecha_inicio' => '2026-09-24T08:15',
            'fecha_fin' => '2026-09-25',
        ])->assertOk()->assertJsonPath('ok', true);

        $fila = DB::connection('sqlsrv')->table('AtaKmMontado')->first();
        $this->assertSame('2229', $fila->CveEmpl1);
        $this->assertSame('Franco Sanchez', $fila->NomEmpl1);
        $this->assertSame('1002', $fila->CveEmpl2);
        $this->assertSame('Ana', $fila->NomEmpl2);
        $this->assertNull($fila->CveEmpl3);
        $this->assertStringStartsWith('2026-09-24 08:15', (string) $fila->FechaInicio);
        $this->assertStringStartsWith('2026-09-25', (string) $fila->FechaFin);
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaKmEnhebrado')->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaMontadoActividades')->count());
    }
}
