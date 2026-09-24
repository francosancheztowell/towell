<?php

namespace Tests\Feature\Monitoreo;

use App\Mail\Monitoreo\ErrorSistemaMail;
use App\Services\Monitoreo\Monitoreo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Feature\Monitoreo\Concerns\PreparaMonitoreo;
use Tests\TestCase;

class AlertasCorreoTest extends TestCase
{
    use PreparaMonitoreo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararMonitoreo();
        config()->set('app.debug', false);

        Route::middleware('web')->get('/_prueba/falla/{n}', fn ($n) => throw new RuntimeException('Falla tipo '.str_repeat('X', (int) $n)));
    }

    public function test_error_nuevo_envia_un_correo_al_destinatario_fijo_y_el_repetido_no(): void
    {
        Mail::fake();

        $this->get('/_prueba/falla/1')->assertStatus(500);
        $this->get('/_prueba/falla/1')->assertStatus(500);

        Mail::assertSentCount(1);
        Mail::assertSent(ErrorSistemaMail::class, function (ErrorSistemaMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('francost15@gmail.com')
                && count($mail->to) === 1
                && $mail->hasSubject('[Towell] ERROR NUEVO: RuntimeException')
                && str_contains($html, '/admin/errores/')
                && str_contains($html, 'Falla tipo X');
        });

        $this->assertNotNull(DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->value('AlertadoEn'));
    }

    public function test_regresion_vuelve_a_alertar_con_asunto_de_regresion(): void
    {
        Mail::fake();

        $this->get('/_prueba/falla/1');
        DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->update(['Estado' => 'resuelto']);
        $this->get('/_prueba/falla/1');

        Mail::assertSentCount(2);
        Mail::assertSent(ErrorSistemaMail::class, fn (ErrorSistemaMail $mail) => $mail->hasSubject('[Towell] REGRESIÓN: RuntimeException'));
        Mail::assertSent(ErrorSistemaMail::class, fn (ErrorSistemaMail $mail) => $mail->hasSubject('[Towell] ERROR NUEVO: RuntimeException'));
    }

    public function test_respeta_el_tope_por_hora(): void
    {
        config()->set('monitoreo.errores.alertas_max_hora', 2);
        Mail::fake();

        foreach ([1, 2, 3, 4] as $n) {
            $this->get('/_prueba/falla/'.$n);
        }

        // Los errores 3 y 4 quedan registrados sin alerta.
        Mail::assertSentCount(2);
        $this->assertSame(4, DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->count());
    }

    public function test_error_nuevo_con_varias_ocurrencias_no_se_anuncia_como_regresion(): void
    {
        Mail::fake();

        $this->get('/_prueba/falla/1');
        $this->get('/_prueba/falla/1');
        $this->get('/_prueba/falla/1');

        Mail::assertNotSent(ErrorSistemaMail::class, fn (ErrorSistemaMail $mail) => $mail->hasSubject('[Towell] REGRESIÓN: RuntimeException'));
    }

    public function test_fallo_del_mailer_no_afecta_la_request_ni_marca_alertado(): void
    {
        config()->set('mail.default', 'mailer-inexistente');

        $this->get('/_prueba/falla/1')->assertStatus(500)->assertSee('Código de referencia', false);

        $this->assertNull(DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->value('AlertadoEn'));
    }

    public function test_destinatario_vacio_o_invalido_no_envia(): void
    {
        Mail::fake();

        config()->set('monitoreo.errores.correo_alertas', '');
        $this->get('/_prueba/falla/1')->assertStatus(500);
        config()->set('monitoreo.errores.correo_alertas', 'no-es-correo');
        $this->get('/_prueba/falla/2')->assertStatus(500);

        Mail::assertNothingSent();
        $this->assertSame(0, DB::connection(Monitoreo::CONEXION_ERRORES)->table('SYSMonError')->whereNotNull('AlertadoEn')->count());
    }

    public function test_destinatario_sobreescribible_por_config(): void
    {
        config()->set('monitoreo.errores.correo_alertas', 'sistemas@towell.test');
        Mail::fake();

        $this->get('/_prueba/falla/1');

        Mail::assertSent(ErrorSistemaMail::class, fn (ErrorSistemaMail $mail) => $mail->hasTo('sistemas@towell.test'));
    }
}
