<?php

namespace Tests\Feature\UrdEng;

use App\Models\Engomado\EngActividadesBpmModel;
use App\Models\Urdido\UrdActividadesBpmModel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\UrdEng\Concerns\ModuloUrdEng;
use Tests\TestCase;

/** Catálogo Actividades BPM (Urdido/Engomado): vista parametrizada y CRUD por formulario. */
class BpmActividadesTest extends TestCase
{
    use ModuloUrdEng;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->prepararSqlite();
        $this->tablaDe(UrdActividadesBpmModel::class);
        $this->tablaDe(EngActividadesBpmModel::class);

        $db = DB::connection('sqlsrv');
        $db->table('UrdActividadesBPM')->insert([
            ['Id' => 1, 'Orden' => 1, 'Actividad' => 'Limpieza & fileta', 'Maquina' => 'MC'],
            ['Id' => 2, 'Orden' => 2, 'Actividad' => 'Tensores', 'Maquina' => 'KM'],
        ]);
        $db->table('EngActividadesBPM')->insert(['Id' => 1, 'Orden' => 1, 'Actividad' => 'Limpieza & tina']);
    }

    /** @return array<string, array{string, string, int, string}> */
    public static function variantes(): array
    {
        return [
            'urdido' => ['/urdido/configuracion/actividades-bpm', '/urd-actividades-bpm', 144, 'UrdActividadesBPM'],
            'engomado' => ['/engomado/configuracion/actividades-bpm', '/eng-actividades-bpm', 164, 'EngActividadesBPM'],
        ];
    }

    #[DataProvider('variantes')]
    public function test_indice_renderiza_sin_js_inline(string $url, string $base, int $modulo): void
    {
        // Los botones del navbar comprueban el permiso por nombre de módulo.
        $nombre = $base === '/urd-actividades-bpm' ? 'Actividades BPM Urdido' : 'Actividades BPM Engomado';
        $html = $this->actingAs($this->usuarioCon([$nombre => ['acceso', 'crear', 'modificar', 'eliminar']]))
            ->get($url)->assertOk()
            ->assertSee('data-actividades-bpm=', false)
            ->assertSee('data-actividades-accion="eliminar"', false)
            ->getContent();

        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('Swal.fire', $html);
        // data-actividad con un solo escape (antes e() dentro de {{ }}: el modal Editar mostraba "&amp;").
        $this->assertStringContainsString('data-actividad="Limpieza &amp; ', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);

        $conMaquina = $base === '/urd-actividades-bpm';
        $this->assertSame($conMaquina, str_contains($html, 'id="editMaquina"'));
        $this->assertSame($conMaquina, str_contains($html, 'Karl Mayer 1</span>'));
        $this->assertStringContainsString(url($base).'/__ID__', str_replace('\\/', '/', html_entity_decode($html)));
    }

    #[DataProvider('variantes')]
    public function test_crear_editar_eliminar(string $url, string $base, int $modulo, string $tabla): void
    {
        $usuario = $this->usuarioCon([$modulo => ['acceso', 'crear', 'modificar', 'eliminar']]);
        $datos = ['Orden' => 5, 'Actividad' => 'Nueva'] + ($base === '/urd-actividades-bpm' ? ['Maquina' => 'KM'] : []);
        $db = DB::connection('sqlsrv');

        $this->actingAs($usuario)->from($url)->post($base, $datos)->assertRedirect()->assertSessionHas('success');
        $id = (int) $db->table($tabla)->where('Actividad', 'Nueva')->value('Id');
        $this->assertGreaterThan(0, $id);

        $this->actingAs($usuario)->from($url)->put("{$base}/{$id}", ['Actividad' => 'Editada'] + $datos)
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Editada', $db->table($tabla)->where('Id', $id)->value('Actividad'));

        $this->actingAs($usuario)->from($url)->delete("{$base}/{$id}")->assertRedirect()->assertSessionHas('success');
        $this->assertSame(0, $db->table($tabla)->where('Id', $id)->count());
    }

    public function test_las_vistas_no_traen_js_inline(): void
    {
        foreach (['urdido/comun/actividades-bpm', 'urdido/urd-actividades-bpm/index', 'engomado/eng-actividades-bpm/index'] as $v) {
            $fuente = file_get_contents(resource_path("views/modulos/{$v}.blade.php"));
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $fuente, $v);
            $this->assertDoesNotMatchRegularExpression('/<script\b(?![^>]*\bsrc=)/i', $fuente, $v);
        }
    }
}
