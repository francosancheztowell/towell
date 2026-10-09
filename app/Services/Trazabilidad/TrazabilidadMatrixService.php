<?php

declare(strict_types=1);

namespace App\Services\Trazabilidad;

use App\Models\Trazabilidad\TrazaProduccion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Construye la matriz "Producción por día y área" de Trazabilidad
 * (columnas = fechas, filas = áreas fijas, celdas = suma de la métrica).
 *
 * Lógica compartida por el controlador (vista web) y la exportación a Excel,
 * para que ambos muestren exactamente lo mismo (mismas áreas, colores y heatmap).
 */
final class TrazabilidadMatrixService
{
    /**
     * Áreas FIJAS de la matriz (filas), en orden. Colores pastel por área.
     * text = color de texto/valor, dot = punto indicador, tint = fondo base de celda.
     */
    public array $areasFijas = [
        ['nombre' => 'Crudo',              'text' => '#475569', 'dot' => '#94a3b8', 'tint' => '#eef2f7'],
        ['nombre' => 'Rasurado Crudo',     'text' => '#57534e', 'dot' => '#a8a29e', 'tint' => '#f5f5f4'],
        ['nombre' => 'Rollos Teñido',      'text' => '#1e40af', 'dot' => '#60a5fa', 'tint' => '#dbeafe'],
        ['nombre' => 'Acabado',            'text' => '#0d9488', 'dot' => '#2dd4bf', 'tint' => '#d3f5ee'],
        ['nombre' => 'Desengome',          'text' => '#155e75', 'dot' => '#22d3ee', 'tint' => '#cffafe'],
        ['nombre' => 'Felpa Cortada',      'text' => '#3730a3', 'dot' => '#818cf8', 'tint' => '#e0e7ff'],
        ['nombre' => 'Piezas Cortadas',    'text' => '#6b21a8', 'dot' => '#a78bfa', 'tint' => '#ede9fe'],
        ['nombre' => 'Ent Taller',         'label' => 'Entrada Taller', 'text' => '#86198f', 'dot' => '#e879f9', 'tint' => '#fae8ff'],
        ['nombre' => 'Costura Manual',     'text' => '#166534', 'dot' => '#4ade80', 'tint' => '#dcfce7'],
        ['nombre' => 'Taller 1ras',        'text' => '#3f6212', 'dot' => '#a3e635', 'tint' => '#ecfccb'],
        ['nombre' => 'Recep Maq Toalla',   'text' => '#92400e', 'dot' => '#fbbf24', 'tint' => '#fef3c7'],
        ['nombre' => 'Recep Maq Bata',     'text' => '#9a3412', 'dot' => '#fb923c', 'tint' => '#ffedd5'],
        ['nombre' => 'Recep Maq Bordado',  'text' => '#9f1239', 'dot' => '#fb7185', 'tint' => '#ffe4e6'],
        ['nombre' => 'Recep Maq Estampado', 'text' => '#0369a1', 'dot' => '#38bdf8', 'tint' => '#e0f2fe'],
        ['nombre' => 'Recep Maq Peg Eti',  'label' => 'Recep Maq Pega Etiqueta', 'text' => '#991b1b', 'dot' => '#f87171', 'tint' => '#fee2e2'],
        ['nombre' => 'Segundas',           'text' => '#9d174d', 'dot' => '#f472b6', 'tint' => '#fce7f3'],
        ['nombre' => 'Felpas Prod Term', 'label' => 'Felpas Prod Term', 'text' => '#854d0e', 'dot' => '#eab308', 'tint' => '#fef9c3'],
        ['nombre' => 'Ent Prod Term',      'label' => 'Entrada Prod Term', 'text' => '#065f46', 'dot' => '#34d399', 'tint' => '#d1fae5'],
    ];

    /**
     * Áreas fijas más las que traiga la BD y no estén en la lista: van al final, en gris,
     * para que un área nueva del ETL no desaparezca sin aviso de la matriz y del resumen.
     *
     * @param  iterable<mixed>  $nombres  NombreAlmacen presentes en los datos.
     * @return array<int, array<string, string>>
     */
    public function areasPara(iterable $nombres): array
    {
        $nuevas = collect($nombres)
            ->map(static fn (mixed $nombre): string => trim((string) $nombre))
            ->filter()
            ->unique()
            ->diff(array_column($this->areasFijas, 'nombre'))
            ->sort()
            ->map(static fn (string $nombre): array => [
                'nombre' => $nombre, 'text' => '#475569', 'dot' => '#94a3b8', 'tint' => '#f1f5f9',
            ])
            ->values()
            ->all();

        return [...$this->areasFijas, ...$nuevas];
    }

    /**
     * Construye la matriz de piezas a partir de los filtros activos.
     *
     * @param  array  $filtros  ['flog','articulo','tamano']
     * @return array{fechas:array, columnasPeriodos:array, areas:array, totales:array, info:object|null, metrica:string, decimales:int, hayFlog:bool, dropdown:bool} Dropdown = true si alguna área tiene desglose.
     */
    public function build(array $filtros): array
    {
        $metrica = 'cantidad';
        $columnaMetrica = 'Cantidad';
        $decimales = 0;

        $hayFlog = filled($filtros['flog'] ?? null);

        $agrupacion = 'CAST(Fecha AS date), NombreAlmacen, Articulo, NombreArticulo, Color, NombreColor';

        // Desglose por artículo+color dentro de cada área (dropdown expandible por fila).
        // Mapa [NombreAlmacen][articulo|color] => ['articulo','nombreArticulo','color','nombreColor','valores'=>[pos=>total]].
        $detalleRaw = TrazaProduccion::query()
            ->filtrados($filtros)
            ->selectRaw("
                CAST(Fecha AS date) as Fecha,
                NombreAlmacen,
                Articulo,
                NombreArticulo,
                Color,
                NombreColor,
                SUM($columnaMetrica) as total
            ")
            ->whereNotNull('Fecha')
            ->groupByRaw($agrupacion)
            ->orderByRaw('CAST(Fecha AS date)')
            // Filas agregadas, no modelos: con filtros amplios son ~23 mil grupos y
            // hidratarlos (más el cast de Fecha a Carbon) costaba segundos.
            ->toBase()
            ->get();

        // Tipo/Cliente/Agente solo se muestran con un Flog: una consulta aparte en vez de
        // tres MAX() sobre cada grupo de la consulta grande.
        $info = $hayFlog
            ? TrazaProduccion::query()->filtrados($filtros)
                ->selectRaw('MAX(Tipo) as Tipo, MAX(Cliente) as Cliente, MAX(Agente) as Agente')
                ->toBase()
                ->first()
            : null;

        // CAST(Fecha AS date) llega como 'Y-m-d' (o con hora en algunos drivers): basta cortar.
        $claveFecha = static fn (mixed $fecha): string => substr((string) $fecha, 0, 10);

        // --- Columnas (fechas distintas, ordenadas) ---
        $clavesFechas = $this->ordenarClavesFechas(
            $detalleRaw->pluck('Fecha')->map($claveFecha)
        );

        $mesAnterior = null;
        $fechas = $clavesFechas->map(function ($clave) use (&$mesAnterior) {
            $c = Carbon::parse($clave);
            $mesActual = $c->format('Y-m');

            // Marca la primera columna de cada mes (salvo la primera de todas) para
            // dibujar un separador más grueso entre meses distintos.
            $nuevoMes = $mesAnterior !== null && $mesActual !== $mesAnterior;
            $mesAnterior = $mesActual;

            return [
                'clave' => $clave,
                'label' => $c->format('d/m'),
                'destacada' => $c->isWeekend(),
                'nuevoMes' => $nuevoMes,
            ];
        })->all();

        // La web presenta primero meses, después semanas y finalmente días.
        // Cada columna conserva los índices de las fechas que resume para que el
        // mismo arreglo de valores siga sirviendo también a la exportación diaria.
        $columnasPeriodos = $this->construirColumnasPeriodos($clavesFechas);
        $posFecha = $clavesFechas->flip();
        $valoresPorArea = [];
        $detallePorArea = [];

        foreach ($detalleRaw as $fila) {
            $area = trim((string) ($fila->NombreAlmacen ?? ''));
            $pos = $posFecha[$claveFecha($fila->Fecha)] ?? null;
            if ($pos === null) {
                continue;
            }
            $valoresPorArea[$area][$pos] = ($valoresPorArea[$area][$pos] ?? 0) + (float) $fila->total;
            $ac = ($fila->Articulo ?? '').'|'.($fila->Color ?? '');
            if (! isset($detallePorArea[$area][$ac])) {
                $detallePorArea[$area][$ac] = [
                    'articulo' => $fila->Articulo,
                    'nombreArticulo' => $fila->NombreArticulo,
                    'color' => $fila->Color,
                    'nombreColor' => $fila->NombreColor,
                    'valores' => [],
                ];
            }
            $detallePorArea[$area][$ac]['valores'][$pos] =
                ($detallePorArea[$area][$ac]['valores'][$pos] ?? 0) + (float) $fila->total;
        }

        $numCols = count($fechas);
        $areas = collect($this->areasPara(array_keys($valoresPorArea)))->map(function ($area) use ($valoresPorArea, $detallePorArea, $numCols, $decimales) {
            $valores = [];
            for ($c = 0; $c < $numCols; $c++) {
                $valores[$c] = isset($valoresPorArea[$area['nombre']][$c])
                    ? round($valoresPorArea[$area['nombre']][$c], $decimales)
                    : null;
            }

            // Heatmap: alpha 0.10 (poco) → 0.60 (máximo de la fila).
            $maxFila = 0;
            foreach ($valores as $v) {
                if ($v !== null && $v > $maxFila) {
                    $maxFila = $v;
                }
            }
            [$r, $g, $b] = sscanf($area['dot'], '#%02x%02x%02x');
            $bgs = [];
            for ($c = 0; $c < $numCols; $c++) {
                $v = $valores[$c];
                if ($v === null || $maxFila <= 0) {
                    $bgs[$c] = null;

                    continue;
                }
                $alpha = round(0.10 + 0.50 * ($v / $maxFila), 3);
                $bgs[$c] = "rgba($r,$g,$b,$alpha)";
            }

            // Sub-filas: una por cada artículo+color presente en el área. Cada una
            // con sus valores alineados a las columnas de fecha y su total de fila.
            $detalles = [];
            if (! empty($detallePorArea[$area['nombre']])) {
                foreach ($detallePorArea[$area['nombre']] as $d) {
                    // Disperso desde el origen: solo los días con valor, sin recorrer todas las columnas.
                    $vals = array_map(static fn (float $v): float => round($v, $decimales), $d['valores']);
                    ksort($vals);
                    $totalFila = round(array_sum($vals), $decimales);
                    if ($totalFila == 0.0) {
                        continue; // sin aporte real en el filtro actual
                    }
                    $detalles[] = [
                        'articulo' => trim(($d['articulo'] ?? '').(filled($d['nombreArticulo']) ? ' / '.$d['nombreArticulo'] : '')),
                        'color' => trim(($d['color'] ?? '').(filled($d['nombreColor']) ? ' / '.$d['nombreColor'] : '')),
                        // Disperso (índice => valor): estos detalles viajan en el JSON de
                        // la respuesta y la mayoría de los días vienen vacíos.
                        'valores' => $vals,
                        'total' => $totalFila,
                    ];
                }
                // Ordenar por artículo y luego color para una lectura estable.
                usort($detalles, fn ($a, $b) => [$a['articulo'], $a['color']] <=> [$b['articulo'], $b['color']]);
            }

            return array_merge($area, ['valores' => $valores, 'bgs' => $bgs, 'detalles' => $detalles]);
        })
        // Ocultar áreas completamente vacías/en cero para el filtro actual.
            ->filter(function ($area) {
                foreach ($area['valores'] as $v) {
                    if ($v !== null && (float) $v != 0.0) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();

        // Hay dropdown si al menos un área trae desglose por artículo/color.
        $dropdown = collect($areas)->contains(fn ($area) => ! empty($area['detalles']));

        // --- Totales por columna ---
        $totales = [];
        foreach ($fechas as $i => $fecha) {
            $suma = 0;
            foreach ($areas as $area) {
                $suma += (float) ($area['valores'][$i] ?? 0);
            }
            $totales[$i] = $suma ? round($suma, $decimales) : null;
        }

        return compact('fechas', 'columnasPeriodos', 'areas', 'totales', 'info', 'metrica', 'decimales', 'hayFlog', 'dropdown');
    }

    /**
     * Construye las columnas jerárquicas de la matriz web en orden descendente:
     * mes -> semana ISO -> día. Las semanas que cruzan de mes se mantienen dentro
     * de su mes para que ningún día se duplique ni cambie de agrupación visual.
     *
     * @param  Collection<int, string>  $clavesFechas
     * @return array<int, array{nivel:string, clave:string, mesClave:string, semanaClave:?string, label:string, subLabel:string, indices:array<int, int>, destacada:bool}>
     */
    private function construirColumnasPeriodos(Collection $clavesFechas): array
    {
        $items = $clavesFechas->values()->map(function (string $clave, int $indice): array {
            $fecha = Carbon::parse($clave);

            return [
                'clave' => $clave,
                'indice' => $indice,
                'mesClave' => $fecha->format('Y-m'),
                'semanaIso' => $fecha->format('o-W'),
            ];
        });

        $columnas = [];
        foreach ($items->groupBy('mesClave') as $mesClave => $itemsMes) {
            $fechaMes = Carbon::parse($itemsMes->first()['clave']);
            $indicesMes = $itemsMes->pluck('indice')->values()->all();
            $cantidadDias = count($indicesMes);

            $columnas[] = [
                'nivel' => 'mes',
                'clave' => (string) $mesClave,
                'mesClave' => (string) $mesClave,
                'semanaClave' => null,
                'label' => ucfirst($fechaMes->copy()->locale('es')->translatedFormat('F Y')),
                'subLabel' => $cantidadDias.' '.($cantidadDias === 1 ? 'día' : 'días'),
                'indices' => $indicesMes,
                'destacada' => false,
            ];

            foreach ($itemsMes->groupBy('semanaIso') as $semanaIso => $itemsSemana) {
                $fechaSemana = Carbon::parse($itemsSemana->first()['clave']);
                $inicioSemana = $fechaSemana->copy()->startOfWeek(Carbon::MONDAY);
                $finSemana = $fechaSemana->copy()->endOfWeek(Carbon::SUNDAY);
                $semanaClave = $mesClave.'-w'.$semanaIso;

                if ($inicioSemana->isSameMonth($finSemana)) {
                    $rangoSemana = $inicioSemana->format('d').'–'.$finSemana->copy()->locale('es')->translatedFormat('d M');
                } else {
                    $rangoSemana = $inicioSemana->copy()->locale('es')->translatedFormat('d M')
                        .'–'.$finSemana->copy()->locale('es')->translatedFormat('d M');
                }

                $columnas[] = [
                    'nivel' => 'semana',
                    'clave' => $semanaClave,
                    'mesClave' => (string) $mesClave,
                    'semanaClave' => $semanaClave,
                    'label' => 'Semana '.(int) $fechaSemana->format('W'),
                    'subLabel' => str_replace('.', '', $rangoSemana),
                    'indices' => $itemsSemana->pluck('indice')->values()->all(),
                    'destacada' => false,
                ];

                foreach ($itemsSemana as $itemDia) {
                    $fechaDia = Carbon::parse($itemDia['clave']);
                    $columnas[] = [
                        'nivel' => 'dia',
                        'clave' => $itemDia['clave'],
                        'mesClave' => (string) $mesClave,
                        'semanaClave' => $semanaClave,
                        'label' => $fechaDia->format('d/m'),
                        'subLabel' => ucfirst(str_replace('.', '', $fechaDia->copy()->locale('es')->translatedFormat('D'))),
                        'indices' => [$itemDia['indice']],
                        'destacada' => $fechaDia->isWeekend(),
                    ];
                }
            }
        }

        return $columnas;
    }

    /**
     * @param  Collection<int, string>  $claves
     * @return Collection<int, string>
     */
    private function ordenarClavesFechas(Collection $claves): Collection
    {
        return $claves->unique()->sortDesc()->values();
    }
}
