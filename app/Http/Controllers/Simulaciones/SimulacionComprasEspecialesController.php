<?php

namespace App\Http\Controllers\Simulaciones;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimulacionComprasEspecialesController extends \App\Http\Controllers\Controller
{
    /**
     * Vista principal de Compras Especiales para Simulación.
     */
    public function index(Request $request)
    {
        try {
            $filtros   = $this->gestionarFiltros($request);

            // 1) Consulta base (excluye batas)
            $normales  = $this->obtenerRegistrosNormales($filtros);

            // 2) Consulta de batas (solo 10..19)
            $batas     = $this->obtenerRegistrosBatas($filtros);

            // 3) Fusionar para la vista (sin conflictos)
            $registros = $this->formatearRegistros($normales, $batas);

            return view('modulos.simulacion.altas-especiales', compact('registros'));
        } catch (\Throwable $e) {
            Log::error('SimulacionComprasEspecialesController.index', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            $registros    = collect();
            $errorMensaje = 'Ocurrió un error al cargar los datos: ' . $e->getMessage();
            return view('modulos.simulacion.altas-especiales', compact('registros', 'errorMensaje'));
        }
    }

    /* ------------------------- Filtros ------------------------- */

    private function gestionarFiltros(Request $request): array
    {
        if (!$request->has('column') && !$request->has('value')) {
            session()->forget('filtros_busqueda');
            return ['columns' => [], 'values' => []];
        }

        if ($request->has('column') && $request->has('value')) {
            session()->put('filtros_busqueda', [
                'column' => $request->input('column', []),
                'value'  => $request->input('value', []),
            ]);
        }

        $columns = $request->input('column', session('filtros_busqueda.column', []));
        $values  = $request->input('value',  session('filtros_busqueda.value',  []));
        return [
            'columns' => is_array($columns) ? $columns : [],
            'values'  => is_array($values)  ? $values  : [],
        ];
    }

    private function aplicarFiltros($query, array $filtros, array $mapeo): void
    {
        $cols = $filtros['columns'] ?? [];
        $vals = $filtros['values']  ?? [];

        foreach ($cols as $i => $vista) {
            $val = $vals[$i] ?? null;
            if ($vista === null || $vista === '' || $val === null || $val === '') continue;
            if (!isset($mapeo[$vista])) continue;

            $campo = $mapeo[$vista];
            $query->where($campo, 'LIKE', '%' . $val . '%');
        }
    }
    
    /** Estado como texto (4 = Aprobado por finanzas). */
    private function exprEstadoNombre(): \Illuminate\Database\Query\Expression
    {
        return DB::raw("
            CASE
                WHEN f.ESTADOFLOG = 4 THEN 'Aprobado por finanzas'
                ELSE CAST(f.ESTADOFLOG AS VARCHAR(20))
            END as Estado
        ");
    }

    /* ------------------------- Consultas ------------------------- */

    /**
     * 1) Consulta BASE (NO batas) — misma lógica:
     *    - EstadoFlog = 4
     *    - TipoPedido = 1 ó 3
     *    - DataAreaId = 'PRO'
     *    - EstadoLinea = 0
     *    - EXCLUIR ItemTypeId 10–19
     */
    private function obtenerRegistrosNormales(array $filtros): Collection
    {
        $q = DB::connection('sqlsrv_ti')
            ->table('TI_PRO.dbo.TWFLOGSITEMLINE as l')
            ->join('TI_PRO.dbo.TWFLOGSTABLE as f', 'l.IDFLOG', '=', 'f.IDFLOG')
            ->leftJoin('TI_PRO.dbo.TWFLOGSCUSTOMER as c', 'c.IDFLOG', '=', 'f.IDFLOG')
            ->select(
                'f.IDFLOG as FlogsId',
                $this->exprEstadoNombre(),
                'f.NAMEPROYECT as NombreProyecto',
                'f.CUSTNAME as CustName',
                'c.CATEGORIACALIDAD as CategoriaCalidad',
                DB::raw('MAX(l.ANCHO)            as Ancho'),
                DB::raw('MAX(l.LARGO)            as Largo'),
                DB::raw('MAX(l.ITEMID)           as ItemId'),
                DB::raw('MAX(l.ITEMNAME)         as ItemName'),
                DB::raw('MAX(l.INVENTSIZEID)     as InventSizeId'),
                DB::raw('MAX(l.TIPOHILOID)       as TipoHilo'),
                DB::raw('MAX(l.VALORAGREGADO)    as ValorAgregado'),
                DB::raw('CAST(l.FECHACANCELACION AS DATE) as FechaCancelacion'),
                DB::raw('SUM(l.PORENTREGAR)      as Cantidad'),
                DB::raw('MAX(l.ITEMTYPEID)       as ItemTypeId'),
                DB::raw('MAX(l.RASURADOCRUDO) as RasuradoCrudo')
            )
            ->where('f.ESTADOFLOG', 4)
            ->whereIn('f.TIPOPEDIDO', [1, 3])
            ->where('f.DATAAREAID', 'PRO')
            ->where('l.ESTADOLINEA', 0)
            // EXCLUIR 10..19 (compatibilidad versiones: usar ISNUMERIC + CAST)
            ->whereRaw("NOT (ISNUMERIC(l.ITEMTYPEID) = 1 AND CAST(l.ITEMTYPEID AS INT) BETWEEN 10 AND 19)");

        // Filtros dinámicos (mapeo de esta consulta)
        $this->aplicarFiltros($q, $filtros, $this->mapeoNormales());

        return $q->groupBy(
                'f.IDFLOG',
                'f.ESTADOFLOG',
                'f.NAMEPROYECT',
                'f.CUSTNAME',
                'c.CATEGORIACALIDAD',
                DB::raw('CAST(l.FECHACANCELACION AS DATE)')
            )
            ->orderBy(DB::raw('CAST(l.FECHACANCELACION AS DATE)'), 'asc')
            ->get();
    }

    /**
     * 2) Consulta Batas (SOLO ItemTypeId 10–19) con datos de BOMID
     *    - TwFlogsItemLine.EstadoLinea = 0
     *    - TwFlogsItemLine.ItemTypeId BETWEEN 10 AND 19 (numérico)
     *    - Campos principales provienen de TWFLOGBOMID según mapeo entregado
     */
    private function obtenerRegistrosBatas(array $filtros): Collection
    {
        $q = DB::connection('sqlsrv_ti')
            ->table('TI_PRO.dbo.TWFLOGSITEMLINE as l')
            ->join('TI_PRO.dbo.TWFLOGBOMID as b', function ($j) {
                $j->on('b.IDFLOG', '=', 'l.IDFLOG')
                  ->on('b.REFRECID', '=', 'l.RECID');
            })
            ->join('TI_PRO.dbo.TWFLOGSTABLE as f', 'l.IDFLOG', '=', 'f.IDFLOG')
            ->leftJoin('TI_PRO.dbo.TWFLOGSCUSTOMER as c', 'c.IDFLOG', '=', 'f.IDFLOG')
            ->select(
                'f.IDFLOG as FlogsId',
                $this->exprEstadoNombre(),
                'f.NAMEPROYECT as NombreProyecto',
                'f.CUSTNAME as CustName',
                'c.CATEGORIACALIDAD as CategoriaCalidad',
                'b.ANCHO as Ancho',
                'b.LARGO as Largo',
                'b.ITEMID as ItemId',
                'b.ITEMNAME as ItemName',
                'b.INVENTSIZEID as InventSizeId',
                'b.TIPOHILOID as TipoHilo',
                'l.VALORAGREGADO as ValorAgregado',
                DB::raw('CAST(l.FECHACANCELACION AS DATE) as FechaCancelacion'),
                DB::raw('SUM(b.CONSUMOTOTAL) as Cantidad'),
                'l.ITEMTYPEID as ItemTypeId',
                'b.RASURADO as Rasurado'
            )
            // SOLO 10..19 (compatibilidad versiones)
            ->whereRaw('ISNUMERIC(l.ITEMTYPEID) = 1 AND CAST(l.ITEMTYPEID AS INT) BETWEEN 10 AND 19')
            // Filtros base fuertes
            ->where('f.ESTADOFLOG', 4)
            ->whereIn('f.TIPOPEDIDO', [1, 3])
            ->where('f.DATAAREAID', 'PRO')
            ->where('l.ESTADOLINEA', 0)
            ->where('l.PORENTREGAR', '!=', 0);

        // Filtros dinámicos según mapeo de batas
        $this->aplicarFiltros($q, $filtros, $this->mapeoBatas());

        return $q->groupBy(
                'f.IDFLOG',
                'f.ESTADOFLOG',
                'f.NAMEPROYECT',
                'f.CUSTNAME',
                'c.CATEGORIACALIDAD',
                'b.ANCHO',
                'b.LARGO',
                'b.ITEMID',
                'b.ITEMNAME',
                'b.INVENTSIZEID',
                'b.TIPOHILOID',
                'b.RASURADO',
                'l.VALORAGREGADO',
                'l.ITEMTYPEID',
                DB::raw('CAST(l.FECHACANCELACION AS DATE)')
            )
            ->orderBy(DB::raw('CAST(l.FECHACANCELACION AS DATE)'), 'asc')
            ->get();
    }

    /* ------------------ Mapeos para filtros ------------------ */

    private function mapeoNormales(): array
    {
        return [
            'FlogsId'          => 'f.IDFLOG',
            'Estado'           => 'f.ESTADOFLOG',
            'NombreProyecto'   => 'f.NAMEPROYECT',
            'CustName'         => 'f.CUSTNAME',
            'CategoriaCalidad' => 'c.CATEGORIACALIDAD',
            'Ancho'            => 'l.ANCHO',
            'Largo'            => 'l.LARGO',
            'ItemId'           => 'l.ITEMID',
            'ItemName'         => 'l.ITEMNAME',
            'InventSizeId'     => 'l.INVENTSIZEID',
            'TipoHilo'         => 'l.TIPOHILOID',
            'ValorAgregado'    => 'l.VALORAGREGADO',
            'FechaCancelacion' => 'l.FECHACANCELACION',
        ];
    }

    private function mapeoBatas(): array
    {
        return [
            'FlogsId'          => 'f.IDFLOG',
            'Estado'           => 'f.ESTADOFLOG',
            'NombreProyecto'   => 'f.NAMEPROYECT',
            'CustName'         => 'f.CUSTNAME',
            'CategoriaCalidad' => 'c.CATEGORIACALIDAD',
            'Ancho'            => 'b.ANCHO',
            'Largo'            => 'b.LARGO',
            'ItemId'           => 'b.ITEMID',
            'ItemName'         => 'b.ITEMNAME',
            'InventSizeId'     => 'b.INVENTSIZEID',
            'TipoHilo'         => 'b.TIPOHILOID',
            'ValorAgregado'    => 'l.VALORAGREGADO',
            'FechaCancelacion' => 'l.FECHACANCELACION',
        ];
    }

    /* ---------------------- Formateo salida ---------------------- */

    private function formatearRegistros(Collection $normales, Collection $toallas): Collection
    {
        return $normales->merge($toallas)
            ->map(function ($r) {
                $tipo = null;
                $esBata = '';
                if (isset($r->ItemTypeId)) {
                    $val = (string)$r->ItemTypeId;
                    $tipoNumerico = (int)$val;

                    // En esta vista NO mostramos toallas (10..19)
                    if ($tipoNumerico >= 10 && $tipoNumerico <= 19) {
                        $tipo = null;      // ocultar 10..19
                        $esBata = 'bata';  // marcar tipo bata
                    } else {
                        // Oculta 0x (01, 02, ..., 09). Deja visible otros
                        if (!preg_match('/^0\d$/', $val)) {
                            $tipo = $val;
                        }
                    }
                }

                // Formatear Rasurado: 0 = "NA", 1 = "Normal", 2 = "Premium"
                // -1 = NULL o no existe, se dejará como null/vacío
                $rasuradoValor = null;

                // Para registros normales: usar RasuradoCrudo de ItemLine
                if (isset($r->RasuradoCrudo) && $r->RasuradoCrudo !== null && $r->RasuradoCrudo >= 0) {
                    $rasuradoNum = (int) $r->RasuradoCrudo;
                    if ($rasuradoNum === 0) {
                        $rasuradoValor = 'NA';
                    } elseif ($rasuradoNum === 1) {
                        $rasuradoValor = 'Normal';
                    } elseif ($rasuradoNum === 2) {
                        $rasuradoValor = 'Premium';
                    } else {
                        // Si es otro valor numérico, mantenerlo como string del número
                        $rasuradoValor = (string) $rasuradoNum;
                    }
                }
                // Para batas: usar Rasurado de BomId
                elseif (isset($r->Rasurado) && $r->Rasurado !== null && $r->Rasurado >= 0) {
                    $rasuradoNum = (int) $r->Rasurado;
                    if ($rasuradoNum === 0) {
                        $rasuradoValor = 'NA';
                    } elseif ($rasuradoNum === 1) {
                        $rasuradoValor = 'Normal';
                    } elseif ($rasuradoNum === 2) {
                        $rasuradoValor = 'Premium';
                    } else {
                        // Si es otro valor numérico, mantenerlo como string del número
                        $rasuradoValor = (string) $rasuradoNum;
                    }
                }

                return [
                    'FlogsId'          => $r->FlogsId ?? '',
                    'Estado'           => $r->Estado ?? '',
                    'NombreProyecto'   => $r->NombreProyecto ?? '',
                    'CustName'         => $r->CustName ?? '',
                    'CategoriaCalidad' => $r->CategoriaCalidad ?? '',
                    'Ancho'            => $r->Ancho ?? null,
                    'Largo'            => $r->Largo ?? null,
                    'ItemId'           => $r->ItemId ?? '',
                    'ItemName'         => $r->ItemName ?? '',
                    'InventSizeId'     => $r->InventSizeId ?? '',
                    'TipoHilo'         => $r->TipoHilo ?? '',
                    'ValorAgregado'    => $r->ValorAgregado ?? '',
                    'FechaCancelacion' => $r->FechaCancelacion ?? null,
                    'Cantidad'         => (float) ($r->Cantidad ?? 0),
                    'ItemTypeId'       => $tipo,
                    'EsBata'           => $esBata,
                    'Rasurado'         => $rasuradoValor,
                    'Razurado'         => $rasuradoValor, // Alias para compatibilidad
                ];
            })
            ->values();
    }

    /**
     * GET /simulacion/altas-especiales/nuevo
     * Carga la vista de Altas Especiales (formulario) para simulación.
     */
    public function nuevo(Request $request)
    {
        $prefill = [
            'idflog'       => $request->query('idflog') ?? $request->query('IDFLOG'),
            'itemid'       => $request->query('itemid') ?? $request->query('ITEMID'),
            'inventsizeid' => $request->query('inventsizeid') ?? $request->query('INVENTSIZEID'),
            'cantidad'     => $request->query('cantidad') ?? $request->query('CANTIDAD'),
            'tipohilo'     => $request->query('tipohilo') ?? $request->query('TIPOHILO'),
        ];
        return view('modulos.simulacion.simulacionform.altas', compact('prefill'));
    }
}

