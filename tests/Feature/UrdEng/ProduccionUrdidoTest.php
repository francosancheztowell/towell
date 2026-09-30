<?php

namespace Tests\Feature\UrdEng;

use App\Http\Controllers\Urdido\Configuracion\ModuloProduccionUrdidoController;
use App\Models\Engomado\EngProgramaEngomado;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdJuliosOrden;
use App\Models\Urdido\UrdProduccionUrdido;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Producción Urdido (19-01, unidad F): vista migrada a TS, SEC-07 de los endpoints
 * propios del controller y PERF del alta/baja de renglones.
 */
class ProduccionUrdidoTest extends TestCase
{
    use ModuloUrdEng;

    private const BASE = '/urdido/modulo-produccion-urdido';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(UrdProgramaUrdido::class, ['Incorrecto']);
        $this->tablaDe(UrdJuliosOrden::class);
        $this->tablaDe(UrdProduccionUrdido::class);
        $this->tablaDe(EngProgramaEngomado::class);
        $this->tablaDe(SYSUsuario::class, ['area']);
        // ManFallasParos usa el prefijo dbo.: sqlite lo lee como esquema.
        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        DB::connection('sqlsrv')->statement('CREATE TABLE dbo.ManFallasParos (Id INTEGER PRIMARY KEY, Folio TEXT, Estatus TEXT, Depto TEXT, MaquinaId TEXT)');

        DB::connection('sqlsrv')->table('UrdProgramaUrdido')->insert([
            'Id' => 1, 'Folio' => 'F-1', 'Status' => 'En Proceso', 'MaquinaId' => 'Mc Coy 2',
            'Metros' => 6000, 'Incorrecto' => 0,
        ]);
    }

    // ── vista ────────────────────────────────────────────────────────

    /** Las vistas propias de la pantalla no traen JS inline ni manejadores on*=. */
    public function test_las_vistas_no_traen_js_inline(): void
    {
        $vistas = [
            'modulo-produccion-urdido.blade.php',
            'produccion/_header-orden.blade.php',
            'produccion/_tabla-registros.blade.php',
            'produccion/_modal-oficial.blade.php',
            'produccion/_scripts.blade.php',
        ];
        foreach ($vistas as $vista) {
            $fuente = file_get_contents(resource_path('views/modulos/urdido/'.$vista));
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $fuente, $vista);
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)/i', $fuente, $vista);
            $this->assertStringNotContainsString('csrf_token', $fuente, $vista);
        }
    }

    public function test_render_con_orden_pasa_la_config_por_data(): void
    {
        DB::connection('sqlsrv')->table('UrdJuliosOrden')->insert(['Folio' => 'F-1', 'Julios' => 2, 'Hilos' => 640]);

        $html = $this->withoutVite()
            ->actingAs($this->usuarioCon(['Producción Urdido' => ['acceso', 'modificar']], 'Urdido'))
            ->get(self::BASE.'?orden_id=1')
            ->assertOk()
            ->assertSee('data-accion="finalizar"', false)
            ->getContent();

        $this->assertSame(2, substr_count($html, 'class="hover:bg-gray-50" data-registro-id="'));
        $this->assertSame(8, substr_count($html, 'data-accion="editar-cantidad"'));
        $config = $this->configDe($html);
        $this->assertSame(1, $config['ordenId']);
        $this->assertSame(700, (int) $config['maxKgNeto']);
        $this->assertTrue($config['puedeEditar']);
        $this->assertFalse($config['esKarlMayer']);
        $this->assertStringContainsString('orden_id=1', $config['rutas']['pdf']);
        $this->assertStringEndsWith('/modulo-produccion-urdido/finalizar', $config['rutas']['finalizar']);
        $this->assertDoesNotMatchRegularExpression('/\sonclick=/i', $html);
    }

    public function test_render_vacio(): void
    {
        $html = $this->withoutVite()
            ->actingAs($this->usuarioCon(['Producción Urdido' => ['acceso']], 'Urdido'))
            ->get(self::BASE)
            ->assertOk()
            ->getContent();

        $config = $this->configDe($html);
        $this->assertNull($config['ordenId']);
        $this->assertFalse($config['puedeEditar']);
        $this->assertStringContainsString('No hay registros para generar', $html);
    }

    // ── SEC-07 ───────────────────────────────────────────────────────

    public function test_usuarios_error_interno_no_expone_la_excepcion(): void
    {
        DB::connection('sqlsrv')->getSchemaBuilder()->drop('SYSUsuario');

        $r = $this->actingAs($this->usuarioCon(['Producción Urdido' => ['acceso']], 'Urdido'))
            ->getJson(self::BASE.'/usuarios-urdido')
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'trace_id']);

        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        $this->assertStringNotContainsString('SYSUsuario', $r->getContent());
    }

    public function test_actualizar_campo_error_interno_no_expone_la_excepcion(): void
    {
        DB::connection('sqlsrv')->getSchemaBuilder()->drop('UrdProduccionUrdido');

        $r = $this->actingAs($this->usuarioCon(['Producción Urdido' => ['acceso', 'modificar']], 'Urdido'))
            ->postJson(self::BASE.'/actualizar-campos-produccion', ['registro_id' => 1, 'campo' => 'Hilatura', 'valor' => 3])
            ->assertStatus(500)
            ->assertJsonStructure(['message', 'trace_id']);

        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        $this->assertStringNotContainsString('UrdProduccionUrdido', $r->getContent());
    }

    public function test_actualizar_campo_sigue_funcionando(): void
    {
        $id = DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insertGetId(['Folio' => 'F-1', 'Hilos' => 640]);

        $this->actingAs($this->usuarioCon(['Producción Urdido' => ['acceso', 'modificar']], 'Urdido'))
            ->postJson(self::BASE.'/actualizar-campos-produccion', ['registro_id' => $id, 'campo' => 'Hilatura', 'valor' => 3])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valor', 3);
    }

    public function test_finalizar_error_interno_no_expone_la_excepcion(): void
    {
        DB::connection('sqlsrv')->getSchemaBuilder()->drop('UrdProduccionUrdido');

        $r = $this->actingAs($this->usuarioCon([154 => ['acceso', 'modificar'], 'Producción Urdido' => ['acceso', 'modificar']], 'Urdido'))
            ->postJson(self::BASE.'/finalizar', ['orden_id' => 1])
            ->assertStatus(500)
            ->assertJsonStructure(['message', 'trace_id']);

        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        $this->assertStringNotContainsString('UrdProduccionUrdido', $r->getContent());
    }

    /** El contrato que usa el TS para preguntar antes de descartar sigue igual. */
    public function test_finalizar_pide_confirmacion_con_el_mismo_contrato(): void
    {
        DB::connection('sqlsrv')->table('UrdProduccionUrdido')->insert(['Folio' => 'F-1', 'Hilos' => 640, 'NoJulio' => '7', 'KgBruto' => 300]);

        $this->actingAs($this->usuarioCon([154 => ['acceso', 'modificar'], 'Producción Urdido' => ['acceso', 'modificar']], 'Urdido'))
            ->postJson(self::BASE.'/finalizar', ['orden_id' => 1])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requiere_confirmacion', true)
            ->assertJsonPath('registros_a_descartar', 1);
    }

    // ── paro activo en la máquina ───────────────────────────────────

    /**
     * Casos reales de dbo.ManFallasParos / URDCatalogoMaquinas (sep-2026):
     * urdidoras Mc Coy 1-3 y KM1 (la orden la llama "Karl Mayer"); 401/402 son
     * telares del depto Karl Mayer; Calidad puede levantar paros sobre urdidoras.
     *
     * @return array<string, array{string, list<array{string, string, string}>, ?string}>
     */
    public static function casosParo(): array
    {
        return [
            'Mc Coy 1 con paro activo' => ['Mc Coy 1', [['Urdido', 'Mc Coy 1', 'Activo']], 'Hay un paro activo en esta máquina (folio P-1). Ve a Paros y finalízalo.'],
            'Mc Coy 2 con paro activo' => ['Mc Coy 2', [['Urdido', 'Mc Coy 2', 'Activo']], 'Hay un paro activo en esta máquina (folio P-1). Ve a Paros y finalízalo.'],
            'Mc Coy 3 con paro activo' => ['Mc Coy 3', [['Urdido', 'Mc Coy 3', 'Activo']], 'Hay un paro activo en esta máquina (folio P-1). Ve a Paros y finalízalo.'],
            'Karl Mayer: el paro se registra como KM1' => ['Karl Mayer', [['Urdido', 'KM1', 'Activo']], 'Hay un paro activo en esta máquina (folio P-1). Ve a Paros y finalízalo.'],
            'paro levantado por Calidad en la urdidora' => ['Mc Coy 2', [['Calidad', 'Mc Coy 2', 'Activo']], 'Hay un paro activo en esta máquina (folio P-1). Ve a Paros y finalízalo.'],
            'varios paros activos: los lista todos' => ['Mc Coy 2', [['Urdido', 'Mc Coy 2', 'Activo'], ['Calidad', 'Mc Coy 2', 'Activo']], 'Hay 2 paros activos en esta máquina (folios P-1, P-2). Ve a Paros y finalízalos.'],
            'sin paros' => ['Mc Coy 2', [], null],
            'paro ya terminado' => ['Mc Coy 2', [['Urdido', 'Mc Coy 2', 'Terminado']], null],
            'paro activo en otra urdidora' => ['Mc Coy 2', [['Urdido', 'Mc Coy 1', 'Activo'], ['Urdido', 'KM1', 'Activo']], null],
            'Karl Mayer: paro de telar 401 no es la urdidora' => ['Karl Mayer', [['Karl Mayer', '401', 'Activo']], null],
            'Mc Coy: paro de KM1 no le aplica' => ['Mc Coy 3', [['Urdido', 'KM1', 'Activo']], null],
            'paro activo en Engomado' => ['Mc Coy 2', [['Engomado', 'WestPoint 2', 'Activo']], null],
        ];
    }

    /** @param list<array{string, string, string}> $paros */
    #[DataProvider('casosParo')]
    public function test_finalizar_valida_paro_activo_de_la_maquina(string $maquinaOrden, array $paros, ?string $mensaje): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('UrdProgramaUrdido')->where('Id', 1)->update(['MaquinaId' => $maquinaOrden]);
        foreach ($paros as $i => [$depto, $maquina, $estatus]) {
            $db->table('dbo.ManFallasParos')->insert(['Folio' => 'P-'.($i + 1), 'Depto' => $depto, 'MaquinaId' => $maquina, 'Estatus' => $estatus]);
        }

        $r = $this->actingAs($this->usuarioCon([154 => ['acceso', 'modificar'], 'Producción Urdido' => ['acceso', 'modificar']], 'Urdido'))
            ->postJson(self::BASE.'/finalizar', ['orden_id' => 1]);

        if ($mensaje === null) {
            $r->assertOk()->assertJsonPath('success', true);
            $this->assertSame('Finalizado', $db->table('UrdProgramaUrdido')->where('Id', 1)->value('Status'));

            return;
        }

        $r->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', $mensaje);
        $this->assertSame('En Proceso', $db->table('UrdProgramaUrdido')->where('Id', 1)->value('Status'));
    }

    /** Bloqueado por paro no descarta ni marca registros: la orden queda como estaba. */
    public function test_finalizar_con_paro_no_toca_los_registros(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('UrdProduccionUrdido')->insert([
            ['Folio' => 'F-1', 'Hilos' => 640, 'NoJulio' => '7', 'KgBruto' => 300, 'HoraInicial' => '07:00', 'HoraFinal' => '08:00'],
            ['Folio' => 'F-1', 'Hilos' => 640, 'NoJulio' => null, 'KgBruto' => null, 'HoraInicial' => null, 'HoraFinal' => null],
        ]);
        $db->table('dbo.ManFallasParos')->insert(['Folio' => 'P-1', 'Depto' => 'Urdido', 'MaquinaId' => 'Mc Coy 2', 'Estatus' => 'Activo']);

        $this->actingAs($this->usuarioCon([154 => ['acceso', 'modificar'], 'Producción Urdido' => ['acceso', 'modificar']], 'Urdido'))
            ->postJson(self::BASE.'/finalizar', ['orden_id' => 1, 'confirmar_descarte' => true])
            ->assertStatus(422);

        $this->assertSame(2, $db->table('UrdProduccionUrdido')->where('Folio', 'F-1')->count());
        $this->assertSame(0, $db->table('UrdProduccionUrdido')->where('Finalizar', 1)->count());
        $this->assertNull($db->table('UrdProgramaUrdido')->where('Id', 1)->value('FechaFinaliza'));
    }

    // ── PERF ─────────────────────────────────────────────────────────

    /**
     * Sobrantes en dos grupos de Hilos: antes una consulta de ids por grupo,
     * ahora una sola para todos (mismo resultado: se borran los más nuevos de cada grupo).
     */
    public function test_perf_sobrantes_en_varios_grupos_una_sola_consulta(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('UrdJuliosOrden')->insert([
            ['Folio' => 'F-1', 'Julios' => 2, 'Hilos' => 640],
            ['Folio' => 'F-1', 'Julios' => 2, 'Hilos' => 500],
            ['Folio' => 'F-1', 'Julios' => 1, 'Hilos' => 300],
        ]);
        // Plan [640,640,500,500,300]; sobran 2 de 640, 1 de 500 y 1 de 300.
        foreach ([640, 640, 500, 500, 300, 640, 500, 640, 300] as $hilos) {
            $db->table('UrdProduccionUrdido')->insert(['Folio' => 'F-1', 'Hilos' => $hilos]);
        }
        // La 8 (640) trae captura: no se toca. Se borran los 2 vacíos más nuevos de 640 (6 y 2),
        // el de 500 (7) y el de 300 (9), igual que con una consulta por grupo.
        $db->table('UrdProduccionUrdido')->where('Id', 8)->update(['NoJulio' => '12']);

        $n = $this->contarQueries(fn () => $this->sincronizar());

        $this->assertSame([1, 3, 4, 5, 8], $db->table('UrdProduccionUrdido')->orderBy('Id')->pluck('Id')->all());
        // Antes: 8 (una consulta de ids por cada uno de los 3 grupos con sobrante). Después: 6.
        $this->assertSame(6, $n);
    }

    /**
     * Realinear Hilos de esqueletos: antes un UPDATE por fila, ahora un UPDATE por valor
     * de Hilos (misma tabla resultante; el modelo no tiene timestamps ni eventos).
     */
    public function test_perf_reproyectar_hilos_un_update_por_valor(): void
    {
        $db = DB::connection('sqlsrv');
        $db->table('UrdJuliosOrden')->insert([
            ['Folio' => 'F-1', 'Julios' => 3, 'Hilos' => 640],
            ['Folio' => 'F-1', 'Julios' => 3, 'Hilos' => 500],
        ]);
        for ($i = 0; $i < 6; $i++) {
            $db->table('UrdProduccionUrdido')->insert(['Folio' => 'F-1', 'Hilos' => 700]);
        }
        // La 2 trae captura: se queda con 700.
        $db->table('UrdProduccionUrdido')->where('Id', 2)->update(['KgBruto' => 250]);

        $n = $this->contarQueries(fn () => $this->sincronizar());

        $this->assertSame(
            [1 => 640, 2 => 700, 3 => 640, 4 => 500, 5 => 500, 6 => 500],
            $db->table('UrdProduccionUrdido')->orderBy('Id')->pluck('Hilos', 'Id')->map(fn ($h) => (int) $h)->all()
        );
        // Antes: 9 (5 UPDATE fila a fila). Después: 6 (2 UPDATE, uno por valor de Hilos).
        $this->assertSame(6, $n);
    }

    // ── helpers ──────────────────────────────────────────────────────

    private function sincronizar(): void
    {
        $controller = new ModuloProduccionUrdidoController;
        $orden = UrdProgramaUrdido::find(1);
        $g = new \ReflectionMethod($controller, 'getJuliosForOrder');
        $julios = $g->invoke($controller, $orden);
        $total = (int) $julios->sum('Julios');
        (new \ReflectionMethod($controller, 'ensureProductionRecordsExist'))->invoke($controller, $orden, $julios, $total);
    }

    /** @return array<string, mixed> */
    private function configDe(string $html): array
    {
        $this->assertMatchesRegularExpression("/data-produccion-urdido='([^']*)'/", $html);
        preg_match("/data-produccion-urdido='([^']*)'/", $html, $m);

        return json_decode(html_entity_decode($m[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }
}
