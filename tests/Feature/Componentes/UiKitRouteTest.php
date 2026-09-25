<?php

declare(strict_types=1);

namespace Tests\Feature\Componentes;

use Illuminate\Support\Facades\Route;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/** DS-11: la galería /dev/ui-kit solo existe con APP_ENV=local. */
class UiKitRouteTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSqlsrvSqlite();
        config()->set('database.default', 'sqlsrv');
        $this->createAuthTable();
    }

    public function test_fuera_de_local_la_ruta_no_existe(): void
    {
        $this->assertFalse(app()->isLocal());
        $this->assertFalse(Route::has('dev.ui-kit'));

        $this->actingAs($this->createUsuario(), 'web')->get('/dev/ui-kit')->assertNotFound();
    }

    public function test_en_local_pinta_todos_los_componentes(): void
    {
        app()->detectEnvironment(fn () => 'local');
        Route::middleware(['web', 'auth'])->group(base_path('routes/modules/dev.php'));
        Route::getRoutes()->refreshNameLookups();

        $html = $this->actingAs($this->createUsuario(), 'web')
            ->get('/dev/ui-kit')
            ->assertOk()
            ->getContent();

        foreach (['tokens', 'botones', 'badges', 'campos', 'modal', 'tabla', 'filtros', 'vacios', 'carga', 'avisos', 'pt'] as $seccion) {
            $this->assertStringContainsString('id="'.$seccion.'"', $html, "falta la sección {$seccion}");
        }
        // Los 3 modales reales de Programa Tejido y los de la galería son <dialog>.
        foreach (['modalRepaso', 'modalActCalendarios', 'modalMarbetes', 'kitModal', 'kitModalBorrar'] as $id) {
            $this->assertMatchesRegularExpression('/<dialog id="'.$id.'"/', $html);
        }
        // El código de ejemplo sale escapado, no como componentes renderizados.
        $this->assertStringContainsString('&lt;x-ui.button variant=&quot;create&quot;', $html);
    }

    public function test_el_require_esta_dentro_del_grupo_auth(): void
    {
        $web = file_get_contents(base_path('routes/web.php'));

        $this->assertMatchesRegularExpression("/middleware\\(\\['auth'\\]\\)->group\\(function \\(\\) \\{.*require __DIR__ ?\\. ?'\\/modules\\/dev\\.php';.*\\}\\);/s", $web);
    }
}
