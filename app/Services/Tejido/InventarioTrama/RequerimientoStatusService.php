<?php

namespace App\Services\Tejido\InventarioTrama;

use App\Jobs\Telegram\EnviarMensajeTelegram;
use App\Models\Sistema\SYSMensaje;
use App\Models\Tejido\TejTrama;
use Illuminate\Support\Facades\Log;

class RequerimientoStatusService
{
    /** Transiciones permitidas entre estatus de un requerimiento de trama. */
    private const TRANSICIONES = [
        'En Proceso' => ['Solicitado', 'Cancelado'],
        'Solicitado' => ['Surtido', 'Cancelado'],
        'Surtido' => [],
        'Cancelado' => [],
        'Creado' => ['En Proceso', 'Cancelado'],
    ];

    private const ESTATUS_VALIDOS = ['En Proceso', 'Solicitado', 'Surtido', 'Cancelado', 'Creado'];

    public static function estatusValidos(): array
    {
        return self::ESTATUS_VALIDOS;
    }

    /**
     * @return array{ok: bool, message: string, code: int}
     */
    public function cambiar(string $folio, string $nuevoStatus): array
    {
        if (! in_array($nuevoStatus, self::ESTATUS_VALIDOS, true)) {
            return ['ok' => false, 'message' => 'Estatus inválido', 'code' => 422];
        }

        $requerimiento = TejTrama::where('Folio', $folio)->first();
        if (! $requerimiento) {
            return ['ok' => false, 'message' => 'Requerimiento no encontrado', 'code' => 404];
        }

        $statusActual = (string) $requerimiento->Status;

        if (! in_array($nuevoStatus, self::TRANSICIONES[$statusActual] ?? [], true)) {
            Log::warning('UpdateStatus - Transición no permitida', [
                'folio' => $folio,
                'status_actual' => $statusActual,
                'nuevo_status' => $nuevoStatus,
            ]);

            return [
                'ok' => false,
                'message' => "No se puede cambiar de '{$statusActual}' a '{$nuevoStatus}'",
                'code' => 400,
            ];
        }

        $requerimiento->Status = $nuevoStatus;
        $requerimiento->save();

        if ($nuevoStatus === 'Solicitado') {
            $this->enviarTelegram($requerimiento);
        }

        return [
            'ok' => true,
            'message' => "Status cambiado de '{$statusActual}' a '{$nuevoStatus}' correctamente",
            'code' => 200,
        ];
    }

    /**
     * Enviar mensaje a Telegram al solicitar consumo.
     * Destinatarios: registros de SYSMensajes con InvTrama=1 y Activo=1.
     */
    public function enviarTelegram(TejTrama $req): void
    {
        try {
            $botToken = config('services.telegram.bot_token');
            if (empty($botToken)) {
                Log::warning('Telegram: TELEGRAM_BOT_TOKEN no configurado');

                return;
            }

            $chatIds = SYSMensaje::getChatIdsPorModulo('InvTrama');
            if (empty($chatIds)) {
                Log::warning('Telegram: no hay destinatarios con InvTrama activo en SYSMensajes');

                return;
            }

            $mensaje = "📦 *SOLICITAR CONSUMO TRAMA*\n";
            $mensaje .= "Folio: {$req->Folio}\n";
            $mensaje .= 'Fecha: '.now()->format('d/m/Y H:i')."\n";
            $mensaje .= 'Turno: '.($req->Turno ?? 'N/A')."\n";
            $mensaje .= 'Operador: '.($req->numero_empleado ?? 'N/A')."\n";

            // En la cola: quien solicita no espera a Telegram (PERF-13).
            EnviarMensajeTelegram::encolar(new EnviarMensajeTelegram(
                chatIds: $chatIds,
                texto: $mensaje,
                extra: ['parse_mode' => 'Markdown'],
                mensajeLog: 'Telegram: fallo al enviar',
                contextoLog: ['folio' => $req->Folio],
                nivelLog: 'error',
            ));
        } catch (\Throwable $e) {
            Log::error('Telegram: excepción al enviar', ['folio' => $req->Folio, 'error' => $e->getMessage()]);
        }
    }
}
