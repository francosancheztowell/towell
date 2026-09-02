<?php

declare(strict_types=1);

namespace App\Jobs\Programas;

use App\Models\Sistema\SYSMensaje;
use App\Models\Urdido\UrdProgramaUrdido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendUrdidoQualityNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, mixed>  $quality
     */
    public function __construct(
        public readonly array $quality,
    ) {}

    public function handle(): void
    {
        $botToken = (string) config('services.telegram.bot_token', '');
        $chatIds = SYSMensaje::getChatIdsPorModulo('UrdidoCalidad');

        if ($botToken === '' || $chatIds === []) {
            return;
        }

        $message = $this->message();
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $fallidos = [];

        // Sin parse_mode: el comentario es texto libre del operador y un `*` o `_` suelto
        // hacía que Telegram devolviera 400 y el aviso se perdiera en silencio.
        // Timeout corto y sin reintentos: esto corre dentro del request del usuario
        // (mod_php no cierra la conexión antes de tiempo), así que el peor caso es
        // 3s por destinatario. Un aviso perdido se ve en el log, no vale hacer esperar.
        foreach ($chatIds as $chatId) {
            try {
                Http::timeout(3)
                    ->post($url, [
                        'chat_id' => $chatId,
                        'text' => $message,
                    ])
                    ->throw();
            } catch (Throwable $e) {
                // Un destinatario caído no debe dejar sin aviso a los demás.
                $fallidos[$chatId] = $e->getMessage();
            }
        }

        if ($fallidos !== []) {
            Log::warning('Evaluación de calidad de Urdido: destinatarios de Telegram sin entregar.', [
                'folio' => $this->quality['folio'] ?? null,
                'fallidos' => $fallidos,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('No fue posible enviar la evaluación de calidad de Urdido a Telegram.', [
            'folio' => $this->quality['folio'] ?? null,
            'error' => $exception->getMessage(),
        ]);
    }

    private function message(): string
    {
        $state = match ($this->quality['quality'] ?? '') {
            '1', 'A' => '✅ Aprobado',
            '0', 'R' => '❌ Rechazado',
            'O' => '⚠️ Con observaciones',
            default => 'Sin evaluar',
        };

        $message = "🏭 CALIDAD URDIDO\n\n";
        $message .= "📋 Folio: {$this->quality['folio']}\n";
        $message .= "📅 Fecha: {$this->quality['date']}\n";
        $message .= "👷 Realizó: {$this->quality['author']}\n";
        $message .= "🏭 Máquina: {$this->quality['machine']}\n";
        $message .= "🏭 Lote Prov: {$this->quality['supplier_lot']}\n";
        $message .= "⚙️ Fibra: {$this->quality['fiber']}\n";
        $message .= "📐 Cuenta: {$this->quality['size']}\n\n";
        $message .= "Status: {$state}";

        // `points` sólo lo manda el checklist; el tablero Livewire aún no lo envía.
        foreach ((array) ($this->quality['points'] ?? []) as $campo => $valor) {
            $etiqueta = UrdProgramaUrdido::CALIDAD_PUNTOS[$campo] ?? $campo;
            $marca = $valor === null ? '—' : ($valor ? '✔' : '✘');
            $message .= "\n{$marca} {$etiqueta}";
        }

        if (($this->quality['comment'] ?? '') !== '') {
            $message .= "\n💬 Obs: {$this->quality['comment']}";
        }

        return $message;
    }
}
