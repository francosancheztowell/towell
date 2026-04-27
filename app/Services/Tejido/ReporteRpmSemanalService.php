<?php

namespace App\Services\Tejido;

use App\Models\Tejido\TejEficienciaLine;
use Illuminate\Support\Collection;

class ReporteRpmSemanalService
{
    /**
     * RPM ideal fijo por telar (orden 201…320). No se calcula desde cortes/BD.
     *
     * @var array<string, int>
     */
    private const RPM_IDEAL_FIJO_POR_TELAR = [
        '201' => 286, '202' => 271, '203' => 287, '204' => 302, '205' => 294, '206' => 303,
        '207' => 280, '208' => 300, '209' => 300, '210' => 290, '211' => 290, '213' => 300, '214' => 305, '215' => 310,
        '299' => 400, '300' => 280, '301' => 380, '302' => 350,
        '303' => 320, '304' => 335,
        '305' => 302, '306' => 302, '307' => 304, '308' => 285, '309' => 300, '310' => 308, '311' => 330, '312' => 254, '313' => 266, '314' => 330, '315' => 320, '316' => 300,
        '317' => 305, '318' => 320, '319' => 381, '320' => 340,
    ];

    /**
     * Orden de filas alineado al reporte Inv Telas (mismos grupos / telares).
     *
     * @var array<int, array{nombre: string, telares: array<int, string>}>
     */
    public const SECCIONES = [
        [
            'nombre' => 'JACQUARD SULZER',
            'telares' => ['207', '208', '209', '210', '211', '215'],
        ],
        [
            'nombre' => 'JACQUARD SMIT',
            'telares' => ['201', '202', '203', '204', '205', '206', '213', '214'],
        ],
        [
            'nombre' => 'SMIT',
            'telares' => ['305', '306', '307', '308', '309', '310', '311', '312', '313', '314', '315', '316'],
        ],
        [
            'nombre' => 'ITEMA VIEJO',
            'telares' => ['303', '304', '317', '318'],
        ],
        [
            'nombre' => 'ITEMA NUEVO',
            'telares' => ['299', '300', '301', '302', '319', '320'],
        ],
    ];

    /**
     * @return array{
     *     secciones: array<int, array{nombre: string, filas: array<int, array{no_telar: string, rpm_real: int|null, rpm_ideal: int}>}>,
     *     filas_orden_telar: array<int, array{grupo: string, no_telar: string, rpm_real: int|null, rpm_ideal: int}>,
     *     total_general: array{grupo: string, no_telar: string, rpm_real: int|null, rpm_ideal: int},
     *     lunes: string,
     *     domingo: string
     * }
     */
    public function build(string $lunesYmd, string $domingoYmd): array
    {
        $lines = TejEficienciaLine::query()
            ->join('TejEficiencia as h', 'TejEficienciaLine.Folio', '=', 'h.Folio')
            ->whereDate('h.Date', '>=', $lunesYmd)
            ->whereDate('h.Date', '<=', $domingoYmd)
            ->get([
                'TejEficienciaLine.NoTelarId',
                'TejEficienciaLine.RpmR1',
                'TejEficienciaLine.RpmR2',
                'TejEficienciaLine.RpmR3',
            ]);

        $rpmRealPorTelar = $this->aggregateRpmRealPorTelar($lines);

        $secciones = [];
        foreach (self::SECCIONES as $seccion) {
            $filas = [];
            foreach ($seccion['telares'] as $telar) {
                $key = $this->normalizeTelar($telar);
                $filas[] = [
                    'no_telar' => $telar,
                    'rpm_real' => $rpmRealPorTelar[$key] ?? null,
                    'rpm_ideal' => $this->rpmIdealFijoParaTelar($key),
                ];
            }
            $secciones[] = [
                'nombre' => $seccion['nombre'],
                'filas' => $filas,
            ];
        }

        $filasOrden = $this->filasOrdenadasPorNumeroTelar($secciones);

        return [
            'secciones' => $secciones,
            'filas_orden_telar' => $filasOrden,
            'total_general' => $this->totalGeneralDesdeFilas($filasOrden),
            'lunes' => $lunesYmd,
            'domingo' => $domingoYmd,
        ];
    }

    /**
     * Totales de pie: **sumatoria** de la columna real (filas con dato) e **sumatoria** de RPM ideales fijos.
     *
     * @param  array<int, array{grupo: string, no_telar: string, rpm_real: int|null, rpm_ideal: int}>  $filasOrden
     * @return array{grupo: string, no_telar: string, rpm_real: int|null, rpm_ideal: int}
     */
    private function totalGeneralDesdeFilas(array $filasOrden): array
    {
        $reals = array_values(array_filter(
            array_column($filasOrden, 'rpm_real'),
            static fn ($v) => $v !== null
        ));
        $sumaReal = $reals !== []
            ? (int) array_sum($reals)
            : null;

        $ideales = array_column($filasOrden, 'rpm_ideal');
        $sumaIdeal = $ideales !== []
            ? (int) array_sum($ideales)
            : 0;

        return [
            'grupo' => 'TOTAL GENERAL',
            'no_telar' => '',
            'rpm_real' => $sumaReal,
            'rpm_ideal' => $sumaIdeal,
        ];
    }

    private function rpmIdealFijoParaTelar(string $telarNormalizado): int
    {
        if (! isset(self::RPM_IDEAL_FIJO_POR_TELAR[$telarNormalizado])) {
            throw new \InvalidArgumentException('Telar sin RPM ideal fijo definido: '.$telarNormalizado);
        }

        return self::RPM_IDEAL_FIJO_POR_TELAR[$telarNormalizado];
    }

    /**
     * Una fila por telar, orden numérico ascendente (201, 202, … 320), para tabla y gráfico.
     *
     * @param  array<int, array{nombre: string, filas: array<int, array{no_telar: string, rpm_real: int|null, rpm_ideal: int|null}>}>  $secciones
     * @return array<int, array{grupo: string, no_telar: string, rpm_real: int|null, rpm_ideal: int}>
     */
    public function filasOrdenadasPorNumeroTelar(array $secciones): array
    {
        $flat = [];
        foreach ($secciones as $sec) {
            foreach ($sec['filas'] as $f) {
                $flat[] = [
                    'grupo' => $sec['nombre'],
                    'no_telar' => $f['no_telar'],
                    'rpm_real' => $f['rpm_real'],
                    'rpm_ideal' => $f['rpm_ideal'],
                ];
            }
        }

        usort($flat, function (array $a, array $b): int {
            return (int) $a['no_telar'] <=> (int) $b['no_telar'];
        });

        return $flat;
    }

    /**
     * Promedio semanal de RPM real por telar (cortes de eficiencia en el rango de fechas).
     *
     * @param  Collection<int, TejEficienciaLine>  $lines
     * @return array<string, int>
     */
    private function aggregateRpmRealPorTelar(Collection $lines): array
    {
        $realSamples = [];

        foreach ($lines as $line) {
            $t = $this->normalizeTelar($line->NoTelarId ?? null);
            if ($t === '') {
                continue;
            }

            $avgReal = $this->averageRpmCapturado($line);
            if ($avgReal !== null) {
                $realSamples[$t][] = $avgReal;
            }
        }

        $out = [];
        foreach ($realSamples as $t => $samples) {
            if ($samples === []) {
                continue;
            }
            $out[$t] = (int) round(array_sum($samples) / count($samples), 0);
        }

        return $out;
    }

    private function normalizeTelar(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * Misma lógica que PromedioParosEficienciaReportService::averageRpm / averageCapturedValues (RPM > 0).
     */
    private function averageRpmCapturado(object $line): ?float
    {
        $captured = [];
        foreach (['RpmR1', 'RpmR2', 'RpmR3'] as $col) {
            $v = $line->{$col} ?? null;
            if ($v === null || $v === '' || ! is_numeric($v)) {
                continue;
            }
            $n = (float) $v;
            if ($n == 0.0) {
                continue;
            }
            $captured[] = $n;
        }

        if ($captured === []) {
            return null;
        }

        return round(array_sum($captured) / count($captured), 2);
    }
}
