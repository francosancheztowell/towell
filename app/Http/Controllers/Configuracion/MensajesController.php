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

class MensajesController extends Controller
{
    /**
     * Llama getUpdates de Telegram con el token del bot global y devuelve chat_ids encontrados.
     *
     * @return array{ chat_ids: array, ultimo_chat_id: string|null }
     */
    private function obtenerChatIdsDesdeTelegram(): array
    {
        $botToken = config('services.telegram.bot_token');
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

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'DepartamentoId' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! SysDepartamento::where('id', $value)->exists()) {
                        $fail(__('validation.exists', ['attribute' => $attribute]));
                    }
                },
            ],
            'Telefono'       => ['required', 'string', 'max:20'],
            'Token'          => ['required', 'string', 'max:255'],
            'Activo'         => ['nullable', 'boolean'],
            'Nombre'         => ['nullable', 'string', 'max:150'],
            'Desarrolladores'         => ['nullable', 'boolean'],
            'NotificarAtadoJulio'     => ['nullable', 'boolean'],
            'CorteSEF'                => ['nullable', 'boolean'],
            'MarcasFinales'           => ['nullable', 'boolean'],
            'ReporteElectrico'        => ['nullable', 'boolean'],
            'ReporteMecanico'         => ['nullable', 'boolean'],
            'ReporteTiempoMuerto'     => ['nullable', 'boolean'],
            'Atadores'                => ['nullable', 'boolean'],
            'InvTrama'                => ['nullable', 'boolean'],
        ]);

        $validated['Activo'] = (bool) ($request->boolean('Activo') ?? true);
        $validated['Nombre'] = $request->input('Nombre');
        $validated['Desarrolladores'] = (bool) ($request->boolean('Desarrolladores') ?? false);
        $validated['NotificarAtadoJulio'] = (bool) ($request->boolean('NotificarAtadoJulio') ?? false);
        $validated['CorteSEF'] = (bool) ($request->boolean('CorteSEF') ?? false);
        $validated['MarcasFinales'] = (bool) ($request->boolean('MarcasFinales') ?? false);
        $validated['ReporteElectrico'] = (bool) ($request->boolean('ReporteElectrico') ?? false);
        $validated['ReporteMecanico'] = (bool) ($request->boolean('ReporteMecanico') ?? false);
        $validated['ReporteTiempoMuerto'] = (bool) ($request->boolean('ReporteTiempoMuerto') ?? false);
        $validated['Atadores'] = (bool) ($request->boolean('Atadores') ?? false);
        $validated['InvTrama'] = (bool) ($request->boolean('InvTrama') ?? false);

        $mensaje = SYSMensaje::create($validated);

        if ($request->expectsJson()) {
            $mensaje->load('departamento');
            return response()->json([
                'ok' => true,
                'message' => 'Mensaje creado correctamente.',
                'item' => $this->itemToArray($mensaje),
            ]);
        }

        return redirect()
            ->route('configuracion.mensajes')
            ->with('success', 'Mensaje creado correctamente.');
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $mensaje = SYSMensaje::findOrFail($id);

        $validated = $request->validate([
            'DepartamentoId' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! SysDepartamento::where('id', $value)->exists()) {
                        $fail(__('validation.exists', ['attribute' => $attribute]));
                    }
                },
            ],
            'Telefono'       => ['required', 'string', 'max:20'],
            'Token'          => ['required', 'string', 'max:255'],
            'Activo'         => ['nullable', 'boolean'],
            'Nombre'         => ['nullable', 'string', 'max:150'],
            'Desarrolladores'         => ['nullable', 'boolean'],
            'NotificarAtadoJulio'     => ['nullable', 'boolean'],
            'CorteSEF'                => ['nullable', 'boolean'],
            'MarcasFinales'           => ['nullable', 'boolean'],
            'ReporteElectrico'        => ['nullable', 'boolean'],
            'ReporteMecanico'         => ['nullable', 'boolean'],
            'ReporteTiempoMuerto'     => ['nullable', 'boolean'],
            'Atadores'                => ['nullable', 'boolean'],
            'InvTrama'                => ['nullable', 'boolean'],
        ]);

        $validated['Activo'] = (bool) ($request->boolean('Activo') ?? true);
        $validated['Nombre'] = $request->input('Nombre');
        $validated['Desarrolladores'] = (bool) ($request->boolean('Desarrolladores') ?? false);
        $validated['NotificarAtadoJulio'] = (bool) ($request->boolean('NotificarAtadoJulio') ?? false);
        $validated['CorteSEF'] = (bool) ($request->boolean('CorteSEF') ?? false);
        $validated['MarcasFinales'] = (bool) ($request->boolean('MarcasFinales') ?? false);
        $validated['ReporteElectrico'] = (bool) ($request->boolean('ReporteElectrico') ?? false);
        $validated['ReporteMecanico'] = (bool) ($request->boolean('ReporteMecanico') ?? false);
        $validated['ReporteTiempoMuerto'] = (bool) ($request->boolean('ReporteTiempoMuerto') ?? false);
        $validated['Atadores'] = (bool) ($request->boolean('Atadores') ?? false);
        $validated['InvTrama'] = (bool) ($request->boolean('InvTrama') ?? false);

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

    public function obtenerChatIds(int $id): JsonResponse
    {
        SYSMensaje::findOrFail($id);
        $telegram = $this->obtenerChatIdsDesdeTelegram();

        return response()->json([
            'ok' => true,
            'chat_ids' => $telegram['chat_ids'],
            'ultimo_chat_id' => $telegram['ultimo_chat_id'],
            'instructions' => [
                '1. Pide al usuario que envíe un mensaje al bot en Telegram',
                '2. Haz clic de nuevo en "Obtener Chat ID" o recarga',
                '3. Elige un chat_id y asígnalo al registro (se guarda en Token)',
            ],
        ]);
    }

    /**
     * Actualizar solo el Token (Chat ID de Telegram) del mensaje (desde el modal "Obtener Chat ID").
     */
    public function actualizarChatId(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'ChatId' => ['required', 'string', 'max:50'],
        ]);

        $mensaje = SYSMensaje::findOrFail($id);
        $mensaje->Token = $validated['ChatId'];
        $mensaje->save();

        $mensaje->load('departamento');
        return response()->json([
            'ok' => true,
            'message' => 'Chat ID (Token) actualizado correctamente.',
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
            'Activo' => (bool) $mensaje->Activo,
            'FechaRegistro' => $mensaje->FechaRegistro?->format('d/m/Y H:i'),
            'Nombre' => $mensaje->Nombre ?? '',
            'Desarrolladores' => (bool) $mensaje->Desarrolladores,
            'NotificarAtadoJulio' => (bool) $mensaje->NotificarAtadoJulio,
            'CorteSEF' => (bool) $mensaje->CorteSEF,
            'MarcasFinales' => (bool) $mensaje->MarcasFinales,
            'ReporteElectrico' => (bool) $mensaje->ReporteElectrico,
            'ReporteMecanico' => (bool) $mensaje->ReporteMecanico,
            'ReporteTiempoMuerto' => (bool) $mensaje->ReporteTiempoMuerto,
            'Atadores' => (bool) $mensaje->Atadores,
            'InvTrama' => (bool) $mensaje->InvTrama,
        ];
    }
}
