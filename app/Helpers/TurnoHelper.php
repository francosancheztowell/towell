<?php

namespace App\Helpers;

use Carbon\Carbon;

class TurnoHelper
{
    /**
     * Determina el turno basado en la hora actual de México
     *
     * @return string
     */
    public static function getTurnoActual(): string
    {
        // Obtener hora actual en zona horaria de México
        $horaMexico = Carbon::now('America/Mexico_City');
        $hora = $horaMexico->hour;
        $minuto = $horaMexico->minute;

        // Convertir a minutos desde medianoche para facilitar comparación
        $minutosActuales = ($hora * 60) + $minuto;

        // Definir rangos de turnos en minutos desde medianoche
        // Turno 1: 6:30 AM - 2:30 PM (390 - 870 minutos)
        // Turno 2: 2:30 PM - 10:30 PM (870 - 1350 minutos)
        // Turno 3: 10:30 PM - 6:30 AM (1350 - 390 minutos, cruza medianoche)
        // recibe tomalo del usuario actual, 
        if ($minutosActuales >= 390 && $minutosActuales < 870) {
            return '1'; // 6:30 AM - 2:30 PM
        } elseif ($minutosActuales >= 870 && $minutosActuales < 1350) {
            return '2'; // 2:30 PM - 10:30 PM
        } else {
            return '3'; // 10:30 PM - 6:30 AM
        }
    }

    /**
     * Obtiene la descripción del turno
     *
     * @param string $turno
     * @return string
     */
    public static function getDescripcionTurno(string $turno): string
    {
        switch ($turno) {
            case '1':
                return '6:30 AM - 2:30 PM';
            case '2':
                return '2:30 PM - 10:30 PM';
            case '3':
                return '10:30 PM - 6:30 AM';
            default:
                return 'Turno no válido';
        }
    }

    /**
     * Obtiene la descripción del turno en formato "Turno X"
     *
     * @param string $turno
     * @return string
     */
    public static function getTurnoFormato(string $turno): string
    {
        switch ($turno) {
            case '1':
                return 'Turno 1';
            case '2':
                return 'Turno 2';
            case '3':
                return 'Turno 3';
            default:
                return 'Turno no válido';
        }
    }

    /**
     * Devuelve la fecha de producción correcta considerando:
     *   - Turno 3 post-medianoche: entre 00:00 y 06:30 el turno empezó ayer → devuelve fecha de ayer.
     *   - Regla de cierre mensual: el día 1 antes de las 08:30 → devuelve el último día del mes anterior.
     *   - Resto de los casos → devuelve hoy.
     *
     * @return string Fecha en formato Y-m-d
     */
    public static function getFechaProduccion(): string
    {
        $ahora = Carbon::now('America/Mexico_City');
        $minutosActuales = ($ahora->hour * 60) + $ahora->minute;

        // Turno 3, porción post-medianoche: 00:00 – 06:30 → fecha de ayer
        if ($minutosActuales < 390) { // 390 min = 6:30 AM
            return $ahora->copy()->subDay()->toDateString();
        }

        // Regla de cierre mensual: día 1 antes de las 08:30 → último día del mes anterior
        $corte830 = $ahora->copy()->startOfDay()->addHours(8)->addMinutes(30);
        if ($ahora->day === 1 && $ahora->lt($corte830)) {
            return $ahora->copy()->startOfMonth()->subDay()->toDateString();
        }

        return $ahora->toDateString();
    }

    /**
     * Genera un folio único basado en fecha y turno
     *
     * @return string
     */
    public static function generarFolio(): string
    {
        $fecha = Carbon::now('America/Mexico_City');
        $turno = self::getTurnoActual();

        // Formato: TRAMA-YYYYMMDD-T
        return 'TRAMA-' . $fecha->format('Ymd') . '-' . $turno;
    }
}
