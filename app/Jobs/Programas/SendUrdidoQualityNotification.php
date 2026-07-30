<?php

declare(strict_types=1);

namespace App\Jobs\Programas;

use App\Models\Sistema\SYSMensaje;
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
     * @param  array<string, string|null>  $quality
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

        foreach ($chatIds as $chatId) {
            Http::timeout(10)
                ->retry(2, 250)
                ->post($url, [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                ])
                ->throw();
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
            'A' => '✅ Aprobado',
            'R' => '❌ Rechazado',
            'O' => '⚠️ Con observaciones',
            default => 'Sin evaluar',
        };

        $message = "🏭 *CALIDAD URDIDO*\n\n";
        $message .= "📋 Folio: {$this->quality['folio']}\n";
        $message .= "📅 Fecha: {$this->quality['date']}\n";
        $message .= "👷 Realizó: {$this->quality['author']}\n";
        $message .= "🏭 Máquina: {$this->quality['machine']}\n";
        $message .= "🏭 Lote Prov: {$this->quality['supplier_lot']}\n";
        $message .= "⚙️ Fibra: {$this->quality['fiber']}\n";
        $message .= "📐 Cuenta: {$this->quality['size']}\n\n";
        $message .= "Status: {$state}";

        if (($this->quality['comment'] ?? '') !== '') {
            $message .= "\n💬 Obs: {$this->quality['comment']}";
        }

        return $message;
    }
}
