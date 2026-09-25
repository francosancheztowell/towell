<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manda un mensaje o un archivo a varios chats de Telegram a la vez.
 *
 * Todos los chats salen en paralelo (Http::pool), así que el peor caso es un
 * timeout y no uno por destinatario. No lee destinatarios: cada llamador conserva
 * sus validaciones y sus mensajes de log.
 */
final class TelegramEnvio
{
    public const SEGUNDOS_CONEXION = 3;

    public const SEGUNDOS_TEXTO = 8;

    public const SEGUNDOS_ARCHIVO = 15;

    /**
     * @param  array<int|string>  $chatIds
     * @param  array<string, mixed>  $extra  Campos adicionales de sendMessage (p. ej. parse_mode).
     * @return array<int|string, Response|Throwable> Resultado por chat_id.
     */
    public function mensaje(array $chatIds, string $texto, array $extra = []): array
    {
        return $this->enviar('sendMessage', $chatIds, static fn (PendingRequest $peticion, string $url, string $chatId) => $peticion
            ->timeout(self::SEGUNDOS_TEXTO)
            ->post($url, ['chat_id' => $chatId, 'text' => $texto] + $extra));
    }

    /**
     * @param  'sendDocument'|'sendPhoto'  $metodo
     * @param  array<int|string>  $chatIds
     * @return array<int|string, Response|Throwable> Resultado por chat_id.
     */
    public function archivo(string $metodo, array $chatIds, string $contenido, string $nombre, string $caption): array
    {
        $campo = $metodo === 'sendPhoto' ? 'photo' : 'document';

        return $this->enviar($metodo, $chatIds, static fn (PendingRequest $peticion, string $url, string $chatId) => $peticion
            ->timeout(self::SEGUNDOS_ARCHIVO)
            ->attach($campo, $contenido, $nombre)
            ->post($url, ['chat_id' => $chatId, 'caption' => $caption]));
    }

    /** Telegram aceptó el envío: 2xx y `ok: true`. */
    public static function exitoso(Response|Throwable $resultado): bool
    {
        return $resultado instanceof Response && $resultado->successful() && ($resultado->json('ok') ?? false) === true;
    }

    /** Falla pasajera (conexión, 429 o 5xx) que vale la pena reintentar una vez. */
    public static function reintentable(Response|Throwable $resultado): bool
    {
        return $resultado instanceof Throwable || $resultado->status() === 429 || $resultado->serverError();
    }

    /**
     * @param  array<int|string, Response|Throwable>  $resultados
     */
    public static function enviados(array $resultados): int
    {
        return count(array_filter($resultados, self::exitoso(...)));
    }

    /**
     * Contexto de log de un envío fallido.
     *
     * @return array{status: int|null, response: mixed}
     */
    public static function detalle(Response|Throwable $resultado): array
    {
        return $resultado instanceof Response
            ? ['status' => $resultado->status(), 'response' => $resultado->json() ?? $resultado->body()]
            : ['status' => null, 'response' => $resultado->getMessage()];
    }

    /**
     * Resultado para el usuario: "Reporte enviado por Telegram a 2 de 3 destinatarios" o
     * "Reporte: no se pudo enviar por Telegram (0 de 3 destinatarios)".
     */
    public static function resumen(string $sujeto, int $enviados, int $total, bool $femenino = false): string
    {
        $destinatarios = $total === 1 ? 'destinatario' : 'destinatarios';

        return match (true) {
            $total === 0 => "{$sujeto}: no se pudo enviar por Telegram (sin destinatarios activos o bot sin configurar)",
            $enviados === 0 => "{$sujeto}: no se pudo enviar por Telegram (0 de {$total} {$destinatarios})",
            default => $sujeto.($femenino ? ' enviada' : ' enviado')." por Telegram a {$enviados} de {$total} {$destinatarios}",
        };
    }

    /**
     * Log por chat fallido con los mensajes de siempre de los reportes PDF/imagen:
     * "Telegram respondió ok=false para {que}" y "Error HTTP al enviar {que} a Telegram".
     *
     * @param  array<int|string, Response|Throwable>  $resultados
     * @param  array<string, mixed>  $contexto
     */
    public static function registrarFallos(array $resultados, string $que, array $contexto): void
    {
        foreach ($resultados as $chatId => $resultado) {
            if (self::exitoso($resultado)) {
                continue;
            }

            $contexto['chat_id'] = (string) $chatId;
            if ($resultado instanceof Response && $resultado->successful()) {
                Log::error("Telegram respondió ok=false para {$que}", ['response' => $resultado->json()] + $contexto);
            } else {
                Log::error("Error HTTP al enviar {$que} a Telegram", self::detalle($resultado) + $contexto);
            }
        }
    }

    /**
     * @param  array<int|string>  $chatIds
     * @param  callable(PendingRequest, string, string): mixed  $peticion
     * @return array<int|string, Response|Throwable>
     */
    private function enviar(string $metodo, array $chatIds, callable $peticion): array
    {
        $chatIds = array_values(array_unique(array_map('strval', $chatIds)));
        if ($chatIds === []) {
            return [];
        }

        $url = 'https://api.telegram.org/bot'.trim((string) config('services.telegram.bot_token')).'/'.$metodo;

        return Http::pool(static function (Pool $pool) use ($chatIds, $url, $peticion): void {
            foreach ($chatIds as $chatId) {
                $peticion($pool->as($chatId)->connectTimeout(self::SEGUNDOS_CONEXION), $url, $chatId);
            }
        });
    }
}
