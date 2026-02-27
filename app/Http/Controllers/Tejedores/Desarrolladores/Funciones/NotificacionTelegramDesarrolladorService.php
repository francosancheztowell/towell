<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para enviar notificaciones de desarrollador a Telegram.
 */
class NotificacionTelegramDesarrolladorService
{
    public function enviarProcesoCompletado(
        array $validated,
        ReqProgramaTejido $programa,
        string $codigoDibujo
    ): void {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.chat_id');

            if (empty($botToken) || empty($chatId)) {
                return;
            }

            $mensaje = $this->construirMensajeProcesoCompletado($validated, $programa, $codigoDibujo);

            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $mensaje,
                'parse_mode' => 'Markdown',
            ]);
        } catch (Exception $e) {
            Log::error('Error al enviar notificacion de desarrollador a Telegram', [
                'telar' => $validated['NoTelarId'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function construirMensajeProcesoCompletado(
        array $validated,
        ReqProgramaTejido $programa,
        string $codigoDibujo
    ): string {
        $telarActual = (string) ($validated['NoTelarId'] ?? '');
        $hayCambioTelar = !empty($validated['CambioTelarActivo']);
        $telarOrigen = (string) ($validated['NoTelarOrigen'] ?? '');
        $telarDestino = (string) ($validated['NoTelarDestino'] ?? $telarActual);
        $salonOrigen = (string) ($validated['SalonOrigen'] ?? '');
        $salonDestino = (string) ($validated['SalonDestino'] ?? '');

        $mensaje = " *PROCESO DE DESARROLLADOR COMPLETADO* \n\n";
        $mensaje .= " *Telar:* {$telarActual}\n";
        $mensaje .= " *Produccion:* {$validated['NoProduccion']}\n";
        // si hay cambio de telar, se muestra el origen y el destino
        if ($hayCambioTelar && $telarOrigen !== '' && $telarDestino !== '') {
            $origen = $telarOrigen . ($salonOrigen !== '' ? " ({$salonOrigen})" : '');
            $destino = $telarDestino . ($salonDestino !== '' ? " ({$salonDestino})" : '');
            $mensaje .= " *Cambio de Telar:* {$origen} -> {$destino}\n";
        }

        if (!empty($validated['Desarrollador'])) {
            $mensaje .= " *Desarrollador:* {$validated['Desarrollador']}\n";
        }

        $codigoAnterior = $validated['CodigoDibujoAnterior'] ?? null;
        if ($hayCambioTelar && $codigoAnterior && $codigoAnterior !== $codigoDibujo) {
            $mensaje .= " *Cambio de Codigo Dibujo:* {$codigoAnterior} -> {$codigoDibujo}\n";
        } else {
            $mensaje .= " *Codigo Dibujo:* {$codigoDibujo}\n";
        }
        
        $mensaje .= " *Total Pasadas:* {$validated['TotalPasadasDibujo']}\n";

        if (!empty($validated['NumeroJulioRizo'])) {
            $mensaje .= " *Julio Rizo:* {$validated['NumeroJulioRizo']}\n";
        }

        if (!empty($validated['NumeroJulioPie'])) {
            $mensaje .= " *Julio Pie:* {$validated['NumeroJulioPie']}\n";
        }

        if (!empty($validated['HoraInicio'])) {
            $mensaje .= " *Hora Inicio:* {$validated['HoraInicio']}\n";
        }

        if (!empty($validated['HoraFinal'])) {
            $mensaje .= " *Hora Final:* {$validated['HoraFinal']}\n";
        }

        if (isset($validated['EficienciaInicio']) && $validated['EficienciaInicio'] !== null) {
            $mensaje .= " *Eficiencia Inicio:* {$validated['EficienciaInicio']}%\n";
        }

        if (isset($validated['EficienciaFinal']) && $validated['EficienciaFinal'] !== null) {
            $mensaje .= " *Eficiencia Final:* {$validated['EficienciaFinal']}%\n";
        }

        if (!empty($programa->FechaInicio)) {
            $fechaInicio = Carbon::parse($programa->FechaInicio)->format('d/m/Y H:i');
            $mensaje .= " *Fecha Inicio Programada:* {$fechaInicio}\n";
        }

        if (!empty($programa->FechaFinal)) {
            $fechaFinal = Carbon::parse($programa->FechaFinal)->format('d/m/Y H:i');
            $mensaje .= " *Fecha Final Programada:* {$fechaFinal}\n";
        }

        $mensaje .= "\n *Estado:* Registro actualizado y puesto en proceso";
        $mensaje .= "\n *Fechas:* Actualizadas para el telar {$telarActual}";

        return $mensaje;
    }
}

