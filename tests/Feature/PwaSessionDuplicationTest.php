<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sistema\Usuario;
use App\Services\ModuloService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesSqlsrvSqlite;
use Tests\TestCase;

/**
 * Reproduce el escenario de consola (SW intercepta GET /produccionProceso,
 * app-pwa pide manifest, SW precachea /offline) y cuenta filas en `sessions`.
 *
 * En PHP-FPM cada petición es un proceso nuevo. Aquí hay que olvidar Auth y la
 * sesión entre llamadas; si no, PHPUnit reutiliza el usuario y miente.
 */
class PwaSessionDuplicationTest extends TestCase
{
    use UsesSqlsrvSqlite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useSqlsrvSqlite();
        config()->set('auth.providers.usuarios.model', Usuario::class);
        config()->set('database.default', 'sqlsrv');

        DB::connection('sqlsrv')->statement("ATTACH DATABASE ':memory:' AS dbo");
        Schema::connection('sqlsrv')->create('dbo.SYSUsuario', function (Blueprint $table) {
            $table->increments('idusuario');
            $table->string('nombre', 150);
            $table->string('contrasenia', 255);
            $table->string('numero_empleado', 30)->nullable();
            $table->string('area', 50)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
        });

        config()->set('database.connections.sessions_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('session.driver', 'database');
        config()->set('session.connection', 'sessions_test');
        config()->set('session.table', 'sessions');
        config()->set('session.lottery', [0, 100]);

        DB::purge('sessions_test');
        DB::connection('sessions_test')->getPdo();
        Schema::connection('sessions_test')->create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');

        $this->mock(ModuloService::class, function ($mock) {
            $mock->shouldReceive('getModulosPrincipalesPorUsuario')
                ->zeroOrMoreTimes()
                ->andReturn(new Collection);
        });
    }

    public function test_repetir_produccion_proceso_y_offline_con_la_misma_cookie_no_duplica_sesion(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario)->get('/produccionProceso')->assertOk();
        $this->get('/produccionProceso')->assertOk();
        $this->get('/offline')->assertOk();

        $this->assertSame(
            1,
            $this->filasSesionAutenticadas((int) $usuario->idusuario),
            'El SW reenvía el Request original: misma cookie, una sola fila en sessions.'
        );
        $this->assertSame(1, $this->filasSesionTotales());
    }

    public function test_dos_logins_con_cookies_distintas_si_dejan_dos_filas_del_mismo_usuario(): void
    {
        $usuario = $this->crearUsuario();
        $credenciales = [
            'numero_empleado' => '8801',
            'contrasenia' => 'secret',
        ];

        $this->post('/login', $credenciales)->assertRedirect('/produccionProceso');
        $this->assertSame(1, $this->filasSesionAutenticadas((int) $usuario->idusuario));

        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->flushSession();
        $this->simularNuevoProcesoHttp();

        $this->post('/login', $credenciales)->assertRedirect('/produccionProceso');

        $this->assertSame(
            2,
            $this->filasSesionAutenticadas((int) $usuario->idusuario),
            'Dos jar de cookies (p. ej. Chrome y otro perfil) son dos filas. La PWA con la misma cookie no hace esto.'
        );
    }

    public function test_login_con_remember_y_misma_cookie_sigue_siendo_una_sesion(): void
    {
        $usuario = $this->crearUsuario();

        $this->post('/login', [
            'numero_empleado' => '8801',
            'contrasenia' => 'secret',
        ])->assertRedirect('/produccionProceso');

        $this->get('/produccionProceso')->assertOk();
        $this->get('/offline')->assertOk();

        $this->assertSame(1, $this->filasSesionAutenticadas((int) $usuario->idusuario));
        $this->assertSame(1, $this->filasSesionTotales());
    }

    public function test_manifest_json_no_es_ruta_web_de_laravel(): void
    {
        $this->assertFileExists(public_path('manifest.json'));

        $cubreManifest = collect(Route::getRoutes())->contains(
            fn ($route) => str_contains($route->uri(), 'manifest')
        );

        $this->assertFalse(
            $cubreManifest,
            'El GET de manifest.json en consola es archivo estático; no debe pasar por StartSession.'
        );
    }

    public function test_el_service_worker_reusa_el_request_de_navegacion_con_cookies(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString('fetch(req)', $sw);
        $this->assertStringNotContainsString('fetch(req.url)', $sw);
        $this->assertDoesNotMatchRegularExpression("/credentials\\s*:\\s*['\"]omit['\"]/", $sw);
    }

    private function crearUsuario(): Usuario
    {
        return Usuario::create([
            'nombre' => 'Operador Sesion',
            'contrasenia' => 'secret',
            'numero_empleado' => '8801',
            'area' => 'Tejido',
        ]);
    }

    private function simularNuevoProcesoHttp(): void
    {
        Auth::forgetGuards();
        $this->app->forgetInstance('auth');
        $this->app->forgetInstance('session');
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance(StartSession::class);
    }

    private function filasSesionTotales(): int
    {
        return (int) DB::connection('sessions_test')->table('sessions')->count();
    }

    private function filasSesionAutenticadas(int $userId): int
    {
        return (int) DB::connection('sessions_test')->table('sessions')->where('user_id', $userId)->count();
    }
}
