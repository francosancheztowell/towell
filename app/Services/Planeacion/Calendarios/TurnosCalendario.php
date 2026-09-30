<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Calendarios;

use App\Models\Planeacion\ReqCalendarioLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Traducción entre la plantilla semanal de turnos del modal de calendario
 * ({turno: {dia: {horas, inicio, activo}}}) y las líneas de ReqCalendarioLine. Sin base de datos.
 */
final class TurnosCalendario
{
    public const DIAS = [0 => 'domingo', 1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sabado'];

    public const TURNOS = [1, 2, 3];

    /**
     * Líneas a insertar para cada día del rango según la plantilla.
     *
     * @param  array<int|string, mixed>  $turnos
     * @return list<array{CalendarioId: string, FechaInicio: string, FechaFin: string, HorasTurno: float, Turno: int}>
     *
     * @throws \InvalidArgumentException si el rango está invertido o un día pasa de 24 h
     */
    public function lineas(string $calendarioId, string $fechaInicial, string $fechaFinal, array $turnos): array
    {
        $inicio = Carbon::parse($fechaInicial)->startOfDay();
        $fin = Carbon::parse($fechaFinal)->startOfDay();
        if ($fin->lt($inicio)) {
            throw new \InvalidArgumentException('Fecha final menor a fecha inicial');
        }
        $plantilla = $this->plantilla($turnos);
        $this->validarHorasPorDia($plantilla);

        $lineas = [];
        for ($fecha = $inicio->copy(); $fecha->lte($fin); $fecha->addDay()) {
            foreach ($plantilla[self::DIAS[$fecha->dayOfWeek]] ?? [] as $turno => [$horas, $horaInicio]) {
                $inicioDt = Carbon::parse($fecha->format('Y-m-d').' '.$horaInicio);
                $lineas[] = [
                    'CalendarioId' => $calendarioId,
                    'FechaInicio' => $inicioDt->format('Y-m-d H:i:s'),
                    'FechaFin' => $inicioDt->copy()->addSeconds((int) round($horas * 3600))->format('Y-m-d H:i:s'),
                    'HorasTurno' => $horas,
                    'Turno' => (int) $turno,
                ];
            }
        }

        return $lineas;
    }

    /**
     * Resumen de las líneas para precargar el modal: la primera línea de cada turno/día (las
     * líneas vienen de la más reciente a la más antigua) y el rango de días.
     *
     * @param  Collection<int, ReqCalendarioLine>  $lineas
     * @return array{fechaInicial: string, fechaFinal: string, turnos: array<int, array<string, array{horas: float|int, inicio: string, fin: string, activo: bool}>>}
     */
    public function resumen(Collection $lineas): array
    {
        $turnos = [];
        foreach (self::TURNOS as $turno) {
            foreach (self::DIAS as $dia) {
                $turnos[$turno][$dia] = ['horas' => 0, 'inicio' => '', 'fin' => '', 'activo' => false];
            }
        }

        $visto = [];
        $dias = [];
        foreach ($lineas as $linea) {
            $inicio = Carbon::parse($linea->FechaInicio);
            $turno = (int) $linea->Turno;
            if (! in_array($turno, self::TURNOS, true)) {
                continue;
            }
            $dia = self::DIAS[$inicio->dayOfWeek];
            if (! isset($visto[$turno.'|'.$dia])) {
                $turnos[$turno][$dia] = [
                    'horas' => (float) $linea->HorasTurno,
                    'inicio' => $inicio->format('H:i:s'),
                    'fin' => Carbon::parse($linea->FechaFin)->format('H:i:s'),
                    'activo' => true,
                ];
                $visto[$turno.'|'.$dia] = true;
            }
            $dias[] = $inicio->format('Y-m-d');
        }

        // Sin líneas, el rango es hoy.
        $hoy = Carbon::today()->format('Y-m-d');

        return [
            'fechaInicial' => $dias === [] ? $hoy : min($dias),
            'fechaFinal' => $dias === [] ? $hoy : max($dias),
            'turnos' => $turnos,
        ];
    }

    /**
     * Turnos activos con horas y hora de inicio, por día: [dia => [turno => [horas, 'H:i:s']]].
     *
     * @param  array<int|string, mixed>  $turnos
     * @return array<string, array<int|string, array{0: float, 1: string}>>
     */
    private function plantilla(array $turnos): array
    {
        $plantilla = [];
        foreach ($turnos as $turno => $dias) {
            foreach (is_array($dias) ? $dias : [] as $dia => $info) {
                if (! is_array($info) || ($info['activo'] ?? true) === false) {
                    continue;
                }
                $horas = (float) ($info['horas'] ?? 0);
                $plantilla[$dia][$turno] = [$horas, self::horaHms((string) ($info['inicio'] ?? ''))];
            }
        }

        return $plantilla;
    }

    /** @param  array<string, array<int|string, array{0: float, 1: string}>>  $plantilla */
    private function validarHorasPorDia(array &$plantilla): void
    {
        foreach ($plantilla as $dia => $turnos) {
            if (array_sum(array_column($turnos, 0)) > 24.01) {
                throw new \InvalidArgumentException("La suma de horas para {$dia} no puede ser mayor a 24");
            }
            // Para generar líneas solo cuentan los turnos con horas y hora de inicio.
            $plantilla[$dia] = array_filter($turnos, fn (array $t) => $t[0] > 0 && $t[1] !== '');
        }
    }

    public static function horaHms(string $hora): string
    {
        $hora = trim($hora);

        return preg_match('/^\d{1,2}:\d{2}$/', $hora) ? "{$hora}:00" : $hora;
    }
}
