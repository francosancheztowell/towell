<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Sistema\SysDepartamento;
use App\Models\Sistema\SYSMensaje;
use App\Models\Sistema\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class MensajesController extends Controller
{
    /** Casillas de SYSMensaje: si no llegan en el request se guardan en false. */
    private const BANDERAS = [
        'Activo', 'Desarrolladores', 'DesarrolladoresPrue', 'NotificarAtadoJulio', 'CorteSEF', 'MarcasFinales', 'ReporteElectrico', 'ReporteMecanico', 'ReporteTiempoMuerto', 'Atadores', 'InvTrama', 'UrdidoCalidad', 'Calidad', 'Andon',
    ];

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
        $mensajes = SYSMensaje::with(['departamento', 'usuario'])
            ->orderBy('Id')
            ->get();

        $departamentos = SysDepartamento::orderBy('id')->get(['id', 'Depto', 'Descripcion']);

        // Solo usuarios con correo: los demas no sirven como destinatarios.
        $usuarios = Usuario::query()
            ->whereNotNull('correo')
            ->where('correo', '<>', '')
            ->orderBy('nombre')
            ->get(['idusuario', 'nombre', 'correo']);

        return view('modulos.configuracion.mensajes', [
            'mensajes' => $mensajes,
            'departamentos' => $departamentos,
            'usuarios' => $usuarios,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $this->validar($request);

        $mensaje = SYSMensaje::create($validated);

        if ($request->expectsJson()) {
            $mensaje->load(['departamento', 'usuario']);

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

        $validated = $this->validar($request);

        $mensaje->update($validated);

        if ($request->expectsJson()) {
            $mensaje->load(['departamento', 'usuario']);

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

        $mensaje->load(['departamento', 'usuario']);

        return response()->json([
            'ok' => true,
            'message' => 'Chat ID (Token) actualizado correctamente.',
            'item' => $this->itemToArray($mensaje),
        ]);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request): array
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
            'UsuarioId' => ['nullable', 'integer', 'exists:sqlsrv.dbo.SYSUsuario,idusuario'],
            'Telefono' => ['required', 'string', 'max:20'],
            'Token' => ['required', 'string', 'max:255'],
            'Nombre' => ['nullable', 'string', 'max:150'],
        ] + array_fill_keys(self::BANDERAS, ['nullable', 'boolean']));

        foreach (self::BANDERAS as $bandera) {
            $validated[$bandera] = $request->boolean($bandera);
        }
        $validated['Nombre'] = $request->input('Nombre');
        $validated['UsuarioId'] = $request->filled('UsuarioId') ? (int) $request->input('UsuarioId') : null;

        return $validated;
    }

    private function itemToArray(SYSMensaje $mensaje): array
    {
        $depto = $mensaje->departamento;

        return [
            'Id' => $mensaje->Id,
            'DepartamentoId' => $mensaje->DepartamentoId,
            'UsuarioId' => $mensaje->UsuarioId,
            'UsuarioNombre' => $mensaje->usuario?->nombre ?? '',
            'Correo' => $mensaje->usuario?->correo ?? '',
            'DepartamentoNombre' => $depto ? ($depto->Depto ?? $depto->Descripcion ?? (string) $mensaje->DepartamentoId) : (string) $mensaje->DepartamentoId,
            'Telefono' => $mensaje->Telefono,
            'Token' => $mensaje->Token,
            'Activo' => (bool) $mensaje->Activo,
            'FechaRegistro' => $mensaje->FechaRegistro?->format('d/m/Y H:i'),
            'Nombre' => $mensaje->Nombre ?? '',
            'Desarrolladores' => (bool) $mensaje->Desarrolladores,
            'DesarrolladoresPrue' => (bool) $mensaje->DesarrolladoresPrue,
            'NotificarAtadoJulio' => (bool) $mensaje->NotificarAtadoJulio,
            'CorteSEF' => (bool) $mensaje->CorteSEF,
            'MarcasFinales' => (bool) $mensaje->MarcasFinales,
            'ReporteElectrico' => (bool) $mensaje->ReporteElectrico,
            'ReporteMecanico' => (bool) $mensaje->ReporteMecanico,
            'ReporteTiempoMuerto' => (bool) $mensaje->ReporteTiempoMuerto,
            'Atadores' => (bool) $mensaje->Atadores,
            'InvTrama' => (bool) $mensaje->InvTrama,
            'UrdidoCalidad' => (bool) $mensaje->UrdidoCalidad,
            'Calidad' => (bool) $mensaje->Calidad,
            'Andon' => (bool) $mensaje->Andon,
        ];
    }
}
