<?php

namespace Tests\Feature\Seguridad;

use App\Services\Monitoreo\Monitoreo;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

/**
 * SEC-04 — Render central de 5xx en JSON: mensaje genérico + trace_id, sin detalle interno
 * con APP_DEBUG=false. Los 4xx y la página HTML 500 no cambian.
 */
class ErroresJsonServidorTest extends TestCase
{
    use PreparaMonitoreo;

    private const GENERICO = 'Ocurrió un error en el servidor. Si continúa, comparte el código de referencia con Sistemas.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        config()->set('app.debug', false);
        config()->set('monitoreo.errores.correo_alertas', '');

        Route::middleware('web')->group(function () {
            Route::any('/_seg/explota', fn () => throw new RuntimeException('Fallo del telar 1234 en /var/www/app/secreto.php'));
            Route::get('/_seg/sql', fn () => DB::connection('sqlsrv')->select('select * from TablaSecreta where Clave = ?', ['binding-777']));
            Route::get('/_seg/abort500', fn () => abort(500, 'Detalle interno del abort'));
            Route::get('/_seg/abort503', fn () => abort(503, 'Detalle 503', ['Retry-After' => '120']));
            Route::post('/_seg/validacion', fn () => request()->validate(['x' => 'required']));
            Route::get('/_seg/prohibido', fn () => abort(403, 'Sin permiso de prueba'));
            Route::get('/_seg/csrf', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
            Route::get('/_seg/dos-errores', function () {
                report(new RuntimeException('Primer error, registrado'));
                throw new \LogicException('Segundo error, ignorado por monitoreo');
            });
            Route::get('/_seg/api-error', function () {
                $controller = new class
                {
                    use HandlesApiErrors;

                    public function responder(\Throwable $e)
                    {
                        return $this->apiErrorResponse($e, 'Fallo al guardar', 'No se pudo guardar');
                    }
                };

                return $controller->responder(new RuntimeException('Fallo en API'));
            });
        });
    }

    private function ultimoEventoId(): int
    {
        return (int) DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonErrorEvento')->max('Id');
    }

    private function assertSinDetalle(string $cuerpo, array $prohibidos): void
    {
        $this->assertSame(['success', 'message', 'trace_id'], array_keys(json_decode($cuerpo, true)));
        foreach (['Exception', 'SQLSTATE', '.php'] as $marca) {
            $this->assertStringNotContainsString($marca, $cuerpo);
        }
        foreach ($prohibidos as $texto) {
            $this->assertStringNotContainsString($texto, $cuerpo);
        }
    }

    public function test_500_en_json_es_generico_y_trae_el_evento_como_trace_id(): void
    {
        $respuesta = $this->getJson('/_seg/explota')->assertStatus(500);

        $respuesta->assertExactJson([
            'success' => false,
            'message' => self::GENERICO,
            'trace_id' => (string) $this->ultimoEventoId(),
        ]);
        $this->assertGreaterThan(0, $this->ultimoEventoId());
        $this->assertSinDetalle($respuesta->getContent(), ['telar', '1234', 'secreto']);
    }

    public function test_query_exception_no_expone_sql_ni_bindings(): void
    {
        $respuesta = $this->getJson('/_seg/sql')->assertStatus(500)
            ->assertJsonPath('message', self::GENERICO)
            ->assertJsonPath('trace_id', (string) $this->ultimoEventoId());

        $this->assertSinDetalle($respuesta->getContent(), ['TablaSecreta', 'binding-777', 'select']);
    }

    public function test_http_exception_5xx_no_expone_su_mensaje_y_conserva_status_y_headers(): void
    {
        $this->getJson('/_seg/abort500')->assertStatus(500)
            ->assertJsonPath('message', self::GENERICO)
            ->assertDontSee('Detalle interno');

        $this->getJson('/_seg/abort503')->assertStatus(503)
            ->assertHeader('Retry-After', '120')
            ->assertJsonPath('message', self::GENERICO)
            ->assertDontSee('Detalle 503');
    }

    public function test_sin_evento_de_monitoreo_el_trace_id_es_un_uuid(): void
    {
        config()->set('monitoreo.enabled', false);

        $traceId = $this->getJson('/_seg/explota')->assertStatus(500)
            ->assertJsonPath('message', self::GENERICO)
            ->json('trace_id');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $traceId);
    }

    public function test_el_trace_id_es_el_de_esta_excepcion_y_no_el_ultimo_de_la_request(): void
    {
        config()->set('monitoreo.errores.ignorar', [\LogicException::class]);

        $traceId = $this->getJson('/_seg/dos-errores')->assertStatus(500)->json('trace_id');

        $this->assertSame(1, $this->ultimoEventoId(), 'el primer error sí se registró');
        $this->assertNotSame('1', $traceId);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $traceId);
    }

    public function test_con_app_debug_se_conserva_el_detalle_de_laravel(): void
    {
        config()->set('app.debug', true);

        $this->getJson('/_seg/explota')->assertStatus(500)
            ->assertJsonPath('exception', RuntimeException::class)
            ->assertJsonStructure(['message', 'exception', 'file', 'line', 'trace']);
    }

    public function test_422_403_404_y_419_no_cambian(): void
    {
        $this->postJson('/_seg/validacion')->assertStatus(422)
            ->assertJsonValidationErrors('x')
            ->assertJsonMissingPath('trace_id');

        $this->getJson('/_seg/prohibido')->assertStatus(403)
            ->assertExactJson(['message' => 'Sin permiso de prueba']);

        $this->getJson('/_seg/no-existe-esta-ruta')->assertStatus(404)
            ->assertJsonMissingPath('trace_id');

        $this->getJson('/_seg/csrf')->assertStatus(419)
            ->assertJsonMissingPath('trace_id');
    }

    public function test_la_pagina_html_500_no_cambia(): void
    {
        $html = $this->get('/_seg/explota')->assertStatus(500)->getContent();

        $this->assertStringContainsString('Error del servidor', $html);
        $this->assertStringContainsString('#'.$this->ultimoEventoId().'</strong>', $html);
        $this->assertStringNotContainsString(self::GENERICO, $html);
    }

    public function test_livewire_sigue_recibiendo_la_pagina_500_sin_detalle(): void
    {
        // D1 (owner): Livewire pinta la respuesta en su modal; JSON se vería crudo.
        $html = $this->withHeaders(['X-Livewire' => '1', 'Content-Type' => 'application/json'])
            ->post('/_seg/explota', ['components' => []])
            ->assertStatus(500)
            ->getContent();

        $this->assertStringContainsString('Error del servidor', $html);
        $this->assertStringContainsString('#'.$this->ultimoEventoId().'</strong>', $html);
        foreach (['RuntimeException', 'telar', '1234', 'secreto.php'] as $texto) {
            $this->assertStringNotContainsString($texto, $html);
        }
    }

    public function test_api_error_response_usa_el_evento_como_trace_id(): void
    {
        $this->getJson('/_seg/api-error')->assertStatus(500)
            ->assertExactJson([
                'success' => false,
                'message' => 'No se pudo guardar',
                'trace_id' => (string) $this->ultimoEventoId(),
            ]);
    }
}
