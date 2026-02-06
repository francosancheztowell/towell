<?php

namespace App\Http\Controllers\Tejedores\NotificarMontadoJulios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Sistema\SYSMensaje;
use Carbon\Carbon;

class NotificarMontadoJulioController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        // Obtener los telares asignados al usuario actual
        $telaresOperador = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
            ->pluck('NoTelarId')
            ->toArray();
        
        // Si es una petición AJAX
        if ($request->ajax() || $request->wantsJson()) {
            // Si se solicita solo el listado de telares
            if ($request->has('listado')) {
                $telaresDisponibles = TejInventarioTelares::whereIn('no_telar', $telaresOperador)
                    ->whereNotNull('no_julio')
                    ->whereNotNull('no_orden')
                    ->where('no_julio', '<>', '')
                    ->where('no_orden', '<>', '')
                    ->where(function ($query) {
                        $query->whereNull('horaParo')
                            ->orWhere('horaParo', '=', '');
                    })
                    ->distinct()
                    ->orderBy('no_telar')
                    ->pluck('no_telar')
                    ->values();

                return response()->json(['telares' => $telaresDisponibles]);
            }
            
            // Si se solicita detalle de un telar específico con tipo
            if ($request->has('no_telar') && $request->has('tipo')) {
                $detalles = TejInventarioTelares::where('no_telar', $request->no_telar)
                    ->where('tipo', $request->tipo)
                    ->whereIn('no_telar', $telaresOperador)
                    ->whereNotNull('no_julio')
                    ->whereNotNull('no_orden')
                    ->where('no_julio', '<>', '')
                    ->where('no_orden', '<>', '')
                    ->where(function ($query) {
                        $query->whereNull('horaParo')
                            ->orWhere('horaParo', '=', '');
                    })
                    ->select('id', 'no_telar', 'cuenta', 'calibre', 'tipo', 'tipo_atado', 'no_orden', 'no_julio', 'metros', 'horaParo')
                    ->orderByDesc('fecha')
                    ->orderByDesc('turno')
                    ->first();
                
                return response()->json(['detalles' => $detalles]);
            }
            
            return response()->json(['error' => 'Parámetros inválidos'], 400);
        }
        
        // Si no es AJAX, devolver vista (por compatibilidad)
        $telares = TejInventarioTelares::whereIn('no_telar', $telaresOperador)
            ->select('no_telar', 'tipo')
            ->distinct()
            ->orderBy('no_telar')
            ->get();
            
        return view('modulos.notificar-montado-julios.index', compact('telares'));
    }

    public function notificar(Request $request)
    {
        try {
            $registro = TejInventarioTelares::find($request->id);
            
            if (!$registro) {
                return response()->json(['error' => 'Registro no encontrado'], 404);
            }

            // Actualizar horaParo con la hora actual
            $horaActual = Carbon::now()->format('H:i:s');
            $registro->horaParo = $horaActual;
            $registro->save();

            try {
                $this->enviarNotificacionTelegram($registro, Auth::user());
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar notificacion de atado de julio a Telegram', [
                    'error' => $e->getMessage(),
                    'telar' => $registro->no_telar ?? null,
                    'orden' => $registro->no_orden ?? null,
                    'julio' => $registro->no_julio ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'horaParo' => $horaActual,
                'message' => 'Notificación registrada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Enviar notificacion de Atado de Julio a Telegram.
     * Destinatarios: registros de SYSMensajes con NotificarAtadoJulio=1 y Activo=1.
     */
    private function enviarNotificacionTelegram(TejInventarioTelares $registro, $usuario = null): void
    {
        $botToken = config('services.telegram.bot_token');
        if (empty($botToken)) {
            Log::warning('No se pudo enviar notificacion a Telegram: TELEGRAM_BOT_TOKEN no configurado');
            return;
        }

        $chatIds = SYSMensaje::getChatIdsPorModulo('NotificarAtadoJulio');
        if (empty($chatIds)) {
            Log::warning('No hay destinatarios con NotificarAtadoJulio activo en SYSMensajes');
            return;
        }

        $nombreUsuario = $usuario->nombre ?? $usuario->name ?? null;
        $numeroEmpleado = $usuario->numero_empleado ?? null;

        $mensaje = "*ATADO DE JULIO NOTIFICADO*\n\n";
        $mensaje .= "*Telar:* " . ($registro->no_telar ?? 'N/A') . "\n";
        $mensaje .= "*Tipo:* " . ($registro->tipo ?? 'N/A') . "\n";
        if (!empty($registro->tipo_atado)) {
            $mensaje .= "*Tipo Atado:* {$registro->tipo_atado}\n";
        }
        if (!empty($registro->cuenta)) {
            $mensaje .= "*Cuenta:* {$registro->cuenta}\n";
        }
        if (!empty($registro->calibre)) {
            $mensaje .= "*Calibre:* {$registro->calibre}\n";
        }
        if (!empty($registro->no_orden)) {
            $mensaje .= "*No. Orden:* {$registro->no_orden}\n";
        }
        if (!empty($registro->no_julio)) {
            $mensaje .= "*No. Julio:* {$registro->no_julio}\n";
        }
        if (!empty($registro->metros)) {
            $mensaje .= "*Metros:* {$registro->metros}\n";
        }
        if (!empty($registro->horaParo)) {
            $mensaje .= "*Hora Paro:* {$registro->horaParo}\n";
        }

        $mensaje .= "*Fecha:* " . Carbon::now()->format('d/m/Y') . "\n";

        if (!empty($nombreUsuario)) {
            $mensaje .= "*Operador:* {$nombreUsuario}";
            if (!empty($numeroEmpleado)) {
                $mensaje .= " ({$numeroEmpleado})";
            }
            $mensaje .= "\n";
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        foreach ($chatIds as $chatId) {
            $response = Http::timeout(20)->post($url, [
                'chat_id' => $chatId,
                'text' => $mensaje,
                'parse_mode' => 'Markdown'
            ]);

            if (!$response->successful()) {
                Log::error('Error al enviar notificacion de atado de julio a Telegram', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'telar' => $registro->no_telar ?? null,
                    'chat_id' => $chatId,
                ]);
            }
        }
    }
}
