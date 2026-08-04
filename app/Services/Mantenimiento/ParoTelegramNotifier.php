<?php

declare(strict_types=1);

namespace App\Services\Mantenimiento;

use App\Models\Mantenimiento\ManFallasParos;
use App\Models\Sistema\SYSMensaje;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ParoTelegramNotifier
{
    public function notifyCreated(ManFallasParos $stop): void
    {
        try {
            $botToken = trim((string) config('services.telegram.bot_token'));
            if ($botToken === '') {
                Log::warning('No se notificó el paro de Crudo: Telegram no está configurado.', [
                    'paro_id' => $stop->Id,
                ]);

                return;
            }

            $module = $this->moduleForFailureType((string) $stop->TipoFallaId);
            if ($module === null) {
                Log::info('El paro de Crudo no tiene un canal Telegram asociado.', [
                    'paro_id' => $stop->Id,
                    'tipo_falla' => $stop->TipoFallaId,
                ]);

                return;
            }

            $chatIds = SYSMensaje::getChatIdsPorModulo($module);
            if ($chatIds === []) {
                Log::warning('No hay destinatarios Telegram activos para el paro de Crudo.', [
                    'paro_id' => $stop->Id,
                    'modulo' => $module,
                ]);

                return;
            }

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
            $message = $this->buildCreatedMessage($stop);

            foreach ($chatIds as $chatId) {
                $response = Http::timeout(5)->post($url, [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]);

                if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                    Log::warning('Telegram rechazó una notificación de paro de Crudo.', [
                        'paro_id' => $stop->Id,
                        'chat_id' => $chatId,
                        'status' => $response->status(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            // El paro ya quedó guardado; una falla externa no debe revertirlo.
            Log::error('No fue posible notificar el paro de Crudo por Telegram.', [
                'paro_id' => $stop->Id,
                'folio' => $stop->Folio,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function buildCreatedMessage(ManFallasParos $stop): string
    {
        $date = $stop->Fecha !== null
            ? Carbon::parse($stop->Fecha)->format('d/m/Y')
            : 'Sin fecha';

        $lines = [
            '🚨 NOTIFICACIÓN DE FALLA/PARO 🚨',
            '',
            'Folio: '.($stop->Folio ?: 'N/D'),
            'Reportado por: '.($stop->NomEmpl ?: 'N/D'),
            'Fecha: '.$date,
            'Hora: '.($stop->Hora ?: 'N/D'),
            'Departamento: '.($stop->Depto ?: 'N/D'),
            'Máquina: '.($stop->MaquinaId ?: 'N/D'),
            'Tipo de falla: '.($stop->TipoFallaId ?: 'N/D'),
            'Falla: '.($stop->Falla ?: 'N/D'),
        ];

        if (trim((string) $stop->Descripcion) !== '') {
            $lines[] = 'Descripción: '.trim((string) $stop->Descripcion);
        }

        if (trim((string) $stop->OrdenTrabajo) !== '') {
            $lines[] = 'Orden: '.trim((string) $stop->OrdenTrabajo);
        }

        if (trim((string) $stop->Obs) !== '') {
            $lines[] = 'Checklist: '.trim((string) $stop->Obs);
        }

        $lines[] = 'Estatus: '.($stop->Estatus ?: 'Activo');
        $lines[] = 'Turno: '.($stop->Turno ?: 'N/D');

        return implode("\n", $lines);
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
            str_contains($normalized, 'ELECTRIC') || $normalized === 'ELECTRICO' => 'ReporteElectrico',
            str_contains($normalized, 'MECANIC') || $normalized === 'MECANICO' => 'ReporteMecanico',
            (str_contains($normalized, 'TIEMPO') && str_contains($normalized, 'MUERTO'))
                || $normalized === 'TIEMPO MUERTO' => 'ReporteTiempoMuerto',
            str_contains($normalized, 'CALIDAD') => 'Calidad',
            default => null,
        };
    }
}
