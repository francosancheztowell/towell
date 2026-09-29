<?php

namespace Tests\Feature\Tejido;

use App\Http\Controllers\Tejido\CortesEficiencia\CortesEficienciaController;
use App\Models\Inventario\InvSecuenciaCorteEf;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\Usuario;
use App\Models\Tejido\TejeFallasCeModel;
use App\Models\Tejido\TejEficiencia;
use App\Models\Tejido\TejEficienciaLine;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tejido\Concerns\ModuloTejido;
use Tests\TestCase;

/**
 * Cortes de Eficiencia (19-02 p. C): vistas sin JS inline, contrato JSON que usa el TS,
 * errores sin detalle interno (SEC-07), N+1 medidos y el stub update() en 410.
 *
 * sqlite guarda Date como texto ('Y-m-d H:i:s' por el cast del modelo); en SQL Server la
 * columna es date y `where('Date', 'Y-m-d')` compara por valor. Por eso los guardados que
 * vuelven a buscar la línea mandan la fecha con hora.
 */
class CortesEficienciaTest extends TestCase
{
    use ModuloTejido;

    private const FECHA = '2026-09-24 00:00:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        $this->tablaTejido(TejEficiencia::class);
        $this->tablaTejido(TejEficienciaLine::class);
        $this->tablaTejido(InvSecuenciaCorteEf::class);
        $this->tablaTejido(ReqProgramaTejido::class);
        $this->tablaTejido(TejeFallasCeModel::class);
    }

    private ?Usuario $usuario = null;

    private function usuario(string $puesto = 'Operador'): Usuario
    {
        $todas = ['acceso', 'crear', 'modificar', 'eliminar', 'registrar'];
        $this->usuario ??= $this->usuarioCon([105 => $todas, 'Cortes de Eficiencia' => $todas], 'Tejido');
        $this->usuario->puesto = $puesto;

        return $this->usuario;
    }

    private function sembrarTelares(int $n): void
    {
        $filas = [];
        for ($i = 1; $i <= $n; $i++) {
            $filas[] = ['NoTelarId' => 200 + $i, 'SalonTejidoId' => 'JACQUARD', 'Orden' => $i];
        }
        InvSecuenciaCorteEf::query()->insert($filas);
    }

    private function sembrarCorte(string $folio, int $turno, string $status, int $telares = 2): void
    {
        TejEficiencia::query()->insert([
            'Folio' => $folio, 'Date' => self::FECHA, 'Turno' => $turno, 'Status' => $status,
            'numero_empleado' => '', 'nombreEmpl' => "O'Brien <b>", 'Horario1' => '07:05:00.000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $lineas = [];
        for ($i = 1; $i <= $telares; $i++) {
            $lineas[] = [
                'Folio' => $folio, 'Date' => self::FECHA, 'Turno' => $turno, 'NoTelarId' => 200 + $i,
                'RpmStd' => 400, 'EficienciaSTD' => 85, 'RpmR1' => 390 + $i, 'EficienciaR1' => 80,
                'ObsR1' => 'falla <i>', 'StatusOB1' => 1, 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        TejEficienciaLine::query()->insert($lineas);
    }

    /** @return array<int, array<string, mixed>> */
    private function datosTelares(int $n, int $rpm = 410): array
    {
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $out[] = [
                'NoTelar' => 200 + $i, 'SalonTejidoId' => null, 'RpmStd' => 400, 'EficienciaStd' => 85,
                'RpmR1' => $rpm, 'EficienciaR1' => 88, 'RpmR2' => null, 'EficienciaR2' => null, 'RpmR3' => null, 'EficienciaR3' => null,
                'ObsR1' => null, 'ObsR2' => null, 'ObsR3' => null, 'StatusOB1' => 0, 'StatusOB2' => 0, 'StatusOB3' => 0,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function payloadStore(string $folio, int $n, int $rpm = 410): array
    {
        return [
            'folio' => $folio, 'fecha' => self::FECHA, 'turno' => '2', 'status' => 'En Proceso',
            'usuario' => 'Usuario prueba', 'noEmpleado' => '100', 'datos_telares' => $this->datosTelares($n, $rpm),
            'horario1' => '15:00', 'horario2' => null, 'horario3' => null,
        ];
    }

    private function sinJsInline(string $html): void
    {
        foreach (['onclick="CortesManager', 'ondblclick=', 'onclick="exportar', 'onclick="descargar', 'onclick="compartir',
            'onclick="notificar', 'onclick="cerrarModal', 'onclick="confirmar', 'const routes', 'class CortesManager',
            'X-CSRF-TOKEN', 'Swal.fire', 'text-[10px]', 'text-[11px]'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $html);
        }
    }

    /* ---------------- Vistas ---------------- */

    public function test_captura_con_config_y_sin_js_inline(): void
    {
        $this->sembrarTelares(2);
        $this->sembrarCorte('CE0002', 2, 'En Proceso');

        $html = $this->actingAs($this->usuario())->get('/modulo-cortes-de-eficiencia?folio=CE0002')->assertOk()->getContent();

        $this->sinJsInline($html);
        $this->assertStringContainsString('id="pagina-cortes"', $html);
        $this->assertStringContainsString('data-accion="tomar-hora"', $html);
        $this->assertStringContainsString('id="modal-observaciones"', $html);
        $this->assertStringContainsString('aria-label="Tomar hora del horario 1"', $html);
        $cfg = $this->configDe($html, 'pagina-cortes');
        $this->assertFalse($cfg['soloLectura']);
        $this->assertSame('CE0002', $cfg['folioInicial']);
        $this->assertStringEndsWith('/modulo-cortes-de-eficiencia/__FOLIO__', $cfg['rutas']['corte']);
        $this->assertStringEndsWith('/modulo-cortes-de-eficiencia/guardar-hora', $cfg['rutas']['guardarHora']);
    }

    public function test_visualizar_folio_en_solo_lectura(): void
    {
        $this->sembrarTelares(2);
        $this->sembrarCorte('CE0001', 1, 'Finalizado');

        $html = $this->actingAs($this->usuario())->get('/modulo-cortes-de-eficiencia/visualizar-folio/CE0001')->assertOk()->getContent();

        $this->sinJsInline($html);
        $cfg = $this->configDe($html, 'pagina-cortes');
        $this->assertTrue($cfg['soloLectura']);
        $this->assertSame('CE0001', $cfg['folioInicial']);
    }

    public function test_consultar_con_filas_por_data_y_modales(): void
    {
        $this->sembrarCorte('CE0001', 1, 'Finalizado');
        $this->sembrarCorte('CE0002', 2, 'En Proceso');

        $html = $this->actingAs($this->usuario('Supervisor'))->get('/modulo-cortes-de-eficiencia/consultar')->assertOk()->getContent();

        $this->sinJsInline($html);
        $this->assertStringContainsString('data-folio="CE0002"', $html);
        $this->assertStringContainsString('data-fecha="2026-09-24"', $html);
        $this->assertStringContainsString('id="modal-nuevo-corte"', $html);
        $this->assertStringContainsString('id="modal-editar-registro"', $html);
        $cfg = $this->configDe($html, 'pagina-consultar');
        $this->assertTrue($cfg['esSupervisor']);
        $this->assertStringEndsWith('/modulo-cortes-de-eficiencia/__FOLIO__/actualizar-registro', $cfg['rutas']['actualizarRegistro']);
    }

    public function test_visualizar_por_fecha_sin_js_inline(): void
    {
        $this->sembrarTelares(2);
        $this->sembrarCorte('CE0002', 2, 'En Proceso');

        $html = $this->actingAs($this->usuario())->get('/modulo-cortes-de-eficiencia/visualizar/CE0002')->assertOk()->getContent();

        $this->sinJsInline($html);
        $this->assertStringContainsString('data-accion="excel"', $html);
        $this->assertStringContainsString('data-accion="imagen"', $html);
        $this->assertStringContainsString('falla &lt;i&gt;', $html);
        $cfg = $this->configDe($html, 'pagina-visualizar');
        $this->assertSame('2026-09-24', $cfg['fecha']);
        $this->assertStringEndsWith('/modulo-cortes-de-eficiencia/visualizar/descargar-pdf', $cfg['rutas']['pdf']);
    }

    /* ---------------- Contrato JSON que usa el TS ---------------- */

    public function test_contrato_json_de_captura(): void
    {
        $this->sembrarTelares(2);
        $this->sembrarCorte('CE0002', 2, 'En Proceso');
        TejeFallasCeModel::query()->insert(['Clave' => 'F1', 'Descripcion' => 'Falla uno']);
        ReqProgramaTejido::query()->insert(['NoTelarId' => 201, 'EnProceso' => 1, 'VelocidadSTD' => 420, 'EficienciaSTD' => 0.8]);
        $u = $this->usuario();

        $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/turno-info')->assertOk()->assertJsonStructure(['success', 'turno', 'descripcion']);
        $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/fallas')->assertOk()->assertJsonPath('data.0.Clave', 'F1')->assertJsonPath('data.0.Descripcion', 'Falla uno');
        $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/datos-programa-tejido')->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('telares.0.VelocidadSTD', 420);
        $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/datos-telares')->assertOk()
            ->assertJsonStructure(['success', 'telares' => [['NoTelarId', 'VelocidadStd', 'EficienciaStd']]])
            ->assertJsonPath('telares.0.NoTelarId', 201);

        $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/CE0002')->assertOk()
            ->assertJsonPath('data.folio', 'CE0002')->assertJsonPath('data.turno', '2')->assertJsonPath('data.horario_1', '07:05')
            ->assertJsonPath('data.status', 'En Proceso')
            ->assertJsonStructure(['success', 'data' => ['fecha', 'usuario', 'noEmpleado', 'horario_2', 'horario_3', 'datos_telares' => [[
                'NoTelar', 'RpmStd', 'EficienciaStd', 'RpmR1', 'EficienciaR1', 'RpmR2', 'EficienciaR2', 'RpmR3', 'EficienciaR3',
                'ObsR1', 'ObsR2', 'ObsR3', 'StatusOB1', 'StatusOB2', 'StatusOB3',
            ]]]]);
        $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/NOEXISTE')->assertNotFound()->assertJson(['success' => false, 'message' => 'Corte no encontrado']);

        // Ya hay folio para esa fecha/turno → 400 con folio_existente (el TS lo usa para ir a editarlo).
        $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/generar-folio?fecha='.urlencode(self::FECHA).'&turno=2')
            ->assertStatus(400)->assertJsonPath('folio_existente', 'CE0002');

        $this->actingAs($u)->postJson('/modulo-cortes-de-eficiencia/guardar-hora', ['folio' => 'CE0002', 'turno' => 2, 'horario' => 2, 'hora' => '16:10', 'fecha' => self::FECHA])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('16:10', TejEficiencia::find('CE0002')->Horario2);

        $this->actingAs($u)->postJson('/modulo-cortes-de-eficiencia', $this->payloadStore('CE0002', 2))
            ->assertOk()->assertJson(['success' => true, 'folio' => 'CE0002']);

        $this->actingAs($u)->postJson('/modulo-cortes-de-eficiencia/CE0002/finalizar')->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data.status', 'Finalizado');
    }

    public function test_actualizar_registro_de_supervisor(): void
    {
        $this->sembrarCorte('CE0002', 2, 'En Proceso');

        $this->actingAs($this->usuario())->putJson('/modulo-cortes-de-eficiencia/CE0002/actualizar-registro', ['Turno' => 3])->assertForbidden();
        $this->actingAs($this->usuario('Supervisor'))->putJson('/modulo-cortes-de-eficiencia/CE0002/actualizar-registro', ['Date' => '2026-09-25', 'Turno' => 3, 'Status' => 'En Proceso'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame(2, TejEficienciaLine::where('Folio', 'CE0002')->where('Turno', 3)->count());
    }

    /* ---------------- SEC-07 ---------------- */

    public function test_errores_sin_detalle_interno(): void
    {
        $this->sembrarTelares(2);
        $u = $this->usuario();

        // Validación: 422 con errores de campo, no un 500 con el texto de la excepción.
        $this->actingAs($u)->postJson('/modulo-cortes-de-eficiencia', ['folio' => 'X'])->assertStatus(422)->assertJsonStructure(['errors' => ['fecha']]);
        $this->actingAs($u)->postJson('/modulo-cortes-de-eficiencia/guardar-hora', ['folio' => 'X'])->assertStatus(422)->assertJsonStructure(['errors' => ['hora']]);

        // Sin SSYSFoliosSecuencias: el folio no se genera y no se ve el SQL.
        $r = $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/generar-folio?fecha=2026-09-30&turno=1')->assertStatus(500);
        $this->assertSame('Error al generar el folio', $r->json('message'));
        $this->assertArrayHasKey('trace_id', $r->json());
        $this->assertStringNotContainsString('SSYSFoliosSecuencias', $r->getContent());

        $this->sembrarCorte('CE0002', 2, 'En Proceso');
        DB::connection('sqlsrv')->statement('DROP TABLE "TejEficienciaLine"');

        foreach ([
            ['GET', '/modulo-cortes-de-eficiencia/CE0002', [], 'Error al obtener el corte de eficiencia'],
            ['GET', '/modulo-cortes-de-eficiencia/datos-telares', [], 'Error al obtener datos de telares'],
            ['POST', '/modulo-cortes-de-eficiencia', $this->payloadStore('CE0002', 2), 'Error al guardar el corte de eficiencia'],
            ['POST', '/modulo-cortes-de-eficiencia/visualizar/notificar-telegram', ['fecha' => '2026-09-24'], 'Error al enviar por Telegram'],
        ] as [$metodo, $url, $datos, $mensaje]) {
            $r = $this->actingAs($u)->json($metodo, $url, $datos)->assertStatus(500);
            $this->assertSame($mensaje, $r->json('message'), $url);
            $this->assertStringNotContainsString('TejEficienciaLine', $r->getContent(), $url);
        }

        // Excel y PDF conservan la clave `error` que ya mandaban.
        foreach (['exportar-excel' => 'Error al exportar', 'descargar-pdf' => 'Error al generar PDF'] as $ruta => $mensaje) {
            $r = $this->actingAs($u)->postJson('/modulo-cortes-de-eficiencia/visualizar/'.$ruta, ['fecha' => '2026-09-24'])->assertStatus(500);
            $this->assertSame($mensaje, $r->json('error'));
            $this->assertSame($mensaje, $r->json('message'));
            $this->assertStringNotContainsString('TejEficienciaLine', $r->getContent());
        }

        // Flash del redirect: mensaje genérico con referencia, sin el SQL.
        $this->actingAs($u)->get('/modulo-cortes-de-eficiencia/visualizar/CE0002')->assertRedirect('/modulo-cortes-de-eficiencia/consultar');
        $flash = (string) session('error');
        $this->assertStringStartsWith('Error al visualizar el corte (ref: ', $flash);
        $this->assertStringNotContainsString('TejEficienciaLine', $flash);

        DB::connection('sqlsrv')->statement('DROP TABLE "TejEficiencia"');
        $r = $this->actingAs($u)->postJson('/modulo-cortes-de-eficiencia/guardar-hora', ['folio' => 'CE0002', 'turno' => 2, 'horario' => 1, 'hora' => '15:00', 'fecha' => self::FECHA])->assertStatus(500);
        $this->assertSame('Error al guardar la hora', $r->json('message'));
        $this->assertStringNotContainsString('TejEficiencia', $r->getContent());
    }

    public function test_update_stub_responde_410(): void
    {
        $this->sembrarCorte('CE0002', 2, 'En Proceso');

        $this->actingAs($this->usuario())->putJson('/modulo-cortes-de-eficiencia/CE0002', $this->payloadStore('CE0002', 1))
            ->assertStatus(410)
            ->assertJson(['success' => false, 'message' => 'Esta acción ya no existe; usa Guardar del corte.']);

        // Nadie la llama: ni el TS ni Blade usan la ruta cortes.eficiencia.update.
        $fuentes = array_merge(
            glob(base_path('resources/js/modulos/tejido/cortes-eficiencia/*/*.ts')) ?: [],
            glob(base_path('resources/views/modulos/cortes-eficiencia/*.blade.php')) ?: [],
        );
        foreach ($fuentes as $archivo) {
            $this->assertStringNotContainsString('cortes.eficiencia.update', (string) file_get_contents($archivo), $archivo);
            $this->assertDoesNotMatchRegularExpression('/http\.put\(\s*rutas\.corte/', (string) file_get_contents($archivo), $archivo);
        }
    }

    /* ---------------- PERF ---------------- */

    public function test_datos_telares_en_una_consulta_de_lineas(): void
    {
        $this->sembrarTelares(30);
        for ($t = 1; $t <= 30; $t++) {
            $filas = [];
            foreach ([1, 2, 3] as $turno) {
                $filas[] = ['Folio' => 'CE0001', 'Date' => self::FECHA, 'Turno' => $turno, 'NoTelarId' => 200 + $t,
                    'RpmR1' => 300 + $turno, 'RpmR3' => $turno === 3 ? 0 : null, 'EficienciaR2' => 70 + $turno, 'created_at' => now()];
            }
            TejEficienciaLine::query()->insert($filas);
        }
        $u = $this->usuario();

        // Antes: 1 consulta de la secuencia + 1 por telar (el foreach que había en getDatosTelares).
        $antes = $this->contarQueries(function () {
            foreach (InvSecuenciaCorteEf::orderBy('Orden')->get(['NoTelarId']) as $row) {
                TejEficienciaLine::where('NoTelarId', (int) $row->NoTelarId)->orderBy('Date', 'desc')->orderBy('Turno', 'desc')
                    ->orderBy('created_at', 'desc')->limit(20)->get();
            }
        });
        $this->assertSame(31, $antes);

        $despues = $this->contarQueries(fn () => $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/datos-telares')->assertOk());
        $this->assertSame(2, $despues);

        // Mismo resultado: la línea más reciente (turno 3 → RpmR1 303, RpmR3 = 0 se salta) y EficienciaR2 73.
        $r = $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/datos-telares')->json('telares');
        $this->assertCount(30, $r);
        $this->assertEquals(['NoTelarId' => 230, 'VelocidadStd' => 303, 'EficienciaStd' => 73], $r[29]);
    }

    public function test_datos_telares_respeta_el_tope_de_20_lineas(): void
    {
        $this->sembrarTelares(1);
        // 21 líneas: la más vieja (fuera del tope) es la única con RPM; la respuesta cae al RpmStd de la más reciente.
        for ($d = 1; $d <= 21; $d++) {
            TejEficienciaLine::query()->insert(['Folio' => 'F'.$d, 'Date' => sprintf('2026-08-%02d 00:00:00', $d), 'Turno' => 1,
                'NoTelarId' => 201, 'RpmStd' => 100 + $d, 'RpmR1' => $d === 1 ? 999 : 0, 'created_at' => now()]);
        }

        $r = $this->actingAs($this->usuario())->getJson('/modulo-cortes-de-eficiencia/datos-telares')->json('telares.0');
        $this->assertEquals(121, $r['VelocidadStd']);
    }

    public function test_programa_tejido_sin_en_proceso_en_una_consulta(): void
    {
        $this->sembrarTelares(30);
        for ($t = 1; $t <= 30; $t++) {
            ReqProgramaTejido::query()->insert([
                ['NoTelarId' => 200 + $t, 'EnProceso' => 0, 'VelocidadSTD' => 100, 'EficienciaSTD' => 0.5],
                ['NoTelarId' => 200 + $t, 'EnProceso' => 0, 'VelocidadSTD' => 300 + $t, 'EficienciaSTD' => 0.9],
            ]);
        }
        $u = $this->usuario();

        // Antes: pluck + EnProceso + 1 por telar (el map con first() que había en el respaldo).
        $antes = $this->contarQueries(function () {
            $orden = InvSecuenciaCorteEf::orderBy('Orden')->pluck('NoTelarId')->toArray();
            ReqProgramaTejido::whereIn('NoTelarId', $orden)->where('EnProceso', 1)->get();
            foreach ($orden as $telar) {
                ReqProgramaTejido::where('NoTelarId', $telar)->orderBy('Id', 'desc')->first();
            }
        });
        $this->assertSame(32, $antes);

        $despues = $this->contarQueries(fn () => $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/datos-programa-tejido')->assertOk());
        $this->assertSame(3, $despues);

        $telares = $this->actingAs($u)->getJson('/modulo-cortes-de-eficiencia/datos-programa-tejido')->json('telares');
        $this->assertEquals(['NoTelar' => 201, 'VelocidadSTD' => 301, 'EficienciaSTD' => 0.9], $telares[0]);
        $this->assertCount(30, $telares);
    }

    public function test_store_guarda_lineas_sin_una_consulta_por_telar(): void
    {
        $this->sembrarTelares(30);
        TejEficiencia::query()->insert(['Folio' => 'CE0009', 'Date' => self::FECHA, 'Turno' => 2, 'Status' => 'En Proceso']);
        $u = $this->usuario();

        // Antes: un updateOrCreate por telar = SELECT + INSERT por fila en cada autoguardado.
        $antes = $this->contarQueries(function () {
            foreach ($this->datosTelares(30) as $t) {
                TejEficienciaLine::updateOrCreate(['Folio' => 'ANTES', 'NoTelarId' => $t['NoTelar'], 'Turno' => '2', 'Date' => self::FECHA], ['RpmR1' => $t['RpmR1']]);
            }
        });
        $this->assertSame(60, $antes);

        // Primer guardado: 2 de permisos (middleware) + 3 validaciones de cabecera + updateOrCreate
        // de la cabecera (SELECT + UPDATE) + salones + líneas existentes + 1 INSERT de las 30 líneas.
        $primero = $this->contarQueries(fn () => $this->actingAs($u)->postJson('/modulo-cortes-de-eficiencia', $this->payloadStore('CE0009', 30))->assertOk());
        $this->assertSame(10, $primero);
        $this->assertSame(30, TejEficienciaLine::where('Folio', 'CE0009')->count());

        // Autoguardado con un solo cambio (permisos ya en caché): 3 validaciones + SELECT de cabecera
        // (sin cambios, sin UPDATE) + salones + líneas existentes + 1 UPDATE de la línea que cambió.
        // Antes: además un SELECT por cada uno de los 30 telares.
        $payload = $this->payloadStore('CE0009', 30);
        $payload['datos_telares'][4]['RpmR1'] = 455;
        $segundo = $this->contarQueries(fn () => $this->actingAs($u)->postJson('/modulo-cortes-de-eficiencia', $payload)->assertOk());
        $this->assertSame(7, $segundo);
        $this->assertSame(30, TejEficienciaLine::where('Folio', 'CE0009')->count());
        $this->assertEquals(455, TejEficienciaLine::where('Folio', 'CE0009')->where('NoTelarId', 205)->value('RpmR1'));
        $this->assertEquals(410, TejEficienciaLine::where('Folio', 'CE0009')->where('NoTelarId', 206)->value('RpmR1'));
        $this->assertSame('JACQUARD', TejEficienciaLine::where('Folio', 'CE0009')->where('NoTelarId', 206)->value('SalonTejidoId'));
        $this->assertNotNull(TejEficienciaLine::where('Folio', 'CE0009')->where('NoTelarId', 206)->value('created_at'));
    }

    public function test_store_si_otra_sesion_inserta_la_misma_linea_cae_a_fila_por_fila(): void
    {
        $this->sembrarTelares(3);
        TejEficiencia::query()->insert(['Folio' => 'CE0009', 'Date' => self::FECHA, 'Turno' => 2, 'Status' => 'En Proceso']);
        DB::connection('sqlsrv')->statement('CREATE UNIQUE INDEX ux_linea ON "TejEficienciaLine" ("Folio", "NoTelarId", "Turno", "Date")');

        // Otra sesión mete la línea del telar 202 justo después de que este guardado leyó las existentes.
        $metida = false;
        DB::connection('sqlsrv')->listen(function ($q) use (&$metida) {
            if (! $metida && str_starts_with($q->sql, 'select * from "TejEficienciaLine" where "Folio"')) {
                $metida = true;
                TejEficienciaLine::query()->insert(['Folio' => 'CE0009', 'NoTelarId' => 202, 'Turno' => '2', 'Date' => self::FECHA, 'RpmR1' => 1]);
            }
        });

        $this->actingAs($this->usuario())->postJson('/modulo-cortes-de-eficiencia', $this->payloadStore('CE0009', 3))->assertOk()->assertJson(['success' => true]);

        $this->assertTrue($metida);
        $this->assertSame(3, TejEficienciaLine::where('Folio', 'CE0009')->count());
        $this->assertEquals(410, TejEficienciaLine::where('Folio', 'CE0009')->where('NoTelarId', 202)->value('RpmR1'));
    }

    public function test_visualizacion_por_fecha_son_3_consultas_fijas(): void
    {
        // El encargo hablaba de un N+1 "3 consultas por fecha del rango": la función no recorre
        // un rango; son 3 consultas por llamada (líneas, secuencia, cabeceras) sin importar los telares.
        $metodo = new \ReflectionMethod(CortesEficienciaController::class, 'obtenerDatosVisualizacionPorFecha');
        $controller = app(CortesEficienciaController::class);

        $this->sembrarTelares(3);
        $this->sembrarCorte('CE0001', 1, 'Finalizado', 3);
        $pocos = $this->contarQueries(fn () => $metodo->invoke($controller, '2026-09-24'));

        InvSecuenciaCorteEf::query()->delete();
        TejEficienciaLine::query()->delete();
        $this->sembrarTelares(40);
        $this->sembrarCorte('CE0002', 2, 'En Proceso', 40);
        $muchos = $this->contarQueries(fn () => $metodo->invoke($controller, '2026-09-24'));

        $this->assertSame(3, $pocos);
        $this->assertSame(3, $muchos);
        $info = $metodo->invoke($controller, '2026-09-24');
        $this->assertCount(40, $info['datos']);
        $this->assertSame(['1' => null, '2' => 'CE0002'], ['1' => $info['foliosPorTurno']['1'] ?? null, '2' => $info['foliosPorTurno']['2']]);
    }

    /** @return array<string, mixed> */
    private function configDe(string $html, string $id): array
    {
        $this->assertMatchesRegularExpression('/id="'.$id.'"[^>]*data-pagina=\'([^\']*)\'/', $html);
        preg_match('/id="'.$id.'"[^>]*data-pagina=\'([^\']*)\'/', $html, $m);

        return json_decode(html_entity_decode($m[1]), true, 512, JSON_THROW_ON_ERROR);
    }
}
