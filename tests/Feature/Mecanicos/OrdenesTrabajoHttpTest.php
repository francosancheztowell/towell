<?php

declare(strict_types=1);

namespace Tests\Feature\Mecanicos;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Órdenes de trabajo mecánicas de punta a punta: ruta → middleware de permisos →
 * controller → BD. Los tests unitarios (Unit/Mecanicos/OrdenesTrabajoTest) cubren
 * las reglas sueltas; estos fijan quién puede qué en cada estatus.
 */
class OrdenesTrabajoHttpTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const URL = '/mecanicos/ordenes-trabajo';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-09-02 10:00:00', 'America/Mexico_City'));

        $schema = Schema::connection('sqlsrv');
        $schema->create('MecOrdenTrabajoTable', function (Blueprint $table): void {
            $table->string('Folio')->primary();
            $table->date('Fecha')->nullable();
            $table->string('TelarId')->nullable();
            $table->string('FolioParo')->nullable();
            $table->string('TipoFalla')->nullable();
            $table->string('Falla')->nullable();
            $table->string('Comentarios')->nullable();
            $table->date('FechaParo')->nullable();
            $table->string('HoraParo')->nullable();
            $table->string('Estatus')->nullable();
            $table->string('Orden')->nullable();
            $table->integer('Turno')->nullable();
        });
        $schema->create('MecOrdenTrabajoLine', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('Folio');
            $table->string('CveOperador')->nullable();
            $table->string('NomOperador')->nullable();
            foreach (['Ajusto', 'Reparo', 'Cambio', 'Lubrico', 'FaltaRefacc'] as $trabajo) {
                $table->boolean($trabajo)->nullable();
            }
            $table->string('HoraInicial')->nullable();
            $table->string('HoraFinal')->nullable();
            $table->integer('TotalMinutos')->nullable();
            $table->integer('Calificacion')->nullable();
            $table->string('CveTejedor')->nullable();
            $table->string('NomTejedor')->nullable();
            $table->integer('Turno')->nullable();
            $table->date('Fecha')->nullable();
            $table->string('comentarios')->nullable();
        });
        $schema->create('TelTelaresOperador', function (Blueprint $table): void {
            $table->increments('Id');
            $table->string('numero_empleado')->nullable();
            $table->string('NoTelarId')->nullable();
        });
        $this->createTablaDbo('ManFallasParos', ['Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT', 'Folio' => 'TEXT', 'Calidad' => 'INTEGER']);
        $this->createTablaDbo('SSYSFoliosSecuencias', [
            'Id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            'modulo' => 'TEXT',
            'prefijo' => 'TEXT',
            'consecutivo' => 'INTEGER',
        ]);

        // FolioHelper pregunta las columnas de la secuencia a INFORMATION_SCHEMA.
        $conexion = DB::connection();
        $conexion->statement("ATTACH DATABASE ':memory:' AS INFORMATION_SCHEMA");
        $conexion->statement('CREATE TABLE INFORMATION_SCHEMA.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        foreach (['Id', 'modulo', 'prefijo', 'consecutivo'] as $columna) {
            $conexion->table('INFORMATION_SCHEMA.COLUMNS')->insert([
                'TABLE_SCHEMA' => 'dbo',
                'TABLE_NAME' => 'SSYSFoliosSecuencias',
                'COLUMN_NAME' => $columna,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  list<string>  $acciones */
    private function comoUsuario(string $area, array $acciones): void
    {
        $this->actingAs($this->createUsuario(['area' => $area, 'numero_empleado' => '1001', 'nombre' => 'Juan Pérez']), 'web');
        $this->grantModulo('Ordenes de Trabajo', $acciones, idRol: 193);
    }

    private function orden(string $folio, string $estatus, string $telar = '201'): void
    {
        DB::table('MecOrdenTrabajoTable')->insert(['Folio' => $folio, 'Estatus' => $estatus, 'TelarId' => $telar]);
    }

    /** Renglón ya capturado por el mecánico (el que finalizar acepta). */
    private function linea(string $folio, ?int $calificacion = null): int
    {
        return DB::table('MecOrdenTrabajoLine')->insertGetId([
            'Folio' => $folio,
            'CveOperador' => '2002',
            'NomOperador' => 'Mecánico',
            'Ajusto' => true,
            'HoraInicial' => '08:00:00',
            'HoraFinal' => '09:00:00',
            'Calificacion' => $calificacion,
        ]);
    }

    public function test_mecanico_crea_orden_manual_con_fecha_del_folio_y_renglon_inicial(): void
    {
        $this->comoUsuario('MANTENIMIENTO', ['acceso', 'crear']);

        $response = $this->postJson(self::URL, [
            'CapturaManual' => '1',
            'FolioParo' => 'Sin Folio de Paro.',
            'TelarId' => '201',
            'TipoFalla' => 'Mecánica',
            'FechaParo' => '2026-08-20',
        ]);

        $response->assertCreated()->assertJsonPath('data.Folio', 'MEC00001');
        $orden = DB::table('MecOrdenTrabajoTable')->first();
        $this->assertSame('Activo', $orden->Estatus);
        $this->assertNull($orden->FolioParo);
        // La fecha de la orden es la del folio, no la del paro.
        $this->assertSame('2026-09-02', substr((string) $orden->Fecha, 0, 10));
        $this->assertSame('2026-08-20', substr((string) $orden->FechaParo, 0, 10));
        $this->assertSame(1, DB::table('MecOrdenTrabajoLine')->where('Folio', 'MEC00001')->count());
    }

    public function test_crear_sin_paro_ni_captura_manual_responde_422(): void
    {
        $this->comoUsuario('MANTENIMIENTO', ['acceso', 'crear']);

        $this->postJson(self::URL, ['TelarId' => '201', 'TipoFalla' => 'Mecánica'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('CapturaManual');
        $this->assertSame(0, DB::table('MecOrdenTrabajoTable')->count());
    }

    public function test_tejedor_en_modo_calificacion_no_edita_renglones_aunque_tenga_modificar(): void
    {
        $this->comoUsuario('TEJEDORES', ['acceso', 'modificar']);
        DB::table('TelTelaresOperador')->insert(['numero_empleado' => '1001', 'NoTelarId' => '201']);
        $this->orden('MEC00001', 'Activo');
        $id = $this->linea('MEC00001');

        $this->putJson(self::URL."/MEC00001/lineas/{$id}", [
            'CveOperador' => '1001', 'NomOperador' => 'Juan', 'Ajusto' => true,
            'HoraInicial' => '10:00', 'HoraFinal' => '11:00', 'Turno' => 1, 'Fecha' => '2026-09-02', 'comentarios' => 'x',
        ])->assertForbidden();
        $this->assertSame('2002', DB::table('MecOrdenTrabajoLine')->value('CveOperador'));
    }

    public function test_tejedor_no_ve_ordenes_de_telares_que_no_tiene_asignados(): void
    {
        $this->comoUsuario('TEJEDORES', ['acceso']);
        DB::table('TelTelaresOperador')->insert(['numero_empleado' => '1001', 'NoTelarId' => '201']);
        $this->orden('MEC00001', 'Terminado', '201');
        $this->orden('MEC00002', 'Terminado', '305');

        $this->getJson(self::URL.'/MEC00001')->assertOk();
        $this->getJson(self::URL.'/MEC00002')->assertForbidden();
    }

    public function test_tejedor_califica_cada_renglon_y_la_orden_pasa_a_calificado_con_el_ultimo(): void
    {
        $this->comoUsuario('TEJEDORES', ['acceso']);
        DB::table('TelTelaresOperador')->insert(['numero_empleado' => '1001', 'NoTelarId' => '201']);
        $this->orden('MEC00001', 'Terminado');
        $primera = $this->linea('MEC00001');
        $segunda = $this->linea('MEC00001');

        $this->putJson(self::URL."/MEC00001/lineas/{$primera}", ['Calificacion' => 6])->assertStatus(422);

        $this->putJson(self::URL."/MEC00001/lineas/{$primera}", ['Calificacion' => 4])
            ->assertOk()
            ->assertJsonPath('orden.Estatus', 'Terminado');
        $this->putJson(self::URL."/MEC00001/lineas/{$segunda}", ['Calificacion' => 5])
            ->assertOk()
            ->assertJsonPath('orden.Estatus', 'Calificado');

        $linea = DB::table('MecOrdenTrabajoLine')->find($primera);
        $this->assertSame(['1001', 'Juan Pérez'], [$linea->CveTejedor, $linea->NomTejedor]);
    }

    public function test_supervisor_con_solo_registrar_puede_finalizar(): void
    {
        $this->comoUsuario('MANTENIMIENTO', ['acceso', 'registrar']);
        $this->orden('MEC00001', 'Activo');
        $this->linea('MEC00001');

        $this->postJson(self::URL.'/MEC00001/finalizar')->assertOk()->assertJsonPath('data.Estatus', 'Terminado');
    }

    public function test_finalizar_rechaza_renglones_sin_captura(): void
    {
        $this->comoUsuario('MANTENIMIENTO', ['acceso', 'modificar']);
        $this->orden('MEC00001', 'Activo');
        DB::table('MecOrdenTrabajoLine')->insert(['Folio' => 'MEC00001']);

        $this->postJson(self::URL.'/MEC00001/finalizar')->assertStatus(422);
        $this->assertSame('Activo', DB::table('MecOrdenTrabajoTable')->value('Estatus'));
    }

    public function test_orden_cancelada_no_admite_captura_ni_eliminacion(): void
    {
        $this->comoUsuario('MANTENIMIENTO', ['acceso', 'crear', 'eliminar']);
        $this->orden('MEC00001', 'Cancelado');
        $this->linea('MEC00001');

        $this->postJson(self::URL.'/MEC00001/lineas', [
            'CveOperador' => '1001', 'NomOperador' => 'Juan', 'Ajusto' => true,
            'HoraInicial' => '10:00', 'HoraFinal' => '11:00', 'Turno' => 1, 'Fecha' => '2026-09-02', 'comentarios' => 'x',
        ])->assertStatus(422);
        $this->deleteJson(self::URL.'/MEC00001')->assertStatus(422);
        $this->assertSame(1, DB::table('MecOrdenTrabajoLine')->count());
    }

    public function test_eliminar_orden_se_lleva_sus_renglones(): void
    {
        $this->comoUsuario('MANTENIMIENTO', ['acceso', 'eliminar']);
        $this->orden('MEC00001', 'Activo');
        $this->linea('MEC00001');
        $this->linea('MEC00001');

        $this->deleteJson(self::URL.'/MEC00001')->assertOk();
        $this->assertSame(0, DB::table('MecOrdenTrabajoTable')->count());
        $this->assertSame(0, DB::table('MecOrdenTrabajoLine')->count());
    }

    public function test_no_se_elimina_el_unico_renglon(): void
    {
        $this->comoUsuario('MANTENIMIENTO', ['acceso', 'eliminar']);
        $this->orden('MEC00001', 'Activo');
        $id = $this->linea('MEC00001');

        $this->deleteJson(self::URL."/MEC00001/lineas/{$id}")->assertStatus(422);
        $this->assertSame(1, DB::table('MecOrdenTrabajoLine')->count());
    }

    public function test_autorizar_solo_una_orden_calificada(): void
    {
        $this->comoUsuario('MANTENIMIENTO', ['acceso', 'registrar']);
        $this->orden('MEC00001', 'Terminado');
        $this->orden('MEC00002', 'Calificado');

        $this->postJson(self::URL.'/MEC00001/autorizar')->assertStatus(422);
        $this->postJson(self::URL.'/MEC00002/autorizar')->assertOk()->assertJsonPath('data.Estatus', 'Autorizado');
    }
}
