<?php

declare(strict_types=1);

namespace App\Jobs\Telegram;

use App\Services\Telegram\TelegramEnvio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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
    use InteractsWithQueue;
    use Queueable;

    /** Nunca lanza, así que un segundo intento solo duplicaría el aviso. */
    public int $tries = 1;

    /** Un aviso de paro de hace horas ya no sirve y solo satura el grupo: se descarta. */
    public const VIGENCIA_SEGUNDOS = 1800;

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
    /**
     * Cuándo entró a la cola, según el `createdAt` que Laravel pone en todo payload (también
     * en los jobs encolados antes de este cambio). null si se manda en línea, sin cola.
     */
    private function creadoEnCola(): ?int
    {
        $creado = $this->job?->payload()['createdAt'] ?? null;

        return is_int($creado) ? $creado : null;
    }

    public static function encolar(self $job): void
    {
        try {
            Bus::dispatch($job);
        } catch (Throwable $e) {
            Log::warning('Telegram: no se pudo encolar el aviso, se envía en línea.', [
                'error' => $e->getMessage(),
            ] + $job->contextoLog);

            $job->handle(app(TelegramEnvio::class));

            return;
        }

        // Sin esto el aviso se quedaba en `jobs` para siempre si nadie creó la tarea
        // programada del worker (pasó con los paros). Tras el commit, para que el worker
        // vea el renglón recién insertado.
        DB::afterCommit(static fn () => self::arrancarWorker());
    }

    /**
     * Lanza en segundo plano un worker que vacía la cola y se sale. Varios a la vez no
     * duplican envíos: la cola database reserva cada job con bloqueo de renglón.
     *
     * ponytail: un proceso por aviso; son pocos por minuto. Si algún día son cientos,
     * cambiar a la tarea programada del runbook (deploy.md §8) y quitar esto.
     */
    public static function arrancarWorker(): void
    {
        $comando = config('queue.autoworker') ? self::comandoWorker() : null;
        if ($comando === null) {
            return;
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                @pclose(@popen('start /B "" '.$comando.' >NUL 2>&1', 'r'));
            } else {
                @exec($comando.' >/dev/null 2>&1 &');
            }
        } catch (Throwable $e) {
            Log::warning('Telegram: no se pudo arrancar el worker de la cola.', ['error' => $e->getMessage()]);
        }
    }

    /** null con la cola `sync` (no hay nada que vaciar) o si no se encuentra el php de consola. */
    public static function comandoWorker(): ?string
    {
        $conexion = (string) config('queue.default');
        $php = self::phpDeConsola();
        if ($conexion === 'sync' || $php === null) {
            return null;
        }

        return implode(' ', [
            escapeshellarg($php),
            escapeshellarg(base_path('artisan')),
            'queue:work',
            escapeshellarg($conexion),
            '--stop-when-empty',
            '--tries=1',
            '--max-time=60',
        ]);
    }

    /**
     * Dentro de Apache PHP_BINARY es httpd.exe o php-cgi.exe, que no corren artisan. El
     * php.exe de consola vive junto al php.ini en Laragon y en XAMPP.
     */
    private static function phpDeConsola(): ?string
    {
        $ini = php_ini_loaded_file();
        $candidatos = [
            config('queue.php_cli'),
            preg_match('/^php(\.exe)?$/i', basename(PHP_BINARY)) === 1 ? PHP_BINARY : null,
            $ini ? dirname($ini).DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php') : null,
            PHP_BINDIR.DIRECTORY_SEPARATOR.(PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php'),
        ];

        foreach ($candidatos as $ruta) {
            if (is_string($ruta) && $ruta !== '' && is_file($ruta)) {
                return $ruta;
            }
        }

        Log::warning('Telegram: no se encontró php de consola para el worker; defina PHP_CLI_PATH.');

        return null;
    }

    public function handle(TelegramEnvio $telegram): void
    {
        // Los que se quedaron atorados antes de que existiera el worker automático saldrían
        // todos juntos al primer aviso nuevo: decenas de paros viejos de golpe en el grupo.
        $creadoEn = $this->creadoEnCola();
        if ($creadoEn !== null && time() - $creadoEn > self::VIGENCIA_SEGUNDOS) {
            Log::info('Telegram: aviso vencido, no se envía.', $this->contextoLog + [
                'creado_en' => date('Y-m-d H:i:s', $creadoEn),
            ]);

            return;
        }

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
