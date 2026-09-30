<?php

declare(strict_types=1);

namespace Tests\Feature\Mantenimiento;

use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Sistema\User;
use App\Services\Mecanicos\CalificacionParoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Caracterización del API de paros (19-08): lo que ven nuevo paro, Solicitudes y
 * finalizar paro. Se escribió ANTES de tocar el controller (cobertura 17.8 % en 22-01).
 */
class MantenimientoParosApiTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('services.telegram.bot_token', '');
        $this->createAuthTable();

        $schema = Schema::connection('sqlsrv');

        $this->createTablaDbo('SysDepartamentos', ['Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'Depto' => 'TEXT']);
        $this->createTablaDbo('ManFallasParos', [
            'Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'Folio' => 'TEXT', 'Estatus' => 'TEXT', 'Fecha' => 'TEXT', 'Hora' => 'TEXT', 'Depto' => 'TEXT',
            'MaquinaId' => 'TEXT', 'TipoFallaId' => 'TEXT', 'Falla' => 'TEXT', 'Descripcion' => 'TEXT',
            'HoraFin' => 'TEXT', 'CveEmpl' => 'TEXT', 'NomEmpl' => 'TEXT', 'Turno' => 'INTEGER',
            'CveAtendio' => 'TEXT', 'NomAtendio' => 'TEXT', 'TurnoAtendio' => 'INTEGER', 'Obs' => 'TEXT',
            'OrdenTrabajo' => 'TEXT', 'Enviado' => 'INTEGER', 'ObsCierre' => 'TEXT', 'Calidad' => 'INTEGER',
            'FechaFin' => 'TEXT',
        ]);
        $this->createTablaDbo('ManOperadoresMantenimiento', [
            'Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'CveEmpl' => 'TEXT', 'NomEmpl' => 'TEXT', 'Turno' => 'INTEGER', 'Depto' => 'TEXT', 'Telefono' => 'TEXT',
        ]);
        $this->createTablaDbo('SSYSFoliosSecuencias', [
            'Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'modulo' => 'TEXT', 'prefijo' => 'TEXT', 'consecutivo' => 'INTEGER',
        ]);
        $conexion = DB::connection();
        $conexion->statement("ATTACH DATABASE ':memory:' AS INFORMATION_SCHEMA");
        $conexion->statement('CREATE TABLE INFORMATION_SCHEMA.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach (['Id', 'modulo', 'prefijo', 'consecutivo'] as $columna) {
            $conexion->table('INFORMATION_SCHEMA.COLUMNS')->insert(['TABLE_SCHEMA' => 'dbo', 'TABLE_NAME' => 'SSYSFoliosSecuencias', 'COLUMN_NAME' => $columna]);
        }
        DB::table('dbo.SSYSFoliosSecuencias')->insert(['modulo' => 'ParosFallas', 'prefijo' => 'PF', 'consecutivo' => 41]);

        $schema->create('CatParosFallas', function (Blueprint $table) {
            $table->increments('Id');
            foreach (['TipoFallaId', 'Departamento', 'Falla', 'Descripcion', 'Abreviado', 'Seccion'] as $c) {
                $table->string($c)->nullable();
            }
        });
        $schema->create('URDCatalogoMaquinas', function (Blueprint $table) {
            $table->string('MaquinaId')->primary();
            $table->string('Nombre')->nullable();
            $table->string('Departamento');
        });
        $schema->create('AtaMaquinas', function (Blueprint $table) {
            $table->string('MaquinaId')->primary();
        });
        $schema->create('TelTelaresOperador', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('numero_empleado')->nullable();
            $table->string('NoTelarId')->nullable();
            $table->string('SalonTejidoId')->nullable();
        });
        $schema->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaProg')->nullable();
        });

        foreach (['Engomado', 'Tejedores', 'Urdido'] as $depto) {
            DB::table('dbo.SysDepartamentos')->insert(['Depto' => $depto]);
        }

        // La propagación a órdenes de Mecánicos no es de este módulo.
        $this->app->instance(CalificacionParoService::class, new class extends CalificacionParoService
        {
            public function propagarAOrdenesDelParo(ManFallasParos $paro): int
            {
                return 0;
            }
        });
    }

    private function entrar(array $atributos = []): User
    {
        $usuario = $this->createUsuario($atributos);
        $this->actingAs($usuario, 'web');

        return $usuario;
    }

    private function falla(string $tipo = 'MECANICO', string $depto = 'Urdido', string $falla = 'Rotura'): int
    {
        return (int) DB::table('CatParosFallas')->insertGetId([
            'TipoFallaId' => $tipo, 'Departamento' => $depto, 'Falla' => $falla, 'Descripcion' => $falla.' desc',
        ]);
    }

    private function paro(array $datos = []): int
    {
        return (int) DB::table('dbo.ManFallasParos')->insertGetId(array_merge([
            'Folio' => 'PF00001', 'Estatus' => 'Activo', 'Fecha' => now()->toDateString(), 'Hora' => '08:00:00',
            'Depto' => 'Engomado', 'MaquinaId' => 'WP2', 'TipoFallaId' => 'MECANICO', 'Falla' => 'Rotura',
            'NomEmpl' => 'Usuario Prueba', 'CveEmpl' => '1001',
        ], $datos));
    }

    public function test_departamentos_lista_todos(): void
    {
        $this->entrar();

        $this->getJson(route('api.mantenimiento.departamentos'))
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['Engomado', 'Tejedores', 'Urdido']]);
    }

    /** BUG-025: el usuario 6 ya no está limitado a Urdido y Engomado. */
    public function test_departamentos_no_distingue_al_usuario_6(): void
    {
        DB::table('SYSUsuario')->insert(['idusuario' => 6, 'nombre' => 'Seis', 'contrasenia' => 'x', 'area' => 'Urdido']);
        $this->actingAs(User::findOrFail(6), 'web');

        $this->getJson(route('api.mantenimiento.departamentos'))
            ->assertOk()
            ->assertJsonPath('data', ['Engomado', 'Tejedores', 'Urdido']);
    }

    public function test_catalogo_de_filtros_quita_vacios(): void
    {
        DB::table('dbo.SysDepartamentos')->insert(['Depto' => '  ']);
        $this->entrar();

        $this->getJson(route('api.mantenimiento.departamentos.catalogo-filtros'))
            ->assertOk()
            ->assertJsonPath('data', ['Engomado', 'Tejedores', 'Urdido']);
    }

    public function test_maquinas_por_departamento(): void
    {
        DB::table('URDCatalogoMaquinas')->insert([
            ['MaquinaId' => 'MC1', 'Nombre' => 'Urdidora 1', 'Departamento' => 'Urdido'],
            ['MaquinaId' => 'WP2', 'Nombre' => 'West Point 2', 'Departamento' => 'Engomado'],
        ]);
        DB::table('AtaMaquinas')->insert(['MaquinaId' => 'AT1']);
        DB::table('TelTelaresOperador')->insert([
            ['numero_empleado' => '1001', 'NoTelarId' => '205', 'SalonTejidoId' => 'Jacquard'],
            ['numero_empleado' => '1001', 'NoTelarId' => '201', 'SalonTejidoId' => 'Smith'],
            ['numero_empleado' => '9999', 'NoTelarId' => '300', 'SalonTejidoId' => 'Smith'],
        ]);
        $this->entrar();

        $this->getJson(route('api.mantenimiento.maquinas', 'Urdido'))->assertJsonPath('data.0.MaquinaId', 'MC1')->assertJsonCount(1, 'data');
        $this->getJson(route('api.mantenimiento.maquinas', 'Atadores'))->assertJsonPath('data.0.Nombre', 'AT1');
        $this->getJson(route('api.mantenimiento.maquinas', 'Tejedores'))
            ->assertJsonPath('data', [
                ['MaquinaId' => '201', 'Nombre' => '201', 'Departamento' => 'Tejedores'],
                ['MaquinaId' => '205', 'Nombre' => '205', 'Departamento' => 'Tejedores'],
            ]);
        $this->getJson(route('api.mantenimiento.maquinas', 'Smith'))->assertJsonPath('data.0.MaquinaId', '201')->assertJsonCount(1, 'data');
    }

    public function test_tipos_de_falla_y_fallas_del_catalogo(): void
    {
        $this->falla('MECANICO', 'Urdido', 'Rotura');
        $this->falla('ELECTRICO', 'Urdido', 'Corto');
        $this->falla('CALIDAD', 'Calidad', 'Mancha');
        $this->falla('MECANICO', 'Tejido', 'Pie');
        $this->entrar();

        $this->getJson(route('api.mantenimiento.tipos-falla', 'Urdido'))
            ->assertJsonPath('data', ['CALIDAD', 'ELECTRICO', 'MECANICO']);

        $this->getJson(route('api.mantenimiento.fallas', ['departamento' => 'Urdido', 'tipoFallaId' => 'MECANICO']))
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.Falla', 'Rotura');

        // Los salones de tejido reutilizan el catálogo "Tejido"; CALIDAD suma el de Calidad.
        $this->getJson(route('api.mantenimiento.fallas', ['departamento' => 'Jacquard', 'tipoFallaId' => 'MECANICO']))
            ->assertJsonPath('data.0.Falla', 'Pie');
        $this->getJson(route('api.mantenimiento.fallas', ['departamento' => 'Urdido', 'tipoFallaId' => 'CALIDAD']))
            ->assertJsonPath('data.0.Falla', 'Mancha');
    }

    public function test_orden_de_trabajo_de_urdido(): void
    {
        DB::table('UrdProgramaUrdido')->insert([
            ['Folio' => 'U100', 'MaquinaId' => 'MC1', 'Status' => 'En Proceso', 'FechaProg' => '2026-09-01'],
            ['Folio' => 'U101', 'MaquinaId' => 'MC1', 'Status' => 'Finalizado', 'FechaProg' => '2026-09-02'],
        ]);
        $this->entrar();

        $this->getJson(route('api.mantenimiento.orden-trabajo', ['departamento' => 'Urdido', 'maquina' => 'MC1']))
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.Orden_Prod', 'U100');
    }

    public function test_store_crea_el_paro_con_datos_del_catalogo_y_folio(): void
    {
        $fallaId = $this->falla();
        $this->entrar(['nombre' => 'Operador Uno', 'numero_empleado' => '555']);

        $this->postJson(route('api.mantenimiento.paros.store'), [
            'depto' => 'Urdido', 'maquina' => 'MC1', 'falla_id' => $fallaId, 'orden_trabajo' => 'U100', 'obs' => 'ruido',
        ])->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.folio', 'PF00042');

        $paro = ManFallasParos::firstOrFail();
        $this->assertSame('Activo', $paro->Estatus);
        $this->assertSame('Rotura desc', $paro->Descripcion);
        $this->assertSame('555', $paro->CveEmpl);
        $this->assertSame('Operador Uno', $paro->NomEmpl);
    }

    public function test_store_rechaza_duplicado_activo_validacion_y_falla_inexistente(): void
    {
        $fallaId = $this->falla();
        $this->paro(['MaquinaId' => 'MC1', 'TipoFallaId' => 'MECANICO']);
        $this->entrar();

        $this->postJson(route('api.mantenimiento.paros.store'), ['depto' => 'Urdido', 'maquina' => 'MC1', 'falla_id' => $fallaId])
            ->assertStatus(422)->assertJsonStructure(['error', 'errors' => ['falla_id']]);

        $this->postJson(route('api.mantenimiento.paros.store'), ['depto' => 'Urdido', 'maquina' => 'MC1', 'falla_id' => $fallaId, 'orden_trabajo' => 'con espacio'])
            ->assertStatus(422)->assertJsonPath('error', 'La orden de trabajo no puede llevar espacios.');

        $this->postJson(route('api.mantenimiento.paros.store'), ['depto' => 'Urdido', 'maquina' => 'MC9', 'falla_id' => 999])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(1, ManFallasParos::count());
    }

    public function test_index_filtra_por_area_alcance_y_depto(): void
    {
        $this->paro(['Folio' => 'A', 'Depto' => 'Engomado']);
        $this->paro(['Folio' => 'B', 'Depto' => 'Urdido']);
        $this->paro(['Folio' => 'C', 'Depto' => 'Engomado', 'Estatus' => 'Terminado']);
        $this->paro(['Folio' => 'D', 'Depto' => 'Engomado', 'Estatus' => 'Terminado', 'Fecha' => now()->subDays(90)->toDateString()]);
        $this->entrar(['area' => 'Engomado']);

        $folios = fn (string $query) => collect($this->getJson(route('api.mantenimiento.paros.index').$query)->assertOk()->json('data'))->pluck('Folio')->sort()->values()->all();

        $this->assertSame(['A'], $folios(''));
        $this->assertSame(['A', 'B'], $folios('?alcance=todos'));
        $this->assertSame(['B'], $folios('?depto=Urdido'));
        $this->assertSame([], $folios('?depto=Inventado'));
        $this->assertSame(['A', 'C'], $folios('?incluir_finalizados=1'));
    }

    public function test_index_de_tejedores_solo_ve_sus_telares(): void
    {
        DB::table('TelTelaresOperador')->insert(['numero_empleado' => '1001', 'NoTelarId' => '205', 'SalonTejidoId' => 'Jacquard']);
        $this->paro(['Folio' => 'T1', 'Depto' => 'Tejedores', 'MaquinaId' => '205']);
        $this->paro(['Folio' => 'T2', 'Depto' => 'Tejedores', 'MaquinaId' => '300']);
        $this->paro(['Folio' => 'E1', 'Depto' => 'Engomado', 'MaquinaId' => 'WP2']);
        $this->entrar(['area' => 'Tejedores']);

        $this->getJson(route('api.mantenimiento.paros.index'))->assertJsonCount(1, 'data')->assertJsonPath('data.0.Folio', 'T1');
        $this->assertSame(
            ['E1', 'T1'],
            collect($this->getJson(route('api.mantenimiento.paros.index').'?alcance=todos')->json('data'))->pluck('Folio')->sort()->values()->all()
        );
    }

    public function test_show_y_404(): void
    {
        $id = $this->paro();
        $this->entrar();

        $this->getJson(route('api.mantenimiento.paros.show', $id))->assertOk()->assertJsonPath('data.Folio', 'PF00001');
        $this->getJson(route('api.mantenimiento.paros.show', 9999))->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_finalizar_cierra_una_sola_vez(): void
    {
        $id = $this->paro();
        $this->entrar(['numero_empleado' => '777']);

        $this->putJson(route('api.mantenimiento.paros.finalizar', $id), ['atendio' => 'Mecánico', 'calidad' => 6])
            ->assertStatus(422)->assertJsonPath('error', 'La calificación no puede pasar de 5.');

        $this->putJson(route('api.mantenimiento.paros.finalizar', $id), ['atendio' => 'Mecánico', 'turno' => 2, 'calidad' => 4, 'obs_cierre' => 'ok'])
            ->assertOk()->assertJsonPath('data.ordenes_calificadas', 0);

        $paro = ManFallasParos::findOrFail($id);
        $this->assertSame('Terminado', $paro->Estatus);
        $this->assertSame(4, $paro->Calidad);
        $this->assertSame(2, $paro->TurnoAtendio);
        $this->assertSame('777', $paro->CveAtendio);

        $this->putJson(route('api.mantenimiento.paros.finalizar', $id), ['atendio' => 'Otro', 'calidad' => 1])
            ->assertStatus(422)->assertJsonPath('error', 'Este paro ya fue finalizado.');
        $this->putJson(route('api.mantenimiento.paros.finalizar', 9999), ['atendio' => 'Otro', 'calidad' => 1])->assertNotFound();
    }

    public function test_operadores_para_el_combo_atendio(): void
    {
        DB::table('dbo.ManOperadoresMantenimiento')->insert([
            ['CveEmpl' => '2', 'NomEmpl' => 'Zeta', 'Turno' => 2, 'Depto' => 'Mantto'],
            ['CveEmpl' => '1', 'NomEmpl' => 'Alfa', 'Turno' => 1, 'Depto' => 'Mantto'],
        ]);
        $this->entrar();

        $this->getJson(route('api.mantenimiento.operadores'))->assertOk()
            ->assertJsonPath('data.0.NomEmpl', 'Alfa')->assertJsonPath('data.1.Turno', 2);
    }

    /** SEC-07: un 500 no le enseña al usuario el texto de la excepción (SQL, tablas). */
    public function test_errores_de_servidor_no_filtran_el_detalle(): void
    {
        $this->entrar();
        Schema::connection('sqlsrv')->drop('CatParosFallas');
        DB::connection()->statement('DROP TABLE dbo.ManFallasParos');
        DB::connection()->statement('DROP TABLE dbo.ManOperadoresMantenimiento');
        DB::connection()->statement('DROP TABLE dbo.SysDepartamentos');

        $urls = [
            route('api.mantenimiento.departamentos.catalogo-filtros'),
            route('api.mantenimiento.tipos-falla', 'Urdido'),
            route('api.mantenimiento.fallas', 'Urdido'),
            route('api.mantenimiento.maquinas', 'Tejedores').'?x=1',
            route('api.mantenimiento.paros.index').'?alcance=todos',
            route('api.mantenimiento.paros.show', 1),
            route('api.mantenimiento.operadores'),
        ];
        Schema::connection('sqlsrv')->drop('TelTelaresOperador');

        foreach ($urls as $url) {
            $respuesta = $this->getJson($url)->assertStatus(500);
            $this->assertStringNotContainsStringIgnoringCase('no such table', $respuesta->getContent(), $url);
            $this->assertStringNotContainsStringIgnoringCase('SQLSTATE', $respuesta->getContent(), $url);
            $respuesta->assertJsonPath('success', false)->assertJsonStructure(['message', 'trace_id']);
        }

        foreach ([
            ['POST', route('api.mantenimiento.paros.store'), ['depto' => 'Urdido', 'maquina' => 'MC1', 'falla_id' => 1]],
            ['PUT', route('api.mantenimiento.paros.finalizar', 1), ['atendio' => 'X', 'calidad' => 3]],
        ] as [$metodo, $url, $datos]) {
            $respuesta = $this->json($metodo, $url, $datos)->assertStatus(500);
            $this->assertStringNotContainsStringIgnoringCase('no such table', $respuesta->getContent(), $url);
            $respuesta->assertJsonStructure(['message', 'trace_id']);
        }
    }
}
