<?php

namespace Tests\Feature\UrdEng;

use App\Models\Engomado\CatDefectosUrdEng;
use App\Models\Engomado\EngProduccionEngomado;
use App\Models\Urdido\UrdProduccionUrdido;
use Illuminate\Support\Facades\DB;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/** Calificar julios: las dos variantes (urdido / engomado) comparten contrato y controller. */
class CalificarJuliosTest extends TestCase
{
    use ModuloUrdEng;

    private const BASE = '/engomado/modulo-produccion-engomado/calificar-julios';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
        $this->tablaDe(UrdProduccionUrdido::class, ['ClaveDefecto', 'Penalizacion', 'OperadorDefecto', 'NoEmplDefecto', 'FechaDefecto']);
        $this->tablaDe(EngProduccionEngomado::class, ['ClaveDefecto', 'Penalizacion', 'OperadorDefecto', 'NoEmplDefecto', 'FechaDefecto']);
        $this->tablaDe(CatDefectosUrdEng::class);

        $db = DB::connection('sqlsrv');
        $db->table('CatDefectosUrdEng')->insert(['Id' => 7, 'Clave' => 'RHC', 'Defecto' => 'Rotura', 'Penalizacion' => 3, 'Activo' => 1]);
        $db->table('UrdProduccionUrdido')->insert([
            ['Id' => 1, 'Folio' => 'F-1', 'NoJulio' => '10', 'Metros1' => 100, 'NomEmpl1' => 'Ana'],
            ['Id' => 2, 'Folio' => 'F-1', 'NoJulio' => '9', 'Metros1' => 50, 'NomEmpl1' => 'Beto'],
        ]);
        $db->table('EngProduccionEngomado')->insert(['Id' => 5, 'Folio' => 'F-1', 'NoJulio' => '21']);
    }

    /** @return array<string, array{string, string, int}> */
    public static function variantes(): array
    {
        return [
            'urdido' => ['', 'UrdProduccionUrdido', 1],
            'engomado' => ['-eng', 'EngProduccionEngomado', 5],
        ];
    }

    /** @dataProvider variantes */
    public function test_lista_julios_y_defectos(string $sufijo): void
    {
        $this->actingAs($this->usuarioCon(['Producción Engomado' => ['acceso', 'modificar']]))
            ->getJson(self::BASE.$sufijo.'?folio=F-1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('defectos.0.Clave', 'RHC');
    }

    public function test_urdido_ordena_por_numero_de_julio(): void
    {
        $r = $this->actingAs($this->usuarioCon(['Producción Engomado' => ['acceso']]))
            ->getJson(self::BASE.'?folio=F-1')->assertOk();

        $this->assertSame(['9', '10'], array_column($r->json('julios'), 'NoJulio'));
    }

    /** @dataProvider variantes */
    public function test_sin_folio_es_422_y_no_500(string $sufijo): void
    {
        $this->actingAs($this->usuarioCon(['Producción Engomado' => ['acceso']]))
            ->getJson(self::BASE.$sufijo)
            ->assertStatus(422);
    }

    /** @dataProvider variantes */
    public function test_califica_y_limpia(string $sufijo, string $tabla, int $id): void
    {
        $usuario = $this->usuarioCon(['Producción Engomado' => ['acceso', 'modificar']]);

        $this->actingAs($usuario)
            ->postJson(self::BASE.$sufijo.'/calificar', ['julio_id' => $id, 'defecto_id' => 7])
            ->assertOk()
            ->assertJsonPath('data.ClaveDefecto', 7);
        $fila = DB::connection('sqlsrv')->table($tabla)->where('Id', $id)->first();
        $this->assertSame(3.0, (float) $fila->Penalizacion);
        $this->assertSame('Usuario prueba', $fila->OperadorDefecto);

        $this->actingAs($usuario)
            ->postJson(self::BASE.$sufijo.'/calificar', ['julio_id' => $id, 'defecto_id' => null])
            ->assertOk();
        $this->assertNull(DB::connection('sqlsrv')->table($tabla)->where('Id', $id)->value('ClaveDefecto'));
    }

    /** @dataProvider variantes */
    public function test_no_encontrado_es_404_con_mensaje_propio(string $sufijo): void
    {
        $this->actingAs($this->usuarioCon(['Producción Engomado' => ['acceso', 'modificar']]))
            ->postJson(self::BASE.$sufijo.'/calificar', ['julio_id' => 999, 'defecto_id' => 7])
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'trace_id']);
    }

    /** @dataProvider variantes */
    public function test_sin_modificar_es_403(string $sufijo, string $tabla, int $id): void
    {
        $this->actingAs($this->usuarioCon(['Producción Engomado' => ['acceso']]))
            ->postJson(self::BASE.$sufijo.'/calificar', ['julio_id' => $id, 'defecto_id' => 7])
            ->assertForbidden();
    }

    /** SEC-07: un fallo de BD no llega al usuario con el texto de la excepción. */
    public function test_error_interno_no_expone_la_excepcion(): void
    {
        DB::connection('sqlsrv')->getSchemaBuilder()->drop('CatDefectosUrdEng');

        $r = $this->actingAs($this->usuarioCon(['Producción Engomado' => ['acceso']]))
            ->getJson(self::BASE.'-eng?folio=F-1')
            ->assertStatus(500)
            ->assertJsonStructure(['message', 'trace_id']);

        $this->assertStringNotContainsString('CatDefectosUrdEng', $r->getContent());
        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
    }

    public function test_la_vista_parametrizada_no_trae_js_inline(): void
    {
        $this->actingAs($this->usuarioCon([]));
        foreach (['urdido' => 'F-1', 'engomado' => null] as $variante => $folio) {
            $html = view('modulos.urdido.comun.calificar-julios', ['variante' => $variante, 'folio' => $folio])->render();
            $this->assertStringNotContainsString('onclick', $html);
            $this->assertStringContainsString('data-calificar-julios=', $html);
            $this->assertStringContainsString($variante === 'urdido' ? 'calificar-julios"' : 'calificar-julios-eng"', html_entity_decode($html));
        }
    }
}
