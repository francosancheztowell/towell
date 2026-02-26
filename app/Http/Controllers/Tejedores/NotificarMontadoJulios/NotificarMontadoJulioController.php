<?php

namespace App\Http\Controllers\Tejedores\NotificarMontadoJulios;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Tejedores\TejNotificaTejedorModel;
use App\Models\Tejido\TejInventarioTelares;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Models\Sistema\SYSMensaje;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

use function Symfony\Component\Clock\now;

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
            // Si se solicita solo el listado de telares: retornar TODOS los asignados al usuario
            if ($request->has('listado')) {
                $telares = collect($telaresOperador)->sort()->values();
                return response()->json(['telares' => $telares]);
            }

            // Si se solicita detalle de un telar específico con tipo
            if ($request->has('no_telar') && $request->has('tipo')) {
                // 1) Buscar registro completo (con no_julio y no_orden, sin notificar)
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

                if ($detalles) {
                    $result = $detalles->toArray();
                    $result['registroCompleto'] = true;
                    return response()->json(['detalles' => $result]);
                }

                // 2) Buscar registro parcial (existe en tej_inventario_telares pero sin no_julio/no_orden)
                $parcial = TejInventarioTelares::where('no_telar', $request->no_telar)
                    ->where('tipo', $request->tipo)
                    ->whereIn('no_telar', $telaresOperador)
                    ->where(function ($query) {
                        $query->whereNull('horaParo')
                            ->orWhere('horaParo', '=', '');
                    })
                    ->select('id', 'no_telar', 'tipo', 'tipo_atado', 'metros')
                    ->orderByDesc('fecha')
                    ->orderByDesc('turno')
                    ->first();

                // Buscar cuenta/calibre en reqProgramaTejido cuando no hay registro completo
                [$cuentaReq, $calibreReq] = $this->getCuentaCalibreDePrograma(
                    $request->no_telar,
                    $request->tipo
                );

                if ($parcial) {
                    return response()->json([
                        'detalles' => [
                            'id'               => $parcial->id,
                            'no_telar'         => $parcial->no_telar,
                            'tipo'             => $parcial->tipo,
                            'tipo_atado'       => $parcial->tipo_atado ?? '',
                            'metros'           => $parcial->metros ?? '',
                            'cuenta'           => $cuentaReq,
                            'calibre'          => $calibreReq,
                            'no_orden'         => '',
                            'no_julio'         => '',
                            'registroCompleto' => false,
                        ]
                    ]);
                }

                // 3) Sin registro en tej_inventario_telares, pero telar asignado
                if (in_array($request->no_telar, $telaresOperador)) {
                    return response()->json([
                        'detalles' => [
                            'id'               => null,
                            'no_telar'         => $request->no_telar,
                            'tipo'             => $request->tipo,
                            'tipo_atado'       => '',
                            'metros'           => '',
                            'cuenta'           => $cuentaReq,
                            'calibre'          => $calibreReq,
                            'no_orden'         => '',
                            'no_julio'         => '',
                            'registroCompleto' => false,
                        ]
                    ]);
                }

                return response()->json(['detalles' => null]);
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
            $user        = Auth::user();
            $horaActual  = Carbon::now()->format('H:i:s');
            $noTelar     = $request->no_telar;
            $tipo        = $request->tipo;
            $fecha       = Carbon::now()->toDateString();

            // Buscar registro en tej_inventario_telares si viene id
            $registro = $request->id ? TejInventarioTelares::find($request->id) : null;

            // Registro completo: tiene no_julio y no_orden
            $esCompleto = $registro
                && !empty($registro->no_julio)
                && !empty($registro->no_orden);

            if ($esCompleto) {
                // Flujo normal: actualizar horaParo y notificar por Telegram
                $registro->horaParo = $horaActual;
                $registro->save();

                try {
                    $this->enviarNotificacionTelegram($registro, $user);
                } catch (\Throwable $e) {
                    Log::warning('No se pudo enviar notificacion de atado de julio a Telegram', [
                        'error' => $e->getMessage(),
                        'telar' => $registro->no_telar ?? null,
                        'orden' => $registro->no_orden ?? null,
                        'julio' => $registro->no_julio ?? null,
                    ]);
                }
            } else {
                // Registro incompleto o sin registro: insertar en TejNotificaTejedor
                TejNotificaTejedorModel::create([
                    'telar'       => $noTelar,
                    'tipo'        => $tipo,
                    'hora'        => $horaActual,
                    'NomEmpleado' => $user->nombre ?? $user->name ?? null,
                    'NoEmpleado'  => $user->numero_empleado ?? null,
                    'Reserva'     => 0,
                    'no_julio'    => 0,
                    'no_orden'    => 0,
                    'fecha'       => $fecha,
                ]);

                // Enviar Telegram con los datos disponibles
                try {
                    $datosNotificacion = [
                        'no_telar'   => $noTelar,
                        'tipo'       => $tipo,
                        'tipo_atado' => $registro->tipo_atado ?? null,
                        'cuenta'     => null,
                        'calibre'    => null,
                        'no_orden'   => null,
                        'no_julio'   => null,
                        'metros'     => $registro->metros ?? null,
                        'horaParo'   => $horaActual,
                    ];
                    $this->enviarNotificacionTelegram((object) $datosNotificacion, $user);
                } catch (\Throwable $e) {
                    Log::warning('No se pudo enviar notificacion de atado de julio (sin orden) a Telegram', [
                        'error' => $e->getMessage(),
                        'telar' => $noTelar,
                    ]);
                }
            }

            return response()->json([
                'success'  => true,
                'horaParo' => $horaActual,
                'message'  => 'Notificación registrada correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener cuenta y calibre desde ReqProgramaTejido según el tipo (rizo/pie).
     * Retorna [cuenta, calibre] o ['', ''] si no se encuentra registro.
     */
    private function getCuentaCalibreDePrograma(string $noTelar, string $tipo): array
    {
        $programa = ReqProgramaTejido::where('NoTelarId', $noTelar)
            ->where('EnProceso', 1)
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->select('CuentaRizo', 'CalibreRizo', 'CuentaPie', 'CalibrePie')
            ->first();

        if (!$programa) {
            return ['', ''];
        }

        $tipoLower = strtolower(trim($tipo));

        if ($tipoLower === 'rizo') {
            return [
                $programa->CuentaRizo ?? '',
                $programa->CalibreRizo ?? '',
            ];
        }

        if ($tipoLower === 'pie') {
            return [
                $programa->CuentaPie ?? '',
                $programa->CalibrePie ?? '',
            ];
        }

        return ['', ''];
    }

    /**
     * Enviar notificacion de Atado de Julio a Telegram.
     * Destinatarios: registros de SYSMensajes con NotificarAtadoJulio=1 y Activo=1.
     */
    private function enviarNotificacionTelegram($registro, $usuario = null): void
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
