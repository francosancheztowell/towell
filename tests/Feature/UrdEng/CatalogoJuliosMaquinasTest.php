<?php

namespace Tests\Feature\UrdEng;

use App\Models\Urdido\UrdCatJulios;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/** Catálogos de Julios (Urdido/Engomado) y Máquinas de Urdido: CatalogosUrdidoController (19-01 C). */
class CatalogoJuliosMaquinasTest extends TestCase
{
    use ModuloUrdEng;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        $this->tablaDe(UrdCatJulios::class);
        // PK texto: tablaDe() la haría autoincremental.
        Schema::connection('sqlsrv')->create('URDCatalogoMaquinas', function (Blueprint $t): void {
            $t->string('MaquinaId')->primary();
            $t->string('Nombre')->nullable();
            $t->string('Departamento')->nullable();
        });

        $db = DB::connection('sqlsrv');
        $db->table('UrdCatJulios')->insert([
            ['Id' => 1, 'NoJulio' => '11', 'Tara' => 120.5, 'Departamento' => 'Urdido'],
            ['Id' => 2, 'NoJulio' => '12', 'Tara' => 118, 'Departamento' => 'Urdido'],
            ['Id' => 3, 'NoJulio' => '31', 'Tara' => 90, 'Departamento' => 'Engomado'],
        ]);
        $db->table('URDCatalogoMaquinas')->insert([
            ['MaquinaId' => 'MC Coy 1', 'Nombre' => 'MC Coy 1', 'Departamento' => 'Urdido'],
            ['MaquinaId' => 'KM-2', 'Nombre' => 'Karl Mayer', 'Departamento' => 'Urdido'],
        ]);
    }

    private function todos(): array
    {
        $acciones = ['acceso', 'crear', 'modificar', 'eliminar'];

        return [37 => $acciones, 171 => $acciones, 156 => $acciones, 'Catalogo Julios Eng' => $acciones, 'Catalogos Maquinas' => $acciones];
    }

    /** @return array<string, array{string, string, string}> */
    public static function variantesJulios(): array
    {
        return [
            'urdido' => ['/urdido/catalogos-julios', 'Urdido', '31'],
            'engomado' => ['/engomado/configuracion/catalogojulioseng', 'Engomado', '11'],
        ];
    }

    /** @dataProvider variantesJulios */
    public function test_vista_julios_filtra_por_departamento_y_trae_config(string $url, string $dep, string $ajeno): void
    {
        $html = $this->actingAs($this->usuarioCon($this->todos()))->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('id="catalogo-julios"', $html);
        $this->assertStringContainsString('"departamento":"'.$dep.'"', html_entity_decode($html));
        $this->assertStringContainsString('data-accion="crear"', $html);
        $this->assertStringNotContainsString('data-no-julio="'.$ajeno.'"', $html);
    }

    public function test_filtro_no_julio(): void
    {
        $html = $this->actingAs($this->usuarioCon($this->todos()))
            ->get('/urdido/catalogos-julios?no_julio=12')->assertOk()->getContent();

        $this->assertStringContainsString('data-no-julio="12"', $html);
        $this->assertStringNotContainsString('data-no-julio="11"', $html);
    }

    /** @dataProvider variantesJulios */
    public function test_crea_edita_y_elimina_julio(string $url, string $dep): void
    {
        $u = $this->usuarioCon($this->todos());
        $db = DB::connection('sqlsrv');

        $this->actingAs($u)->postJson($url, ['NoJulio' => 'J99', 'Tara' => 5])
            ->assertOk()->assertJsonPath('success', true);
        $id = $db->table('UrdCatJulios')->where('NoJulio', 'J99')->value('Id');
        $this->assertSame($dep, $db->table('UrdCatJulios')->where('Id', $id)->value('Departamento'));

        $this->actingAs($u)->putJson($url.'/'.$id, ['NoJulio' => 'J98', 'Tara' => 7.5])
            ->assertOk()->assertJsonPath('success', true);
        $this->assertSame(7.5, (float) $db->table('UrdCatJulios')->where('Id', $id)->value('Tara'));

        $this->actingAs($u)->deleteJson($url.'/'.$id)->assertOk()->assertJsonPath('success', true);
        $this->assertFalse($db->table('UrdCatJulios')->where('Id', $id)->exists());
    }

    public function test_julio_duplicado_es_422_con_message_texto_y_errors(): void
    {
        $this->actingAs($this->usuarioCon($this->todos()))
            ->postJson('/urdido/catalogos-julios', ['NoJulio' => '11'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['NoJulio']])
            ->assertJson(fn ($j) => $j->whereType('message', 'string')->etc());
    }

    public function test_julio_inexistente_es_404(): void
    {
        $u = $this->usuarioCon($this->todos());
        $this->actingAs($u)->putJson('/urdido/catalogos-julios/999', ['NoJulio' => 'X'])->assertNotFound();
        $this->actingAs($u)->deleteJson('/urdido/catalogos-julios/999')->assertNotFound();
    }

    public function test_julio_sin_permiso_crear_es_403(): void
    {
        $this->actingAs($this->usuarioCon([37 => ['acceso']]))
            ->postJson('/urdido/catalogos-julios', ['NoJulio' => 'J1'])
            ->assertForbidden();
    }

    /** SEC-07: un fallo de BD no llega al usuario con el texto de la excepción. */
    public function test_errores_internos_no_exponen_la_excepcion(): void
    {
        $u = $this->usuarioCon($this->todos());
        Schema::connection('sqlsrv')->drop('UrdCatJulios');
        Schema::connection('sqlsrv')->drop('URDCatalogoMaquinas');

        foreach ([
            $this->actingAs($u)->deleteJson('/urdido/catalogos-julios/1'),
            $this->actingAs($u)->deleteJson('/urdido/catalogo-maquinas/KM-2'),
        ] as $r) {
            $r->assertStatus(500)->assertJsonStructure(['message', 'trace_id']);
            $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        }

        foreach (['/urdido/catalogos-julios', '/urdido/catalogo-maquinas'] as $url) {
            $html = $this->actingAs($u)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('Error al cargar los datos (ref:', $html);
            $this->assertStringNotContainsString('SQLSTATE', $html);
        }
    }

    public function test_maquinas_crud_y_filtro(): void
    {
        $u = $this->usuarioCon($this->todos());
        $db = DB::connection('sqlsrv');

        $html = $this->actingAs($u)->get('/urdido/catalogo-maquinas?nombre=Karl')->assertOk()->getContent();
        $this->assertStringContainsString('data-maquina-id="KM-2"', $html);
        $this->assertStringNotContainsString('data-maquina-id="MC Coy 1"', $html);
        $this->assertStringContainsString('id="catalogo-maquinas"', $html);

        $this->actingAs($u)->postJson('/urdido/catalogo-maquinas', ['MaquinaId' => 'N-1', 'Nombre' => 'Nueva'])
            ->assertOk()->assertJsonPath('success', true);
        $this->actingAs($u)->postJson('/urdido/catalogo-maquinas', ['MaquinaId' => 'N-1'])
            ->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['MaquinaId']]);

        $this->actingAs($u)->putJson('/urdido/catalogo-maquinas/N-1', ['MaquinaId' => 'N-1', 'Nombre' => 'Otra', 'Departamento' => 'Urdido'])
            ->assertOk();
        $this->assertSame('Otra', $db->table('URDCatalogoMaquinas')->where('MaquinaId', 'N-1')->value('Nombre'));
        $this->actingAs($u)->putJson('/urdido/catalogo-maquinas/NO-EXISTE', ['MaquinaId' => 'NO-EXISTE'])->assertNotFound();

        $this->actingAs($u)->deleteJson('/urdido/catalogo-maquinas/N-1')->assertOk();
        $this->assertFalse($db->table('URDCatalogoMaquinas')->where('MaquinaId', 'N-1')->exists());
    }

    /** data-pagina='@json($config)' sobrevive a datos con ' y < (en filas y en la URL del listado). */
    public function test_datos_con_apostrofo_y_menor_que_no_rompen_los_atributos(): void
    {
        DB::connection('sqlsrv')->table('UrdCatJulios')->insert(['Id' => 9, 'NoJulio' => "J'<9", 'Tara' => 1, 'Departamento' => 'Urdido']);
        DB::connection('sqlsrv')->table('URDCatalogoMaquinas')->insert(['MaquinaId' => "M'<1", 'Nombre' => "O'Neil <b>", 'Departamento' => 'Urdido']);
        $u = $this->usuarioCon($this->todos());

        foreach (['/urdido/catalogos-julios' => 'data-no-julio', '/urdido/catalogo-maquinas' => 'data-nombre'] as $url => $attr) {
            $html = $this->actingAs($u)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression("/data-pagina='([^']*)'/", $html);
            preg_match("/data-pagina='([^']*)'/", $html, $m);
            $config = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
            $this->assertIsArray($config, $url);
            $this->assertStringContainsString('__ID__', $config['rutas']['actualizar']);
            $this->assertStringNotContainsString("J'<9", $html);
            $this->assertStringNotContainsString("O'Neil <b>", $html);
        }
        $this->assertStringContainsString('data-no-julio="J&#039;&lt;9"', $this->actingAs($u)->get('/urdido/catalogos-julios')->getContent());
    }

    /** Guardián 19-01: las vistas no traen JS inline (se revisa el fuente Blade; x-ui.modal-base pone su propio onclick). */
    public function test_vistas_sin_js_inline(): void
    {
        foreach (['catalago-julios', 'catalago-maquinas'] as $vista) {
            $fuente = (string) file_get_contents(resource_path("views/catalogosurdido/{$vista}.blade.php"));
            $this->assertDoesNotMatchRegularExpression('/\bon(click|change|submit)\s*=/i', $fuente, $vista);
            $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)/i', $fuente, $vista);
            $this->assertStringNotContainsString('Swal.fire', $fuente, $vista);
            $this->assertStringContainsString('@vite(', $fuente, $vista);
        }
    }
}
