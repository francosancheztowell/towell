<?php

namespace App\Http\Controllers\Trazabilidad;

use App\Http\Controllers\Controller;
use App\Models\Trazabilidad\TrazaProduccion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrazabilidadController extends Controller
{
    /**
     * Áreas FIJAS de la matriz (filas), en orden. Siempre se muestran todas, aunque
     * no tengan producción para el Flog/mes elegido. Solo se agregan filas cuyo
     * NombreAlmacen coincida con estos nombres. Colores pastel por área.
     * text = color de texto/valor, dot = punto indicador, tint = fondo de celda con valor.
     */
    private array $areasFijas = [
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
     * Página principal del módulo de Trazabilidad.
     * Matriz "Producción por día y área": columnas = fechas, filas = áreas (NombreAlmacen),
     * celdas = suma de Cantidad. Datos reales desde la tabla TrazaProduccion.
     */
    public function index(Request $request)
    {
        abort_unless(userCan('acceso', 'Trazabilidad'), 403, 'No tienes acceso al módulo de Trazabilidad.');

        // Filtros activos (querystring sobre la misma ruta).
        // 'articulo' guarda el código; el select combinado muestra "código / nombre".
        $filtros = [
            'flog'     => $request->query('flog'),
            'articulo' => $request->query('articulo'),
            'tamano'   => $request->query('tamano'),
            'color'    => $request->query('color'),
            'mes'      => $request->query('mes'),
        ];

        // Métrica de la matriz: 'peso' (Kilos) o 'cantidad' (Material). No es un filtro
        // de fila → se maneja aparte para no afectar la cascada ni la caché.
        $metrica = $request->query('metrica') === 'peso' ? 'peso' : 'cantidad';
        $columnaMetrica = $metrica === 'peso' ? 'Peso' : 'Cantidad';
        $decimales = $metrica === 'peso' ? 1 : 0;

        // Mes es MULTI-selección: viene como CSV "5,6". Se normaliza a lista de enteros.
        $mesesSel = collect(explode(',', (string) $filtros['mes']))
            ->map(fn ($v) => (int) trim($v))->filter()->unique()->values()->all();
        $filtros['mes'] = implode(',', $mesesSel); // CSV normalizado para la vista.

        // Por defecto, al seleccionar Flog/Artículo/Tamaño/Color (sin haber elegido mes),
        // se preselecciona el ÚLTIMO mes disponible para esos filtros. El usuario puede
        // luego agregar otro mes o ambos desde los badges.
        $hayFiltroNoMes = filled($filtros['flog']) || filled($filtros['articulo'])
            || filled($filtros['tamano']) || filled($filtros['color']);

        if (empty($mesesSel) && $hayFiltroNoMes) {
            $ultimoMes = (int) TrazaProduccion::query()
                ->when($filtros['flog'], fn ($q, $v) => $q->where('Flogs', $v))
                ->when($filtros['articulo'], fn ($q, $v) => $q->where('Articulo', $v))
                ->when($filtros['tamano'], fn ($q, $v) => $q->where('Tamano', $v))
                ->when($filtros['color'], fn ($q, $v) => $q->where('Color', $v))
                ->whereNotNull('Fecha')
                ->selectRaw('MAX(MONTH(Fecha)) as m')
                ->value('m');

            if ($ultimoMes) {
                $mesesSel = [$ultimoMes];
                $filtros['mes'] = (string) $ultimoMes;
            }
        }

        // La tabla se muestra con CUALQUIER filtro. El Flog es opcional; solo cuando hay
        // un Flog específico se muestran los datos de Tipo/Cliente/Agente.
        $hayFlog = filled($filtros['flog']);
        $hayFiltro = filled($filtros['flog']) || filled($filtros['articulo'])
            || filled($filtros['tamano']) || filled($filtros['color']) || ! empty($mesesSel);

        // Filtros en cascada (faceted): las opciones de cada select se calculan
        // aplicando TODOS los demás filtros activos, menos el propio.
        $aplicarFiltros = function ($query, string $excepto) use ($filtros, $mesesSel) {
            if ($excepto !== 'flog' && filled($filtros['flog'])) {
                $query->where('Flogs', $filtros['flog']);
            }
            if ($excepto !== 'articulo' && filled($filtros['articulo'])) {
                $query->where('Articulo', $filtros['articulo']);
            }
            if ($excepto !== 'tamano' && filled($filtros['tamano'])) {
                $query->where('Tamano', $filtros['tamano']);
            }
            if ($excepto !== 'color' && filled($filtros['color'])) {
                $query->where('Color', $filtros['color']);
            }
            if ($excepto !== 'mes' && ! empty($mesesSel)) {
                $query->whereRaw('MONTH(Fecha) IN (' . implode(',', $mesesSel) . ')');
            }

            return $query;
        };

        // Sin ningún filtro las listas completas son las mismas siempre → se cachean
        // 1h para no escanear las >130k filas en cada carga. Con filtros activos las
        // consultas ya van acotadas (rápidas) y se calculan al vuelo.
        $sinFiltros = collect($filtros)->every(fn ($v) => blank($v));

        $opcionFacet = fn (string $col, string $excepto) => $aplicarFiltros(TrazaProduccion::query(), $excepto)
            ->whereNotNull($col)->where($col, '<>', '')
            ->distinct()->orderBy($col)->pluck($col);

        // Select combinado "código / nombre" (Artículo y Color), faceted.
        $opcionCombo = fn (string $colCod, string $colNom, string $excepto) => $aplicarFiltros(TrazaProduccion::query(), $excepto)
            ->whereNotNull($colCod)->where($colCod, '<>', '')
            ->select($colCod, $colNom)->distinct()->orderBy($colCod)->get()
            ->map(fn ($r) => [
                'codigo' => $r->{$colCod},
                'label'  => trim($r->{$colCod} . (filled($r->{$colNom}) ? ' / ' . $r->{$colNom} : '')),
            ])->values();

        if ($sinFiltros) {
            $opcionesFlog     = Cache::remember('traza_opt_flog', 3600, fn () => $opcionFacet('Flogs', 'flog'));
            $opcionesArticulo = Cache::remember('traza_opt_articulo_combo', 3600, fn () => $opcionCombo('Articulo', 'NombreArticulo', 'articulo'));
            $opcionesTamano   = Cache::remember('traza_opt_tamano', 3600, fn () => $opcionFacet('Tamano', 'tamano'));
            $opcionesColor    = Cache::remember('traza_opt_color_combo', 3600, fn () => $opcionCombo('Color', 'NombreColor', 'color'));
        } else {
            $opcionesFlog     = $opcionFacet('Flogs', 'flog');
            $opcionesArticulo = $opcionCombo('Articulo', 'NombreArticulo', 'articulo');
            $opcionesTamano   = $opcionFacet('Tamano', 'tamano');
            $opcionesColor    = $opcionCombo('Color', 'NombreColor', 'color');
        }

        // Meses disponibles (con nº de registros) según los filtros activos, menos Mes.
        // Una sola consulta agrupada → no afecta el rendimiento.
        $nombresMeses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        $mesesDisponibles = $aplicarFiltros(TrazaProduccion::query(), 'mes')
            ->whereNotNull('Fecha')
            ->selectRaw('MONTH(Fecha) as mes, COUNT(*) as registros')
            ->groupByRaw('MONTH(Fecha)')
            ->orderByRaw('MONTH(Fecha)')
            ->get()
            ->map(fn ($r) => [
                'mes'       => (int) $r->mes,
                'nombre'    => $nombresMeses[(int) $r->mes] ?? (string) $r->mes,
                'registros' => (int) $r->registros,
            ]);

        // Valores por defecto (sin filtros → matriz vacía / estado inicial).
        $fechas = [];
        $areas = [];
        $totales = [];
        $info = null; // Tipo / Cliente / Agente (solo con un Flog específico).

        // La matriz se calcula con CUALQUIER filtro activo.
        if ($hayFiltro) {
            // Query base con los filtros presentes (todos opcionales).
            $base = fn () => TrazaProduccion::query()
                ->when($filtros['flog'], fn ($q, $v) => $q->where('Flogs', $v))
                ->when($filtros['articulo'], fn ($q, $v) => $q->where('Articulo', $v))
                ->when($filtros['tamano'], fn ($q, $v) => $q->where('Tamano', $v))
                ->when($filtros['color'], fn ($q, $v) => $q->where('Color', $v))
                ->when(! empty($mesesSel), fn ($q) => $q->whereRaw('MONTH(Fecha) IN (' . implode(',', $mesesSel) . ')'));

            // Tipo / Cliente / Agente: solo cuando hay un Flog específico.
            $info = $hayFlog ? $base()->select('Tipo', 'Cliente', 'Agente')->first() : null;

            // Agregación en SQL: una fila por (Fecha, NombreAlmacen) con la suma de la
            // métrica elegida (Cantidad = Material, Peso = Kilos).
            $datos = $base()
                ->selectRaw("Fecha, NombreAlmacen, SUM($columnaMetrica) as total")
                ->whereNotNull('Fecha')
                ->groupBy('Fecha', 'NombreAlmacen')
                ->orderBy('Fecha')
                ->get();

            // --- Construir columnas (fechas distintas, ordenadas) ---
            $clavesFechas = $datos->pluck('Fecha')
                ->map(fn ($f) => Carbon::parse($f)->format('Y-m-d'))
                ->unique()
                ->sort()
                ->values();

            $fechas = $clavesFechas->map(function ($clave) {
                $c = Carbon::parse($clave);
                return [
                    'label'     => $c->format('d/m'),
                    'destacada' => $c->isWeekend(), // sábado y domingo resaltados
                ];
            })->all();

            // Índice clave-fecha => posición de columna.
            $posFecha = $clavesFechas->flip();

            // --- Filas FIJAS (áreas predefinidas), match por NombreAlmacen ---
            // Mapa [NombreAlmacen][posFecha] => total.
            $valoresPorArea = [];
            foreach ($datos as $fila) {
                $area = $fila->NombreAlmacen ?? '';
                $clave = Carbon::parse($fila->Fecha)->format('Y-m-d');
                $pos = $posFecha[$clave];
                $valoresPorArea[$area][$pos] = ($valoresPorArea[$area][$pos] ?? 0) + (float) $fila->total;
            }

            $numCols = count($fechas);
            $areas = collect($this->areasFijas)->map(function ($area) use ($valoresPorArea, $numCols, $decimales) {
                $valores = [];
                for ($c = 0; $c < $numCols; $c++) {
                    $valores[$c] = isset($valoresPorArea[$area['nombre']][$c])
                        ? round($valoresPorArea[$area['nombre']][$c], $decimales)
                        : null;
                }

                // Heatmap: color más fuerte/claro según el valor vs el máximo de la fila.
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
                    $alpha = round(0.10 + 0.50 * ($v / $maxFila), 3); // 0.10 (poco) → 0.60 (máximo), pastel
                    $bgs[$c] = "rgba($r,$g,$b,$alpha)";
                }

                return array_merge($area, ['valores' => $valores, 'bgs' => $bgs]);
            })
            // Ocultar áreas completamente vacías/en cero para el filtro actual:
            // si todos sus valores son null o 0, no se muestra la fila.
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

            // --- Totales por columna ---
            foreach ($fechas as $i => $fecha) {
                $suma = 0;
                foreach ($areas as $area) {
                    $suma += (float) ($area['valores'][$i] ?? 0);
                }
                $totales[$i] = $suma ? round($suma, $decimales) : null;
            }
        }

        $datosVista = compact(
            'fechas', 'areas', 'totales', 'filtros', 'hayFlog', 'hayFiltro', 'info',
            'metrica', 'decimales',
            'opcionesFlog', 'opcionesArticulo', 'opcionesTamano', 'opcionesColor',
            'mesesDisponibles'
        );

        // Respuesta AJAX: solo el bloque de resultado + las opciones de los selects
        // (para refrescar la cascada sin recargar la página).
        if ($request->ajax()) {
            return response()->json([
                'resultado' => view('modulos.trazabilidad._resultado', $datosVista)->render(),
                'opciones'  => [
                    'flog'     => $opcionesFlog->values(),
                    'articulo' => $opcionesArticulo->values(), // [{codigo, label}]
                    'tamano'   => $opcionesTamano->values(),
                    'color'    => $opcionesColor->values(),     // [{codigo, label}]
                    'mes'      => $mesesDisponibles->values(),
                ],
                'filtros' => $filtros,
            ]);
        }

        return view('modulos.trazabilidad.index', $datosVista);
    }
}
