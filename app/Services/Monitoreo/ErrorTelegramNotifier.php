<?php

declare(strict_types=1);

namespace App\Services\Monitoreo;

use App\Models\Sistema\SYSMensaje;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alerta de Telegram para errores nuevos o regresiones (contrato §9).
 *
 * Copia el patrón de ParoTelegramNotifier: 5 s de timeout, nunca lanza, texto plano
 * sin parse_mode (el mensaje del error trae '*', '[' o '_' que Telegram rechazaría
 * con 400) y deja en el log el resultado. Destinatarios: SYSMensajes.ErroresSistema = 1.
 */
final class ErrorTelegramNotifier
{
    public const MODULO = 'ErroresSistema';

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
            Log::info('Monitoreo: alerta Telegram omitida por el tope por hora.', ['error_id' => $errorId]);

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
            $botToken = trim((string) config('services.telegram.bot_token'));
            if ($botToken === '') {
                Log::warning('Monitoreo: no se alertó el error, Telegram no está configurado.', ['error_id' => $errorId]);

                return;
            }

            $db = DB::connection(Monitoreo::conexionErrores());
            $error = $db->table('SYSMonError')->where('Id', $errorId)->first();
            if ($error === null) {
                return;
            }

            $chatIds = SYSMensaje::getChatIdsPorModulo(self::MODULO);
            if ($chatIds === []) {
                Log::warning('Monitoreo: no hay destinatarios Telegram activos para errores.', ['error_id' => $errorId]);

                return;
            }

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $texto = $this->mensaje($error, $regresion);

            foreach ($chatIds as $chatId) {
                $response = Http::timeout(5)->post($url, ['chat_id' => $chatId, 'text' => $texto]);

                if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                    Log::warning('Monitoreo: Telegram rechazó una alerta de error.', [
                        'error_id' => $errorId,
                        'chat_id' => $chatId,
                        'status' => $response->status(),
                    ]);
                }
            }

            $db->table('SYSMonError')->where('Id', $errorId)->update(['AlertadoEn' => now()]);
        } catch (Throwable $exception) {
            Log::error('Monitoreo: no fue posible alertar el error por Telegram.', [
                'error_id' => $errorId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function mensaje(object $error, bool $regresion = false): string
    {
        return implode("\n", [
            $regresion ? '🔁 REGRESIÓN DE ERROR EN TOWELL' : '🔴 ERROR NUEVO EN TOWELL',
            '',
            'Origen: '.$error->Origen,
            'Clase: '.$error->Clase,
            'Mensaje: '.mb_substr((string) $error->Mensaje, 0, 300),
            'Ruta: '.($error->Ruta ?: 'N/D'),
            'Ocurrencias: '.$error->Ocurrencias,
            'Detalle: '.url('/admin/errores/'.$error->Id),
        ]);
    }

    private function dentroDelTopePorHora(): bool
    {
        $llave = 'mon:tg:'.now()->format('YmdH');
        Cache::add($llave, 0, now()->addHour());

        return (int) Cache::increment($llave) <= (int) config('monitoreo.errores.telegram_max_hora', 10);
    }
}
