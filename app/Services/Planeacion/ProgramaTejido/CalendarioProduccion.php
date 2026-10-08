<?php

namespace App\Services\Planeacion\ProgramaTejido;

use App\Models\Planeacion\ReqCalendarioLine;
use Carbon\Carbon;

/**
 * Fechas del programa de tejido sobre las líneas de un calendario (ReqCalendarioLine): ajustar
 * el inicio a la primera línea activa y consumir horas de producción hasta la fecha final.
 * Sin calendario, o si sus líneas se acaban, el tiempo corre continuo.
 */
final class CalendarioProduccion
{
    /** Duración por defecto cuando se crea/duplica un registro sin fechas calculadas */
    public const DEFAULT_DURACION_DIAS = 30;

    /** Duración por defecto para registros de tipo REPASO */
    public const DEFAULT_DURACION_REPASO_HORAS = 12;

    /**
     * Líneas de calendario parseadas en memoria por request/proceso.
     * En workers persistentes (p. ej. Octane) puede desactualizarse si se editan calendarios en BD;
     * llamar limpiarCache() tras modificar ReqCalendarioLine.
     *
     * @var array<string, list<array{ini: Carbon, fin: Carbon, ini_ts: int, fin_ts: int}>>
     */
    private static array $lineasCache = [];

    /**
     * FechaFinal a partir de un inicio y las horas de producción (PT-DUP-02):
     * horas <= 0 → inicio + DEFAULT_DURACION_DIAS; si no, finDesdeHoras().
     * Las políticas de horas <= 0 / saldo < 0 de Balancear, SecuenciaFechasTelar y el calendario
     * masivo NO son esta: esos usan solo finDesdeHoras() (ver 05-SUMMARY.md, divergencias).
     */
    public static function resolverFechaFinal(Carbon $inicio, float $horas, ?string $calendarioId): Carbon
    {
        if ($horas <= 0) {
            return $inicio->copy()->addDays(self::DEFAULT_DURACION_DIAS);
        }

        return self::finDesdeHoras($inicio, $horas, $calendarioId);
    }

    /**
     * Indica si el producto es un repaso (NombreProducto empieza con REPASO).
     * Para repasos con saldo bajo se usa duración mínima de medio día en lugar de 30 días.
     *
     * @param  object|string|null  $programaOrNombre
     */
    public static function esRepaso($programaOrNombre): bool
    {
        $nombre = is_object($programaOrNombre)
            ? trim((string) ($programaOrNombre->NombreProducto ?? ''))
            : trim((string) $programaOrNombre);

        return $nombre !== '' && strtoupper(substr($nombre, 0, 6)) === 'REPASO';
    }

    /**
     * Consume las horas sobre las líneas del calendario; sin calendario, o si sus líneas se
     * acaban, en tiempo continuo (segundos redondeados).
     */
    public static function finDesdeHoras(Carbon $inicio, float $horas, ?string $calendarioId): Carbon
    {
        $fin = ! empty($calendarioId)
            ? self::calcularFechaFinalDesdeInicio($calendarioId, $inicio, $horas)
            : null;

        return $fin ?? $inicio->copy()->addSeconds((int) round($horas * 3600));
    }

    /**
     * FechaFinal recorriendo líneas reales del calendario.
     * Retorna null si las líneas se agotan antes de consumir todas las horas
     * (el caller aplica el fallback continuo).
     */
    public static function calcularFechaFinalDesdeInicio(string $calendarioId, Carbon $fechaInicio, float $horasNecesarias): ?Carbon
    {
        $segundosRestantes = (int) round(max(0, $horasNecesarias) * 3600);
        if ($segundosRestantes === 0) {
            return $fechaInicio->copy();
        }

        [$cursor, $linesExhausted] = self::iterarLineasActivas(
            self::lineas($calendarioId),
            $fechaInicio->copy(),
            function (int $disponibles) use (&$segundosRestantes): array {
                $usar = min($disponibles, $segundosRestantes);
                $segundosRestantes -= (int) $usar;

                return [(int) $usar, $segundosRestantes > 0];
            }
        );

        return $linesExhausted ? null : $cursor;
    }

    /**
     * Recorre las líneas activas desde $cursor llamando a $procesarSegmento por cada tramo
     * disponible. Retorna [$cursor, $linesExhausted]:
     *   - $linesExhausted = true  → no quedaron líneas suficientes
     *   - $linesExhausted = false → detenido por callback o maxIter
     *
     * @param  array<array{ini:Carbon,fin:Carbon,fin_ts:int}>  $lines
     * @param  Carbon  $cursor  Inicio de iteración (se modifica en lugar)
     * @param  callable  $procesarSegmento  (int $disp, Carbon $ini, Carbon $fin): array{int,bool}
     * @return array{0:Carbon, 1:bool}
     */
    public static function iterarLineasActivas(array $lines, Carbon $cursor, callable $procesarSegmento): array
    {
        $idx = 0;
        $iter = 0;
        $maxIter = 200000;

        while ($iter < $maxIter) {
            $iter++;
            $cursorTs = $cursor->getTimestamp();
            $idx = self::saltarLineasVencidas($lines, $idx, $cursorTs);

            if ($idx >= count($lines)) {
                return [$cursor, true]; // líneas agotadas
            }

            $ini = $lines[$idx]['ini'];
            $fin = $lines[$idx]['fin'];

            // Gap antes de la línea: saltar al inicio
            if ($cursor->lt($ini)) {
                $cursor = $ini->copy();

                continue;
            }

            // Línea ya superada
            if ($cursor->gte($fin)) {
                $idx++;

                continue;
            }

            $disponibles = (int) ($fin->getTimestamp() - $cursorTs);
            if ($disponibles <= 0) {
                $cursor = $fin->copy();

                continue;
            }

            [$usar, $continuar] = $procesarSegmento($disponibles, $ini, $fin);
            $cursor->addSeconds((int) max(0, $usar));

            if ($cursor->gte($fin)) {
                $idx++;
            }

            if (! $continuar) {
                return [$cursor, false]; // detenido por callback
            }
        }

        return [$cursor, false]; // maxIter alcanzado
    }

    private static function saltarLineasVencidas(array $lines, int $idx, int $cursorTs): int
    {
        $total = count($lines);
        while ($idx < $total && $lines[$idx]['fin_ts'] <= $cursorTs) {
            $idx++;
        }

        return $idx;
    }

    /**
     * Inicio ajustado al calendario: el mismo si cae dentro de una línea, si no el inicio de la
     * siguiente línea. Null si el calendario no tiene líneas después de la fecha.
     *
     * @param  list<array{ini: Carbon, fin: Carbon, fin_ts: int}>|null  $lines  líneas ya cargadas (si no, consulta la BD)
     */
    public static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio, ?array $lines = null): ?Carbon
    {
        $calendarioId = trim((string) $calendarioId);
        if ($calendarioId === '') {
            return null;
        }

        if ($lines !== null) {
            return self::snapInicioEnLineas($fechaInicio, $lines);
        }

        $linea = ReqCalendarioLine::where('CalendarioId', $calendarioId)
            ->where('FechaFin', '>', $fechaInicio->format('Y-m-d H:i:s'))
            ->orderBy('FechaInicio')
            ->first();

        if (! $linea) {
            return null;
        }

        $ini = Carbon::parse($linea->FechaInicio);
        $fin = Carbon::parse($linea->FechaFin);

        if ($fechaInicio->gte($ini) && $fechaInicio->lt($fin)) {
            return $fechaInicio->copy();
        }

        return $ini->copy();
    }

    private static function snapInicioEnLineas(Carbon $fechaInicio, array $lines): ?Carbon
    {
        $inicioTs = $fechaInicio->getTimestamp();
        foreach ($lines as $line) {
            $finTs = $line['fin_ts'] ?? null;
            if ($finTs === null || $finTs <= $inicioTs) {
                continue;
            }

            $ini = $line['ini'] ?? null;
            $fin = $line['fin'] ?? null;
            if (! $ini || ! $fin) {
                continue;
            }

            if ($fechaInicio->gte($ini) && $fechaInicio->lt($fin)) {
                return $fechaInicio->copy();
            }

            return $ini->copy();
        }

        return null;
    }

    /**
     * Líneas del calendario ordenadas por FechaInicio (cacheadas por proceso).
     *
     * @return list<array{ini: Carbon, fin: Carbon, ini_ts: int, fin_ts: int}>
     */
    public static function lineas(string $calendarioId): array
    {
        $calendarioId = trim((string) $calendarioId);
        if ($calendarioId === '') {
            return [];
        }

        if (! isset(self::$lineasCache[$calendarioId])) {
            self::precargar([$calendarioId]);
        }

        return self::$lineasCache[$calendarioId] ?? [];
    }

    /** Carga en una consulta las líneas de los calendarios que aún no están en caché. */
    public static function precargar(array $calIds): void
    {
        $missing = [];
        foreach (array_unique(array_filter(array_map(fn ($x) => trim((string) $x), $calIds))) as $id) {
            if (! isset(self::$lineasCache[$id])) {
                $missing[] = $id;
                self::$lineasCache[$id] = []; // inicializa para evitar doble carga
            }
        }
        if (empty($missing)) {
            return;
        }

        $rows = ReqCalendarioLine::query()
            ->whereIn('CalendarioId', $missing)
            ->orderBy('CalendarioId')
            ->orderBy('FechaInicio')
            ->get(['CalendarioId', 'FechaInicio', 'FechaFin']);

        foreach ($rows as $row) {
            $calId = trim((string) $row->CalendarioId);
            if ($calId === '') {
                continue;
            }

            $ini = Carbon::parse($row->FechaInicio);
            $fin = Carbon::parse($row->FechaFin);

            self::$lineasCache[$calId][] = [
                'ini' => $ini,
                'fin' => $fin,
                'ini_ts' => $ini->getTimestamp(),
                'fin_ts' => $fin->getTimestamp(),
            ];
        }
    }

    public static function limpiarCache(): void
    {
        self::$lineasCache = [];
    }
}
