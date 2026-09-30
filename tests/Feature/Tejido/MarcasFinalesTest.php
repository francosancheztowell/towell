<?php

namespace Tests\Feature\Tejido;

use App\Models\Inventario\InvSecuenciaMarcas;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\Usuario;
use App\Models\Tejido\TejMarcas;
use App\Models\Tejido\TejMarcasLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Tejido\Concerns\ModuloTejido;
use Tests\TestCase;

/** Marcas Finales (19-02): 3 vistas sin JS inline, contrato JSON que usa el TS y SEC-07. */
class MarcasFinalesTest extends TestCase
{
    use ModuloTejido;

    private const VISTAS = 'resources/views/modulos/marcas-finales/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        $this->tablaTejido(TejMarcas::class);
        $this->tablaTejido(TejMarcasLine::class);
        $this->tablaTejido(InvSecuenciaMarcas::class);
        Schema::connection('sqlsrv')->create(ReqProgramaTejido::tableName(), function ($t): void {
            $t->increments('Id');
            $t->string('NoTelarId')->nullable();
            $t->string('SalonTejidoId')->nullable();
            $t->float('EficienciaSTD')->nullable();
            $t->dateTime('FechaInicio')->nullable();
        });
        Schema::connection('sqlsrv')->create('SYSUsuario', function ($t): void {
            $t->increments('idusuario');
            $t->string('numero_empleado')->nullable();
            $t->integer('turno')->nullable();
        });

        $db = DB::connection('sqlsrv');
        $db->table('InvSecuenciaMarcas')->insert([
            ['NoTelarId' => '201', 'SalonTejidoId' => 'JACQUARD', 'Orden' => 1],
            ['NoTelarId' => '202', 'SalonTejidoId' => "Jacq'uard <b>", 'Orden' => 2],
        ]);
        $db->table(ReqProgramaTejido::tableName())->insert([
            ['NoTelarId' => '201', 'SalonTejidoId' => 'SMIT', 'EficienciaSTD' => 0.7, 'FechaInicio' => '2026-01-01'],
            ['NoTelarId' => '201', 'SalonTejidoId' => 'ITEMA', 'EficienciaSTD' => 0.83, 'FechaInicio' => '2026-09-01'],
        ]);
        $db->table('SYSUsuario')->insert(['numero_empleado' => '100', 'turno' => 4]);
    }

    private ?Usuario $usuarioBase = null;

    /** Usuario 10 con todo en Marcas Finales (idrol 177); el puesto decide si es supervisor. */
    private function usuario(bool $supervisor = false): Usuario
    {
        $todas = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'];
        $this->usuarioBase ??= $this->usuarioCon([177 => $todas, 'Marcas Finales' => $todas], 'Tejido');
        $u = clone $this->usuarioBase;
        $u->setAttribute('puesto', $supervisor ? 'Supervisor' : 'Tejedor');

        return $u;
    }

    private function marca(string $folio, string $fecha, int $turno, string $status, array $lineas = []): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('TejMarcas')->insert(['Folio' => $folio, 'Date' => $fecha, 'Turno' => $turno, 'Status' => $status, 'numero_empleado' => '100', 'nombreEmpl' => 'Ana']);
        foreach ($lineas as $telar => $valores) {
            $db->table('TejMarcasLine')->insert(array_merge(['Folio' => $folio, 'Date' => $fecha, 'Turno' => $turno, 'NoTelarId' => (string) $telar], $valores));
        }
    }

    /** @return array<string, array{string}> */
    public static function vistas(): array
    {
        return ['consultar' => ['marcasFinales'], 'nuevo' => ['nuevo-marcas'], 'reporte' => ['reporte-marcas']];
    }

    #[DataProvider('vistas')]
    public function test_fuente_blade_sin_js_inline(string $vista): void
    {
        $fuente = file_get_contents(base_path(self::VISTAS.$vista.'.blade.php'));
        $this->assertSame(0, preg_match_all('/<script(?![^>]*\bsrc=)/i', $fuente), 'script inline');
        $this->assertSame(0, preg_match_all('/\son[a-z]+\s*=\s*["\']/i', $fuente), 'atributo on*=');
        $this->assertStringNotContainsString('csrf_token()', $fuente);
        $this->assertStringContainsString("@vite('resources/js/modulos/tejido/marcas-finales/", $fuente);
        $this->assertDoesNotMatchRegularExpression('/text-\[(9|10|11)px\]/', $fuente);
        $this->assertStringNotContainsString('<h1', $fuente);
    }

    public function test_consultar_con_config_y_estado_por_fila(): void
    {
        $this->marca('FM0001', '2026-09-28', 1, 'Finalizado');
        $this->marca('FM0002', '2026-09-29', 2, 'En Proceso');

        $html = $this->actingAs($this->usuario())->get('/modulo-marcas/consultar')->assertOk()->getContent();

        $this->assertStringContainsString('id="pagina-marcas-consultar"', $html);
        $this->assertStringContainsString('data-folio="FM0002"', $html);
        $this->assertStringContainsString('data-status="En Proceso"', $html);
        $this->assertStringContainsString('data-accion="finalizar"', $html);
        $this->assertStringContainsString('data-accion="generar-reporte"', $html);
        // Rutas resueltas en PHP con marcador, y el último folio (En Proceso primero).
        $this->assertStringContainsString('modulo-marcas\/__FOLIO__\/finalizar', $html);
        $this->assertStringContainsString('"ultimoFolio":"FM0002"', $html);
        // Capturado por turno 4 (SYSUsuario.turno = 4).
        $this->assertStringContainsString('(turno 4)', $html);
        $this->assertStringNotContainsString('MarcasManager', $html);
        $this->assertStringNotContainsString('onclick="window', $html);
    }

    public function test_consultar_supervisor_tiene_modal_de_edicion(): void
    {
        $this->marca('FM0001', '2026-09-28', 1, 'Finalizado');
        $html = $this->actingAs($this->usuario(true))->get('/modulo-marcas/consultar')->assertOk()->getContent();
        $this->assertStringContainsString('id="modal-editar-registro"', $html);
        $this->assertStringContainsString('data-accion="guardar-registro"', $html);
        $this->assertStringContainsString('"esSupervisor":true', $html);
    }

    public function test_nuevo_sin_folio_en_proceso_pinta_la_captura(): void
    {
        $html = $this->actingAs($this->usuario())->get('/modulo-marcas')->assertOk()->getContent();

        $this->assertStringContainsString('id="pagina-marcas-nuevo"', $html);
        $this->assertStringContainsString('<tr class="even:bg-gray-50 hover:bg-gray-100 transition-colors duration-150" data-telar="201">', $html);
        $this->assertStringContainsString('data-modal-accion="ok"', $html);
        $this->assertStringContainsString('"soloLectura":false', $html);
        $this->assertStringContainsString('modulo-marcas\/generar-folio', $html);
        $this->assertStringNotContainsString('PAGE_MODE', $html);
        // Salón con comilla y < escapado en el HTML.
        $this->assertStringContainsString('Jacq&#039;uard &lt;b&gt;', $html);
    }

    public function test_nuevo_redirige_al_folio_en_proceso(): void
    {
        $this->marca('FM0002', '2026-09-29', 2, 'En Proceso');
        $this->actingAs($this->usuario())->get('/modulo-marcas')->assertRedirect('/modulo-marcas?folio=FM0002');
    }

    public function test_visualizar_es_solo_lectura(): void
    {
        $this->marca('FM0001', '2026-09-28', 1, 'Finalizado');
        $html = $this->actingAs($this->usuario())->get('/modulo-marcas/visualizar/FM0001')->assertOk()->getContent();
        $this->assertStringContainsString('"soloLectura":true', $html);
        $this->assertStringContainsString('"folioInicial":"FM0001"', $html);
        $this->assertStringContainsString('readonly', $html);

        $this->actingAs($this->usuario())->get('/modulo-marcas/visualizar/NOEXISTE')
            ->assertRedirect(route('marcas.consultar'))->assertSessionHas('error', 'Folio no encontrado');
    }

    public function test_reporte_por_fecha(): void
    {
        $this->marca('FM0001', '2026-09-28', 1, 'Finalizado', ['201' => ['Eficiencia' => 0.8, 'Marcas' => 120, 'Horas' => 8]]);
        $html = $this->actingAs($this->usuario())->get('/modulo-marcas/reporte?fecha=2026-09-28')->assertOk()->getContent();

        $this->assertStringContainsString('id="pagina-marcas-reporte"', $html);
        $this->assertStringContainsString('data-accion="pdf"', $html);
        $this->assertStringContainsString('"fecha":"2026-09-28"', $html);
        $this->assertStringContainsString('Folio Turno 1: FM0001', $html);
        $this->assertStringContainsString('80%', $html);
        $this->assertStringNotContainsString('function exportarExcel', $html);

        $this->actingAs($this->usuario())->get('/modulo-marcas/reporte')->assertRedirect(route('marcas.consultar'));
    }

    public function test_generar_folio_contrato(): void
    {
        $u = $this->usuario();
        $this->actingAs($u)->postJson('/modulo-marcas/generar-folio', [])->assertStatus(422)->assertJson(['success' => false]);
        $this->actingAs($u)->postJson('/modulo-marcas/generar-folio', ['fecha' => '2026-09-29', 'turno' => 5])
            ->assertStatus(422)->assertJson(['message' => 'Turno inválido.']);

        $this->marca('FM0007', '2026-09-28', 1, 'Finalizado');
        $this->actingAs($u)->postJson('/modulo-marcas/generar-folio', ['fecha' => '2026-09-29', 'turno' => '2'])
            ->assertOk()->assertJson(['success' => true, 'folio' => 'FM0008', 'turno' => 2, 'fecha' => '2026-09-29', 'numero_empleado' => '100']);

        // Misma fecha y turno que un folio existente → 409 con el folio.
        $this->actingAs($u)->postJson('/modulo-marcas/generar-folio', ['fecha' => '2026-09-28', 'turno' => 1])
            ->assertStatus(409)->assertJson(['success' => false, 'folio_existente' => 'FM0007']);

        // Hay uno En Proceso → 400 con folio_existente y creado_por_otro.
        $this->marca('FM0009', '2026-09-30', 3, 'En Proceso');
        $this->actingAs($u)->postJson('/modulo-marcas/generar-folio', ['fecha' => '2026-10-01', 'turno' => 1])
            ->assertStatus(400)->assertJson(['folio_existente' => 'FM0009', 'creado_por_otro' => true]);
    }

    public function test_obtener_datos_std(): void
    {
        $r = $this->actingAs($this->usuario())->getJson('/modulo-marcas/obtener-datos-std')->assertOk()->assertJson(['success' => true]);
        $this->assertEquals([
            ['telar' => '201', 'salon' => 'ITEMA', 'porcentaje_efi' => 83],
            ['telar' => '202', 'salon' => "Jacq'uard <b>", 'porcentaje_efi' => 0],
        ], $r->json('datos'));
    }

    /** @return array<int, array<string, mixed>> */
    private function lineas(int $n): array
    {
        $lineas = [];
        for ($i = 1; $i <= $n; $i++) {
            $lineas[] = ['NoTelarId' => (string) (200 + $i), 'PorcentajeEfi' => 150, 'Trama' => 1, 'Pie' => 2, 'Rizo' => 3, 'Otros' => 4, 'Marcas' => 100, 'Horas' => 7.5];
        }

        return $lineas;
    }

    public function test_store_guarda_cabecera_y_lineas_y_update_usa_el_folio_de_la_url(): void
    {
        $u = $this->usuario();
        $payload = ['folio' => 'FM0001', 'fecha' => '2026-09-29', 'turno' => '2', 'lineas' => $this->lineas(2)];

        $this->actingAs($u)->postJson('/modulo-marcas/store', $payload)->assertOk()->assertJson(['success' => true, 'message' => 'Datos guardados correctamente']);
        $marca = DB::connection('sqlsrv')->table('TejMarcas')->where('Folio', 'FM0001')->first();
        $this->assertSame('En Proceso', $marca->Status);
        $this->assertSame('100', $marca->numero_empleado);
        $linea = DB::connection('sqlsrv')->table('TejMarcasLine')->where('Folio', 'FM0001')->where('NoTelarId', '201')->first();
        $this->assertEquals(100, $linea->Eficiencia); // 150 → tope 100
        $this->assertEquals(7.5, $linea->Horas);
        $this->assertSame('ITEMA', $linea->SalonTejidoId); // del STD más reciente
        // Sin STD: el salón de la secuencia.
        $this->assertSame("Jacq'uard <b>", DB::connection('sqlsrv')->table('TejMarcasLine')->where('NoTelarId', '202')->value('SalonTejidoId'));

        // Guardar otra vez reemplaza las líneas (no las duplica).
        $this->actingAs($u)->postJson('/modulo-marcas/store', $payload)->assertOk();
        $this->assertSame(2, DB::connection('sqlsrv')->table('TejMarcasLine')->where('Folio', 'FM0001')->count());

        // PUT: el folio de la URL manda sobre el del cuerpo.
        $this->actingAs($u)->putJson('/modulo-marcas/FM0002', array_merge($payload, ['fecha' => '2026-09-30']))->assertOk()->assertJson(['success' => true]);
        $this->assertSame(2, DB::connection('sqlsrv')->table('TejMarcasLine')->where('Folio', 'FM0002')->count());

        // Otro folio con la misma fecha y turno → 409.
        $this->marca('FM0005', '2026-10-05', 1, 'Finalizado');
        $this->actingAs($u)->postJson('/modulo-marcas/store', array_merge($payload, ['folio' => 'FM0003', 'fecha' => '2026-10-05', 'turno' => 1]))
            ->assertStatus(409)->assertJson(['success' => false]);
        $this->actingAs($u)->postJson('/modulo-marcas/store', ['folio' => 'FM0003'])->assertStatus(422);
    }

    public function test_store_inserta_lineas_en_bloques_para_sql_server(): void
    {
        $u = $this->usuario();
        $inserts = function (int $n, string $folio, int $turno) use ($u): int {
            $db = DB::connection('sqlsrv');
            $db->flushQueryLog();
            $db->enableQueryLog();
            $this->actingAs($u)->postJson('/modulo-marcas/store', ['folio' => $folio, 'fecha' => '2026-09-29', 'turno' => $turno, 'lineas' => $this->lineas($n)])->assertOk();
            $n = count(array_filter($db->getQueryLog(), fn ($q) => str_starts_with(strtolower($q['query']), 'insert into "tejmarcasline"')));
            $db->disableQueryLog();

            return $n;
        };

        // 14 columnas × 142 filas = 1 988 parámetros (SQL Server rechaza 2 100 con los del RPC): 142 cabe en uno, 200 van en dos (antes: 1 de 2 800).
        $this->assertSame(1, $inserts(142, 'FM0001', 1));
        $this->assertSame(2, $inserts(200, 'FM0002', 2));
        $this->assertSame(200, DB::connection('sqlsrv')->table('TejMarcasLine')->where('Folio', 'FM0002')->count());
        // Sin N+1: las demás consultas no crecen con el número de telares.
        $total = fn (int $n, string $folio, int $turno) => $this->contarQueries(fn () => $this->actingAs($u)->postJson('/modulo-marcas/store', ['folio' => $folio, 'fecha' => '2026-09-30', 'turno' => $turno, 'lineas' => $this->lineas($n)]));
        $this->assertSame($total(2, 'FM0003', 1) + 1, $total(200, 'FM0004', 2));
    }

    public function test_show_finalizar_y_reabrir(): void
    {
        $this->marca('FM0001', '2026-09-29', 2, 'En Proceso', ['201' => ['Eficiencia' => 80, 'Marcas' => 100]]);
        $u = $this->usuario();

        $this->actingAs($u)->getJson('/modulo-marcas/FM0001')->assertOk()
            ->assertJson(['success' => true, 'marca' => ['Folio' => 'FM0001', 'Status' => 'En Proceso', 'turno_capturista' => 4]])
            ->assertJsonCount(1, 'lineas');
        $this->actingAs($u)->getJson('/modulo-marcas/NOEXISTE')->assertNotFound()->assertJson(['success' => false, 'message' => 'Marca no encontrada']);

        $this->actingAs($u)->postJson('/modulo-marcas/FM0001/finalizar')->assertOk()->assertJson(['success' => true]);
        $this->assertSame('Finalizado', DB::connection('sqlsrv')->table('TejMarcas')->value('Status'));
        $this->actingAs($u)->postJson('/modulo-marcas/NOEXISTE/finalizar')->assertNotFound();

        $this->actingAs($u)->postJson('/modulo-marcas/FM0001/reabrir')->assertForbidden();
        $this->actingAs($this->usuario(true))->postJson('/modulo-marcas/FM0001/reabrir')->assertOk()->assertJson(['success' => true]);
        $this->assertSame('En Proceso', DB::connection('sqlsrv')->table('TejMarcas')->value('Status'));
        $this->actingAs($this->usuario(true))->postJson('/modulo-marcas/FM0001/reabrir')->assertStatus(422);
    }

    public function test_actualizar_registro_de_supervisor_sincroniza_lineas(): void
    {
        $this->marca('FM0001', '2026-09-29', 2, 'Finalizado', ['201' => ['Marcas' => 100]]);
        $url = '/modulo-marcas/FM0001/actualizar-registro';

        $this->actingAs($this->usuario())->putJson($url, ['Turno' => 1])->assertForbidden();
        $sup = $this->usuario(true);
        $this->actingAs($sup)->putJson($url, ['Turno' => 7])->assertStatus(422)->assertJson(['message' => 'Turno inválido (debe ser 1, 2 o 3)']);
        $this->actingAs($sup)->putJson($url, ['Status' => 'Otro'])->assertStatus(422);

        $this->actingAs($sup)->putJson($url, ['Date' => '2026-09-30', 'Turno' => '3', 'numero_empleado' => '200', 'nombreEmpl' => 'Luis', 'Status' => 'En Proceso'])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Registro actualizado correctamente']);
        $linea = DB::connection('sqlsrv')->table('TejMarcasLine')->where('Folio', 'FM0001')->first();
        $this->assertEquals(3, $linea->Turno);
        $this->assertStringStartsWith('2026-09-30', (string) $linea->Date);
        $this->assertSame('Luis', DB::connection('sqlsrv')->table('TejMarcas')->value('nombreEmpl'));
    }

    public function test_pdf_del_reporte(): void
    {
        $this->marca('FM0001', '2026-09-28', 1, 'Finalizado', ['201' => ['Eficiencia' => 80, 'Marcas' => 120]]);
        $r = $this->actingAs($this->usuario())->post('/modulo-marcas/reporte/descargar-pdf', ['fecha' => '2026-09-28'])->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }

    public function test_errores_sin_detalle_interno(): void
    {
        $u = $this->usuario();
        $this->marca('FM0001', '2026-09-28', 1, 'En Proceso');
        DB::connection('sqlsrv')->statement('DROP TABLE "TejMarcasLine"');

        $r = $this->actingAs($u)->postJson('/modulo-marcas/store', ['folio' => 'FM0001', 'fecha' => '2026-09-28', 'turno' => 1, 'lineas' => $this->lineas(1)])->assertStatus(500);
        $this->assertSame('Error al guardar datos', $r->json('message'));
        $this->assertArrayHasKey('trace_id', $r->json());
        $this->assertArrayNotHasKey('error', $r->json());
        $this->assertStringNotContainsString('TejMarcasLine', $r->getContent());

        $r = $this->actingAs($u)->getJson('/modulo-marcas/FM0001')->assertStatus(500);
        $this->assertSame('Error al obtener marca', $r->json('message'));

        foreach (['descargar-pdf' => 'Error al generar PDF', 'notificar-telegram' => 'Error al enviar por Telegram'] as $accion => $mensaje) {
            $r = $this->actingAs($u)->postJson('/modulo-marcas/reporte/'.$accion, ['fecha' => '2026-09-28'])->assertStatus(500);
            $this->assertSame($mensaje, $r->json('message'), $accion);
            $this->assertStringNotContainsString('no such table', $r->getContent());
        }

        $this->actingAs($u)->get('/modulo-marcas/reporte?fecha=2026-09-28')->assertRedirect(route('marcas.consultar'));
        $flash = (string) session('error');
        $this->assertStringStartsWith('Error al generar reporte (ref: ', $flash);
        $this->assertStringNotContainsString('no such table', $flash);

        $this->actingAs($u)->from('/modulo-marcas/reporte?fecha=2026-09-28')->post('/modulo-marcas/reporte/exportar-excel', ['fecha' => '2026-09-28'])
            ->assertRedirect('/modulo-marcas/reporte?fecha=2026-09-28');
        $this->assertStringStartsWith('Error al exportar (ref: ', (string) session('error'));
    }
}
