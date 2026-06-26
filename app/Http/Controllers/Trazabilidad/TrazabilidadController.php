<?php

namespace App\Http\Controllers\Trazabilidad;

use App\Exports\TrazabilidadExport;
use App\Http\Controllers\Controller;
use App\Models\Trazabilidad\TrazaProduccion;
use App\Services\Trazabilidad\TrazabilidadMatrixService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class TrazabilidadController extends Controller
{
    public function __construct(private TrazabilidadMatrixService $matriz) {}

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

        // Select combinado "código / nombre" (Artículo y Color), faceted.
        $opcionCombo = fn (string $colCod, string $colNom, string $excepto) => $aplicarFiltros(TrazaProduccion::query(), $excepto)
            ->whereNotNull($colCod)->where($colCod, '<>', '')
            ->select($colCod, $colNom)->distinct()->orderBy($colCod)->get()
            ->map(fn ($r) => [
                'codigo' => $r->{$colCod},
                'label' => trim($r->{$colCod}.(filled($r->{$colNom}) ? ' / '.$r->{$colNom} : '')),
            ])->values();

        if ($sinFiltros) {
            $opcionesFlog = Cache::remember('traza_opt_flog', 3600, fn () => $opcionFacet('Flogs', 'flog'));
            $opcionesArticulo = Cache::remember('traza_opt_articulo_combo', 3600, fn () => $opcionCombo('Articulo', 'NombreArticulo', 'articulo'));
            $opcionesTamano = Cache::remember('traza_opt_tamano', 3600, fn () => $opcionFacet('Tamano', 'tamano'));
            $opcionesColor = Cache::remember('traza_opt_color_combo', 3600, fn () => $opcionCombo('Color', 'NombreColor', 'color'));
        } else {
            $opcionesFlog = $opcionFacet('Flogs', 'flog');
            $opcionesArticulo = $opcionCombo('Articulo', 'NombreArticulo', 'articulo');
            $opcionesTamano = $opcionFacet('Tamano', 'tamano');
            $opcionesColor = $opcionCombo('Color', 'NombreColor', 'color');
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
        $areas = [];
        $totales = [];
        $info = null; // Tipo / Cliente / Agente (solo con un Flog específico).

        // La matriz se calcula con CUALQUIER filtro activo. La lógica vive en el
        // servicio compartido para que la web y la exportación a Excel coincidan.
        if ($hayFiltro) {
            $matriz = $this->matriz->build($filtros, $metrica);
            $fechas = $matriz['fechas'];
            $areas = $matriz['areas'];
            $totales = $matriz['totales'];
            $info = $matriz['info'];
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
}
