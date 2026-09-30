<?php

declare(strict_types=1);

namespace Tests\Feature\Atadores;

use App\Models\Atadores\AtaComentariosModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * HANDOFF 16 C3 (19-03): Comentarios usaba Nota1 (texto libre) como llave de ruta; una nota con "/"
 * daba 404 al editar o eliminar. Con la columna Id la pantalla va por /comentarios/id/{Id};
 * sin ella todo sigue como antes. Las URLs por Nota1 siguen funcionando en los dos casos.
 */
class CatalogoComentariosLlaveTest extends TestCase
{
    use UsesSqlsrvSqlite;

    private function preparar(bool $conId): void
    {
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
        Cache::forget(AtaComentariosModel::CACHE_TIENE_ID);

        Schema::connection('sqlsrv')->create('AtaComentarios', function (Blueprint $t) use ($conId) {
            if ($conId) {
                $t->increments('Id');
                $t->string('Nota1')->unique();
            } else {
                $t->string('Nota1')->primary();
            }
            $t->string('Nota2')->nullable();
        });

        $this->actingAs($this->createUsuario(), 'web');
        $this->grantModulo('Comentarios', ['acceso', 'crear', 'modificar', 'eliminar'], idRol: 151);
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        $html = $this->get('/atadores/catalogos/comentarios')->assertOk()->getContent();
        preg_match("/data-catalogo='([^']+)'/", (string) $html, $m);

        return json_decode(html_entity_decode($m[1] ?? '{}'), true);
    }

    public function test_sin_columna_id_la_pantalla_sigue_con_nota1(): void
    {
        $this->preparar(conId: false);

        $config = $this->config();
        $this->assertSame('Nota1', $config['llave']);
        $this->assertSame('/atadores/catalogos/comentarios', $config['endpoint']);

        // Las rutas por Id no inventan nada si la columna no existe.
        $this->postJson('/atadores/catalogos/comentarios', ['Nota1' => 'Nota simple'])->assertOk();
        $this->getJson('/atadores/catalogos/comentarios/id/1')->assertNotFound();
        $this->getJson('/atadores/catalogos/comentarios/'.rawurlencode('Nota simple'))->assertOk();
    }

    public function test_con_columna_id_una_nota_con_diagonal_se_edita_y_elimina(): void
    {
        $this->preparar(conId: true);

        $config = $this->config();
        $this->assertSame('Id', $config['llave']);
        $this->assertSame('/atadores/catalogos/comentarios/id', $config['endpoint']);

        // Alta en {endpoint} (lo que hace catalog-base.ts) y la respuesta trae el Id para la fila nueva.
        $id = $this->postJson('/atadores/catalogos/comentarios/id', ['Nota1' => 'Julio 1/2 cruzado', 'Nota2' => 'A/B'])
            ->assertOk()
            ->assertJsonPath('data.Nota1', 'Julio 1/2 cruzado')
            ->json('data.Id');
        $this->assertIsInt($id);

        $this->get('/atadores/catalogos/comentarios')->assertSee('data-id="'.$id.'"', false);

        $this->getJson("/atadores/catalogos/comentarios/id/{$id}")->assertOk()->assertJsonPath('data.Nota2', 'A/B');
        $this->putJson("/atadores/catalogos/comentarios/id/{$id}", ['Nota1' => 'Julio 1/2 cruzado, revisar', 'Nota2' => null])
            ->assertOk()
            ->assertJsonPath('data.Id', $id)
            ->assertJsonPath('data.Nota1', 'Julio 1/2 cruzado, revisar');

        $this->deleteJson("/atadores/catalogos/comentarios/id/{$id}")->assertOk()->assertJson(['success' => true]);
        $this->assertSame(0, DB::connection('sqlsrv')->table('AtaComentarios')->count());
    }

    public function test_con_columna_id_las_urls_por_nota1_siguen_respondiendo(): void
    {
        $this->preparar(conId: true);

        $this->postJson('/atadores/catalogos/comentarios', ['Nota1' => 'Peine sucio'])->assertOk();
        $this->getJson('/atadores/catalogos/comentarios/'.rawurlencode('Peine sucio'))->assertOk();
        $this->putJson('/atadores/catalogos/comentarios/'.rawurlencode('Peine sucio'), ['Nota1' => 'Peine limpio'])->assertOk();
        $this->deleteJson('/atadores/catalogos/comentarios/'.rawurlencode('Peine limpio'))->assertOk();
    }

    public function test_nota_duplicada_es_422_y_una_coma_ya_no_rompe_la_regla(): void
    {
        $this->preparar(conId: false);

        $this->postJson('/atadores/catalogos/comentarios', ['Nota1' => 'Uno, dos'])->assertOk();
        $this->postJson('/atadores/catalogos/comentarios', ['Nota1' => 'Uno, dos'])
            ->assertStatus(422)->assertJsonValidationErrors('Nota1');

        // Antes 'unique:AtaComentarios,Nota1,'.$nota1.',Nota1' partía la nota en la coma.
        $this->putJson('/atadores/catalogos/comentarios/'.rawurlencode('Uno, dos'), ['Nota1' => 'Uno, dos', 'Nota2' => 'x'])
            ->assertOk();
    }

    public function test_un_fallo_de_base_no_manda_el_error_de_sql_al_usuario(): void
    {
        $this->preparar(conId: false);
        $this->postJson('/atadores/catalogos/comentarios', ['Nota1' => 'Existe'])->assertOk();
        Schema::connection('sqlsrv')->table('AtaComentarios', function (Blueprint $t) {
            $t->dropColumn('Nota2');
        });

        $respuesta = $this->putJson('/atadores/catalogos/comentarios/Existe', ['Nota1' => 'Existe', 'Nota2' => 'x'])
            ->assertStatus(500)
            ->assertJson(['success' => false]);

        $this->assertNotEmpty($respuesta->json('trace_id'));
        $this->assertStringStartsWith('No se pudo actualizar el comentario', (string) $respuesta->json('message'));
        $this->assertStringNotContainsString('SQLSTATE', (string) $respuesta->getContent());
        $this->assertStringNotContainsString('Nota2', (string) $respuesta->json('message'));
    }

    public function test_no_encontrado_es_404(): void
    {
        $this->preparar(conId: true);

        $this->putJson('/atadores/catalogos/comentarios/id/99', ['Nota1' => 'x'])->assertNotFound();
        $this->deleteJson('/atadores/catalogos/comentarios/No%20existe')->assertNotFound();
    }
}
