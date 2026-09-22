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
                $table->date('FechaInicio')->nullable();
                $table->date('FechaFin')->nullable();
            });
        }

        $schema->create('tej_inventario_telares', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no_telar')->nullable();
            $table->string('tipo')->nullable();
            $table->string('status')->nullable();
            $table->string('no_julio')->nullable();
            $table->string('no_orden')->nullable();
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
        $this->assertStringContainsString('id="modal-montado"', $html);
        $this->assertStringContainsString('id="modal-enhebrado"', $html);
        $this->assertStringContainsString('abrirProcesoKm(\'montado\')', $html);
        $this->assertStringContainsString('abrirProcesoKm(\'enhebrado\')', $html);
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
            'fecha_inicio' => '2026-09-24',
            'fecha_fin' => '2026-09-25',
        ])->assertOk()->assertJsonPath('ok', true);

        $fila = DB::connection('sqlsrv')->table('AtaKmMontado')->first();
        $this->assertSame('2229', $fila->CveEmpl1);
        $this->assertSame('Franco Sanchez', $fila->NomEmpl1);
        $this->assertSame('1002', $fila->CveEmpl2);
        $this->assertSame('Ana', $fila->NomEmpl2);
        $this->assertNull($fila->CveEmpl3);
        $this->assertStringStartsWith('2026-09-24', (string) $fila->FechaInicio);
        $this->assertStringStartsWith('2026-09-25', (string) $fila->FechaFin);
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaKmEnhebrado')->count());
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaMontadoActividades')->count());
    }
}
