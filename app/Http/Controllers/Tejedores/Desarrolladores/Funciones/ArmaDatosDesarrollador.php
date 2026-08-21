<?php

namespace App\Http\Controllers\Tejedores\Desarrolladores\Funciones;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;

/**
 * Calculos que la captura de desarrollador y la de muestras hacian por duplicado,
 * byte a byte, en dos archivos distintos. No tocan base de datos: solo normalizan
 * horas, codigos y longitudes.
 *
 * Tenerlos en un solo sitio es lo que evita que se repita lo que ya paso: el fork
 * de muestras se quedo sin la regla de longitud del codigo y sin la normalizacion
 * de eficiencias porque las correcciones se aplicaron una sola vez.
 */
trait ArmaDatosDesarrollador
{
    /**
     * Ancla una hora capturada al dia al que realmente pertenece.
     *
     * Con Carbon::today() la hora se pegaba siempre al dia del servidor: el turno 3
     * captura 23:50 y si envia pasada la medianoche el registro quedaba sellado al dia
     * siguiente. Se elige la ocurrencia de esa hora mas cercana a ahora, que para una
     * jornada de 8 horas es siempre la correcta.
     */
    private function anclarAlDiaMasCercano(?Carbon $momento): ?Carbon
    {
        if (! $momento) {
            return null;
        }

        $ahora = Carbon::now();

        if ($momento->greaterThan($ahora->copy()->addHours(12))) {
            return $momento->copy()->subDay();
        }

        if ($momento->lessThan($ahora->copy()->subHours(12))) {
            return $momento->copy()->addDay();
        }

        return $momento;
    }

    private function construirFechaInicioProgramada(?string $horaFinal): ?string
    {
        if (empty($horaFinal)) {
            return null;
        }

        try {
            $horaFinalCarbon = Carbon::createFromFormat('H:i', $horaFinal);

            $momento = Carbon::today()->setTimeFromTimeString($horaFinalCarbon->format('H:i'));

            return $this->anclarAlDiaMasCercano($momento)?->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }

    private function normalizarLongitudLucha($longitudLuchaRaw): ?int
    {
        return $longitudLuchaRaw !== null && $longitudLuchaRaw !== ''
            ? (int) round((float) $longitudLuchaRaw)
            : null;
    }

    private function normalizeCodigoDibujo(?string $value, ?string $telarId = null): string
    {
        $normalized = Str::of((string) ($value ?? ''))
            ->trim()
            ->upper()
            ->replaceMatches('/\s+/', '')
            ->replaceMatches('/\.(?:JC5|JCS)$/i', '')
            ->toString();

        if ($normalized === '') {
            return '';
        }

        $suffix = $this->resolverSufijoCodigoPorTelar($telarId);

        return $suffix === '' ? $normalized : ($normalized.'.'.$suffix);
    }

    private function resolverSufijoCodigoPorTelar(?string $telarId): string
    {
        $n = (int) trim((string) ($telarId ?? ''));
        if ($n >= 300) {
            return '';
        }

        return 'JC5';
    }

    private function calcularMinutosCambio(?string $horaInicio, ?string $horaFinal): ?int
    {
        if (! $horaInicio || ! $horaFinal) {
            return null;
        }
        try {
            $inicio = Carbon::createFromFormat('H:i', $horaInicio);
            $final = Carbon::createFromFormat('H:i', $horaFinal);
            if ($final->lt($inicio)) {
                $final->addDay();
            }

            return max(0, $inicio->diffInMinutes($final));
        } catch (Exception $e) {
            return null;
        }
    }
}
