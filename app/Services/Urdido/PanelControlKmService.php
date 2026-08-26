<?php

namespace App\Services\Urdido;

use App\Models\Tejido\TejEficienciaLine;
use Carbon\Carbon;

/**
 * Panel de Control — Telares KM (401 / 402).
 *
 * Agrega TejEficienciaLine por semana ISO y arma KPIs, detalle semanal,
 * análisis de observaciones y hallazgos automáticos.
 * Los valores numéricos de la tabla vienen como VARCHAR: se castean con (float).
 */
class PanelControlKmService
{
    private const TELARES_KM = ['401', '402'];

    private const CATEGORIAS = [
        'Repaso',
        'Calidad',
        'Enhebrado',
        'Montado',
        'Plomo',
        'Aguja',
        'Platina',
        'Falla',
        'Rotura',
        'Hilos sueltos',
    ];

    public function build(array $filtros): array
    {
        $telar = in_array(($filtros['telar'] ?? 'ambos'), self::TELARES_KM, true)
            ? (string) $filtros['telar']
            : 'ambos';

        $anio = (int) ($filtros['anio'] ?? now('America/Mexico_City')->year);
        if ($anio <= 0) {
            $anio = (int) now('America/Mexico_City')->year;
        }

        $desde = $this->normalizarFecha($filtros['desde'] ?? null) ?? sprintf('%04d-01-01', $anio);
        $hasta = $this->normalizarFecha($filtros['hasta'] ?? null) ?? sprintf('%04d-12-31', $anio);
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $umbralVerde = (float) ($filtros['umbral_verde'] ?? 0.90);
        $umbralAmarillo = (float) ($filtros['umbral_amarillo'] ?? 0.75);

        $telares = $telar === 'ambos' ? self::TELARES_KM : [$telar];

        $filas = TejEficienciaLine::query()
            ->select([
                'Date', 'NoTelarId', 'RpmStd', 'EficienciaSTD',
                'RpmR1', 'EficienciaR1', 'RpmR2', 'EficienciaR2', 'RpmR3', 'EficienciaR3',
                'ObsR1', 'ObsR2', 'ObsR3',
            ])
            ->whereIn('NoTelarId', $telares)
            ->whereNotNull('Date')
            ->where('Date', '>=', $desde.' 00:00:00')
            ->where('Date', '<=', $hasta.' 23:59:59')
            ->get();

        $semanas = $this->agruparPorSemana($filas);
        $detalle = $this->armarDetalle($semanas, $umbralVerde, $umbralAmarillo);
        $kpis = $this->armarKpis($detalle);
        $categorias = $this->armarCategorias($detalle);
        $hallazgos = $this->armarHallazgos($telar, $detalle, $kpis, $categorias);

        return [
            'telar' => $telar,
            'anio' => $anio,
            'desde' => $desde,
            'hasta' => $hasta,
            'umbral_verde' => $umbralVerde,
            'umbral_amarillo' => $umbralAmarillo,
            'kpis' => $kpis,
            'semanas_detalle' => $detalle,
            'categorias' => $categorias,
            'hallazgos' => $hallazgos,
        ];
    }

    /**
     * Agrupa las filas crudas por semana ISO acumulando los valores necesarios.
     */
    private function agruparPorSemana($filas): array
    {
        $semanas = [];

        foreach ($filas as $fila) {
            $fecha = $this->fechaDe($fila->Date);
            if ($fecha === null) {
                continue;
            }

            $semana = (int) $fecha->isoWeek();
            // Clave por (anio ISO, semana ISO): sin el anio ISO, el 29-31/dic cae en la
            // semana 1 del anio siguiente y se fusiona con el 1-4/ene del mismo anio.
            $clave = sprintf('%04d-%02d', $fecha->isoWeekYear(), $semana);
            $ymd = $fecha->format('Y-m-d');

            if (! isset($semanas[$clave])) {
                $semanas[$clave] = [
                    'semana' => $semana,
                    'efic' => [],
                    'rpm' => [],
                    'est' => [],
                    'rpm_est' => [],
                    'fechas' => [],
                    'comentarios' => [],
                ];
            }

            $ref = &$semanas[$clave];
            $ref['fechas'][$ymd] = true;

            $efic = $this->promedioTurnos([$fila->EficienciaR1, $fila->EficienciaR2, $fila->EficienciaR3]);
            if ($efic !== null) {
                $ref['efic'][] = $efic;
            }

            $rpm = $this->promedioTurnos([$fila->RpmR1, $fila->RpmR2, $fila->RpmR3]);
            if ($rpm !== null) {
                $ref['rpm'][] = $rpm;
            }

            $eficStd = $this->valorPositivo($fila->EficienciaSTD);
            if ($eficStd !== null) {
                $ref['est'][] = $eficStd;
            }

            $rpmStd = $this->valorPositivo($fila->RpmStd);
            if ($rpmStd !== null) {
                $ref['rpm_est'][] = $rpmStd;
            }

            foreach ([$fila->ObsR1, $fila->ObsR2, $fila->ObsR3] as $obs) {
                $texto = trim((string) $obs);
                if ($texto !== '') {
                    $ref['comentarios'][] = $texto;
                }
            }

            unset($ref);
        }

        ksort($semanas);

        return $semanas;
    }

    private function armarDetalle(array $semanas, float $umbralVerde, float $umbralAmarillo): array
    {
        $detalle = [];

        foreach ($semanas as $s) {
            $efic = $this->promedio($s['efic']);
            $est = $this->promedio($s['est']);
            $rpm = $this->promedio($s['rpm']);
            $rpmEst = $this->promedio($s['rpm_est']);
            $dif = ($efic !== null && $est !== null) ? round($efic - $est, 1) : null;

            $fechas = array_keys($s['fechas']);
            sort($fechas);

            $detalle[] = [
                'semana' => $s['semana'],
                'efic' => $efic,
                'est' => $est,
                'rpm' => $rpm,
                'rpm_est' => $rpmEst,
                'dif' => $dif,
                'dias' => count($fechas),
                'eventos' => count($s['comentarios']),
                'desde' => $fechas[0] ?? null,
                'hasta' => $fechas ? $fechas[count($fechas) - 1] : null,
                'estado' => $this->estado($efic, $est, $umbralVerde, $umbralAmarillo),
                'comentarios' => $s['comentarios'],
            ];
        }

        return $detalle;
    }

    private function estado(?float $efic, ?float $est, float $umbralVerde, float $umbralAmarillo): string
    {
        if ($efic === null || $est === null) {
            return 'Sin dato';
        }

        if ($efic >= $est * $umbralVerde) {
            return 'En meta';
        }

        if ($efic >= $est * $umbralAmarillo) {
            return 'Atención';
        }

        return 'Crítico';
    }

    private function armarKpis(array $detalle): array
    {
        $efics = [];
        $ests = [];
        $rpms = [];
        $eventos = 0;

        foreach ($detalle as $d) {
            if ($d['efic'] !== null) {
                $efics[] = $d['efic'];
            }
            if ($d['est'] !== null) {
                $ests[] = $d['est'];
            }
            if ($d['rpm'] !== null) {
                $rpms[] = $d['rpm'];
            }
            $eventos += $d['eventos'];
        }

        $eficienciaProm = $this->promedio($efics);
        $estandarProm = $this->promedio($ests);

        return [
            'eficiencia_prom' => $eficienciaProm,
            'estandar_prom' => $estandarProm,
            'brecha' => ($eficienciaProm !== null && $estandarProm !== null)
                ? round($eficienciaProm - $estandarProm, 1)
                : null,
            'rpm_prom' => $this->promedio($rpms),
            'semanas' => count($efics),
            'eventos' => $eventos,
        ];
    }

    private function armarCategorias(array $detalle): array
    {
        $textos = '';
        foreach ($detalle as $d) {
            if ($d['comentarios']) {
                $textos .= ' '.implode(' | ', $d['comentarios']);
            }
        }

        $textoUpper = mb_strtoupper($textos);

        $conteos = [];
        $total = 0;
        foreach (self::CATEGORIAS as $categoria) {
            $n = $textoUpper === '' ? 0 : substr_count($textoUpper, mb_strtoupper($categoria));
            $conteos[$categoria] = $n;
            $total += $n;
        }

        $categorias = [];
        foreach ($conteos as $categoria => $n) {
            $categorias[] = [
                'categoria' => $categoria,
                'menciones' => $n,
                'porcentaje' => $total > 0 ? round($n / $total, 4) : 0,
            ];
        }

        return $categorias;
    }

    private function armarHallazgos(string $telar, array $detalle, array $kpis, array $categorias): array
    {
        $etiquetaTelar = $telar === 'ambos' ? 'Ambos (401 y 402)' : $telar;

        $conDato = array_values(array_filter($detalle, fn ($d) => $d['efic'] !== null));

        $fechas = [];
        foreach ($detalle as $d) {
            if ($d['desde']) {
                $fechas[] = $d['desde'];
            }
            if ($d['hasta']) {
                $fechas[] = $d['hasta'];
            }
        }
        sort($fechas);
        $periodoIni = $fechas ? Carbon::parse($fechas[0])->format('d/m/Y') : '—';
        $periodoFin = $fechas ? Carbon::parse($fechas[count($fechas) - 1])->format('d/m/Y') : '—';

        $hallazgos = [];

        $hallazgos[] = sprintf(
            'Telar analizado: %s  |  Semanas con dato de eficiencia: %d  |  Periodo: %s a %s',
            $etiquetaTelar,
            $kpis['semanas'],
            $periodoIni,
            $periodoFin
        );

        if ($conDato) {
            $mejor = $conDato[0];
            $peor = $conDato[0];
            foreach ($conDato as $d) {
                if ($d['efic'] > $mejor['efic']) {
                    $mejor = $d;
                }
                if ($d['efic'] < $peor['efic']) {
                    $peor = $d;
                }
            }
            $hallazgos[] = sprintf('Mejor semana: S%d con %.1f%% de eficiencia.', $mejor['semana'], $mejor['efic']);
            $hallazgos[] = sprintf('Peor semana: S%d con %.1f%% de eficiencia.', $peor['semana'], $peor['efic']);
        } else {
            $hallazgos[] = 'Mejor semana: sin datos de eficiencia en el periodo.';
            $hallazgos[] = 'Peor semana: sin datos de eficiencia en el periodo.';
        }

        if ($detalle && array_sum(array_column($detalle, 'eventos')) > 0) {
            $topEventos = $detalle[0];
            foreach ($detalle as $d) {
                if ($d['eventos'] > $topEventos['eventos']) {
                    $topEventos = $d;
                }
            }
            $hallazgos[] = sprintf(
                'Semana con más eventos: S%d con %d registros; total del periodo: %d.',
                $topEventos['semana'],
                $topEventos['eventos'],
                $kpis['eventos']
            );
        } else {
            $hallazgos[] = 'Semana con más eventos: sin registros; total del periodo: 0.';
        }

        $topCat = null;
        foreach ($categorias as $c) {
            if ($c['menciones'] > 0 && ($topCat === null || $c['menciones'] > $topCat['menciones'])) {
                $topCat = $c;
            }
        }
        $hallazgos[] = $topCat === null
            ? 'Causa más mencionada: sin observaciones capturadas.'
            : sprintf(
                'Causa más mencionada: %s (%d menciones, %.1f%% del total).',
                $topCat['categoria'],
                $topCat['menciones'],
                $topCat['porcentaje'] * 100
            );

        $conteoEstados = ['En meta' => 0, 'Atención' => 0, 'Crítico' => 0, 'Sin dato' => 0];
        foreach ($detalle as $d) {
            $conteoEstados[$d['estado']]++;
        }
        $hallazgos[] = sprintf(
            'Semanas en meta: %d   |   Atención: %d   |   Críticas: %d   |   Sin dato: %d',
            $conteoEstados['En meta'],
            $conteoEstados['Atención'],
            $conteoEstados['Crítico'],
            $conteoEstados['Sin dato']
        );

        $hallazgos[] = $kpis['brecha'] === null
            ? 'Brecha promedio vs estándar: sin datos suficientes.'
            : sprintf('Brecha promedio vs estándar: %+.1f puntos porcentuales.', $kpis['brecha']);

        return $hallazgos;
    }

    /**
     * Promedio de los turnos de una fila considerando sólo valores no nulos y > 0.
     */
    private function promedioTurnos(array $valores): ?float
    {
        $validos = [];
        foreach ($valores as $valor) {
            $v = $this->valorPositivo($valor);
            if ($v !== null) {
                $validos[] = $v;
            }
        }

        return $validos ? array_sum($validos) / count($validos) : null;
    }

    private function valorPositivo($valor): ?float
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        $numero = (float) str_replace(',', '', trim((string) $valor));

        return $numero > 0 ? $numero : null;
    }

    private function promedio(array $valores): ?float
    {
        return $valores ? round(array_sum($valores) / count($valores), 1) : null;
    }

    private function fechaDe($valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }

        try {
            return Carbon::parse($valor instanceof Carbon ? $valor->toDateString() : (string) $valor);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizarFecha(?string $fecha): ?string
    {
        if (blank($fecha)) {
            return null;
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
