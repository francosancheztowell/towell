<?php

namespace Tests\Feature\UrdEng;

use App\Models\Urdido\UrdCatJulios;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/** SEC-07: los endpoints de ProduccionTrait (Urdido y Engomado) no devuelven el texto de la excepción. */
class ProduccionTraitErroresTest extends TestCase
{
    use ModuloUrdEng;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararSqlite();
    }

    /** @return array<string, array{string, string}> */
    public static function variantes(): array
    {
        return [
            'urdido' => ['/urdido/modulo-produccion-urdido/catalogos-julios', 'Producción Urdido'],
            'engomado' => ['/engomado/modulo-produccion-engomado/catalogos-julios', 'Producción Engomado'],
        ];
    }

    /** @dataProvider variantes */
    public function test_catalogo_de_julios_con_error_de_bd_no_expone_la_excepcion(string $url, string $modulo): void
    {
        // Sin la tabla UrdCatJulios la consulta falla con un QueryException que menciona SQL.
        $r = $this->actingAs($this->usuarioCon([$modulo => ['acceso', 'modificar']]))
            ->getJson($url)
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'trace_id']);

        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        $this->assertStringNotContainsString((new UrdCatJulios)->getTable(), $r->getContent());
    }
}
