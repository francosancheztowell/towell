<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    /**
     * Enviar mensaje a Telegram
     *
     * LÍMITES DE TELEGRAM:
     * - Chats individuales: ~1 mensaje por segundo
     * - Grupos: hasta 20 mensajes por segundo
     * - Global: hasta 30 mensajes por segundo
     * - Tamaño de mensaje: máximo 4,096 caracteres
     * - Sin límite diario de mensajes (plan gratuito)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        try {
            // Obtener configuración de Telegram desde config/services.php
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');

            // Validar que existan las credenciales
            if (empty($botToken) || empty($chatId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales de Telegram no configuradas. Por favor, configure TELEGRAM_BOT_TOKEN y TELEGRAM_CHAT_ID en el archivo .env'
                ], 500);
            }

            // Mensaje a enviar (por ahora "Hola Mundo")
            $mensaje = $request->input('mensaje', 'Hola , estimado towellamigo 👋');

            // Limitar el tamaño del mensaje (Telegram tiene un límite de 4096 caracteres)
            // NOTA: Si necesitas enviar mensajes más largos, divídelos en múltiples mensajes
            $mensaje = mb_substr($mensaje, 0, 4096);

            // URL de la API de Telegram
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            // Enviar mensaje a Telegram
            $response = Http::post($url, [
                'chat_id' => $chatId,
                'text' => $mensaje
            ]);

            // Verificar respuesta
            if ($response->successful()) {
                $data = $response->json();

                if ($data['ok'] ?? false) {
                    Log::info('Mensaje enviado a Telegram exitosamente', [
                        'chat_id' => $chatId,
                        'mensaje' => $mensaje
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Mensaje enviado a Telegram correctamente',
                        'data' => $data
                    ]);
                }
            }

            // Si llegamos aquí, hubo un error
            $errorData = $response->json();
            $errorMessage = $errorData['description'] ?? 'Error desconocido';

            Log::error('Error al enviar mensaje a Telegram', [
                'response' => $errorData,
                'status' => $response->status(),
                'chat_id' => $chatId,
                'mensaje_length' => mb_strlen($mensaje)
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo enviar el mensaje a Telegram: ' . $errorMessage,
                'error' => $errorData
            ], $response->status() ?: 500);

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
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBotInfo()
    {
        try {
            $botToken = config('services.telegram.bot_token');

            if (empty($botToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token del bot no configurado'
                ], 500);
            }

            $url = "https://api.telegram.org/bot{$botToken}/getMe";
            $response = Http::get($url);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener información del bot',
                'error' => $response->json()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el chat_id del usuario (útil para configurar)
     *
     * IMPORTANTE: El usuario debe enviar un mensaje al bot primero
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getChatId()
    {
        try {
            $botToken = config('services.telegram.bot_token');

            if (empty($botToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token del bot no configurado'
                ], 500);
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

                    return response()->json([
                        'success' => true,
                        'message' => count($chatIds) > 0
                            ? 'Chat IDs encontrados. Copia el chat_id correcto a tu archivo .env'
                            : 'No se encontraron mensajes. Envía un mensaje a tu bot primero.',
                        'chat_ids' => $chatIds,
                        'instructions' => [
                            '1. Envía un mensaje a tu bot en Telegram',
                            '2. Recarga esta página',
                            '3. Copia el chat_id que aparece arriba',
                            '4. Agrégalo a tu archivo .env como: TELEGRAM_CHAT_ID=tu_chat_id_aqui'
                        ]
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener los updates',
                'error' => $response->json()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}

