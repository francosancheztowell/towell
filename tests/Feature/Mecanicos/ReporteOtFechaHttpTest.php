<?php

declare(strict_types=1);

namespace Tests\Feature\Mecanicos;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Reporte "OT x Fecha" (SYSRoles idrol 209, /mecanicos/reportes/ot-fecha): ruta → permiso
 * del módulo → controller → BD.
 */
class ReporteOtFechaHttpTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private const URL = '/mecanicos/reportes/ot-fecha';

    private const IDROL = 209;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        config()->set('services.telegram.bot_token', '');
        $this->createAuthTable();
        $this->withoutMiddleware(PreventRequestForgery::class);
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'America/Mexico_City'));

        $schema = Schema::connection('sqlsrv');
        $schema->create('MecOrdenTrabajoTable', function (Blueprint $table): void {
            $table->string('Folio')->primary();
            $table->string('TelarId')->nullable();
            $table->string('Falla')->nullable();
            $table->string('Estatus')->nullable();
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
            $table->date('Fecha')->nullable();
            $table->string('comentarios')->nullable();
        });

        DB::table('MecOrdenTrabajoTable')->insert([
            ['Folio' => 'MEC00001', 'TelarId' => '201', 'Falla' => 'Rotura de hilo', 'Estatus' => 'Terminado'],
            ['Folio' => 'MEC00002', 'TelarId' => '305', 'Falla' => 'Ruido en peine', 'Estatus' => 'Activo'],
        ]);
        $this->linea('MEC00001', '2002', 'PEDRO LOPEZ', '2026-10-01', '23:50:00', '00:05:00', 15);
        $this->linea('MEC00002', '3003', 'RAUL ZAPATA', '2026-10-02', '08:00:00', '09:30:00', 90);
        // Placeholder sin captura: no es trabajo de nadie.
        DB::table('MecOrdenTrabajoLine')->insert(['Folio' => 'MEC00002', 'Fecha' => '2026-10-02']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function linea(string $folio, string $cve, string $nombre, string $fecha, string $inicio, string $fin, int $minutos): void
    {
        DB::table('MecOrdenTrabajoLine')->insert([
            'Folio' => $folio,
            'CveOperador' => $cve,
            'NomOperador' => $nombre,
            'Ajusto' => true,
            'Reparo' => false,
            'Cambio' => false,
            'Lubrico' => false,
            'FaltaRefacc' => false,
            'Fecha' => $fecha,
            'HoraInicial' => $inicio,
            'HoraFinal' => $fin,
            'TotalMinutos' => $minutos,
            'comentarios' => 'Trabajo de '.$nombre,
        ]);
    }

    private function conAcceso(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'MANTENIMIENTO', 'numero_empleado' => '2002', 'nombre' => 'PEDRO LOPEZ']), 'web');
        $this->grantModulo('OT x Fecha', ['acceso'], idRol: self::IDROL);

        // El catálogo del select sale del modelo Usuario (dbo.SYSUsuario, área MANTENIMIENTO).
        $this->createTablaDbo('SYSUsuario', ['numero_empleado' => 'TEXT', 'nombre' => 'TEXT', 'area' => 'TEXT']);
        DB::table('dbo.SYSUsuario')->insert([
            ['numero_empleado' => '2002', 'nombre' => 'PEDRO LOPEZ', 'area' => 'MANTENIMIENTO'],
            ['numero_empleado' => '3003', 'nombre' => 'RAUL ZAPATA', 'area' => 'Mantenimiento '],
            ['numero_empleado' => '4004', 'nombre' => 'ANA TEJEDORA', 'area' => 'TEJIDO'],
        ]);
    }

    public function test_sin_permiso_del_modulo_responde_403(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'MANTENIMIENTO']), 'web');
        $this->grantModulo('Otro módulo', ['acceso'], idRol: 1);

        $this->get(self::URL)->assertForbidden();
    }

    public function test_sin_rango_muestra_el_filtro_y_el_select_de_mecanicos_sin_reporte(): void
    {
        $this->conAcceso();

        $this->get(self::URL)
            ->assertOk()
            ->assertSee('id="desde"', false)
            ->assertSee('id="hasta"', false)
            ->assertSee('Todos los mecánicos')
            ->assertSee('RAUL ZAPATA (3003)')
            ->assertDontSee('ANA TEJEDORA')
            ->assertDontSee('MEC00001');
    }

    public function test_sin_mecanico_muestra_a_todos_los_que_hicieron_ot_en_el_rango(): void
    {
        $this->conAcceso();

        $this->get(self::URL.'?desde=2026-10-01&hasta=2026-10-02')
            ->assertOk()
            ->assertSee('MEC00001')
            ->assertSee('MEC00002')
            ->assertSee('Rotura de hilo')
            ->assertSee('1 h 30 min');
    }

    public function test_cada_renglon_muestra_numero_de_empleado_y_nombre(): void
    {
        $this->conAcceso();

        $this->get(self::URL.'?desde=2026-10-02&hasta=2026-10-02')
            ->assertOk()
            ->assertSeeInOrder(['No. Empleado', 'Nombre', 'Fecha', 'Folio'])
            ->assertSeeInOrder(['<td', '3003', '</td>', '<td', 'RAUL ZAPATA', '</td>', '<td', '02/10/2026', '</td>', '<td', 'MEC00002'], false);
    }

    public function test_con_mecanico_solo_muestra_sus_ot(): void
    {
        $this->conAcceso();

        $this->get(self::URL.'?desde=2026-10-01&hasta=2026-10-02&mecanico=3003')
            ->assertOk()
            ->assertSee('MEC00002')
            ->assertDontSee('MEC00001');
    }

    public function test_trabajo_que_cruza_medianoche_cuenta_en_el_dia_que_empezo(): void
    {
        $this->conAcceso();

        $this->get(self::URL.'?desde=2026-10-01&hasta=2026-10-01')
            ->assertOk()
            ->assertSee('MEC00001')
            ->assertSee('+1 día');

        $this->get(self::URL.'?desde=2026-10-02&hasta=2026-10-02')
            ->assertOk()
            ->assertDontSee('MEC00001');
    }

    public function test_rango_invertido_muestra_el_error(): void
    {
        $this->conAcceso();

        $this->get(self::URL.'?desde=2026-10-05&hasta=2026-10-01')
            ->assertOk()
            ->assertSee('La fecha inicial no puede ser mayor que la fecha final.');
    }

    public function test_excel_descarga_el_rango_filtrado(): void
    {
        $this->conAcceso();

        $response = $this->post(self::URL.'/excel', ['desde' => '2026-10-01', 'hasta' => '2026-10-02', 'mecanico' => '3003']);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename="ot-fecha_2026-10-01_2026-10-02.xlsx"');
        $this->assertStringStartsWith('PK', (string) $response->getContent());
    }

    public function test_pdf_descarga_el_rango(): void
    {
        $this->conAcceso();

        $response = $this->post(self::URL.'/pdf', ['desde' => '2026-10-01', 'hasta' => '2026-10-02']);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="ot-fecha_2026-10-01_2026-10-02.pdf"');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_exportar_con_rango_invertido_responde_422(): void
    {
        $this->conAcceso();

        $this->post(self::URL.'/excel', ['desde' => '2026-10-05', 'hasta' => '2026-10-01'])->assertStatus(422);
    }

    public function test_imagen_se_acepta_para_telegram(): void
    {
        $this->conAcceso();

        $this->postJson(self::URL.'/telegram-imagen', [
            'desde' => '2026-10-01',
            'hasta' => '2026-10-02',
            'mecanico' => '',
            'imagen' => UploadedFile::fake()->image('ot-fecha.png'),
        ])->assertOk()->assertJson(['ok' => true]);
    }

    public function test_exportar_sin_permiso_responde_403(): void
    {
        $this->actingAs($this->createUsuario(['area' => 'MANTENIMIENTO']), 'web');
        $this->grantModulo('Otro módulo', ['acceso'], idRol: 1);

        $this->post(self::URL.'/pdf', ['desde' => '2026-10-01', 'hasta' => '2026-10-02'])->assertForbidden();
    }

    public function test_rango_sin_ot_muestra_aviso_vacio(): void
    {
        $this->conAcceso();

        $this->get(self::URL.'?desde=2026-09-01&hasta=2026-09-02')
            ->assertOk()
            ->assertSee('No hay órdenes de trabajo en el rango seleccionado.');
    }
}
