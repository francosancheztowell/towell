<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Atadores\ProgramaAtadoresListado;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

class ProgramaAtadoresListadoTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->withoutVite();
        $this->createAuthTable();
        $this->crearTablas();
    }

    public function test_atador_sin_filtro_solo_ve_activo_y_en_proceso(): void
    {
        $this->entrarComoAtador();
        $this->sembrarFilas();

        $julios = $this->julios(null);

        $this->assertSame(['JUL-ACTIVO', 'JUL-CERO', 'JUL-PROCESO', 'JUL-VIEJO'], $julios);
    }

    public function test_atador_con_filtro_todos_ve_el_resto_y_una_sola_fila_por_atado(): void
    {
        $this->entrarComoAtador();
        $this->sembrarFilas();

        $filas = app(ProgramaAtadoresListado::class)->filas(auth()->user(), 'todos');
        $porJulio = $filas->keyBy(fn ($fila) => (string) $fila->no_julio);

        $this->assertSame('Terminado', $porJulio['JUL-DUP']->status_proceso);
        $this->assertSame(1, $filas->where('no_julio', 'JUL-DUP')->count());
        $this->assertTrue($porJulio->has('JUL-CAL'));
        $this->assertSame('0.00', number_format((float) $porJulio['JUL-CERO']->metros, 2));
    }

    public function test_filtro_autorizados_usa_el_estatus_mas_reciente(): void
    {
        $this->entrarComoAtador();
        $this->sembrarFilas();

        $julios = $this->julios('autorizados');

        $this->assertSame(['JUL-AUT'], $julios);
    }

    public function test_tejedor_sin_filtro_solo_ve_sus_telares_terminados(): void
    {
        $usuario = $this->createUsuario([
            'area' => 'Tejedores',
            'puesto' => 'Operador',
            'numero_empleado' => '2002',
        ]);
        $this->actingAs($usuario);
        DB::connection('sqlsrv')->table('TelTelaresOperador')->insert([
            'numero_empleado' => '2002',
            'NoTelarId' => '15',
        ]);
        $this->sembrarFilas();

        $this->assertSame(['JUL-TERM'], $this->julios(null));
    }

    public function test_la_pantalla_separa_activo_de_en_proceso_y_no_trae_el_modal_muerto(): void
    {
        $this->entrarComoAtador();
        $this->sembrarFilas();

        $html = $this->get(route('atadores.programa', ['filtro' => 'todos']))
            ->assertOk()
            ->assertSee('Tipo atado', false)
            ->assertSee("case 'activo': return status === 'Activo';", false)
            ->assertSee('JUL-CERO', false)
            ->assertSee('0.00', false)
            ->assertSee('programaatadores\/estatus', false)
            ->getContent();

        $this->assertStringNotContainsString('de J', $html);
        $this->assertStringNotContainsString('modalReporteFecha', $html);
        $this->assertSame(1, substr_count($html, 'data-no-julio="JUL-DUP"'));
    }

    public function test_estatus_respeta_el_filtro_de_la_pantalla(): void
    {
        $this->entrarComoAtador();
        $this->sembrarFilas();

        $this->getJson(route('atadores.programa.estatus', ['filtro' => 'autorizados']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['status' => 'Autorizado']);
    }

    public function test_iniciar_exige_permiso_de_crear(): void
    {
        $middleware = Route::getRoutes()->getByName('atadores.iniciar')->gatherMiddleware();

        $this->assertContains('module.permission:crear,45', $middleware);
    }

    private function entrarComoAtador(): void
    {
        $usuario = $this->createUsuario([
            'area' => 'Atadores',
            'puesto' => 'Atador',
            'numero_empleado' => '1001',
        ]);
        $this->actingAs($usuario);
        $this->grantModulo('Programa Atadores', ['acceso', 'crear'], null, 45);
    }

    /**
     * @return array<int, string>
     */
    private function julios(?string $filtro): array
    {
        return app(ProgramaAtadoresListado::class)
            ->filas(auth()->user(), $filtro)
            ->pluck('no_julio')
            ->map(fn ($julio) => (string) $julio)
            ->sort()
            ->values()
            ->all();
    }

    private function sembrarFilas(): void
    {
        $inventario = [
            ['julio' => 'JUL-ACTIVO', 'orden' => 'O1', 'telar' => '10', 'metros' => 10, 'estatus' => null],
            ['julio' => 'JUL-PROCESO', 'orden' => 'O2', 'telar' => '11', 'metros' => 10, 'estatus' => 'En Proceso'],
            ['julio' => 'JUL-TERM', 'orden' => 'O3', 'telar' => '15', 'metros' => 10, 'estatus' => 'Terminado'],
            ['julio' => 'JUL-CAL', 'orden' => 'O4', 'telar' => '12', 'metros' => 10, 'estatus' => 'Calificado'],
            ['julio' => 'JUL-CERO', 'orden' => 'O5', 'telar' => '13', 'metros' => 0, 'estatus' => null],
            ['julio' => 'JUL-DUP', 'orden' => 'O6', 'telar' => '14', 'metros' => 10, 'estatus' => 'En Proceso'],
            ['julio' => 'JUL-AUT', 'orden' => 'O7', 'telar' => '16', 'metros' => 10, 'estatus' => 'Autorizado'],
            ['julio' => 'JUL-VIEJO', 'orden' => 'O8', 'telar' => '17', 'metros' => 10, 'estatus' => 'Autorizado'],
        ];

        foreach ($inventario as $fila) {
            DB::connection('sqlsrv')->table('tej_inventario_telares')->insert([
                'fecha' => '2026-08-17',
                'turno' => 1,
                'no_telar' => $fila['telar'],
                'tipo' => 'Rizo',
                'no_julio' => $fila['julio'],
                'no_orden' => $fila['orden'],
                'metros' => $fila['metros'],
                'calibre' => 0,
                'cuenta' => '40',
                'loteProveedor' => 'L1',
                'noProveedor' => 'P1',
                'horaParo' => '08:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($fila['estatus'] === null) {
                continue;
            }

            DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
                'Estatus' => $fila['estatus'],
                'Fecha' => '2026-08-17',
                'Turno' => '1',
                'NoJulio' => $fila['julio'],
                'NoProduccion' => $fila['orden'],
                'NoTelarId' => $fila['telar'],
                'Metros' => $fila['metros'],
                'Tipo' => 'Rizo',
            ]);
        }

        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'Terminado',
            'Fecha' => '2026-08-18',
            'Turno' => '1',
            'NoJulio' => 'JUL-DUP',
            'NoProduccion' => 'O6',
            'NoTelarId' => '14',
            'Metros' => 10,
            'Tipo' => 'Rizo',
        ]);

        DB::connection('sqlsrv')->table('AtaMontadoTelas')->insert([
            'Estatus' => 'En Proceso',
            'Fecha' => '2026-08-18',
            'Turno' => '2',
            'NoJulio' => 'JUL-VIEJO',
            'NoProduccion' => 'O8',
            'NoTelarId' => '17',
            'Metros' => 10,
            'Tipo' => 'Rizo',
        ]);
    }

    private function crearTablas(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->create('tej_inventario_telares', function (Blueprint $table) {
            $table->increments('id');
            $table->date('fecha')->nullable();
            $table->integer('turno')->nullable();
            $table->string('no_telar')->nullable();
            $table->string('tipo')->nullable();
            $table->string('no_julio')->nullable();
            $table->string('no_julio2')->nullable();
            $table->string('no_julio3')->nullable();
            $table->string('no_julio4')->nullable();
            $table->string('localidad')->nullable();
            $table->float('metros')->nullable();
            $table->string('no_orden')->nullable();
            $table->string('tipo_atado')->nullable();
            $table->string('cuenta')->nullable();
            $table->float('calibre')->nullable();
            $table->string('hilo')->nullable();
            $table->string('ConfigId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('InventColorId')->nullable();
            $table->string('loteProveedor')->nullable();
            $table->string('noProveedor')->nullable();
            $table->string('horaParo')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $schema->create('AtaMontadoTelas', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Estatus')->nullable();
            $table->date('Fecha')->nullable();
            $table->string('Turno')->nullable();
            $table->string('NoJulio')->nullable();
            $table->string('NoProduccion')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->float('Metros')->nullable();
            $table->string('Tipo')->nullable();
            $table->string('LoteProveedor')->nullable();
            $table->string('NoProveedor')->nullable();
            $table->string('HoraParo')->nullable();
            $table->string('ConfigId')->nullable();
            $table->string('InventSizeId')->nullable();
            $table->string('InventColorId')->nullable();
        });

        $schema->create('TelTelaresOperador', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('numero_empleado')->nullable();
            $table->string('NoTelarId')->nullable();
        });
    }
}
