<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Models\Sistema\SYSMensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    /**
     * Enviar mensaje a Telegram.
     * Los destinatarios se obtienen de la tabla SYSMensajes según el módulo (columna).
     * Parámetros: mensaje (texto), modulo (ej: InvTrama, Desarrolladores, NotificarAtadoJulio, etc.)
     *
     * LÍMITES DE TELEGRAM:
     * - Chats individuales: ~1 mensaje por segundo
     * - Grupos: hasta 20 mensajes por segundo
     * - Tamaño de mensaje: máximo 4,096 caracteres
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        try {
            $botToken = config('services.telegram.bot_token');
            if (empty($botToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configure TELEGRAM_BOT_TOKEN en el archivo .env'
                ], 500);
            }

            $mensaje = $request->input('mensaje', '');
            $mensaje = mb_substr($mensaje, 0, 4096);

            $modulo = $request->input('modulo');
            $chatIds = [];

            if (! empty($modulo)) {
                // Destinatarios según la columna del módulo en SYSMensajes (Activo=1 y columna=1)
                $chatIds = SYSMensaje::getChatIdsPorModulo($modulo);
            }

            // Fallback: si no hay módulo o no hay registros, usar chat_id global del .env (opcional)
            if (empty($chatIds)) {
                $chatIdGlobal = config('services.telegram.chat_id');
                if (! empty($chatIdGlobal)) {
                    $chatIds = [$chatIdGlobal];
                }
            }

            if (empty($chatIds)) {
                return response()->json([
                    'success' => false,
                    'message' => $modulo
                        ? "No hay destinatarios en SYSMensajes con módulo \"{$modulo}\" activo (y Activo=1). Configure registros en Configuración > Mensajes."
                        : 'Indique el parámetro "modulo" (ej: InvTrama) o configure TELEGRAM_CHAT_ID en .env'
                ], 400);
            }

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $enviados = 0;
            $errores = [];

            foreach ($chatIds as $chatId) {
                $response = Http::post($url, [
                    'chat_id' => $chatId,
                    'text' => $mensaje
                ]);
                if ($response->successful() && ($response->json()['ok'] ?? false)) {
                    $enviados++;
                } else {
                    $errores[] = [
                        'chat_id' => $chatId,
                        'description' => $response->json()['description'] ?? 'Error desconocido'
                    ];
                }
            }

            return response()->json([
                'success' => $enviados > 0,
                'message' => $enviados === count($chatIds)
                    ? "Mensaje enviado a {$enviados} destinatario(s)."
                    : "Enviado a {$enviados} de " . count($chatIds) . ". Algunos fallaron.",
                'enviados' => $enviados,
                'total' => count($chatIds),
                'errores' => $errores
            ]);

        } catch (\Exception $e) {
            Log::error('Excepción al enviar mensaje a Telegram', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar mensaje: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información del bot de Telegram
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function getBotInfo(Request $request)
    {
        try {
            $botToken = config('services.telegram.bot_token');

            if (empty($botToken)) {
                $payload = ['success' => false, 'message' => 'Token del bot no configurado'];
                return $request->wantsJson()
                    ? response()->json($payload, 500)
                    : view('modulos.telegram.bot-info', $payload);
            }

            $url = "https://api.telegram.org/bot{$botToken}/getMe";
            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json();
                $payload = ['success' => true, 'data' => $data];
                return $request->wantsJson()
                    ? response()->json($payload)
                    : view('modulos.telegram.bot-info', $payload);
            }

            $payload = [
                'success' => false,
                'message' => 'No se pudo obtener información del bot',
                'error' => $response->json()
            ];
            return $request->wantsJson()
                ? response()->json($payload, 500)
                : view('modulos.telegram.bot-info', $payload);

        } catch (\Exception $e) {
            $payload = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            return $request->wantsJson()
                ? response()->json($payload, 500)
                : view('modulos.telegram.bot-info', $payload);
        }
    }

    /**
     * Obtener el chat_id del usuario (útil para configurar)
     *
     * IMPORTANTE: El usuario debe enviar un mensaje al bot primero
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function getChatId(Request $request)
    {
        try {
            $botToken = config('services.telegram.bot_token');

            if (empty($botToken)) {
                $payload = [
                    'success' => false,
                    'message' => 'Token del bot no configurado',
                    'chat_ids' => [],
                    'instructions' => []
                ];
                return $request->wantsJson()
                    ? response()->json($payload, 500)
                    : view('modulos.telegram.get-chat-id', $payload);
            }

            $url = "https://api.telegram.org/bot{$botToken}/getUpdates";
            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['ok'] ?? false) {
                    $updates = $data['result'] ?? [];
                    $chatIds = [];

                    foreach ($updates as $update) {
                        if (isset($update['message']['chat']['id'])) {
                            $chatId = $update['message']['chat']['id'];
                            $firstName = $update['message']['chat']['first_name'] ?? 'Sin nombre';
                            $username = $update['message']['chat']['username'] ?? 'Sin username';

                            if (!in_array($chatId, array_column($chatIds, 'chat_id'))) {
                                $chatIds[] = [
                                    'chat_id' => $chatId,
                                    'first_name' => $firstName,
                                    'username' => $username,
                                    'type' => $update['message']['chat']['type'] ?? 'private'
                                ];
                            }
                        }
                    }

                    $payload = [
                        'success' => true,
                        'message' => count($chatIds) > 0
                            ? 'Chat IDs encontrados. Copia el chat_id correcto a tu archivo .env'
                            : 'No se encontraron mensajes. Envía un mensaje a tu bot primero.',
                        'chat_ids' => $chatIds,
                        'instructions' => [
                            'Envía un mensaje a tu bot en Telegram (cualquier texto).',
                            'Recarga esta página con el botón de abajo.',
                            'Copia el Chat ID que aparece en la tabla (botón "Copiar").',
                            'Agrégalo a tu archivo .env como: TELEGRAM_CHAT_ID=tu_chat_id_aqui',
                        ]
                    ];
                    return $request->wantsJson()
                        ? response()->json($payload)
                        : view('modulos.telegram.get-chat-id', $payload);
                }
            }

            $payload = [
                'success' => false,
                'message' => 'No se pudo obtener los updates',
                'error' => $response->json(),
                'chat_ids' => [],
                'instructions' => []
            ];
            return $request->wantsJson()
                ? response()->json($payload, 500)
                : view('modulos.telegram.get-chat-id', $payload);

        } catch (\Exception $e) {
            $payload = [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'chat_ids' => [],
                'instructions' => []
            ];
            return $request->wantsJson()
                ? response()->json($payload, 500)
                : view('modulos.telegram.get-chat-id', $payload);
        }
    }
}

