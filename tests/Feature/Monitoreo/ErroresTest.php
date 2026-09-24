<?php

namespace Tests\Feature\Monitoreo;

use App\Services\Monitoreo\Monitoreo;
use App\Support\Http\Concerns\HandlesApiErrors;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class ErroresTest extends TestCase
{
    use PreparaMonitoreo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        config()->set('app.debug', false);
        config()->set('services.telegram.bot_token', '');

        Route::middleware('web')->group(function () {
            Route::any('/_prueba/explota', fn () => throw new RuntimeException('Fallo del telar 1234 en "Salon A"'))->name('prueba.explota');

            Route::post('/_prueba/transaccion', function () {
                DB::connection('sqlsrv')->beginTransaction();
                DB::connection('sqlsrv')->table('Negocio')->insert(['Valor' => 'x']);
                report(new RuntimeException('Fallo dentro de la transacción'));
                DB::connection('sqlsrv')->rollBack();

                return response()->json(['message' => 'No se pudo guardar'], 500);
            })->name('prueba.transaccion');

            Route::get('/_prueba/tragado', function () {
                try {
                    throw new RuntimeException('Folio 555 no existe');
                } catch (RuntimeException $e) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
                }
            })->name('prueba.tragado');

            Route::get('/_prueba/api-error', function () {
                $controller = new class
                {
                    use HandlesApiErrors;

                    public function responder(\Throwable $e)
                    {
                        return $this->apiErrorResponse($e, 'Fallo al guardar', 'No se pudo guardar');
                    }
                };

                return $controller->responder(new RuntimeException('Fallo en API'));
            })->name('prueba.api-error');

            Route::get('/_prueba/sql', fn () => DB::connection('sqlsrv')->select('select * from NoExiste where Clave = ?', ['secreto-777']))->name('prueba.sql');
            Route::get('/_prueba/validacion', fn () => request()->validate(['x' => 'required']))->name('prueba.validacion');
        });
        Route::getRoutes()->refreshNameLookups();
    }

    private function errores()
    {
        return DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError');
    }

    private function eventos()
    {
        return DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonErrorEvento');
    }

    public function test_dos_excepciones_iguales_son_un_error_con_dos_ocurrencias(): void
    {
        $this->get('/_prueba/explota')->assertStatus(500);
        $this->get('/_prueba/explota')->assertStatus(500);

        $this->assertSame(1, $this->errores()->count());
        $error = $this->errores()->first();
        $this->assertSame(2, (int) $error->Ocurrencias);
        $this->assertSame('php', $error->Origen);
        $this->assertSame(RuntimeException::class, $error->Clase);
        $this->assertSame('prueba.explota', $error->Ruta);
        $this->assertSame('nuevo', $error->Estado);
        $this->assertStringStartsWith('tests/Feature/Monitoreo/', $error->Archivo);
        $this->assertSame(2, $this->eventos()->where('ErrorId', $error->Id)->count());
    }

    public function test_numeros_y_comillas_distintos_agrupan_en_la_misma_huella(): void
    {
        Route::get('/_prueba/variable/{n}', fn ($n) => throw new RuntimeException("Orden {$n} con clave 'K{$n}'"));

        $this->get('/_prueba/variable/1');
        $this->get('/_prueba/variable/2');

        $this->assertSame(1, $this->errores()->count());
    }

    public function test_error_dentro_de_una_transaccion_revertida_queda_registrado(): void
    {
        Schema::connection('sqlsrv')->create('Negocio', fn (Blueprint $t) => $t->string('Valor'));

        $this->post('/_prueba/transaccion')->assertStatus(500);

        $this->assertSame(0, DB::connection('sqlsrv')->table('Negocio')->count(), 'El negocio se revirtió.');
        $this->assertSame(1, $this->errores()->count(), 'El error sobrevive al rollback.');
        $this->assertSame('Fallo dentro de la transacción', $this->errores()->value('Mensaje'));
        // Y no se duplicó como http5xx: el recorder ya había reportado en esta request.
        $this->assertSame(0, $this->errores()->where('Origen', 'http5xx')->count());
    }

    public function test_si_la_tabla_de_errores_no_existe_la_request_devuelve_su_error_normal(): void
    {
        Schema::connection(Monitoreo::CONEXION_ERRORES)->drop('SYSMonError');

        $this->get('/_prueba/explota')->assertStatus(500)->assertSee('Error del servidor');
        $this->get('/_prueba/tragado')->assertStatus(500)->assertJson(['message' => 'Folio 555 no existe']);
    }

    public function test_nunca_guarda_la_contrasena_ni_valores_del_input(): void
    {
        $this->post('/_prueba/explota', ['numero_empleado' => '7701', 'contrasenia' => 'Secreta-9981', 'items' => [['nota' => 'privada']]])
            ->assertStatus(500);

        $todo = json_encode([$this->errores()->get(), $this->eventos()->get()]);
        $this->assertStringNotContainsString('Secreta-9981', $todo);
        $this->assertStringNotContainsString('privada', $todo);

        $traza = (string) $this->eventos()->value('Traza');
        $this->assertStringContainsString('Input: numero_empleado, contrasenia, items.0.nota', $traza);
        $this->assertLessThanOrEqual(8192, strlen($traza));
    }

    public function test_query_exception_guarda_solo_sql_con_placeholders(): void
    {
        $this->get('/_prueba/sql')->assertStatus(500);

        $mensaje = (string) $this->errores()->value('Mensaje');
        $this->assertStringContainsString('select * from NoExiste where Clave = ?', $mensaje);
        $this->assertStringNotContainsString('secreto-777', json_encode([$this->errores()->get(), $this->eventos()->get()]));
    }

    public function test_respuesta_500_de_un_catch_queda_como_http5xx(): void
    {
        $this->get('/_prueba/tragado')->assertStatus(500)->assertJson(['message' => 'Folio 555 no existe']);
        $this->get('/_prueba/tragado');

        $error = $this->errores()->first();
        $this->assertSame('http5xx', $error->Origen);
        $this->assertSame('HTTP 500', $error->Clase);
        $this->assertSame('Folio N no existe', $error->Mensaje);
        $this->assertSame('prueba.tragado', $error->Ruta);
        $this->assertSame(2, (int) $error->Ocurrencias);
        $this->assertSame(500, (int) $this->eventos()->value('Status'));
    }

    public function test_handles_api_errors_reporta_una_sola_vez_sin_cambiar_la_respuesta(): void
    {
        $this->get('/_prueba/api-error')->assertStatus(500)->assertJson(['success' => false, 'message' => 'No se pudo guardar']);

        $this->assertSame(1, $this->errores()->count());
        $this->assertSame('php', $this->errores()->value('Origen'));
    }

    public function test_excepciones_ignoradas_no_se_registran(): void
    {
        $this->get('/_prueba/validacion');
        $this->get('/_prueba/no-existe-esta-ruta')->assertNotFound();

        $this->assertSame(0, $this->errores()->count());
    }

    public function test_tope_diario_de_eventos_pero_las_ocurrencias_siempre_suman(): void
    {
        config()->set('monitoreo.errores.max_eventos_dia', 2);

        for ($i = 0; $i < 4; $i++) {
            $this->get('/_prueba/explota');
        }

        $this->assertSame(4, (int) $this->errores()->value('Ocurrencias'));
        $this->assertSame(2, $this->eventos()->count());
    }

    public function test_un_error_resuelto_que_vuelve_pasa_a_nuevo(): void
    {
        $this->get('/_prueba/explota');
        $this->errores()->update(['Estado' => 'resuelto']);

        $this->get('/_prueba/explota');

        $this->assertSame('nuevo', $this->errores()->value('Estado'));
    }

    public function test_la_pagina_500_muestra_el_codigo_de_referencia(): void
    {
        $html = $this->get('/_prueba/explota')->assertStatus(500)->getContent();

        $eventoId = (int) $this->eventos()->value('Id');
        $this->assertGreaterThan(0, $eventoId);
        $this->assertStringContainsString('Código de referencia: <strong class="text-gray-700">#'.$eventoId.'</strong>', $html);
        $this->assertStringNotContainsString('ha sido notificado', $html);
    }

    public function test_kill_switch_no_registra_errores(): void
    {
        config()->set('monitoreo.enabled', false);

        $this->get('/_prueba/explota')->assertStatus(500);
        $this->get('/_prueba/tragado')->assertStatus(500);

        $this->assertSame(0, $this->errores()->count());
    }
}
