<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Piloto DS-12: Actividades, Comentarios y Máquinas comparten una vista
 * (modulos/catalogos-atadores/index.blade.php). Rutas, validaciones y JSON no cambian.
 */
class CatalogosAtadoresTest extends TestCase
{
    use UsesSqlsrvSqlite;

    /** @var array<string, array{0: string, 1: int, 2: string, 3: array<string, mixed>, 4: string, 5: string}> */
    private const CATALOGOS = [
        // clave => [módulo, idrol, llave, alta válida, texto de la tabla, título]
        'actividades' => ['Actividades', 150, 'ActividadId', ['ActividadId' => 'MONTAJE', 'Porcentaje' => 40], 'MONTAJE', 'Catálogo de Actividades'],
        'comentarios' => ['Comentarios', 151, 'Nota1', ['Nota1' => 'Julio cruzado', 'Nota2' => 'Revisar'], 'Julio cruzado', 'Catálogo de Comentarios'],
        'maquinas' => ['Maquinas', 152, 'MaquinaId', ['MaquinaId' => 'KM-01'], 'KM-01', 'Máquinas Atadores'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();

        $schema = Schema::connection('sqlsrv');
        $schema->create('AtaActividades', function (Blueprint $t) {
            $t->increments('Id');
            $t->string('ActividadId');
            $t->float('Porcentaje')->nullable();
        });
        $schema->create('AtaComentarios', function (Blueprint $t) {
            $t->string('Nota1')->primary();
            $t->string('Nota2')->nullable();
        });
        $schema->create('AtaMaquinas', function (Blueprint $t) {
            $t->string('MaquinaId')->primary();
        });

        $this->actingAs($this->createUsuario(), 'web');
        foreach (self::CATALOGOS as [$modulo, $idrol]) {
            $this->grantModulo($modulo, ['acceso', 'crear', 'modificar', 'eliminar'], idRol: $idrol);
        }
    }

    /** @return array<string, array{string}> */
    public static function catalogos(): array
    {
        return array_combine(array_keys(self::CATALOGOS), array_map(fn ($c) => [$c], array_keys(self::CATALOGOS)));
    }

    #[DataProvider('catalogos')]
    public function test_index_pinta_la_vista_unica_con_sus_filas_y_sin_js_inline(string $clave): void
    {
        [, , , $alta, $texto, $titulo] = self::CATALOGOS[$clave];
        $this->postJson("/atadores/catalogos/{$clave}", $alta)->assertOk();

        $html = $this->get("/atadores/catalogos/{$clave}")
            ->assertOk()
            ->assertViewIs('modulos.catalogos-atadores.index')
            ->assertSee($titulo)
            ->assertSee($texto)
            ->getContent();

        $this->assertMatchesRegularExpression('/<dialog id="formModal"/', $html);
        $this->assertMatchesRegularExpression('/<dialog id="deleteModal"/', $html);
        $this->assertStringContainsString('data-catalogo=', $html);
        $this->assertStringContainsString('<template data-catalogo-plantilla>', $html);
        $this->assertStringContainsString('id="btnCreate"', $html);
        $this->assertStringContainsString('id="btnEdit"', $html);
        $this->assertStringContainsString('id="btnDelete"', $html);
        // La fila vacía existe pero está oculta cuando hay registros.
        $this->assertMatchesRegularExpression('/<tr data-catalogo-vacio[^>]* hidden="hidden">/', $html);

        // Sin onclick ni JS inline propio de la vista (el de la app/navbar no cuenta).
        $vista = file_get_contents(resource_path('views/modulos/catalogos-atadores/index.blade.php'));
        $this->assertStringNotContainsString('onclick', $vista);
        $this->assertStringNotContainsString('<script', $vista);
    }

    #[DataProvider('catalogos')]
    public function test_config_llega_al_js_con_endpoint_llave_y_campos(string $clave): void
    {
        [, , $llave] = self::CATALOGOS[$clave];

        $html = $this->get("/atadores/catalogos/{$clave}")->assertOk()->getContent();
        preg_match("/data-catalogo='([^']+)'/", $html, $m);
        $config = json_decode(html_entity_decode($m[1] ?? '{}'), true);

        $this->assertSame("/atadores/catalogos/{$clave}", $config['endpoint']);
        $this->assertSame($llave, $config['llave']);
        $this->assertNotEmpty($config['campos']);
        $this->assertSame($llave, $config['campos'][0]['nombre']);
    }

    public function test_vacio_se_muestra_sin_registros(): void
    {
        $this->get('/atadores/catalogos/maquinas')
            ->assertOk()
            ->assertSee('No hay máquinas registradas')
            ->assertDontSee('hidden="hidden"', false);
    }

    public function test_crud_json_sin_cambios(): void
    {
        $this->postJson('/atadores/catalogos/actividades', ['ActividadId' => 'MONTAJE', 'Porcentaje' => 40])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Actividad creada exitosamente']);

        $this->getJson('/atadores/catalogos/actividades/MONTAJE')
            ->assertOk()->assertJsonPath('data.ActividadId', 'MONTAJE');

        $this->putJson('/atadores/catalogos/actividades/MONTAJE', ['ActividadId' => 'MONTAJE-2', 'Porcentaje' => 50])
            ->assertOk()->assertJson(['success' => true]);

        // Duplicado: el mensaje del servidor es el que muestra el JS.
        $this->postJson('/atadores/catalogos/actividades', ['ActividadId' => 'MONTAJE-2', 'Porcentaje' => 1])
            ->assertStatus(500)->assertJson(['success' => false]);

        $this->deleteJson('/atadores/catalogos/actividades/MONTAJE-2')->assertOk()->assertJson(['success' => true]);
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaActividades')->count());

        // Comentarios: llave de texto libre con espacios y acentos (el JS la codifica).
        $this->postJson('/atadores/catalogos/comentarios', ['Nota1' => 'Falta de peine ñ', 'Nota2' => null])->assertOk();
        $this->getJson('/atadores/catalogos/comentarios/'.rawurlencode('Falta de peine ñ'))
            ->assertOk()->assertJsonPath('data.Nota1', 'Falta de peine ñ');
    }

    public function test_sin_permiso_de_crear_no_hay_boton_ni_se_puede_crear(): void
    {
        DB::connection('sqlsrv')->table('SYSUsuariosRoles')->where('idrol', 152)->update(['crear' => 0]);
        app()->forgetInstance('permisos.usuario.'.auth()->id());

        $this->get('/atadores/catalogos/maquinas')->assertOk()->assertDontSee('id="btnCreate"', false);
        $this->postJson('/atadores/catalogos/maquinas', ['MaquinaId' => 'X'])->assertForbidden();
    }

    public function test_las_tres_vistas_viejas_ya_no_existen(): void
    {
        foreach (array_keys(self::CATALOGOS) as $clave) {
            $this->assertFalse(view()->exists("modulos.catalogos-atadores.{$clave}.index"));
        }
    }
}
