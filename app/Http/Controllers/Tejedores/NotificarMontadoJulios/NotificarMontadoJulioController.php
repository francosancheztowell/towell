<?php

namespace App\Http\Controllers\Tejedores\NotificarMontadoJulios;

use App\Http\Controllers\Controller;
use App\Jobs\Telegram\EnviarMensajeTelegram;
use App\Models\Sistema\SYSMensaje;
use App\Models\Tejedores\TejNotificaTejedorModel;
use App\Models\Tejedores\TelTelaresOperador;
use App\Models\Tejido\TejInventarioTelares;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificarMontadoJulioController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $asignaciones = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
            ->get(['NoTelarId', 'SalonTejidoId']);
        $telaresOperador = $asignaciones->pluck('NoTelarId')->all();

        if ($request->ajax() || $request->wantsJson()) {
            if (! $request->has('no_telar') || ! $request->has('tipo')) {
                return response()->json(['error' => 'Parámetros inválidos'], 400);
            }

            if (! $this->telarAsignado($request->no_telar, $telaresOperador)) {
                return response()->json(['detalles' => null]);
            }

            return response()->json([
                'detalles' => $this->detalleReservado((string) $request->no_telar, (string) $request->tipo),
            ]);
        }

        $telares = $asignaciones
            ->map(fn ($row) => [
                'id' => trim((string) $row->NoTelarId),
                'km' => TelarSalonResolver::esKarlMayer($row->SalonTejidoId, (string) $row->NoTelarId),
            ])
            ->unique('id')
            ->sortBy(fn (array $row) => (int) $row['id'])
            ->values();

        return view('modulos.notificar-montado-julios.index', compact('telares'));
    }

    public function notificar(Request $request)
    {
        try {
            $user = Auth::user();
            $telaresOperador = TelTelaresOperador::where('numero_empleado', $user->numero_empleado)
                ->pluck('NoTelarId')
                ->toArray();
            $horaActual = $this->horaParoValida($request->input('horaParo'))
                ?? Carbon::now()->format('H:i:s');
            $fecha = Carbon::now()->toDateString();

            $registro = $request->id
                ? TejInventarioTelares::where('id', $request->id)->where('Reservado', 1)->first()
                : null;

            if (! $registro || ! $this->telarAsignado($registro->no_telar, $telaresOperador)) {
                return response()->json(['error' => 'No hay un julio reservado en ese telar'], 422);
            }

            $esCompleto = trim((string) $registro->no_julio) !== ''
                && trim((string) $registro->no_orden) !== '';

            $registro->horaParo = $horaActual;
            $registro->save();

            $this->registrarNotificacionTejedor([
                'telar' => $registro->no_telar,
                'tipo' => $registro->tipo,
                'hora' => $horaActual,
                'NomEmpleado' => $user->nombre ?? $user->name ?? null,
                'NoEmpleado' => $user->numero_empleado ?? null,
                'Reserva' => $esCompleto ? 1 : 0,
                'no_julio' => $esCompleto ? $registro->no_julio : 0,
                'no_orden' => $esCompleto ? $registro->no_orden : 0,
                'Fecha' => $fecha,
            ], $esCompleto);

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

            return response()->json([
                'success' => true,
                'horaParo' => $horaActual,
                'message' => 'Notificación registrada correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Registra notificación en TejNotificaTejedor evitando duplicados:
     * - Busca el registro más reciente de hoy para ese telar y tipo.
     * - Si existe, lo actualiza protegiendo los datos de julio/orden ya asignados.
     */
    private function registrarNotificacionTejedor(array $payload, bool $esCompleto): void
    {
        $telar = trim((string) ($payload['telar'] ?? ''));
        $tipo = strtolower(trim((string) ($payload['tipo'] ?? '')));
        $fecha = $payload['Fecha'] ?? Carbon::now()->toDateString();

        $query = TejNotificaTejedorModel::query()
            ->whereRaw('LTRIM(RTRIM(telar)) = ?', [$telar])
            ->whereRaw('LOWER(LTRIM(RTRIM(tipo))) = ?', [$tipo])
            ->whereDate('Fecha', $fecha);

        if ($esCompleto) {
            $noJulio = trim((string) ($payload['no_julio'] ?? ''));
            $noOrden = trim((string) ($payload['no_orden'] ?? ''));

            $query->where(function ($q) use ($noJulio, $noOrden) {
                $q->whereRaw('LTRIM(RTRIM(no_julio)) = ?', [$noJulio])
                    ->whereRaw('LTRIM(RTRIM(no_orden)) = ?', [$noOrden])
                    ->orWhere(function ($sq) {
                        $sq->whereNull('no_julio')->orWhere('no_julio', '0')->orWhere('no_julio', '');
                    });
            });
        }

        $existente = $query->orderByDesc('id')->first();
        if ($existente) {
            // Actualización inteligente: No sobreescribir datos buenos con vacíos/0
            $updateData = [
                'hora' => $payload['hora'] ?? $existente->hora,
                'NomEmpleado' => $payload['NomEmpleado'] ?? $existente->NomEmpleado,
                'NoEmpleado' => $payload['NoEmpleado'] ?? $existente->NoEmpleado,
            ];

            // Solo actualizar Reserva si el nuevo es 1 o el actual es 0
            if (isset($payload['Reserva'])) {
                $updateData['Reserva'] = $payload['Reserva'] ?: $existente->Reserva;
            }

            // Solo actualizar julio/orden si el nuevo no es vacío/0
            if (! empty($payload['no_julio']) && $payload['no_julio'] !== '0') {
                $updateData['no_julio'] = $payload['no_julio'];
            }
            if (! empty($payload['no_orden']) && $payload['no_orden'] !== '0') {
                $updateData['no_orden'] = $payload['no_orden'];
            }

            $existente->update($updateData);

            return;
        }

        TejNotificaTejedorModel::create($payload);
    }

    /**
     * La fila que el tejedor puede notificar es la reservada de ese telar y tipo.
     * Una fila vieja sigue en status Activo despues de liberar; Reservado es lo que
     * dice si el julio sigue en el telar.
     *
     * @return array<string, mixed>|null
     */
    private function detalleReservado(string $noTelar, string $tipo): ?array
    {
        $registro = TejInventarioTelares::query()
            ->where('no_telar', trim($noTelar))
            ->whereRaw('LOWER(LTRIM(RTRIM(tipo))) = ?', [mb_strtolower(trim($tipo), 'UTF-8')])
            ->where('Reservado', 1)
            ->orderByDesc('fecha')
            ->orderByDesc('turno')
            ->orderByDesc('id')
            ->first();

        if (! $registro) {
            return null;
        }

        return [
            'id' => $registro->id,
            'no_telar' => $registro->no_telar,
            'cuenta' => $registro->cuenta ?? '',
            'calibre' => $registro->calibre ?? '',
            'tipo' => $registro->tipo,
            'tipo_atado' => $registro->tipo_atado ?? '',
            'no_orden' => $registro->no_orden ?? '',
            'no_julio' => $registro->no_julio ?? '',
            'metros' => $registro->metros ?? '',
        ];
    }

    /**
     * @param  array<int, mixed>  $telaresOperador
     */
    private function telarAsignado(mixed $noTelar, array $telaresOperador): bool
    {
        $pedido = trim((string) $noTelar);
        if ($pedido === '') {
            return false;
        }

        foreach ($telaresOperador as $asignado) {
            if (trim((string) $asignado) === $pedido) {
                return true;
            }
        }

        return false;
    }

    /**
     * La hora que el operador ve en pantalla. Si no llega o no es una hora, se usa la del servidor.
     */
    private function horaParoValida(mixed $valor): ?string
    {
        $hora = trim((string) $valor);
        if (! preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $hora, $m)) {
            return null;
        }

        $h = (int) $m[1];
        $i = (int) $m[2];
        $s = (int) $m[3];
        if ($h > 23 || $i > 59 || $s > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $h, $i, $s);
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
        $mensaje .= '*Telar:* '.($registro->no_telar ?? 'N/A')."\n";
        $mensaje .= '*Tipo:* '.($registro->tipo ?? 'N/A')."\n";
        if (! empty($registro->tipo_atado)) {
            $mensaje .= "*Tipo Atado:* {$registro->tipo_atado}\n";
        }
        if (! empty($registro->cuenta)) {
            $mensaje .= "*Cuenta:* {$registro->cuenta}\n";
        }
        if (! empty($registro->calibre)) {
            $mensaje .= "*Calibre:* {$registro->calibre}\n";
        }
        if (! empty($registro->no_orden)) {
            $mensaje .= "*No. Orden:* {$registro->no_orden}\n";
        }
        if (! empty($registro->no_julio)) {
            $mensaje .= "*No. Julio:* {$registro->no_julio}\n";
        }
        if (! empty($registro->metros)) {
            $mensaje .= "*Metros:* {$registro->metros}\n";
        }
        if (! empty($registro->horaParo)) {
            $mensaje .= "*Hora Paro:* {$registro->horaParo}\n";
        }

        $mensaje .= '*Fecha:* '.Carbon::now()->format('d/m/Y')."\n";

        if (! empty($nombreUsuario)) {
            $mensaje .= "*Operador:* {$nombreUsuario}";
            if (! empty($numeroEmpleado)) {
                $mensaje .= " ({$numeroEmpleado})";
            }
            $mensaje .= "\n";
        }

        // En la cola: el tejedor no espera a Telegram (PERF-13).
        EnviarMensajeTelegram::encolar(new EnviarMensajeTelegram(
            chatIds: $chatIds,
            texto: $mensaje,
            extra: ['parse_mode' => 'Markdown'],
            mensajeLog: 'Error al enviar notificacion de atado de julio a Telegram',
            contextoLog: ['telar' => $registro->no_telar ?? null],
            nivelLog: 'error',
        ));
    }
}
