<?php

declare(strict_types=1);

namespace App\Services\Mecanicos;

use App\Models\Sistema\SYSMensaje;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReporteEstadoMaquinaTelegramNotifier
{
    private const MODULO = 'ReporteMecanico';

    private const MAX_MB_DOCUMENTO = 50;

    private const MAX_MB_FOTO = 10;

    public function sendDocument(string $content, string $filename, string $caption): void
    {
        $this->enviar($content, $filename, $caption, 'document', self::MAX_MB_DOCUMENTO);
    }

    /** Manda el Excel/PDF a Telegram y lo devuelve como descarga. */
    public function sendDocumentYDescargar(string $content, string $filename, string $caption): Response
    {
        $this->sendDocument($content, $filename, $caption);

        return response($content, 200, [
            'Content-Type' => str_ends_with($filename, '.pdf')
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function sendPhoto(string $content, string $filename, string $caption): void
    {
        $this->enviar($content, $filename, $caption, 'photo', self::MAX_MB_FOTO);
    }

    /**
     * Captura subida desde la pantalla (html2canvas).
     *
     * @param  Closure(string): string  $nombre  extensión → nombre de archivo
     */
    public function sendUploadedPhoto(?UploadedFile $imagen, Closure $nombre, string $caption): JsonResponse
    {
        $content = $imagen !== null && $imagen->isValid() ? (string) file_get_contents($imagen->getRealPath()) : '';
        $this->sendPhoto($content, $nombre($imagen?->getClientOriginalExtension() ?: 'png'), $caption);

        return response()->json(['ok' => true]);
    }

    private function enviar(string $content, string $filename, string $caption, string $tipo, float $maxMb): void
    {
        try {
            $botToken = trim((string) config('services.telegram.bot_token'));
            if ($botToken === '') {
                Log::warning('Reporte estado máquina: TELEGRAM_BOT_TOKEN no configurado');

                return;
            }

            $chatIds = SYSMensaje::getChatIdsPorModulo(self::MODULO);
            if ($chatIds === []) {
                Log::warning('Reporte estado máquina: no hay destinatarios ReporteMecanico');

                return;
            }

            if ($content === '') {
                Log::warning('Reporte estado máquina: archivo vacío, no se envía a Telegram', [
                    'filename' => $filename,
                ]);

                return;
            }

            $sizeMb = strlen($content) / 1024 / 1024;
            if ($sizeMb > $maxMb) {
                Log::warning('Reporte estado máquina: archivo excede límite de Telegram', [
                    'filename' => $filename,
                    'size_mb' => round($sizeMb, 2),
                    'tipo' => $tipo,
                ]);

                return;
            }

            $campo = $tipo === 'photo' ? 'photo' : 'document';
            $url = "https://api.telegram.org/bot{$botToken}/send".($tipo === 'photo' ? 'Photo' : 'Document');

            foreach ($chatIds as $chatId) {
                $response = Http::timeout(30)
                    ->attach($campo, $content, $filename)
                    ->post($url, [
                        'chat_id' => $chatId,
                        'caption' => $caption,
                    ]);

                if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                    Log::error('Reporte estado máquina: Telegram rechazó el archivo', [
                        'chat_id' => $chatId,
                        'filename' => $filename,
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::error('Reporte estado máquina: no se pudo notificar por Telegram', [
                'filename' => $filename,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
