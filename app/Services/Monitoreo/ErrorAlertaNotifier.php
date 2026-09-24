<?php

declare(strict_types=1);

namespace App\Services\Monitoreo;

use App\Mail\Monitoreo\ErrorSistemaMail;
use App\Models\Sistema\SYSMensaje;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Alerta por correo de errores nuevos o regresiones (contrato §9).
 *
 * Un solo destinatario fijo (monitoreo.errores.correo_alertas). Copia el patrón de
 * CrudoAlineacionNotifier: Mail::to()->send, nunca lanza y deja en el log el resultado.
 */
final class ErrorAlertaNotifier
{
    /**
     * Envíos programados ya ejecutados. Application::terminate() no limpia sus
     * callbacks, así que en un proceso que atiende varias requests (tests, Octane)
     * el mismo envío se volvería a disparar en cada terminate.
     *
     * @var array<string, true>
     */
    private static array $ejecutados = [];

    /**
     * Programa el envío para después de la respuesta, respetando el tope global por hora.
     * En consola (queue:work, schedule) no hay "después de la respuesta" y el worker
     * nunca termina entre jobs: ahí se envía en el momento.
     */
    public function programar(int $errorId, bool $regresion = false): void
    {
        if (! $this->dentroDelTopePorHora()) {
            Log::info('Monitoreo: alerta de error omitida por el tope por hora.', ['error_id' => $errorId]);

            return;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            $this->notificar($errorId, $regresion);

            return;
        }

        $marca = $errorId.':'.bin2hex(random_bytes(8));

        dispatch(function () use ($errorId, $regresion, $marca): void {
            if (isset(self::$ejecutados[$marca])) {
                return;
            }
            self::$ejecutados[$marca] = true;
            app(self::class)->notificar($errorId, $regresion);
        })->afterResponse();
    }

    public function notificar(int $errorId, bool $regresion = false): void
    {
        try {
            $destinatarios = SYSMensaje::soloCorreosValidos([config('monitoreo.errores.correo_alertas')]);
            if ($destinatarios === []) {
                Log::warning('Monitoreo: no se alertó el error, el correo de alertas está vacío o no es válido.', ['error_id' => $errorId]);

                return;
            }

            $db = DB::connection(Monitoreo::conexionErrores());
            $error = $db->table('SYSMonError')->where('Id', $errorId)->first();
            if ($error === null) {
                return;
            }

            Mail::to($destinatarios)->send(new ErrorSistemaMail($error, $regresion));

            $db->table('SYSMonError')->where('Id', $errorId)->update(['AlertadoEn' => now()]);
            Log::info('Monitoreo: alerta de error enviada por correo.', ['error_id' => $errorId, 'regresion' => $regresion]);
        } catch (Throwable $exception) {
            Log::error('Monitoreo: no fue posible alertar el error por correo.', [
                'error_id' => $errorId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function dentroDelTopePorHora(): bool
    {
        $llave = 'mon:alerta:'.now()->format('YmdH');
        Cache::add($llave, 0, now()->addHour());

        return (int) Cache::increment($llave) <= (int) config('monitoreo.errores.alertas_max_hora', 10);
    }
}
