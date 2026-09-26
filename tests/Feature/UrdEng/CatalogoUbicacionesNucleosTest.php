<?php

namespace Tests\Feature\UrdEng;

use App\Http\Controllers\UrdEngomado\UrdEngNucleosController;
use App\Models\Engomado\CatUbicaciones;
use App\Models\UrdEngomado\UrdEngNucleos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/** Catálogos de Ubicaciones (Engomado) y Núcleos (Urd/Eng): CatUbicacionesController y UrdEngNucleosController (19-01 C). */
class CatalogoUbicacionesNucleosTest extends TestCase
{
    use ModuloUrdEng;

    private const UBICACIONES = '/engomado/configuracion/catalogo-ubicaciones';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        $this->tablaDe(CatUbicaciones::class);
        $this->tablaDe(UrdEngNucleos::class);

        $db = DB::connection('sqlsrv');
        $db->table('CatUbicaciones')->insert([['Id' => 1, 'Codigo' => 'A1'], ['Id' => 2, 'Codigo' => 'B1']]);
        $db->table('UrdEngNucleos')->insert([
            ['Id' => 1, 'Salon' => 'JACQUARD', 'Nombre' => 'Jacquard'],
            ['Id' => 2, 'Salon' => 'SMIT', 'Nombre' => 'Smit'],
        ]);
    }

    private function todos(): array
    {
        $acciones = ['acceso', 'crear', 'modificar', 'eliminar'];

        return [178 => $acciones, 174 => $acciones, 'Catalogo Ubicaciones' => $acciones, 'Catálogo de Núcleos' => $acciones];
    }

    public function test_ubicaciones_vista_filtro_y_config(): void
    {
        $u = $this->usuarioCon($this->todos());
        $html = $this->actingAs($u)->get(self::UBICACIONES)->assertOk()->getContent();
        $this->assertStringContainsString('id="catalogo-ubicaciones"', $html);
        $this->assertStringContainsString('data-codigo="A1"', $html);
        $this->assertStringContainsString('ubicacionModal', $html);

        $html = $this->actingAs($u)->get(self::UBICACIONES.'?codigo=B')->assertOk()->getContent();
        $this->assertStringContainsString('data-codigo="B1"', $html);
        $this->assertStringNotContainsString('data-codigo="A1"', $html);
    }

    public function test_ubicaciones_crud(): void
    {
        $u = $this->usuarioCon($this->todos());
        $db = DB::connection('sqlsrv');

        $this->actingAs($u)->postJson(self::UBICACIONES, ['Codigo' => 'c1'])->assertOk()->assertJsonPath('success', true);
        $id = $db->table('CatUbicaciones')->where('Codigo', 'C1')->value('Id');
        $this->assertNotNull($id);

        $this->actingAs($u)->putJson(self::UBICACIONES.'/'.$id, ['Codigo' => 'D1'])->assertOk();
        $this->assertSame('D1', $db->table('CatUbicaciones')->where('Id', $id)->value('Codigo'));

        $this->actingAs($u)->deleteJson(self::UBICACIONES.'/'.$id)->assertOk();
        $this->assertFalse($db->table('CatUbicaciones')->where('Id', $id)->exists());
    }

    public function test_ubicaciones_422_404_y_403(): void
    {
        $u = $this->usuarioCon($this->todos());
        $this->actingAs($u)->postJson(self::UBICACIONES, ['Codigo' => 'A1'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['Codigo']]);
        $this->actingAs($u)->putJson(self::UBICACIONES.'/999', ['Codigo' => 'Z9'])->assertNotFound();
        $this->actingAs($u)->deleteJson(self::UBICACIONES.'/999')->assertNotFound();
    }

    public function test_ubicaciones_sin_permiso_eliminar_es_403(): void
    {
        $this->actingAs($this->usuarioCon([178 => ['acceso']]))
            ->deleteJson(self::UBICACIONES.'/1')->assertForbidden();
    }

    /** SEC-07: la excepción no llega al usuario (JSON ni vista). */
    public function test_ubicaciones_error_interno_no_expone_la_excepcion(): void
    {
        $u = $this->usuarioCon($this->todos());
        Schema::connection('sqlsrv')->drop('CatUbicaciones');

        $r = $this->actingAs($u)->postJson(self::UBICACIONES, ['Codigo' => 'Z1'])->assertStatus(500)
            ->assertJsonStructure(['message', 'trace_id']);
        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());

        $html = $this->actingAs($u)->get(self::UBICACIONES)->assertOk()->getContent();
        $this->assertStringContainsString('Error al cargar los datos (ref:', $html);
        $this->assertStringNotContainsString('SQLSTATE', $html);
    }

    public function test_nucleos_vista_y_busqueda(): void
    {
        $u = $this->usuarioCon($this->todos());
        $html = $this->actingAs($u)->get('/engomado/configuracion/catalogos-nucleos')->assertOk()->getContent();
        $this->assertStringContainsString('id="catalogo-nucleos"', $html);
        $this->assertStringContainsString('id="nucleoForm"', $html);
        $this->assertStringContainsString('data-salon="SMIT"', $html);

        $html = $this->actingAs($u)->get('/engomado/configuracion/catalogos-nucleos?q=SMIT')->assertOk()->getContent();
        $this->assertStringContainsString('data-salon="SMIT"', $html);
        $this->assertStringNotContainsString('data-salon="JACQUARD"', $html);
    }

    public function test_nucleos_crud_por_formulario(): void
    {
        $u = $this->usuarioCon($this->todos());
        $db = DB::connection('sqlsrv');
        $desde = '/engomado/configuracion/catalogos-nucleos';

        $this->actingAs($u)->from($desde)->post('/urd-eng-nucleos', ['Salon' => 'KARL MAYER', 'Nombre' => 'KM'])
            ->assertRedirect($desde)->assertSessionHas('success');
        $id = $db->table('UrdEngNucleos')->where('Nombre', 'KM')->value('Id');

        $this->actingAs($u)->from($desde)->post('/urd-eng-nucleos', ['Salon' => 'KARL MAYER', 'Nombre' => 'KM'])
            ->assertSessionHas('error', 'Ya existe un núcleo con este salón y nombre.');

        $this->actingAs($u)->from($desde)->put('/urd-eng-nucleos/'.$id, ['Salon' => 'SMIT', 'Nombre' => 'KM2'])
            ->assertRedirect($desde)->assertSessionHas('success');
        $this->assertSame('KM2', $db->table('UrdEngNucleos')->where('Id', $id)->value('Nombre'));

        $this->actingAs($u)->from($desde)->delete('/urd-eng-nucleos/'.$id)->assertSessionHas('success');
        $this->assertFalse($db->table('UrdEngNucleos')->where('Id', $id)->exists());
    }

    /** SEC-07: el redirect lleva un mensaje genérico con referencia, no el texto de la excepción. */
    public function test_nucleos_error_interno_no_expone_la_excepcion(): void
    {
        $u = $this->usuarioCon($this->todos());
        UrdEngNucleos::deleting(fn () => throw new \RuntimeException('SQLSTATE[42S02] tabla secreta'));

        $r = $this->actingAs($u)->from('/engomado/configuracion/catalogos-nucleos')->delete('/urd-eng-nucleos/1');
        $mensaje = (string) session('error');
        $r->assertRedirect();
        $this->assertStringStartsWith('No se pudo eliminar el núcleo. (ref: ', $mensaje);
        $this->assertStringNotContainsString('SQLSTATE', $mensaje);

        // API de la lista (la ruta vive tras el permiso de Programa Urd/Eng: se llama al método directo).
        Schema::connection('sqlsrv')->drop('UrdEngNucleos');
        $j = app(UrdEngNucleosController::class)->getNucleos();
        $this->assertSame(500, $j->getStatusCode());
        $this->assertFalse($j->getData(true)['success']);
        $this->assertStringNotContainsString('SQLSTATE', (string) $j->getContent());
    }

    /** data-pagina='@json($config)' y data-* de filas sobreviven a datos con ' y <. */
    public function test_datos_con_apostrofo_y_menor_que_no_rompen_los_atributos(): void
    {
        DB::connection('sqlsrv')->table('CatUbicaciones')->insert(['Id' => 9, 'Codigo' => "Z'<"]);
        DB::connection('sqlsrv')->table('UrdEngNucleos')->insert(['Id' => 9, 'Salon' => 'SMIT', 'Nombre' => "O'Neil <b>"]);
        $u = $this->usuarioCon($this->todos());

        foreach ([self::UBICACIONES, '/engomado/configuracion/catalogos-nucleos'] as $url) {
            $html = $this->actingAs($u)->get($url)->assertOk()->getContent();
            preg_match("/data-pagina='([^']*)'/", $html, $m);
            $config = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true);
            $this->assertIsArray($config, $url);
            $this->assertStringContainsString('__ID__', $config['rutas']['eliminar']);
            $this->assertStringNotContainsString("Z'<", $html);
            $this->assertStringNotContainsString("O'Neil <b>", $html);
        }
    }

    /** Guardián 19-01: sin JS inline en el fuente Blade (x-ui.modal-base pone su propio onclick). */
    public function test_vistas_sin_js_inline(): void
    {
        foreach ([
            'modulos/engomado/configuracion/catalogo-ubicaciones',
            'modulos/engomado/urd-eng-nucleos/index',
        ] as $vista) {
            $fuente = (string) file_get_contents(resource_path("views/{$vista}.blade.php"));
            $this->assertDoesNotMatchRegularExpression('/\bon(click|change|submit)\s*=/i', $fuente, $vista);
            $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc\s*=)/i', $fuente, $vista);
            $this->assertStringNotContainsString('Swal.fire', $fuente, $vista);
            $this->assertStringNotContainsString('fetch(', $fuente, $vista);
            $this->assertStringContainsString('@vite(', $fuente, $vista);
        }
    }
}
