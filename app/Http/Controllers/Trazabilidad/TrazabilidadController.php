<?php

namespace App\Http\Controllers\Trazabilidad;

use App\Exports\TrazabilidadExport;
use App\Http\Controllers\Controller;
use App\Models\Trazabilidad\TrazaProduccion;
use App\Services\Trazabilidad\TrazabilidadFlogsService;
use App\Services\Trazabilidad\TrazabilidadMatrixService;
use App\Services\Trazabilidad\TrazabilidadProduccionService;
use App\Services\Trazabilidad\TrazabilidadRedboothService;
use App\Services\Trazabilidad\TrazabilidadResumenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class TrazabilidadController extends Controller
{
    public function __construct(
        private TrazabilidadMatrixService $matriz,
        private TrazabilidadProduccionService $produccionSrv,
        private TrazabilidadFlogsService $flogsSrv,
        private TrazabilidadResumenService $resumenSrv,
        private TrazabilidadRedboothService $redboothSrv,
    ) {}

    public function redbooth(Request $request): JsonResponse
    {
        abort_unless(userCan('acceso', 'Trazabilidad'), 403, 'No tienes acceso al módulo de Trazabilidad.');

        $validated = $request->validate([
            'flog' => ['required', 'string', 'max:100'],
        ]);

        return response()->json($this->redboothSrv->resolver($validated['flog']));
    }

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
            'flog' => $request->query('flog'),
            'articulo' => $request->query('articulo'),
            'tamano' => $request->query('tamano'),
            'color' => $request->query('color'),
            'mes' => $request->query('mes'),
        ];

        // Métrica de la matriz: 'peso' (Kilos) o 'cantidad' (Material). No es un filtro
        // de fila → se maneja aparte para no afectar la cascada ni la caché.
        $metrica = $request->query('metrica') === 'peso' ? 'peso' : 'cantidad';
        $columnaMetrica = $metrica === 'peso' ? 'Peso' : 'Cantidad';
        $decimales = $metrica === 'peso' ? 1 : 0;

        // Mes y nombre color (teñido) son MULTI-selección en CSV (mes: "5,6" | color: "A|B").
        $mesesSel = collect(explode(',', (string) $filtros['mes']))
            ->map(fn ($v) => (int) trim($v))->filter()->unique()->values()->all();
        $filtros['mes'] = implode(',', $mesesSel);

        // Al seleccionar Flog/Artículo/Tamaño/Color sin elegir mes, NO se preselecciona
        // ningún mes: se muestran TODOS los meses disponibles para esos filtros. El
        // usuario puede luego acotar a uno o varios meses desde los badges.

        // La tabla se muestra con CUALQUIER filtro. El Flog es opcional; solo cuando hay
        // un Flog específico se muestran los datos de Tipo/Cliente/Agente.
        $hayFlog = filled($filtros['flog']);
        $hayFiltro = filled($filtros['flog']) || filled($filtros['articulo'])
            || filled($filtros['tamano']) || filled($filtros['color']) || ! empty($mesesSel);

        // Filtros en cascada (faceted): Color no se aplica aquí porque su alcance
        // funcional es exclusivamente Rollos Teñido, no la matriz completa ni Crudo.
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
            if ($excepto !== 'mes' && ! empty($mesesSel)) {
                $query->whereRaw('MONTH(Fecha) IN ('.implode(',', $mesesSel).')');
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

        // Un registro por código. MAX(nombre) da una etiqueta estable cuando AX
        // contiene más de un nombre para el mismo Color.
        $opcionCombo = function (string $colCod, string $colNom, string $excepto, ?string $area = null) use ($aplicarFiltros) {
            $query = $aplicarFiltros(TrazaProduccion::query(), $excepto)
                ->whereNotNull($colCod)
                ->where($colCod, '<>', '')
                ->when($area, fn ($q, $nombreArea) => $q->where('NombreAlmacen', $nombreArea));

            return $query
                ->selectRaw("{$colCod} as codigo, MAX(NULLIF(LTRIM(RTRIM({$colNom})), '')) as nombre")
                ->groupBy($colCod)
                ->orderBy($colCod)
                ->get()
                ->map(fn ($r) => [
                    'codigo' => $r->codigo,
                    'label' => trim($r->codigo.(filled($r->nombre) ? ' / '.$r->nombre : '')),
                ])
                ->values();
        };

        if ($sinFiltros) {
            $opcionesFlog = Cache::remember('traza_opt_flog', 3600, fn () => $opcionFacet('Flogs', 'flog'));
            $opcionesArticulo = Cache::remember('traza_opt_articulo_combo', 3600, fn () => $opcionCombo('Articulo', 'NombreArticulo', 'articulo'));
            $opcionesTamano = Cache::remember('traza_opt_tamano', 3600, fn () => $opcionFacet('Tamano', 'tamano'));
            $opcionesColor = Cache::remember('traza_opt_color_combo_v2', 3600, fn () => $opcionCombo('Color', 'NombreColor', 'color', 'Rollos Teñido'));
        } else {
            $opcionesFlog = $opcionFacet('Flogs', 'flog');
            $opcionesArticulo = $opcionCombo('Articulo', 'NombreArticulo', 'articulo');
            $opcionesTamano = $opcionFacet('Tamano', 'tamano');
            $opcionesColor = $opcionCombo('Color', 'NombreColor', 'color', 'Rollos Teñido');
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
                'mes' => (int) $r->mes,
                'nombre' => $nombresMeses[(int) $r->mes] ?? (string) $r->mes,
                'registros' => (int) $r->registros,
            ]);

        // Valores por defecto (sin filtros → matriz vacía / estado inicial).
        $fechas = [];
        $columnasPeriodos = [];
        $areas = [];
        $totales = [];
        $info = null; // Tipo / Cliente / Agente (solo con un Flog específico).
        $dropdown = false; // Áreas expandibles con desglose por artículo/color.
        $produccion = null; // Sección "Producción": telares del flog (Orden/Localidad).
        $produccionCargando = false;
        $flogs = null; // Pestaña Flogs: datos desde sqlsrv_ti.
        $flogsCargando = false;
        $resumenFlog = null;
        $tablaAvancePedido = [];

        // part=matriz (resumen) | trazabilidad | produccion | flogs | all.
        $part = $request->query('part', 'matriz');
        if (! in_array($part, ['matriz', 'trazabilidad', 'produccion', 'flogs', 'all'], true)) {
            $part = 'matriz';
        }

        // La pantalla principal muestra el resumen. La matriz se conserva en su
        // servicio para la exportación y para reactivar las secciones posteriores.
        if ($hayFiltro && in_array($part, ['matriz', 'all'], true)) {
            $resumenFlog = $this->resumenSrv->build($filtros);
            $tablaAvancePedido = $this->produccionSrv->buildTablaAvance($filtros);
        }

        if ($hayFiltro && in_array($part, ['trazabilidad', 'all'], true)) {
            $matriz = $this->matriz->build($filtros, $metrica);
            $fechas = $matriz['fechas'];
            $columnasPeriodos = $matriz['columnasPeriodos'];
            $areas = $matriz['areas'];
            $totales = $matriz['totales'];
            $info = $matriz['info'];
            $dropdown = $matriz['dropdown'];
        }

        if ($hayFiltro && $part !== 'matriz') {
            // Filtros amplios (p. ej. solo Tamaño) pueden devolver miles de órdenes.
            set_time_limit(300);
        }

        if ($hayFiltro && in_array($part, ['produccion', 'all'], true)) {
            $produccion = $this->produccionSrv->build($filtros);
        }

        $produccionCargando = false;

        if ($hayFlog && $part === 'flogs') {
            $flogs = $this->flogsSrv->build($filtros['flog']);
        }

        $flogsCargando = false;

        $datosVista = compact(
            'fechas', 'columnasPeriodos', 'areas', 'totales', 'filtros', 'hayFlog', 'hayFiltro', 'info',
            'metrica', 'decimales', 'dropdown', 'produccion', 'produccionCargando',
            'flogs', 'flogsCargando',
            'opcionesFlog', 'opcionesArticulo', 'opcionesTamano', 'opcionesColor',
            'mesesDisponibles', 'resumenFlog', 'tablaAvancePedido'
        );

        // Respuesta AJAX: solo el bloque de resultado + las opciones de los selects
        // (para refrescar la cascada sin recargar la página).
        if ($request->ajax()) {
            if ($part === 'trazabilidad') {
                return response()->json([
                    'detalleHtml' => view('modulos.trazabilidad.resumen._matriz_detalle', $datosVista)->render(),
                    'filtros' => $filtros,
                ]);
            }

            if ($part === 'produccion') {
                return response()->json([
                    'produccionHtml' => view('modulos.trazabilidad._produccion', [
                        'produccion' => $produccion ?? [
                            'crudo' => ['ordenes' => [], 'noEncontradas' => [], 'resumen' => []],
                            'rollosTenido' => ['maquinas' => [], 'resumen' => ['maquinas' => 0, 'ordenes' => 0]],
                        ],
                        'filtros' => $filtros,
                    ])->render(),
                    'prodAlertas' => (int) ($produccion['crudo']['resumen']['alertas'] ?? 0),
                    'filtros' => $filtros,
                ]);
            }

            if ($part === 'flogs') {
                return response()->json([
                    'flogsHtml' => view('modulos.trazabilidad._flogs', [
                        'flogs' => $flogs ?? [
                            'estado' => 'not_found',
                            'encontrado' => false,
                            'errorTipo' => null,
                            'errorMensaje' => null,
                            'general' => [],
                            'etiquetas' => [],
                            'empaques' => [],
                            'lineas' => [],
                        ],
                        'filtros' => $filtros,
                    ])->render(),
                    'filtros' => $filtros,
                ]);
            }

            return response()->json([
                'resultado' => view('modulos.trazabilidad._resultado', $datosVista)->render(),
                'opciones' => [
                    'flog' => $opcionesFlog->values(),
                    'articulo' => $opcionesArticulo->values(), // [{codigo, label}]
                    'tamano' => $opcionesTamano->values(),
                    'color' => $opcionesColor->values(),     // [{codigo, label}]
                    'mes' => $mesesDisponibles->values(),
                ],
                'filtros' => $filtros,
            ]);
        }

        return view('modulos.trazabilidad.index', $datosVista);
    }

    /**
     * Exporta la matriz de Trazabilidad (con los filtros activos) a un Excel
     * con logo de Towell, resumen de filtros y la tabla con colores por área.
     */
    public function exportar(Request $request)
    {
        abort_unless(userCan('acceso', 'Trazabilidad'), 403, 'No tienes acceso al módulo de Trazabilidad.');

        $filtros = [
            'flog' => $request->query('flog'),
            'articulo' => $request->query('articulo'),
            'tamano' => $request->query('tamano'),
            'color' => $request->query('color'),
            'mes' => $request->query('mes'),
        ];

        // Normaliza el CSV de meses (igual que en index).
        $mesesSel = collect(explode(',', (string) $filtros['mes']))
            ->map(fn ($v) => (int) trim($v))->filter()->unique()->values()->all();
        $filtros['mes'] = implode(',', $mesesSel);

        $hayFiltro = filled($filtros['flog']) || filled($filtros['articulo'])
            || filled($filtros['tamano']) || filled($filtros['color']) || ! empty($mesesSel);

        abort_unless($hayFiltro, 422, 'Selecciona al menos un filtro antes de exportar.');

        // El reporte para contabilidad incluye SIEMPRE las dos métricas: una tabla
        // en Cantidad (piezas/material) y otra en Kilos (peso).
        $matrices = [
            'cantidad' => $this->matriz->build($filtros, 'cantidad'),
            'peso' => $this->matriz->build($filtros, 'peso'),
        ];

        // Nombre de archivo legible (se envía a contabilidad). Incluye el Flog si
        // hay uno específico y la fecha en formato día-mes-año.
        $sufijo = filled($filtros['flog']) ? ' - Flog '.$filtros['flog'] : '';
        $nombreArchivo = 'Reporte Trazabilidad Produccion'.$sufijo.' - '.now()->format('d-m-Y').'.xlsx';

        return Excel::download(new TrazabilidadExport($matrices, $filtros), $nombreArchivo);
    }

    /**
     * Sirve imágenes de Flog almacenadas en la ruta de red de TI (UNC).
     */
    public function flogArchivo(Request $request)
    {
        abort_unless(userCan('acceso', 'Trazabilidad'), 403, 'No tienes acceso al módulo de Trazabilidad.');

        $archivo = basename((string) $request->query('file', ''));
        abort_unless($archivo !== '', 404);

        $ruta = $this->flogsSrv->rutaAbsolutaImagen($archivo);
        abort_unless($ruta !== null, 404);

        return response()->file($ruta);
    }
}
