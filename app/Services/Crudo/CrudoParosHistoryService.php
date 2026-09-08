<?php

declare(strict_types=1);

namespace App\Services\Crudo;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

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
                'ordenes' => [],
            ];
        }

        usort($paros, static fn (array $a, array $b): int => $b['ordenamiento'] <=> $a['ordenamiento']);

        return $this->adjuntarRenglones($paros);
    }

    /**
     * Cuelga las OT y sus renglones capturados de cada paro.
     *
     * El puente es MecOrdenTrabajoTable.FolioParo = ManFallasParos.Folio; los
     * renglones viven en MecOrdenTrabajoLine por Folio de OT. Un paro puede
     * tener varias órdenes. Si el catálogo de OT falla, el historial de paros
     * sigue vivo: el andón no depende de mecánicos para mostrar el paro.
     *
     * @param  list<array<string, mixed>>  $paros
     * @return list<array<string, mixed>>
     */
    private function adjuntarRenglones(array $paros): array
    {
        if ($paros === []) {
            return $paros;
        }

        $folios = array_values(array_unique(array_filter(
            array_map(static fn (array $paro): string => trim((string) ($paro['folio'] ?? '')), $paros),
            static fn (string $folio): bool => $folio !== '',
        )));

        if ($folios === []) {
            return $paros;
        }

        try {
            $ordenesPorParo = $this->ordenesPorFolioParo($folios);
        } catch (Throwable $exception) {
            report($exception);

            return $paros;
        }

        foreach ($paros as &$paro) {
            $paro['ordenes'] = $ordenesPorParo[trim((string) ($paro['folio'] ?? ''))] ?? [];
        }
        unset($paro);

        return $paros;
    }

    /**
     * @param  list<string>  $foliosParo
     * @return array<string, list<array<string, mixed>>>
     */
    private function ordenesPorFolioParo(array $foliosParo): array
    {
        $conexion = (string) config('crudo.connections.catalog', 'sqlsrv');
        $tablaOt = (string) config('crudo.tables.ordenes_trabajo', 'dbo.MecOrdenTrabajoTable');
        $tablaLineas = (string) config('crudo.tables.ordenes_trabajo_lineas', 'dbo.MecOrdenTrabajoLine');

        $cabeceras = DB::connection($conexion)
            ->table($tablaOt)
            ->whereIn('FolioParo', $foliosParo)
            ->orderBy('Folio')
            ->get(['Folio', 'FolioParo', 'Estatus']);

        $foliosOt = [];
        foreach ($cabeceras as $cabecera) {
            $folioOt = trim((string) ($cabecera->Folio ?? ''));
            if ($folioOt !== '') {
                $foliosOt[] = $folioOt;
            }
        }

        $lineasPorOt = [];
        if ($foliosOt !== []) {
            $lineas = DB::connection($conexion)
                ->table($tablaLineas)
                ->whereIn('Folio', $foliosOt)
                ->orderBy('Id')
                ->get([
                    'Id',
                    'Folio',
                    'CveOperador',
                    'NomOperador',
                    'Turno',
                    'Fecha',
                    'Ajusto',
                    'Reparo',
                    'Cambio',
                    'Lubrico',
                    'FaltaRefacc',
                    'HoraInicial',
                    'HoraFinal',
                    'TotalMinutos',
                    'comentarios',
                    'Calificacion',
                    'CveTejedor',
                    'NomTejedor',
                ]);

            foreach ($lineas as $linea) {
                $renglon = $this->mapearRenglon($linea);
                if ($renglon === null) {
                    continue;
                }

                $lineasPorOt[trim((string) ($linea->Folio ?? ''))][] = $renglon;
            }
        }

        $ordenesPorParo = [];
        foreach ($cabeceras as $cabecera) {
            $folioParo = trim((string) ($cabecera->FolioParo ?? ''));
            $folioOt = trim((string) ($cabecera->Folio ?? ''));
            if ($folioParo === '' || $folioOt === '') {
                continue;
            }

            $renglones = $this->renglonesUnicos($lineasPorOt[$folioOt] ?? []);
            if ($renglones === []) {
                continue;
            }

            $ordenesPorParo[$folioParo][] = [
                'folio' => $folioOt,
                'estatus' => trim((string) ($cabecera->Estatus ?? '')) ?: 'Activo',
                'renglones' => $renglones,
            ];
        }

        return $ordenesPorParo;
    }

    /**
     * En captura, al guardar se limpia el form y el siguiente Guardar inserta
     * otro renglón. El andón no necesita ver clones idénticos: se queda el primero.
     *
     * @param  list<array<string, mixed>>  $renglones
     * @return list<array<string, mixed>>
     */
    private function renglonesUnicos(array $renglones): array
    {
        $vistos = [];
        $unicos = [];

        foreach ($renglones as $renglon) {
            $huella = implode("\0", [
                (string) ($renglon['cveOperador'] ?? ''),
                (string) ($renglon['nomOperador'] ?? ''),
                (string) ($renglon['turno'] ?? ''),
                (string) ($renglon['fecha'] ?? ''),
                implode(',', $renglon['trabajos'] ?? []),
                (string) ($renglon['horaInicial'] ?? ''),
                (string) ($renglon['horaFinal'] ?? ''),
                (string) ($renglon['comentarios'] ?? ''),
            ]);

            if (isset($vistos[$huella])) {
                continue;
            }

            $vistos[$huella] = true;
            $unicos[] = $renglon;
        }

        return $unicos;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapearRenglon(object $linea): ?array
    {
        $cveOperador = trim((string) ($linea->CveOperador ?? ''));
        $nomOperador = trim((string) ($linea->NomOperador ?? ''));
        $horaInicial = $this->horaCorta($linea->HoraInicial ?? null);
        $horaFinal = $this->horaCorta($linea->HoraFinal ?? null);
        $checks = [
            ['label' => 'Ajustó', 'on' => $this->esVerdadero($linea->Ajusto ?? false)],
            ['label' => 'Reparó', 'on' => $this->esVerdadero($linea->Reparo ?? false)],
            ['label' => 'Cambió', 'on' => $this->esVerdadero($linea->Cambio ?? false)],
            ['label' => 'Lubricó', 'on' => $this->esVerdadero($linea->Lubrico ?? false)],
            ['label' => 'Falta ref.', 'on' => $this->esVerdadero($linea->FaltaRefacc ?? false)],
        ];
        $trabajos = array_values(array_map(
            static fn (array $check): string => $check['label'],
            array_filter($checks, static fn (array $check): bool => $check['on']),
        ));

        if (
            $cveOperador === ''
            && $nomOperador === ''
            && $trabajos === []
            && $horaInicial === ''
            && $horaFinal === ''
        ) {
            return null;
        }

        $minutos = $linea->TotalMinutos !== null && $linea->TotalMinutos !== ''
            ? (int) $linea->TotalMinutos
            : null;

        return [
            'id' => (int) ($linea->Id ?? 0),
            'cveOperador' => $cveOperador,
            'nomOperador' => $nomOperador,
            'turno' => $linea->Turno !== null && $linea->Turno !== '' ? (int) $linea->Turno : null,
            'fecha' => $this->fechaCorta($linea->Fecha ?? null),
            'trabajos' => $trabajos,
            'checks' => $checks,
            'horaInicial' => $horaInicial,
            'horaFinal' => $horaFinal,
            'tiempo' => $this->minutosTexto($minutos),
            'comentarios' => trim((string) ($linea->comentarios ?? '')),
            'calificacion' => $linea->Calificacion !== null && $linea->Calificacion !== ''
                ? (int) $linea->Calificacion
                : null,
            'cveTejedor' => trim((string) ($linea->CveTejedor ?? '')),
            'nomTejedor' => trim((string) ($linea->NomTejedor ?? '')),
        ];
    }

    private function esVerdadero(mixed $valor): bool
    {
        return $valor === true || $valor === 1 || $valor === '1';
    }

    private function horaCorta(mixed $hora): string
    {
        $texto = trim((string) ($hora ?? ''));
        if ($texto === '') {
            return '';
        }

        if (preg_match('/(\d{1,2}):(\d{2})/', $texto, $partes) === 1) {
            return sprintf('%02d:%02d', (int) $partes[1], (int) $partes[2]);
        }

        return $texto;
    }

    private function fechaCorta(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        try {
            return CarbonImmutable::parse($fecha, config('app.timezone'))->format('d/m');
        } catch (Throwable) {
            return trim((string) $fecha);
        }
    }

    private function minutosTexto(?int $minutos): string
    {
        if ($minutos === null || $minutos <= 0) {
            return '';
        }

        $horas = intdiv($minutos, 60);

        return $horas > 0 ? $horas.'h '.($minutos % 60).'m' : $minutos.'m';
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
        } catch (Throwable) {
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
