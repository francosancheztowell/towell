<?php

declare(strict_types=1);

namespace App\Services\Mantenimiento;

use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Sistema\SYSMensaje;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notificaciones de Telegram para ManFallasParos: alta y cierre.
 *
 * Sin parse_mode a propósito. El mensaje interpola texto capturado por el
 * operador (observaciones, nombres) y un solo '*' o '[' haría que Telegram
 * rechace el envío con 400 y la notificación se perdiera en silencio.
 */
final class ParoTelegramNotifier
{
    public function notifyCreated(ManFallasParos $stop): void
    {
        $this->dispatch($stop, $this->buildCreatedMessage($stop), 'alta');
    }

    public function notifyClosed(ManFallasParos $stop, ?string $closedBy = null): void
    {
        $this->dispatch($stop, $this->buildClosedMessage($stop, $closedBy), 'cierre');
    }

    public function buildCreatedMessage(ManFallasParos $stop): string
    {
        $lines = [
            '🚨 NOTIFICACIÓN DE FALLA/PARO 🚨',
            '',
            'Folio: '.($stop->Folio ?: 'N/D'),
            'Reportado por: '.($stop->NomEmpl ?: 'N/D'),
            'Fecha: '.$this->formatDate($stop->Fecha),
            'Hora: '.($stop->Hora ?: 'N/D'),
            'Departamento: '.($stop->Depto ?: 'N/D'),
            'Máquina: '.($stop->MaquinaId ?: 'N/D'),
            'Tipo de falla: '.($stop->TipoFallaId ?: 'N/D'),
            'Falla: '.($stop->Falla ?: 'N/D'),
        ];

        $lines = $this->appendIfPresent($lines, 'Descripción', $stop->Descripcion);
        $lines = $this->appendIfPresent($lines, 'Orden', $stop->OrdenTrabajo);
        $lines = $this->appendIfPresent($lines, 'Observaciones', $stop->Obs);

        $lines[] = 'Estatus: '.($stop->Estatus ?: 'Activo');
        $lines[] = 'Turno: '.($stop->Turno ?: 'N/D');

        return implode("\n", $lines);
    }

    public function buildClosedMessage(ManFallasParos $stop, ?string $closedBy = null): string
    {
        $lines = [
            '✅ PARO FINALIZADO',
            '',
            'Folio: '.($stop->Folio ?: 'N/D'),
            'Departamento: '.($stop->Depto ?: 'N/D'),
            'Máquina: '.($stop->MaquinaId ?: 'N/D'),
            'Tipo de falla: '.($stop->TipoFallaId ?: 'N/D'),
            'Falla: '.($stop->Falla ?: 'N/D'),
            'Fecha cierre: '.$this->formatDate($stop->FechaFin ?? $stop->Fecha),
            'Hora cierre: '.($stop->HoraFin ?: 'N/D'),
        ];

        $lines = $this->appendIfPresent($lines, 'Atendió', $stop->NomAtendio);
        $lines = $this->appendIfPresent($lines, 'Turno atención', $stop->TurnoAtendio);
        $lines = $this->appendIfPresent($lines, 'Calidad', $stop->Calidad ? $stop->Calidad.'/5' : null);
        $lines = $this->appendIfPresent($lines, 'Observaciones cierre', $stop->ObsCierre);
        $lines = $this->appendIfPresent($lines, 'Cerrado por', $closedBy);

        return implode("\n", $lines);
    }

    /**
     * Envía a los destinatarios del módulo que corresponda al tipo de falla.
     * Nunca lanza: el paro ya está guardado y una falla externa no debe revertirlo.
     */
    private function dispatch(ManFallasParos $stop, string $message, string $evento): void
    {
        try {
            $botToken = trim((string) config('services.telegram.bot_token'));
            if ($botToken === '') {
                Log::warning('No se notificó el paro: Telegram no está configurado.', [
                    'paro_id' => $stop->Id,
                    'evento' => $evento,
                ]);

                return;
            }

            $module = $this->moduleForFailureType((string) $stop->TipoFallaId);
            if ($module === null) {
                Log::info('El paro no tiene un canal Telegram asociado.', [
                    'paro_id' => $stop->Id,
                    'evento' => $evento,
                    'tipo_falla' => $stop->TipoFallaId,
                ]);

                return;
            }

            $chatIds = SYSMensaje::getChatIdsPorModulo($module);
            if ($chatIds === []) {
                Log::warning('No hay destinatarios Telegram activos para el paro.', [
                    'paro_id' => $stop->Id,
                    'evento' => $evento,
                    'modulo' => $module,
                ]);

                return;
            }

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            foreach ($chatIds as $chatId) {
                $response = Http::timeout(5)->post($url, [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]);

                if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                    Log::warning('Telegram rechazó una notificación de paro.', [
                        'paro_id' => $stop->Id,
                        'evento' => $evento,
                        'chat_id' => $chatId,
                        'status' => $response->status(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::error('No fue posible notificar el paro por Telegram.', [
                'paro_id' => $stop->Id,
                'folio' => $stop->Folio,
                'evento' => $evento,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function appendIfPresent(array $lines, string $label, mixed $value): array
    {
        $text = trim((string) ($value ?? ''));
        if ($text !== '') {
            $lines[] = $label.': '.$text;
        }

        return $lines;
    }

    private function formatDate(mixed $date): string
    {
        return $date !== null ? Carbon::parse($date)->format('d/m/Y') : 'Sin fecha';
    }

    private function moduleForFailureType(string $failureTypeId): ?string
    {
        $normalized = mb_strtoupper(trim($failureTypeId));
        $normalized = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú'],
            ['A', 'E', 'I', 'O', 'U'],
            $normalized,
        );

        return match (true) {
            str_contains($normalized, 'ELECTRIC') => 'ReporteElectrico',
            str_contains($normalized, 'MECANIC') => 'ReporteMecanico',
            str_contains($normalized, 'TIEMPO') && str_contains($normalized, 'MUERTO') => 'ReporteTiempoMuerto',
            str_contains($normalized, 'CALIDAD') => 'Calidad',
            default => null,
        };
    }
}
