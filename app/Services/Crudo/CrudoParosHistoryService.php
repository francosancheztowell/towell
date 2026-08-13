<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Historial de paros de un telar para el modal del andón: activos y terminados
 * del día productivo consultado y del anterior.
 *
 * ManFallasParos no guarda tiempo muerto ni un datetime único: el inicio vive en
 * Fecha (date) + Hora (texto H:i:s) y el cierre en FechaFin + HoraFin. Por eso el
 * filtro grueso se hace por Fecha en SQL y el recorte de la frontera 06:30 y la
 * duración se calculan aquí, sobre las pocas filas que devuelve un telar en dos días.
 */
final class CrudoParosHistoryService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forMachine(string $telar, DateTimeImmutable $from, DateTimeImmutable $to, int $dias = 2): array
    {
        $telar = trim($telar);
        if ($telar === '') {
            return [];
        }

        $dias = max(1, $dias);
        $minutos = (int) config('crudo.production_day_start_minutes', 390);
        // Ventana de días productivos que termina en $to: de las 06:30 del día
        // ($dias - 1) previo a $from hasta las 06:30 del día siguiente a $to.
        $inicio = CarbonImmutable::instance($from)->startOfDay()->subDays($dias - 1)->addMinutes($minutos);
        $fin = CarbonImmutable::instance($to)->startOfDay()->addDay()->addMinutes($minutos);

        // Misma conexión y tabla configurables que usa SqlServerCrudoReadRepository,
        // para poder apuntarlas a SQLite en pruebas.
        $filas = DB::connection((string) config('crudo.connections.catalog', 'sqlsrv'))
            ->table((string) config('crudo.tables.paros', 'dbo.ManFallasParos'))
            ->where('MaquinaId', $telar)
            ->whereBetween('Fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->orderByDesc('Fecha')
            ->orderByDesc('Hora')
            // ponytail: tope duro. La lista tiene scroll y un mes de paros de un
            // solo telar rara vez pasa de unas decenas; si hiciera falta ver más,
            // el reporte de mantenimiento es el lugar, no el andón.
            ->limit(max(1, (int) config('crudo.paros_history_limit', 50)))
            ->get([
                'Folio',
                'Estatus',
                'Fecha',
                'Hora',
                'FechaFin',
                'HoraFin',
                'Depto',
                'TipoFallaId',
                'Falla',
                'Descripcion',
                'NomEmpl',
                'Turno',
                'NomAtendio',
                'TurnoAtendio',
                'Obs',
                'ObsCierre',
                'OrdenTrabajo',
            ]);

        $paros = [];

        foreach ($filas as $fila) {
            $comienzo = $this->momento($fila->Fecha, $fila->Hora);
            if ($comienzo === null || $comienzo < $inicio || $comienzo >= $fin) {
                continue;
            }

            $cierre = $this->momento($fila->FechaFin, $fila->HoraFin);
            $activo = ! $this->estaTerminado((string) ($fila->Estatus ?? ''));

            $paros[] = [
                'folio' => trim((string) ($fila->Folio ?? '')),
                'activo' => $activo,
                'estatus' => $activo ? 'Activo' : 'Terminado',
                'inicio' => $comienzo->format('d/m H:i'),
                'fin' => $cierre?->format('d/m H:i') ?? '',
                'duracion' => $this->duracion($comienzo, $activo ? CarbonImmutable::now($comienzo->timezone) : $cierre),
                'falla' => trim((string) ($fila->Descripcion ?? '')) ?: trim((string) ($fila->Falla ?? '')),
                'tipo' => trim((string) ($fila->TipoFallaId ?? '')),
                'depto' => trim((string) ($fila->Depto ?? '')),
                'reporto' => trim((string) ($fila->NomEmpl ?? '')),
                'turno' => $fila->Turno !== null ? (int) $fila->Turno : null,
                'atendio' => trim((string) ($fila->NomAtendio ?? '')),
                'turnoAtendio' => $fila->TurnoAtendio !== null ? (int) $fila->TurnoAtendio : null,
                'obs' => trim((string) ($fila->Obs ?? '')),
                'obsCierre' => trim((string) ($fila->ObsCierre ?? '')),
                'ordenTrabajo' => trim((string) ($fila->OrdenTrabajo ?? '')),
                'ordenamiento' => $comienzo->getTimestamp(),
                // Ventana más chica a la que pertenece: la vista filtra con CSS
                // sin volver al servidor.
                'ventana' => $this->ventana($comienzo, $to, $minutos),
            ];
        }

        usort($paros, static fn (array $a, array $b): int => $b['ordenamiento'] <=> $a['ordenamiento']);

        return $paros;
    }

    /**
     * '2d' si cae en el día productivo consultado o el anterior, 'semana' si cae
     * en los 7, 'mes' en el resto.
     */
    private function ventana(CarbonImmutable $inicio, DateTimeImmutable $to, int $minutos): string
    {
        $fin = CarbonImmutable::instance($to)->startOfDay()->addDay()->addMinutes($minutos);

        foreach (['2d' => 2, 'semana' => 7] as $nombre => $dias) {
            if ($inicio >= $fin->subDays($dias)) {
                return $nombre;
            }
        }

        return 'mes';
    }

    /**
     * El alta escribe 'Activo' y el cierre 'Terminado', pero el módulo de
     * mantenimiento ha usado también variantes tipo 'Finalizado': cualquier
     * estatus que no sea 'Activo' cuenta como cerrado.
     */
    private function estaTerminado(string $estatus): bool
    {
        return strcasecmp(trim($estatus), 'Activo') !== 0;
    }

    private function momento(mixed $fecha, mixed $hora): ?CarbonImmutable
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        try {
            $dia = CarbonImmutable::parse($fecha, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $texto = trim((string) ($hora ?? ''));
        if (preg_match('/(\d{1,2}):(\d{2})(?::(\d{2}))?/', $texto, $partes) !== 1) {
            return $dia;
        }

        return $dia->addHours((int) $partes[1])
            ->addMinutes((int) $partes[2])
            ->addSeconds((int) ($partes[3] ?? 0));
    }

    private function duracion(CarbonImmutable $inicio, ?CarbonImmutable $fin): string
    {
        if ($fin === null || $fin <= $inicio) {
            return '';
        }

        $minutos = (int) floor(($fin->getTimestamp() - $inicio->getTimestamp()) / 60);
        $horas = intdiv($minutos, 60);

        return $horas > 0 ? $horas.'h '.($minutos % 60).'m' : $minutos.'m';
    }
}
