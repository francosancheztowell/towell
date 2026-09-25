<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Services\Telegram\TelegramEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Aviso de Telegram que es efecto secundario de una acción (terminar atado, notificar
 * montado de julio, solicitar trama): la acción responde y el aviso sale en la cola.
 *
 * No es defer(): en producción (Windows, Apache/mod_php o php-cgi) no hay
 * fastcgi_finish_request y el navegador espera a que terminen los callbacks
 * diferidos (medición en 18-03-SUMMARY.md).
 */
final class EnviarMensajeTelegram implements ShouldQueue
{
    use Queueable;

    /** Nunca lanza, así que un segundo intento solo duplicaría el aviso. */
    public int $tries = 1;

    /**
     * @param  array<int|string>  $chatIds
     * @param  array<string, mixed>  $extra  Campos adicionales de sendMessage (parse_mode).
     * @param  array<string, mixed>  $contextoLog
     */
    public function __construct(
        public readonly array $chatIds,
        public readonly string $texto,
        public readonly array $extra,
        public readonly string $mensajeLog,
        public readonly array $contextoLog = [],
        public readonly string $nivelLog = 'warning',
    ) {}

    /**
     * Encola el aviso. Si la cola no está disponible (p. ej. falta la tabla `jobs`),
     * lo manda en línea: la acción no se cae y el aviso no se pierde.
     */
    public static function encolar(self $job): void
    {
        try {
            Bus::dispatch($job);
        } catch (Throwable $e) {
            Log::warning('Telegram: no se pudo encolar el aviso, se envía en línea.', [
                'error' => $e->getMessage(),
            ] + $job->contextoLog);

            $job->handle(app(TelegramEnvio::class));
        }
    }

    public function handle(TelegramEnvio $telegram): void
    {
        try {
            $resultados = $telegram->mensaje($this->chatIds, $this->texto, $this->extra);

            // Un reintento, en paralelo y solo a los chats con falla pasajera: los que ya
            // recibieron no se duplican (sustituye el retry(2, 200) que tenía Atadores).
            $reintentar = array_keys(array_filter($resultados, TelegramEnvio::reintentable(...)));
            if ($reintentar !== []) {
                $resultados = array_replace($resultados, $telegram->mensaje($reintentar, $this->texto, $this->extra));
            }

            foreach ($resultados as $chatId => $resultado) {
                if (! TelegramEnvio::exitoso($resultado)) {
                    Log::log($this->nivelLog, $this->mensajeLog, $this->contextoLog + ['chat_id' => (string) $chatId] + TelegramEnvio::detalle($resultado));
                }
            }
        } catch (Throwable $e) {
            Log::error('Telegram: excepción al enviar', ['error' => $e->getMessage()] + $this->contextoLog);
        }
    }
}
