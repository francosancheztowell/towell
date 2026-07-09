<?php

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
class TrazabilidadMatrixService
{
    /**
     * Áreas FIJAS de la matriz (filas), en orden. Colores pastel por área.
     * text = color de texto/valor, dot = punto indicador, tint = fondo base de celda.
     */
    public array $areasFijas = [
        ['nombre' => 'Crudo',              'text' => '#475569', 'dot' => '#94a3b8', 'tint' => '#eef2f7'],
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
        ['nombre' => 'Segundas',           'text' => '#9d174d', 'dot' => '#f472b6', 'tint' => '#fce7f3'],
        ['nombre' => 'Felpas Prod Term', 'label' => 'Felpas Prod Term', 'text' => '#854d0e', 'dot' => '#eab308', 'tint' => '#fef9c3'],
        ['nombre' => 'Ent Prod Term',      'label' => 'Entrada Prod Term', 'text' => '#065f46', 'dot' => '#34d399', 'tint' => '#d1fae5'],
    ];

    /**
     * Construye la matriz a partir de los filtros activos y la métrica elegida.
     *
     * @param  array  $filtros  ['flog','articulo','tamano','color','nombrecolor','mes'(CSV de meses)]
     * @param  string  $metrica  'cantidad' (Material) o 'peso' (Kilos)
     * @return array{fechas:array, areas:array, totales:array, info:object|null, metrica:string, decimales:int, hayFlog:bool, dropdown:bool} Dropdown = true si alguna área tiene desglose.
     */
    public function build(array $filtros, string $metrica = 'cantidad'): array
    {
        $metrica = $metrica === 'peso' ? 'peso' : 'cantidad';
        $columnaMetrica = $metrica === 'peso' ? 'Peso' : 'Cantidad';
        $decimales = $metrica === 'peso' ? 1 : 0;

        $mesesSel = collect(explode(',', (string) ($filtros['mes'] ?? '')))
            ->map(fn ($v) => (int) trim($v))->filter()->unique()->values()->all();

        $hayFlog = filled($filtros['flog'] ?? null);

        // Query base sin color (color solo acota Rollos Teñido en matriz y producción).
        $base = fn () => TrazaProduccion::query()
            ->when($filtros['flog'] ?? null, fn ($q, $v) => $q->where('Flogs', $v))
            ->when($filtros['articulo'] ?? null, fn ($q, $v) => $q->where('Articulo', $v))
            ->when($filtros['tamano'] ?? null, fn ($q, $v) => $q->where('Tamano', $v))
            ->when(! empty($mesesSel), fn ($q) => $q->whereRaw('MONTH(Fecha) IN ('.implode(',', $mesesSel).')'));

        $baseRollosConColor = fn () => $base()
            ->when($filtros['color'] ?? null, fn ($q, $v) => $q->where('Color', $v));

        // Tipo / Cliente / Agente: solo cuando hay un Flog específico.
        $info = $hayFlog ? $base()->select('Tipo', 'Cliente', 'Agente')->first() : null;

        // Agregación en SQL: una fila por (Fecha, NombreAlmacen) con la suma de la métrica.
        $datos = $base()
            ->selectRaw("Fecha, NombreAlmacen, SUM($columnaMetrica) as total")
            ->whereNotNull('Fecha')
            ->groupBy('Fecha', 'NombreAlmacen')
            ->orderBy('Fecha')
            ->get();

        // Rollos Teñido respeta el filtro de color; el resto de áreas no.
        if (filled($filtros['color'] ?? null)) {
            $datosRollos = $baseRollosConColor()
                ->where('NombreAlmacen', 'Rollos Teñido')
                ->selectRaw("Fecha, NombreAlmacen, SUM($columnaMetrica) as total")
                ->whereNotNull('Fecha')
                ->groupBy('Fecha', 'NombreAlmacen')
                ->orderBy('Fecha')
                ->get();

            $datos = $datos
                ->reject(fn ($f) => ($f->NombreAlmacen ?? '') === 'Rollos Teñido')
                ->concat($datosRollos)
                ->values();
        }

        // --- Columnas (fechas distintas, ordenadas) ---
        $clavesFechas = $this->ordenarClavesFechas(
            $datos->pluck('Fecha')->map(fn ($f) => Carbon::parse($f)->format('Y-m-d'))
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
                'label' => $c->format('d/m'),
                'destacada' => $c->isWeekend(),
                'nuevoMes' => $nuevoMes,
            ];
        })->all();

        $posFecha = $clavesFechas->flip();

        // Mapa [NombreAlmacen][posFecha] => total.
        $valoresPorArea = [];
        foreach ($datos as $fila) {
            $area = $fila->NombreAlmacen ?? '';
            $clave = Carbon::parse($fila->Fecha)->format('Y-m-d');
            $pos = $posFecha[$clave];
            $valoresPorArea[$area][$pos] = ($valoresPorArea[$area][$pos] ?? 0) + (float) $fila->total;
        }

        // Desglose por artículo+color dentro de cada área (dropdown expandible por fila).
        // Mapa [NombreAlmacen][articulo|color] => ['articulo','nombreArticulo','color','nombreColor','valores'=>[pos=>total]].
        $detallePorArea = [];
        $detalleRaw = $base()
            ->selectRaw("Fecha, NombreAlmacen, Articulo, NombreArticulo, Color, NombreColor, SUM($columnaMetrica) as total")
            ->whereNotNull('Fecha')
            ->groupBy('Fecha', 'NombreAlmacen', 'Articulo', 'NombreArticulo', 'Color', 'NombreColor')
            ->orderBy('Fecha')
            ->get();

        if (filled($filtros['color'] ?? null)) {
            $detalleRollos = $baseRollosConColor()
                ->where('NombreAlmacen', 'Rollos Teñido')
                ->selectRaw("Fecha, NombreAlmacen, Articulo, NombreArticulo, Color, NombreColor, SUM($columnaMetrica) as total")
                ->whereNotNull('Fecha')
                ->groupBy('Fecha', 'NombreAlmacen', 'Articulo', 'NombreArticulo', 'Color', 'NombreColor')
                ->orderBy('Fecha')
                ->get();

            $detalleRaw = $detalleRaw
                ->reject(fn ($f) => ($f->NombreAlmacen ?? '') === 'Rollos Teñido')
                ->concat($detalleRollos)
                ->values();
        }

        foreach ($detalleRaw as $fila) {
            $area = $fila->NombreAlmacen ?? '';
            $clave = Carbon::parse($fila->Fecha)->format('Y-m-d');
            $pos = $posFecha[$clave] ?? null;
            if ($pos === null) {
                continue;
            }
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
        $areas = collect($this->areasFijas)->map(function ($area) use ($valoresPorArea, $detallePorArea, $numCols, $decimales) {
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
                    $vals = [];
                    for ($c = 0; $c < $numCols; $c++) {
                        $vals[$c] = isset($d['valores'][$c]) ? round($d['valores'][$c], $decimales) : null;
                    }
                    $totalFila = round(array_sum(array_map(fn ($x) => (float) ($x ?? 0), $vals)), $decimales);
                    if ($totalFila == 0.0) {
                        continue; // sin aporte real en el filtro actual
                    }
                    $detalles[] = [
                        'articulo' => trim(($d['articulo'] ?? '').(filled($d['nombreArticulo']) ? ' / '.$d['nombreArticulo'] : '')),
                        'color' => trim(($d['color'] ?? '').(filled($d['nombreColor']) ? ' / '.$d['nombreColor'] : '')),
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

        return compact('fechas', 'areas', 'totales', 'info', 'metrica', 'decimales', 'hayFlog', 'dropdown');
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
