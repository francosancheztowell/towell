<?php

namespace Tests\Feature\ProgramaUrdEng;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/**
 * Creación de órdenes (19-05, BUG-033): la vista se sirve por Vite, sin JS inline ni public/js,
 * y los telares de ?telares= llegan al bundle en data-pagina (con rutas resueltas por route()).
 */
class CreacionOrdenesVistaTest extends TestCase
{
    use ModuloUrdEng;

    private const URL = '/programa-urd-eng/creacion-ordenes';

    private const MODULO = 52; // Programa Urd / Eng (routes/modules/programa-urd-eng.php)

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        Schema::connection('sqlsrv')->create('URDCatalogoMaquinas', function (Blueprint $t): void {
            $t->string('MaquinaId')->primary();
            $t->string('Nombre')->nullable();
            $t->string('Departamento')->nullable();
        });
    }

    /** @return array<string, mixed> */
    private function config(string $html): array
    {
        $this->assertMatchesRegularExpression("/id=\"creacion-ordenes\" data-pagina='([^']*)'/", $html);
        preg_match("/id=\"creacion-ordenes\" data-pagina='([^']*)'/", $html, $m);
        $config = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $this->assertIsArray($config);

        return $config;
    }

    public function test_renderiza_con_telares_de_la_query_y_rutas_resueltas(): void
    {
        $telares = [
            ['no_telar' => '305', 'fecha_req' => '2026-10-02', 'cuenta' => '3040', 'calibre' => 12.5, 'hilo' => "O'Neil <b>",
                'tamano' => '28x60', 'urdido' => 'Mc Coy 1', 'tipo' => 'Rizo', 'destino' => '', 'tipo_atado' => 'Normal',
                'metros' => '1200.50', 'kilos' => '350.25', 'agrupar' => true],
        ];

        $html = $this->actingAs($this->usuarioCon([self::MODULO => ['acceso', 'crear']]))
            ->get(self::URL.'?telares='.urlencode((string) json_encode($telares)))
            ->assertOk()
            ->getContent();

        $config = $this->config((string) $html);
        $this->assertSame($telares, $config['telares']);
        $this->assertSame(route('programa.urd.eng.crear.ordenes'), $config['rutas']['crearOrdenes']);
        $this->assertSame(route('programa.urd.eng.reservar.programar'), $config['rutas']['despues']);
        foreach (['buscarBomUrdido', 'buscarBomEngomado', 'materialesUrdido', 'materialesEngomado', 'anchosBalona', 'maquinasEngomado', 'nucleos'] as $ruta) {
            $this->assertNotEmpty($config['rutas'][$ruta], $ruta);
        }

        // Los datos con ' y < no rompen el atributo.
        $this->assertStringNotContainsString("O'Neil <b>", (string) $html);
        // Botón del navbar por data-accion, modal de fecha y tabla de construcción.
        $this->assertStringContainsString('data-accion="crear-ordenes"', (string) $html);
        $this->assertStringContainsString('id="modalFechaRequerimiento"', (string) $html);
        $this->assertSame(4, substr_count((string) $html, 'data-julios'));
    }

    public function test_sin_telares_manda_lista_vacia(): void
    {
        $html = $this->actingAs($this->usuarioCon([self::MODULO => ['acceso']]))->get(self::URL)->assertOk()->getContent();

        $this->assertSame([], $this->config((string) $html)['telares']);
    }

    public function test_el_contenido_no_trae_script_inline_ni_public_js(): void
    {
        $html = (string) $this->actingAs($this->usuarioCon([self::MODULO => ['acceso']]))->get(self::URL)->assertOk()->getContent();

        // Del inicio del contenido al final del modal de fecha (el layout agrega sus propios scripts).
        $inicio = strpos($html, 'id="creacion-ordenes"');
        $fin = strpos($html, '</dialog>', (int) $inicio);
        $this->assertNotFalse($inicio);
        $this->assertNotFalse($fin);
        $contenido = substr($html, (int) $inicio, (int) $fin - (int) $inicio);

        $this->assertStringNotContainsString('<script', $contenido);
        $this->assertStringNotContainsString('creacion-ordenes.js', $html);
        $this->assertStringNotContainsString('onclick="crearOrdenes()"', $html);
        $this->assertStringNotContainsString('initCreacionOrdenes', $html);
        // Solo el botón × de x-ui.modal-base lleva onclick (lo emite el componente).
        $this->assertSame(1, substr_count($contenido, 'onclick='));
    }

    public function test_sin_permiso_de_acceso_no_entra(): void
    {
        $respuesta = $this->actingAs($this->usuarioCon([self::MODULO => []]))->get(self::URL);

        $this->assertContains($respuesta->getStatusCode(), [302, 403]);
    }

    /** Sin catálogo de máquinas no truena la vista (el controller las pide a URDCatalogoMaquinas). */
    public function test_consulta_de_maquinas_no_rompe_con_datos(): void
    {
        DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->insert(['MaquinaId' => 'MC1', 'Nombre' => 'Mc Coy 1', 'Departamento' => 'Urdido']);

        $this->actingAs($this->usuarioCon([self::MODULO => ['acceso']]))->get(self::URL)->assertOk();
    }
}
