<?php

namespace Tests\Feature\UrdEng;

use App\Http\Controllers\Engomado\BPMEngomado\EngBpmLineController;
use App\Http\Controllers\Urdido\BPMUrdido\UrdBpmLineController;
use App\Models\Engomado\EngActividadesBpmModel;
use App\Models\Engomado\EngBpmLineModel;
use App\Models\Engomado\EngBpmModel;
use App\Models\Sistema\SYSUsuario;
use App\Models\Urdido\UrdActividadesBpmModel;
use App\Models\Urdido\UrdBpmLineModel;
use App\Models\Urdido\UrdBpmModel;
use App\Models\Urdido\URDCatalogoMaquina;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/** Checklist BPM (BPM-Line) de Urdido/Engomado: vista parametrizada, toggle y PERF de la primera visita. */
class BpmLineTest extends TestCase
{
    use ModuloUrdEng;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        foreach ([UrdBpmModel::class, EngBpmModel::class, UrdBpmLineModel::class, EngBpmLineModel::class,
            UrdActividadesBpmModel::class, EngActividadesBpmModel::class, SYSUsuario::class, URDCatalogoMaquina::class] as $m) {
            $this->tablaDe($m);
        }

        $db = DB::connection('sqlsrv');
        $cabecera = ['Status' => 'Creado', 'Fecha' => '2026-09-20 07:00:00', 'NombreEmplRec' => 'Ana', 'TurnoRecibe' => '1',
            'NombreEmplEnt' => 'Beto', 'TurnoEntrega' => '3'];
        $db->table('UrdBPM')->insert(['Id' => 1, 'Folio' => 'UB0001', 'NombreEmplAutoriza' => 'Caro Urd'] + $cabecera);
        $db->table('EngBPM')->insert(['Id' => 1, 'Folio' => 'EB0001', 'NomEmplAutoriza' => 'Caro Eng'] + $cabecera);
        foreach (range(1, 12) as $i) {
            $db->table('UrdActividadesBPM')->insert(['Orden' => $i, 'Actividad' => "Urd {$i}", 'Maquina' => $i <= 10 ? 'MC' : 'KM']);
            $db->table('EngActividadesBPM')->insert(['Orden' => $i, 'Actividad' => "Eng {$i}"]);
        }
        $db->table('URDCatalogoMaquinas')->insert(['MaquinaId' => '401', 'Nombre' => 'Mc Coy 1', 'Departamento' => 'Urdido']);
    }

    /** @return array<string, array{string, class-string, string, string, int}> */
    public static function variantes(): array
    {
        return [
            'urdido' => ['urdido', UrdBpmLineController::class, 'UB0001', 'UrdBPMLine', 10],
            'engomado' => ['engomado', EngBpmLineController::class, 'EB0001', 'EngBPMLine', 12],
        ];
    }

    /**
     * PERF: primera visita. Antes (HEAD 25e4a6d3, medido con el controller viejo): Urdido 19 queries
     * (header + value + actividades + count + first + 10 INSERT + máquina + pluck + 2 supervisor),
     * Engomado 20 (header + actividades + count + first + 12 INSERT + máquina + pluck + 2 supervisor).
     * Después: 8 en ambos (un INSERT en bloque; una sola lectura de las líneas).
     * Visitas siguientes: Urdido 9 → 7, Engomado 8 → 7.
     *
     * @param  class-string  $controller
     */
    #[DataProvider('variantes')]
    public function test_primera_visita_inserta_en_bloque(string $variante, string $controller, string $folio, string $tabla, int $actividades): void
    {
        $this->actingAs($this->usuarioCon([]));
        session($variante === 'urdido' ? ['bpm_maquina_id' => '401'] : ['bpm_eng_maquina_id' => '401']);

        $n = $this->contarQueries(fn () => app($controller)->index($folio));
        $this->assertSame(8, $n);

        $lineas = DB::connection('sqlsrv')->table($tabla)->orderBy('Orden')->get();
        $this->assertCount($actividades, $lineas);
        $this->assertSame(['Folio' => $folio, 'TurnoRecibe' => '1', 'MaquinaId' => '401', 'Departamento' => $variante === 'urdido' ? 'Urdido' : 'Engomado', 'Orden' => 1, 'Valor' => '0'],
            array_intersect_key((array) $lineas[0], array_flip(['Folio', 'TurnoRecibe', 'MaquinaId', 'Departamento', 'Orden', 'Valor'])));
        $this->assertNull(session($variante === 'urdido' ? 'bpm_maquina_id' : 'bpm_eng_maquina_id'));

        // Segunda visita: no vuelve a insertar y toma la máquina de las líneas.
        $this->assertSame(7, $this->contarQueries(fn () => app($controller)->index($folio)));
        $this->assertCount($actividades, DB::connection('sqlsrv')->table($tabla)->get());
    }

    public function test_urdido_karl_mayer_carga_las_actividades_km(): void
    {
        $this->actingAs($this->usuarioCon([]));
        session(['bpm_maquina_id' => 'KM1']);
        app(UrdBpmLineController::class)->index('UB0001');

        $this->assertSame(['Urd 11', 'Urd 12'], DB::connection('sqlsrv')->table('UrdBPMLine')->orderBy('Orden')->pluck('Actividad')->all());
    }

    #[DataProvider('variantes')]
    public function test_vista_y_toggle(string $variante, string $controller, string $folio, string $tabla): void
    {
        $prefijo = $variante === 'urdido' ? '/urd-bpm-line/' : '/eng-bpm-line/';
        $usuario = $this->usuarioCon([35 => ['acceso', 'modificar'], 41 => ['acceso', 'modificar']]);

        $html = $this->actingAs($usuario)->get($prefijo.$folio)->assertOk()
            ->assertSee('data-bpm-line=', false)
            ->assertSee($variante === 'urdido' ? 'Caro Urd' : 'Caro Eng')
            ->assertSee('data-bpm-line-accion="terminar"', false)
            ->getContent();
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('Swal.fire', $html);
        $this->assertSame(1, preg_match("/data-bpm-line='([^']*)'/", $html, $m));
        $config = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertStringEndsWith($prefijo.$folio.'/toggle', $config['rutas']['toggle']);

        $actividad = $variante === 'urdido' ? 'Urd 1' : 'Eng 1';
        $this->actingAs($usuario)->postJson($prefijo.$folio.'/toggle', ['actividad' => $actividad, 'valor' => 2])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame(2, (int) DB::connection('sqlsrv')->table($tabla)->where('Actividad', $actividad)->value('Valor'));

        // Folio ya terminado: 403 con mensaje propio (el JS lo muestra con mensajeError).
        DB::connection('sqlsrv')->table($variante === 'urdido' ? 'UrdBPM' : 'EngBPM')->update(['Status' => 'Terminado']);
        $this->actingAs($usuario)->postJson($prefijo.$folio.'/toggle', ['actividad' => $actividad, 'valor' => 0])
            ->assertForbidden()->assertJsonPath('message', 'No se pueden modificar actividades en estado Terminado');
    }

    public function test_folio_con_apostrofo_no_rompe_el_config(): void
    {
        $folio = "UB'<9";
        DB::connection('sqlsrv')->table('UrdBPM')->insert(['Id' => 9, 'Folio' => $folio, 'Status' => 'Creado', 'NombreEmplRec' => "O'Neil <b>"]);

        $html = $this->actingAs($this->usuarioCon([]))->get('/urd-bpm-line/'.rawurlencode($folio))->assertOk()->getContent();

        $this->assertSame(1, preg_match("/data-bpm-line='([^']*)'/", $html, $m));
        $config = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertStringEndsWith('/urd-bpm-line/'.rawurlencode($folio).'/toggle', $config['rutas']['toggle']);
        $this->assertStringContainsString('O&#039;Neil &lt;b&gt;', $html);
    }

    public function test_las_vistas_no_traen_js_inline(): void
    {
        foreach (['urdido/comun/bpm-line', 'urdido/Urdido-BPM-Line/index', 'engomado/Engomado-BPM-Line/index'] as $v) {
            $fuente = file_get_contents(resource_path("views/modulos/{$v}.blade.php"));
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $fuente, $v);
            $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc=)/i', $fuente, $v);
        }
    }
}
