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
        $schema->create('SYSDepartamentosAsignacion', function (Blueprint $table) {
            $table->increments('Id');
            $table->integer('idusuario');
            $table->string('Departamento');
            $table->boolean('Supervisor')->default(false);
        });
        $schema->create('UrdProgramaUrdido', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Folio')->nullable();
            $table->string('MaquinaId')->nullable();
            $table->string('Status')->nullable();
            $table->date('FechaProg')->nullable();
        });

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

    /**
     * Asigna al usuario el departamento de esas máquinas (la asignación es por departamento: ve
     * todas sus máquinas). Las que no estén en el catálogo se dan de alta en $depto.
     */
    private function asignar(User $usuario, array $maquinas, string $depto = 'Urdido'): void
    {
        foreach ($maquinas as $maquina) {
            if (! DB::table('URDCatalogoMaquinas')->where('MaquinaId', $maquina)->exists()) {
                $this->maquinaUrdido($maquina, $depto);
            }
            $deptoMaquina = DB::table('URDCatalogoMaquinas')->where('MaquinaId', $maquina)->value('Departamento');
            DB::table('SYSDepartamentosAsignacion')->updateOrInsert(['idusuario' => $usuario->getKey(), 'Departamento' => $deptoMaquina]);
        }
    }

    private function falla(string $tipo = 'MECANICO', string $depto = 'Urdido', string $falla = 'Rotura'): int
    {
        return (int) DB::table('CatParosFallas')->insertGetId([
            'TipoFallaId' => $tipo, 'Departamento' => $depto, 'Falla' => $falla, 'Descripcion' => $falla.' desc',
        ]);
    }

    private function maquinaUrdido(string $id = 'MC1', string $depto = 'Urdido'): void
    {
        DB::table('URDCatalogoMaquinas')->insert(['MaquinaId' => $id, 'Nombre' => $id, 'Departamento' => $depto]);
    }

    private function paro(array $datos = []): int
    {
        return (int) DB::table('dbo.ManFallasParos')->insertGetId(array_merge([
            'Folio' => 'PF00001', 'Estatus' => 'Activo', 'Fecha' => now()->toDateString(), 'Hora' => '08:00:00',
            'Depto' => 'Engomado', 'MaquinaId' => 'WP2', 'TipoFallaId' => 'MECANICO', 'Falla' => 'Rotura',
            'NomEmpl' => 'Usuario Prueba', 'CveEmpl' => '1001',
        ], $datos));
    }

    public function test_departamentos_son_los_de_sus_maquinas_asignadas(): void
    {
        $this->maquinaUrdido('KM9', 'Costura');
        $usuario = $this->entrar(['area' => 'Tejedores']);
        $this->asignar($usuario, ['205', '206'], 'Jacquard');
        $this->asignar($usuario, ['MC1'], 'Urdido');

        $this->getJson(route('api.mantenimiento.departamentos'))
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['Jacquard', 'Urdido']]);
    }

    /** Sin asignación no reporta en ningún lado; Atadores y Calidad entran por área. */
    public function test_sin_asignacion_no_hay_departamentos_salvo_atadores_y_calidad(): void
    {
        $this->maquinaUrdido('MC1');
        $this->entrar(['area' => 'Tejedores']);
        $this->getJson(route('api.mantenimiento.departamentos'))->assertJsonPath('data', []);
        $this->get(route('mantenimiento.nuevo-paro'))->assertOk()->assertSee('"departamentos":[]', false);

        $this->entrar(['area' => 'Atadores']);
        $this->getJson(route('api.mantenimiento.departamentos'))->assertJsonPath('data', ['Atadores']);

        $calidad = $this->entrar(['area' => 'Calidad']);
        $this->asignar($calidad, ['MC1']);
        $this->getJson(route('api.mantenimiento.departamentos'))->assertJsonPath('data', ['Calidad', 'Urdido']);
    }

    public function test_catalogo_de_filtros_son_los_departamentos_de_maquinas(): void
    {
        $this->maquinaUrdido('MC1');
        $this->maquinaUrdido('CVERT1', 'Costura');
        $this->maquinaUrdido('X', '  ');
        $this->entrar();

        $this->getJson(route('api.mantenimiento.departamentos.catalogo-filtros'))
            ->assertOk()
            ->assertJsonPath('data', ['Atadores', 'Calidad', 'Costura', 'Tejido', 'Urdido']);
    }

    public function test_maquinas_son_todas_las_del_departamento_asignado(): void
    {
        // CVERT3 no se asignó una por una: entra por ser de Costura (asignación por departamento).
        $this->maquinaUrdido('CVERT3', 'Costura');
        DB::table('AtaMaquinas')->insert(['MaquinaId' => 'AT1']);
        $usuario = $this->entrar();
        $this->asignar($usuario, ['CVERT1', 'CVERT2'], 'Costura');

        $this->getJson(route('api.mantenimiento.maquinas', 'Costura'))
            ->assertOk()
            ->assertJsonPath('data', [
                ['MaquinaId' => 'CVERT1', 'Nombre' => 'CVERT1', 'Departamento' => 'Costura'],
                ['MaquinaId' => 'CVERT2', 'Nombre' => 'CVERT2', 'Departamento' => 'Costura'],
                ['MaquinaId' => 'CVERT3', 'Nombre' => 'CVERT3', 'Departamento' => 'Costura'],
            ]);
        // Departamento ajeno o área que no le toca: lista vacía, no las de otros.
        $this->getJson(route('api.mantenimiento.maquinas', 'Urdido'))->assertJsonPath('data', []);
        $this->getJson(route('api.mantenimiento.maquinas', 'Atadores'))->assertJsonPath('data', []);

        $this->entrar(['area' => 'Atadores']);
        $this->getJson(route('api.mantenimiento.maquinas', 'Atadores'))->assertJsonPath('data.0.MaquinaId', 'AT1');
    }

    public function test_calidad_ve_telares_urdido_y_engomado_del_catalogo(): void
    {
        $this->maquinaUrdido('MC1');
        $this->maquinaUrdido('WP2', 'Engomado');
        $this->maquinaUrdido('205', 'Jacquard');
        $this->maquinaUrdido('CVERT1', 'Costura');
        $this->entrar(['area' => 'Calidad']);

        $this->assertSame(
            ['205' => 'Tejido', 'MC1' => 'Urdido', 'WP2' => 'Engomado'],
            collect($this->getJson(route('api.mantenimiento.maquinas', 'Calidad'))->json('data'))->pluck('DepartamentoOrigen', 'MaquinaId')->all()
        );
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
        $this->asignar($this->entrar(['nombre' => 'Operador Uno', 'numero_empleado' => '555']), ['MC1']);

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
        $this->asignar($this->entrar(), ['MC1']);

        $this->postJson(route('api.mantenimiento.paros.store'), ['depto' => 'Urdido', 'maquina' => 'MC1', 'falla_id' => $fallaId])
            ->assertStatus(422)->assertJsonStructure(['error', 'errors' => ['falla_id']]);

        $this->postJson(route('api.mantenimiento.paros.store'), ['depto' => 'Urdido', 'maquina' => 'MC1', 'falla_id' => $fallaId, 'orden_trabajo' => 'con espacio'])
            ->assertStatus(422)->assertJsonPath('error', 'La orden de trabajo no puede llevar espacios.');

        $this->postJson(route('api.mantenimiento.paros.store'), ['depto' => 'Urdido', 'maquina' => 'MC9', 'falla_id' => 999])
            ->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(1, ManFallasParos::count());
    }

    public function test_store_valida_que_departamento_maquina_y_falla_sean_coherentes(): void
    {
        $urdido = $this->falla('MECANICO', 'Urdido');
        $engomado = $this->falla('MECANICO', 'Engomado', 'Gomas');
        $calidad = $this->falla('CALIDAD', 'Calidad', 'Mancha');
        $this->maquinaUrdido('MC2');
        $usuario = $this->entrar();
        $this->asignar($usuario, ['MC1']);
        $this->asignar($usuario, ['WP2'], 'Engomado');
        $url = route('api.mantenimiento.paros.store');

        // Departamento sin máquinas asignadas.
        $this->postJson($url, ['depto' => 'Costura', 'maquina' => 'MC1', 'falla_id' => $urdido])
            ->assertStatus(422)->assertJsonPath('errors.depto.0', 'No tienes ese departamento asignado.');
        // Máquina de otro departamento e inventada. MC2 sí vale: es de Urdido, que tiene asignado.
        $this->postJson($url, ['depto' => 'Urdido', 'maquina' => 'WP2', 'falla_id' => $urdido])
            ->assertStatus(422)->assertJsonPath('errors.maquina.0', 'Esa máquina no es del departamento elegido.');
        $this->postJson($url, ['depto' => 'Urdido', 'maquina' => 'XX9', 'falla_id' => $urdido])->assertStatus(422);
        // Falla del catálogo de otro departamento.
        $this->postJson($url, ['depto' => 'Urdido', 'maquina' => 'MC1', 'falla_id' => $engomado])
            ->assertStatus(422)->assertJsonPath('errors.falla_id.0', 'Esa falla no corresponde al departamento elegido.');
        $this->assertSame(0, ManFallasParos::count());

        // Las fallas de Calidad se pueden reportar desde cualquier departamento.
        $this->postJson($url, ['depto' => 'Urdido', 'maquina' => 'MC1', 'falla_id' => $calidad])->assertOk();
        $this->assertSame(1, ManFallasParos::count());
    }

    /** Sin token de Telegram no sale ningún aviso: la respuesta no debe afirmar que sí. */
    public function test_store_no_afirma_telegram_si_no_hay_a_quien_avisar(): void
    {
        $fallaId = $this->falla();
        $this->asignar($this->entrar(), ['MC1']);

        $this->postJson(route('api.mantenimiento.paros.store'), ['depto' => 'Urdido', 'maquina' => 'MC1', 'falla_id' => $fallaId])
            ->assertOk()
            ->assertJsonPath('data.notificacion_enviada', false)
            ->assertJsonMissing(['message' => 'Paro reportado correctamente y notificación enviada a Telegram']);

        $this->assertFalse((bool) ManFallasParos::firstOrFail()->Enviado);
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

    /** Con máquinas asignadas solo ve las suyas, en cualquier modo, aunque el Depto guardado sea el viejo. */
    public function test_index_con_asignacion_solo_ve_sus_maquinas(): void
    {
        $this->paro(['Folio' => 'T1', 'Depto' => 'Tejedores', 'MaquinaId' => '205']);
        $this->paro(['Folio' => 'J1', 'Depto' => 'Jacquard', 'MaquinaId' => '205']);
        $this->paro(['Folio' => 'T2', 'Depto' => 'Tejedores', 'MaquinaId' => '300']);
        $this->paro(['Folio' => 'E1', 'Depto' => 'Engomado', 'MaquinaId' => 'WP2']);
        $this->asignar($this->entrar(['area' => 'Tejedores']), ['205'], 'Jacquard');

        $folios = fn (string $query) => collect($this->getJson(route('api.mantenimiento.paros.index').$query)->assertOk()->json('data'))->pluck('Folio')->sort()->values()->all();

        $this->assertSame(['J1', 'T1'], $folios(''));
        $this->assertSame(['J1', 'T1'], $folios('?alcance=todos'));
        $this->assertSame(['J1'], $folios('?depto=Jacquard'));
    }

    public function test_show_y_finalizar_rechazan_paro_de_maquina_ajena(): void
    {
        $ajeno = $this->paro(['MaquinaId' => '300']);
        $propio = $this->paro(['Folio' => 'PF00002', 'MaquinaId' => '205']);
        $this->asignar($this->entrar(), ['205'], 'Jacquard');

        $this->getJson(route('api.mantenimiento.paros.show', $ajeno))->assertForbidden();
        $this->putJson(route('api.mantenimiento.paros.finalizar', $ajeno), ['atendio' => 'X', 'calidad' => 3])->assertForbidden();
        $this->getJson(route('api.mantenimiento.paros.show', $propio))->assertOk();
        $this->assertSame('Activo', ManFallasParos::findOrFail($ajeno)->Estatus);
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

    /**
     * PERF (19-08): el área sale del usuario autenticado (ya es dbo.SYSUsuario).
     * Antes: nuevo paro 1 consulta, listado sin área 3 (SYSUsuario dos veces + paros).
     */
    public function test_el_area_no_vuelve_a_consultar_sysusuario(): void
    {
        $this->entrar(['area' => null]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('api.mantenimiento.paros.index'))->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame([], collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'SYSUsuario'))->all());

        $this->entrar(['area' => 'Urdido']);
        DB::flushQueryLog();
        $this->get(route('mantenimiento.nuevo-paro'))->assertOk()->assertSee('"areaUsuario":"Urdido"', false);
        $this->assertSame([], collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'SYSUsuario'))->all());
    }

    /** SEC-07: un 500 no le enseña al usuario el texto de la excepción (SQL, tablas). */
    public function test_errores_de_servidor_no_filtran_el_detalle(): void
    {
        $this->entrar();
        Schema::connection('sqlsrv')->drop('CatParosFallas');
        DB::connection()->statement('DROP TABLE dbo.ManFallasParos');
        DB::connection()->statement('DROP TABLE dbo.ManOperadoresMantenimiento');
        Schema::connection('sqlsrv')->drop('URDCatalogoMaquinas');

        $urls = [
            route('api.mantenimiento.departamentos.catalogo-filtros'),
            route('api.mantenimiento.tipos-falla', 'Urdido'),
            route('api.mantenimiento.fallas', 'Urdido'),
            route('api.mantenimiento.maquinas', 'Urdido').'?x=1',
            route('api.mantenimiento.paros.index').'?alcance=todos',
            route('api.mantenimiento.paros.show', 1),
            route('api.mantenimiento.operadores'),
        ];
        Schema::connection('sqlsrv')->drop('SYSDepartamentosAsignacion');

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
