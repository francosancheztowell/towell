<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Sistema\SYSMensaje;
use App\Models\Sistema\SysDepartamento;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class MensajesController extends Controller
{
    /**
     * Llama getUpdates de Telegram con el token dado y devuelve chat_ids encontrados
     * y el más reciente (último mensaje). Token puede ser el del registro o el global.
     *
     * @return array{ chat_ids: array, ultimo_chat_id: string|null }
     */
    private function obtenerChatIdsDesdeTelegram(?string $token): array
    {
        $botToken = $token ?: config('services.telegram.bot_token');
        if (empty($botToken)) {
            return ['chat_ids' => [], 'ultimo_chat_id' => null];
        }

        $url = "https://api.telegram.org/bot{$botToken}/getUpdates";
        $response = Http::get($url);

        if (! $response->successful()) {
            return ['chat_ids' => [], 'ultimo_chat_id' => null];
        }

        $data = $response->json();
        $updates = $data['result'] ?? [];
        $chatIds = [];
        $ultimoChatId = null;

        foreach ($updates as $update) {
            if (isset($update['message']['chat']['id'])) {
                $chatId = (string) $update['message']['chat']['id'];
                $firstName = $update['message']['chat']['first_name'] ?? 'Sin nombre';
                $username = $update['message']['chat']['username'] ?? 'Sin username';
                $type = $update['message']['chat']['type'] ?? 'private';

                if (! in_array($chatId, array_column($chatIds, 'chat_id'), true)) {
                    $chatIds[] = [
                        'chat_id' => $chatId,
                        'first_name' => $firstName,
                        'username' => $username,
                        'type' => $type,
                    ];
                }
                $ultimoChatId = $chatId;
            }
        }

        return ['chat_ids' => $chatIds, 'ultimo_chat_id' => $ultimoChatId];
    }
    /**
     * Listado de mensajes (SYSMensajes) con departamento.
     */
    public function index(): View
    {
        $mensajes = SYSMensaje::with('departamento')
            ->orderBy('Id')
            ->get();

        $departamentos = SysDepartamento::orderBy('id')->get(['id', 'Depto', 'Descripcion']);

        return view('modulos.configuracion.mensajes', [
            'mensajes' => $mensajes,
            'departamentos' => $departamentos,
        ]);
    }

    /**
     * Guardar nuevo mensaje.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'DepartamentoId' => ['required', 'integer', Rule::exists((new SysDepartamento)->getTable(), 'id')->using('sqlsrv')],
            'Telefono'       => ['required', 'string', 'max:20'],
            'Token'          => ['required', 'string', 'max:255'],
            'Activo'         => ['nullable', 'boolean'],
        ]);

        $validated['Activo'] = (bool) ($request->boolean('Activo') ?? true);

        $mensaje = SYSMensaje::create($validated);

        // Al crear, intentar obtener el chat_id del número (getUpdates con el Token del registro)
        $telegram = $this->obtenerChatIdsDesdeTelegram($mensaje->Token);
        if ($telegram['ultimo_chat_id'] !== null) {
            $mensaje->ChatId = $telegram['ultimo_chat_id'];
            $mensaje->save();
        }

        if ($request->expectsJson()) {
            $mensaje->load('departamento');
            return response()->json([
                'ok' => true,
                'message' => $telegram['ultimo_chat_id']
                    ? 'Mensaje creado correctamente. Chat ID asignado desde Telegram.'
                    : 'Mensaje creado correctamente. Para asignar Chat ID, pide al usuario que envíe un mensaje al bot y usa "Obtener Chat ID".',
                'item' => $this->itemToArray($mensaje),
            ]);
        }

        return redirect()
            ->route('configuracion.mensajes')
            ->with('success', 'Mensaje creado correctamente.');
    }

    /**
     * Actualizar mensaje.
     */
    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $mensaje = SYSMensaje::findOrFail($id);

        $validated = $request->validate([
            'DepartamentoId' => ['required', 'integer', Rule::exists((new SysDepartamento)->getTable(), 'id')->using('sqlsrv')],
            'Telefono'       => ['required', 'string', 'max:20'],
            'Token'          => ['required', 'string', 'max:255'],
            'ChatId'         => ['nullable', 'string', 'max:50'],
            'Activo'         => ['nullable', 'boolean'],
        ]);

        $validated['Activo'] = (bool) ($request->boolean('Activo') ?? true);

        $mensaje->update($validated);

        if ($request->expectsJson()) {
            $mensaje->load('departamento');
            return response()->json([
                'ok' => true,
                'message' => 'Mensaje actualizado correctamente.',
                'item' => $this->itemToArray($mensaje),
            ]);
        }

        return redirect()
            ->route('configuracion.mensajes')
            ->with('success', 'Mensaje actualizado correctamente.');
    }

    /**
     * Eliminar mensaje.
     */
    public function destroy(int $id): RedirectResponse|JsonResponse
    {
        $mensaje = SYSMensaje::findOrFail($id);
        $mensaje->delete();

        $request = request();
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Mensaje eliminado correctamente.']);
        }

        return redirect()
            ->route('configuracion.mensajes')
            ->with('success', 'Mensaje eliminado correctamente.');
    }

    /**
     * Obtener chat_ids de Telegram para un registro (usa el Token del mensaje).
     * Útil para el botón "Obtener Chat ID": el usuario envía un mensaje al bot y aquí se listan.
     */
    public function obtenerChatIds(int $id): JsonResponse
    {
        $mensaje = SYSMensaje::findOrFail($id);
        $telegram = $this->obtenerChatIdsDesdeTelegram($mensaje->Token);

        return response()->json([
            'ok' => true,
            'chat_ids' => $telegram['chat_ids'],
            'ultimo_chat_id' => $telegram['ultimo_chat_id'],
            'instructions' => [
                '1. Pide al usuario que envíe un mensaje al bot en Telegram',
                '2. Haz clic de nuevo en "Obtener Chat ID" o recarga',
                '3. Elige un chat_id y asígnalo al registro',
            ],
        ]);
    }

    /**
     * Actualizar solo el ChatId de un mensaje (desde el modal "Obtener Chat ID").
     */
    public function actualizarChatId(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'ChatId' => ['required', 'string', 'max:50'],
        ]);

        $mensaje = SYSMensaje::findOrFail($id);
        $mensaje->ChatId = $validated['ChatId'];
        $mensaje->save();

        $mensaje->load('departamento');
        return response()->json([
            'ok' => true,
            'message' => 'Chat ID actualizado correctamente.',
            'item' => $this->itemToArray($mensaje),
        ]);
    }

    private function itemToArray(SYSMensaje $mensaje): array
    {
        $depto = $mensaje->departamento;
        return [
            'Id' => $mensaje->Id,
            'DepartamentoId' => $mensaje->DepartamentoId,
            'DepartamentoNombre' => $depto ? ($depto->Depto ?? $depto->Descripcion ?? (string) $mensaje->DepartamentoId) : (string) $mensaje->DepartamentoId,
            'Telefono' => $mensaje->Telefono,
            'Token' => $mensaje->Token,
            'ChatId' => $mensaje->ChatId ?? '',
            'Activo' => (bool) $mensaje->Activo,
        ];
    }
}
