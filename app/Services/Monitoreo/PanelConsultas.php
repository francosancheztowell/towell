<?php

namespace App\Services\Monitoreo;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lecturas del panel /admin (fase 13, contrato §6). Solo lectura.
 */
final class PanelConsultas
{
    public const EN_LINEA = 'en_linea';

    public const INACTIVO = 'inactivo';

    public const DESCONECTADO = 'desconectado';

    /** Columnas de SYSMonVista con percentiles (lista blanca: van al SQL). */
    public const METRICAS = ['ServidorMs', 'CargaMs'];

    /**
     * Límite de "actividad reciente". Con la pestaña oculta el latido llega cada
     * latido_seg.oculta (300 s), así que la ventana de 150 s del contrato la daría por
     * desconectada entre latidos: para Visible=0 la ventana es 1.5 × ese intervalo.
     *
     * @return array{visible: CarbonInterface, oculta: CarbonInterface}
     */
    public static function limites(): array
    {
        $enLinea = max(1, (int) config('monitoreo.en_linea_seg', 150));
        $oculta = max($enLinea, (int) ceil(1.5 * (int) config('monitoreo.latido_seg.oculta', 300)));

        return ['visible' => now()->subSeconds($enLinea), 'oculta' => now()->subSeconds($oculta)];
    }

    /** Filtra SYSMonDispositivo por estado (en_linea | inactivo | desconectado | '' = en línea + inactivo). */
    public static function filtrarEstado(Builder $query, string $estado, string $tabla = 'SYSMonDispositivo'): Builder
    {
        $limites = self::limites();
        $inactivoSeg = (int) config('monitoreo.inactivo_seg', 600);

        $reciente = function (Builder $q) use ($limites, $tabla): void {
            $q->where(fn (Builder $v) => $v->where($tabla.'.Visible', true)->where($tabla.'.UltimaActividad', '>=', $limites['visible']))
                ->orWhere(fn (Builder $o) => $o->where($tabla.'.Visible', false)->where($tabla.'.UltimaActividad', '>=', $limites['oculta']));
        };

        return match ($estado) {
            self::EN_LINEA => $query->where($tabla.'.Visible', true)
                ->where($tabla.'.UltimaActividad', '>=', $limites['visible'])
                ->where($tabla.'.InactivoSeg', '<=', $inactivoSeg),
            self::INACTIVO => $query->where($reciente)->where(fn (Builder $q) => $q
                ->where($tabla.'.Visible', false)
                ->orWhere($tabla.'.InactivoSeg', '>', $inactivoSeg)),
            self::DESCONECTADO => $query->whereNot($reciente),
            default => $query->where($reciente),
        };
    }

    /** Estado de un dispositivo ya leído (mismas reglas que filtrarEstado). */
    public static function estado(?CarbonInterface $ultimaActividad, bool $visible, int $inactivoSeg): string
    {
        if ($ultimaActividad === null) {
            return self::DESCONECTADO;
        }

        $limites = self::limites();
        if ($ultimaActividad->lt($visible ? $limites['visible'] : $limites['oculta'])) {
            return self::DESCONECTADO;
        }

        return $visible && $inactivoSeg <= (int) config('monitoreo.inactivo_seg', 600) ? self::EN_LINEA : self::INACTIVO;
    }

    /**
     * p50/p95 por ruta de una métrica de SYSMonVista entre $desde (incl.) y $hasta (excl.).
     *
     * Percentil de rango más cercano con ROW_NUMBER() + COUNT(*) OVER: el mismo SQL corre
     * en SQL Server 2008 R2 (producción, sin PERCENTILE_CONT) y en sqlite 3.25+ (tests).
     * k = ceil(p·n) se calcula en enteros: (n·p + 99) / 100.
     *
     * @return array<string, array{n: int, p50: int, p95: int}>
     */
    public static function percentiles(string $metrica, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        if (! in_array($metrica, self::METRICAS, true)) {
            throw new \InvalidArgumentException('Métrica no permitida: '.$metrica);
        }

        $sql = sprintf(
            'SELECT Ruta, MAX(cnt) AS n,'
            .' MAX(CASE WHEN rn = (cnt * 50 + 99) / 100 THEN ms END) AS p50,'
            .' MAX(CASE WHEN rn = (cnt * 95 + 99) / 100 THEN ms END) AS p95'
            .' FROM (SELECT Ruta, %1$s AS ms,'
            .' ROW_NUMBER() OVER (PARTITION BY Ruta ORDER BY %1$s) AS rn,'
            .' COUNT(*) OVER (PARTITION BY Ruta) AS cnt'
            .' FROM SYSMonVista WHERE Inicio >= ? AND Inicio < ? AND %1$s IS NOT NULL) t'
            .' GROUP BY Ruta',
            $metrica,
        );

        $filas = DB::connection('sqlsrv')->select($sql, [$desde, $hasta]);

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[(string) $fila->Ruta] = ['n' => (int) $fila->n, 'p50' => (int) $fila->p50, 'p95' => (int) $fila->p95];
        }

        return $resultado;
    }

    /** "2 h 05 min", "4 min", "35 s". */
    public static function duracion(?int $segundos): string
    {
        if ($segundos === null || $segundos < 0) {
            return '';
        }
        if ($segundos < 60) {
            return $segundos.' s';
        }

        $minutos = intdiv($segundos, 60);
        if ($minutos < 60) {
            return $minutos.' min';
        }

        return intdiv($minutos, 60).' h '.str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT).' min';
    }
}
