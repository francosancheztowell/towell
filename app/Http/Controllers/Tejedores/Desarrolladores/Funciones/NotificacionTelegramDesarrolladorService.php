<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use App\Models\Sistema\SYSMensaje;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notificacion a Telegram de una captura de desarrollador.
 *
 * Habia dos copias de este servicio, una por pantalla, con el mismo mensaje y tres
 * cadenas distintas. La de muestras nunca recibio el escapado de Markdown ni el
 * timeout: corregir un lado y no el otro es exactamente lo que producia el fallo.
 * Lo que cambia entre pantallas se inyecta por constructor (ver AppServiceProvider).
 */
class NotificacionTelegramDesarrolladorService
{
    public function __construct(
        private readonly string $modulo = 'Desarrolladores',
        private readonly string $titulo = 'PROCESO DE DESARROLLADOR COMPLETADO',
        private readonly string $estado = 'Registro actualizado y puesto en proceso',
    ) {}

    public function enviarProcesoCompletado(
        array $validated,
        $programa,
        string $codigoDibujo
    ): void {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatIds = SYSMensaje::getChatIdsPorModulo($this->modulo);

            if (empty($botToken) || empty($chatIds)) {
                return;
            }

            $mensaje = $this->construirMensajeProcesoCompletado($validated, $programa, $codigoDibujo);

            foreach ($chatIds as $chatId) {
                Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $mensaje,
                    'parse_mode' => 'Markdown',
                ]);
            }
        } catch (Exception $e) {
            Log::error('Error al enviar notificacion de desarrollador a Telegram', [
                'modulo' => $this->modulo,
                'telar' => $validated['NoTelarId'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Telegram interpreta _ * ` [ con parse_mode Markdown: un nombre de desarrollador
     * con guion bajo devuelve HTTP 400 y la notificacion entera se pierde en el catch.
     */
    private function escaparMarkdown($valor): string
    {
        return str_replace(['_', '*', '`', '['], ['\_', '\*', '\`', '\['], (string) $valor);
    }

    private function construirMensajeProcesoCompletado(
        array $validated,
        $programa,
        string $codigoDibujo
    ): string {
        $telarActual = (string) ($validated['NoTelarId'] ?? '');
        $hayCambioTelar = ! empty($validated['CambioTelarActivo']);
        $telarOrigen = (string) ($validated['NoTelarOrigen'] ?? '');
        $telarDestino = (string) ($validated['NoTelarDestino'] ?? $telarActual);
        $salonOrigen = (string) ($validated['SalonOrigen'] ?? '');
        $salonDestino = (string) ($validated['SalonDestino'] ?? '');

        $mensaje = " *{$this->titulo}* \n\n";
        $mensaje .= " *Telar:* {$telarActual}\n";
        $mensaje .= " *Produccion:* {$this->escaparMarkdown($validated['NoProduccion'])}\n";
        // si hay cambio de telar, se muestra el origen y el destino
        if ($hayCambioTelar && $telarOrigen !== '' && $telarDestino !== '') {
            $origen = $telarOrigen.($salonOrigen !== '' ? " ({$salonOrigen})" : '');
            $destino = $telarDestino.($salonDestino !== '' ? " ({$salonDestino})" : '');
            $mensaje .= " *Cambio de Telar:* {$origen} -> {$destino}\n";
        }

        if (! empty($validated['Desarrollador'])) {
            $mensaje .= " *Desarrollador:* {$this->escaparMarkdown($validated['Desarrollador'])}\n";
        }

        $codigoAnterior = $validated['CodigoDibujoAnterior'] ?? null;
        if ($hayCambioTelar && $codigoAnterior && $codigoAnterior !== $codigoDibujo) {
            $mensaje .= " *Cambio de Codigo Dibujo:* {$this->escaparMarkdown($codigoAnterior)} -> {$this->escaparMarkdown($codigoDibujo)}\n";
        } else {
            $mensaje .= " *Codigo Dibujo:* {$this->escaparMarkdown($codigoDibujo)}\n";
        }

        $mensaje .= " *Total Pasadas:* {$validated['TotalPasadasDibujo']}\n";

        if (! empty($validated['NumeroJulioRizo'])) {
            $mensaje .= " *Julio Rizo:* {$this->escaparMarkdown($validated['NumeroJulioRizo'])}\n";
        }

        if (! empty($validated['NumeroJulioPie'])) {
            $mensaje .= " *Julio Pie:* {$this->escaparMarkdown($validated['NumeroJulioPie'])}\n";
        }

        if (! empty($validated['HoraInicio'])) {
            $mensaje .= " *Hora Inicio:* {$validated['HoraInicio']}\n";
        }

        if (! empty($validated['HoraFinal'])) {
            $mensaje .= " *Hora Final:* {$validated['HoraFinal']}\n";
        }

        if (isset($validated['EficienciaInicio']) && $validated['EficienciaInicio'] !== null) {
            $mensaje .= " *Eficiencia Inicio:* {$validated['EficienciaInicio']}%\n";
        }

        if (isset($validated['EficienciaFinal']) && $validated['EficienciaFinal'] !== null) {
            $mensaje .= " *Eficiencia Final:* {$validated['EficienciaFinal']}%\n";
        }

        if (! empty($programa->FechaInicio)) {
            $fechaInicio = Carbon::parse($programa->FechaInicio)->format('d/m/Y H:i');
            $mensaje .= " *Fecha Inicio Programada:* {$fechaInicio}\n";
        }

        if (! empty($programa->FechaFinal)) {
            $fechaFinal = Carbon::parse($programa->FechaFinal)->format('d/m/Y H:i');
            $mensaje .= " *Fecha Final Programada:* {$fechaFinal}\n";
        }

        $mensaje .= "\n *Estado:* {$this->estado}";
        $mensaje .= "\n *Fechas:* Actualizadas para el telar {$telarActual}";

        return $mensaje;
    }
}
