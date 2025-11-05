<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PronosticosService
{
    private const CONN = 'sqlsrv_ti';

    /**
     * Punto único para obtener ambos: [batas, otros]
     * @param array|string $meses ['2025-08','2025-09'] o "2025-08,2025-09"
     * @return array{0: array, 1: array} [batas, otros]
     */
    public function obtenerPronosticos($meses): array
    {
        $rangos = $this->construirRangos($meses);
        if (empty($rangos)) {
            return [[], []];
        }

        $otros = $this->obtenerOtros($rangos);
        $batas = $this->obtenerBatas($rangos);

        return [$batas, $otros];
    }

    /**
     * Convierte meses a rangos Y-m-d. Acepta array o "2025-11,2025-12".
     */
    private function construirRangos($meses): array
    {
        if (is_string($meses)) {
            $meses = array_filter(array_map('trim', explode(',', $meses)));
        }
        if (!is_array($meses)) return [];

        $rangos = [];
        foreach ($meses as $m) {
            try {
                $c = Carbon::createFromFormat('Y-m', $m);
            } catch (\Throwable $e) {
                continue;
            }
            $rangos[] = [
                'inicio' => $c->copy()->startOfMonth()->format('Y-m-d'),
                'fin'    => $c->copy()->endOfMonth()->format('Y-m-d'),
            ];
        }
        return $rangos;
    }

    /** Etiquetas compactas para selects (agrupar por mes para que salgan todos los meses) */
    private function etiquetasProyectoSql(): array
    {
        $mes = "CASE MONTH(l.FECHACANCELACION)\n                WHEN 1 THEN 'ENERO' WHEN 2 THEN 'FEBRERO' WHEN 3 THEN 'MARZO'\n                WHEN 4 THEN 'ABRIL' WHEN 5 THEN 'MAYO'    WHEN 6 THEN 'JUNIO'\n                WHEN 7 THEN 'JULIO' WHEN 8 THEN 'AGOSTO'  WHEN 9 THEN 'SEPTIEMBRE'\n                WHEN 10 THEN 'OCTUBRE' WHEN 11 THEN 'NOVIEMBRE' WHEN 12 THEN 'DICIEMBRE'\n            END";
        return [
            "('Pronóstico del Mes ' + $mes)",
            "'Aprobado por finanzas'",
            "'NAC - 1'",
        ];
    }

    /**
     * Otros (no batas): ITEMTYPEID NOT BETWEEN 10 AND 19
     */
    private function obtenerOtros(array $rangos): array
    {
        $rangoWhere = function ($q) use ($rangos) {
            foreach ($rangos as $r) {
                $q->orWhere(function ($sq) use ($r) {
                    $sq->where('pf.TRANSDATE', '>=', $r['inicio'])
                       ->where('pf.TRANSDATE', '<=', $r['fin']);
                });
            }
        };

        // Etiquetas locales basadas en pf.TRANSDATE (agrupar por mes para que salgan todos los meses)
        $mesPf = "CASE MONTH(pf.TRANSDATE)\n                WHEN 1 THEN 'ENERO' WHEN 2 THEN 'FEBRERO' WHEN 3 THEN 'MARZO'\n                WHEN 4 THEN 'ABRIL' WHEN 5 THEN 'MAYO'    WHEN 6 THEN 'JUNIO'\n                WHEN 7 THEN 'JULIO' WHEN 8 THEN 'AGOSTO'  WHEN 9 THEN 'SEPTIEMBRE'\n                WHEN 10 THEN 'OCTUBRE' WHEN 11 THEN 'NOVIEMBRE' WHEN 12 THEN 'DICIEMBRE'\n            END";
        $idFlogExpr = "('RS - ' + $mesPf)";
        $estadoExpr = "'Aprobado por finanzas'";
        $catCalExpr = "'NAC - 1'";

        try {
            return DB::connection(self::CONN)
                ->table('dbo.TwPronosticosFlogs as pf')
                ->where('pf.TIPOPEDIDO', 2)
                ->where(function ($q) use ($rangoWhere) { $rangoWhere($q); })
                ->where(function ($q) {
                    $q->where('pf.ITEMTYPEID', '<', 10)
                      ->orWhere('pf.ITEMTYPEID', '>', 19);
                })
                ->groupBy(
                    'pf.CUSTNAME','pf.ANCHO','pf.LARGO','pf.ITEMID','pf.ITEMNAME',
                    'pf.INVENTSIZEID','pf.TIPOHILOID','pf.VALORAGREGADO',
                    DB::raw('MONTH(pf.TRANSDATE)')
                )
                ->selectRaw("$idFlogExpr as IDFLOG, $estadoExpr as ESTADO, $idFlogExpr as NOMBREPROYECTO, $catCalExpr as CATEGORIACALIDAD")
                ->addSelect([
                    'pf.CUSTNAME',
                    DB::raw('pf.ANCHO as ANCHO'),
                    DB::raw('pf.LARGO as LARGO'),
                    'pf.ITEMID',
                    'pf.ITEMNAME',
                    'pf.INVENTSIZEID',
                    DB::raw('pf.TIPOHILOID as TipoHiloId'),
                    DB::raw('pf.VALORAGREGADO as ValorAgregado'),
                ])
                ->selectRaw('MIN(pf.TRANSDATE) as FECHACANCELACION, ROUND(SUM(ISNULL(pf.INVENTQTY,0)), 0) as CANTIDAD, MIN(pf.ITEMTYPEID) as ITEMTYPEID')
                ->selectRaw('MAX(pf.RASURADOCRUDO) as RASURADOCRUDO')
                ->orderBy('pf.CUSTNAME')->orderBy('pf.ITEMID')
                ->get()->toArray();
        } catch (\Throwable $e) {
            Log::error('Pronosticos.obtenerOtros', ['msg' => $e->getMessage()]);
            return [];
        }
    }


    private function obtenerBatas(array $rangos): array
    {
        [$idFlogExpr, $estadoExpr, $catCalExpr] = $this->etiquetasProyectoSql();

        $rangoL = function ($q) use ($rangos) {
            foreach ($rangos as $r) {
                $q->orWhere(function ($sq) use ($r) {
                    $sq->whereRaw('CAST(l.FECHACANCELACION AS DATE) >= ?', [$r['inicio']])
                       ->whereRaw('CAST(l.FECHACANCELACION AS DATE) <= ?', [$r['fin']]);
                });
            }
        };

        $rangoPf = function ($q) use ($rangos) {
            foreach ($rangos as $r) {
                $q->orWhere(function ($sq) use ($r) {
                    $sq->where('pfp.TRANSDATE', '>=', $r['inicio'])
                       ->where('pfp.TRANSDATE', '<=', $r['fin']);
                });
            }
        };

        try {
            return DB::connection(self::CONN)
                ->table('TI_PRO.dbo.TWFLOGSITEMLINE as l')
                ->join('TI_PRO.dbo.TWFLOGBOMID as b', function ($j) {
                    $j->on('b.IdFlog', '=', 'l.IdFlog')
                      ->on('b.refRecId', '=', 'l.RecId');
                })
                ->join('TI_PRO.dbo.TWFLOGSTABLE as f', 'l.IdFlog', '=', 'f.IdFlog')
                ->join('dbo.TwFlogsItemLine as il', function ($j) {
                    $j->on('il.IdFlog', '=', 'b.IdFlog')
                      ->on('il.RecId', '=', 'b.refRecId')
                      ->where('il.EstadoLinea', 0)
                      ->where('il.IdFlog', 'RS'); // 'RS' literal
                })
                ->join('dbo.TwPronosticosFlogs as pfp', function ($j) use ($rangoPf) {
                    $j->where('pfp.TIPOPEDIDO', 2)
                      ->whereRaw('ISNUMERIC(pfp.ITEMTYPEID) = 1 AND (CAST(pfp.ITEMTYPEID AS INT) < 10 OR CAST(pfp.ITEMTYPEID AS INT) > 19)')
                      // <-- CRUCE CORRECTO: TwFlogsItemLine.CodigoBarras = TwPronosticosFlogs.PurchBarcode
                      ->whereColumn('il.PurchBarcode', 'pfp.CodigoBarras')
                      // rango por el mes seleccionado en pfp
                      ->where(function ($q) use ($rangoPf) { $rangoPf($q); });
                })
                ->select(
                    DB::raw("$idFlogExpr as IDFLOG"),
                    DB::raw("$estadoExpr as ESTADO"),
                    DB::raw("$idFlogExpr as NOMBREPROYECTO"),
                    'f.CUSTNAME as CUSTNAME',
                    DB::raw("$catCalExpr as CATEGORIACALIDAD"),
                    'b.ANCHO as ANCHO',
                    'b.LARGO as LARGO',
                    'b.ITEMID as ITEMID',
                    DB::raw('b.ITEMNAME as ITEMNAME'),
                    'b.INVENTSIZEID as INVENTSIZEID',
                    'b.TIPOHILOID as TIPOHILOID',
                    'l.VALORAGREGADO as VALORAGREGADO',
                    DB::raw('CAST(l.FECHACANCELACION AS DATE) as FECHACANCELACION'),
                    DB::raw('ROUND(SUM(ISNULL(pfp.INVENTQTY,0) * ISNULL(CAST(b.BomQty AS DECIMAL(18,4)),0)), 0) as CANTIDAD'),
                    'l.ITEMTYPEID as ITEMTYPEID',
                    'b.RASURADO as RASURADO'
                )
                ->whereRaw('ISNUMERIC(l.ITEMTYPEID) = 1 AND CAST(l.ITEMTYPEID AS INT) BETWEEN 10 AND 19')
                ->where('l.EstadoLinea', 0)
                ->where('l.PorEntregar', '!=', 0)
                ->where(function ($q) use ($rangoL) { $rangoL($q); })
                ->groupBy(
                    'f.CUSTNAME', 'b.ANCHO', 'b.LARGO', 'b.ITEMID', 'b.ITEMNAME',
                    'b.INVENTSIZEID', 'b.TIPOHILOID', 'l.VALORAGREGADO',
                    DB::raw('CAST(l.FECHACANCELACION AS DATE)'),
                    DB::raw('MONTH(l.FECHACANCELACION)'),
                    'l.ITEMTYPEID', 'b.RASURADO'
                )
                ->orderBy(DB::raw('CAST(l.FECHACANCELACION AS DATE)'), 'asc')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('Pronosticos.obtenerBatas 500', ['msg' => $e->getMessage()]);
            return [];
        }
    }



}
