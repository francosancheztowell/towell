<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PronosticosService
{
    private const CONN = 'sqlsrv_ti';   // TI_PRO

    /**
     * Punto único para obtener ambos (sin guardar en ReqPronosticos)
     * @param array|string $meses ['2025-08','2025-09'] o "2025-08,2025-09"
     * @return array{0: array, 1: array}
     */
    public function obtenerPronosticos($meses): array
    {
        $rangos = $this->construirRangos($meses);
        if (empty($rangos)) return [[], []];

        $batas = $this->obtenerBatas($rangos);
        $otros = $this->obtenerOtros($rangos);

        // Ya no se guarda en ReqPronosticos
        // $this->guardarEnReqPronosticos($batas, $otros, $rangos);

        return [$batas, $otros];
    }

    private function construirRangos($meses): array
    {
        if (is_string($meses)) $meses = array_filter(array_map('trim', explode(',', $meses)));
        if (!is_array($meses)) return [];

        $rangos = [];
        foreach ($meses as $m) {
            try {
                $c = Carbon::createFromFormat('Y-m', $m);
                $rangos[] = [
                    'inicio' => $c->copy()->startOfMonth()->format('Y-m-d'),
                    'fin'    => $c->copy()->endOfMonth()->format('Y-m-d'),
                ];
            } catch (\Throwable $e) { /* ignora inválidos */ }
        }
        return $rangos;
    }

    // ==== CONSULTA "OTROS" (INTACTA) =========================================
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

        try {
            $resultados = DB::connection(self::CONN)
                ->table('dbo.TwPronosticosFlogs as pf')
                ->leftJoin('dbo.TwFlogsItemLine as il', function ($j) {
                    $j->on('il.PurchBarcode', '=', 'pf.CodigoBarras')
                      ->where('il.EstadoLinea', 0);
                })
                ->leftJoin('dbo.TWFLOGSTABLE as f', function ($j) {
                    $j->on('f.IdFlog', '=', 'il.IdFlog');
                })
                ->leftJoin('dbo.TWFLOGSCUSTOMER as c', function ($j) {
                    $j->on('c.IdFlog', '=', 'f.IdFlog');
                })
                ->where('pf.TIPOPEDIDO', 2)
                ->where(function ($q) use ($rangoWhere) { $rangoWhere($q); })
                ->where(function ($q) {
                    $q->where('pf.ITEMTYPEID', '<', 10)
                      ->orWhere('pf.ITEMTYPEID', '>', 19);
                })
                ->groupBy(
                    'pf.CUSTNAME','pf.ANCHO','pf.LARGO','pf.ITEMID','pf.ITEMNAME',
                    'pf.INVENTSIZEID',
                    DB::raw('MONTH(pf.TRANSDATE)')
                )
                ->select(
                    DB::raw('MAX(CASE WHEN il.IdFlog IS NOT NULL AND il.IdFlog LIKE \'RS%\' THEN il.IdFlog ELSE NULL END) as IDFLOG'),
                    DB::raw('MAX(f.EstadoFlog) as ESTADO'),
                    DB::raw('MAX(f.NameProyect) as NOMBREPROYECTO'),
                    DB::raw("'NAC - 1' as CATEGORIACALIDAD"),
                    'pf.CUSTNAME',
                    DB::raw('MAX(pf.CodigoBarras) as CodigoBarras'),
                    DB::raw('CAST(MAX(pf.ANCHO) AS REAL) as ANCHO'),
                    DB::raw('CAST(MAX(pf.LARGO) AS REAL) as LARGO'),
                    'pf.ITEMID',
                    'pf.ITEMNAME',
                    'pf.INVENTSIZEID',
                    DB::raw('MAX(il.TipoHiloId) as TIPOHILOID'),
                    DB::raw('MAX(il.ValorAgregado) as VALORAGREGADO'),
                    DB::raw('MIN(pf.TRANSDATE) as FECHACANCELACION'),
                    DB::raw('ROUND(SUM(ISNULL(pf.INVENTQTY,0)), 0) as CANTIDAD'),
                    DB::raw('MIN(pf.ITEMTYPEID) as ITEMTYPEID'),
                    DB::raw('MAX(pf.RASURADOCRUDO) as RASURADOCRUDO')
                )
                ->get()
                ->toArray();
            return $resultados;
        } catch (\Throwable $e) {
            Log::error('Pronosticos.obtenerOtros', ['msg' => $e->getMessage()]);
            return [];
        }
    }

    // ==== CONSULTA "BATAS"  ========================================
    private function obtenerBatas(array $rangos): array
    {
        $rangoPf = function ($q) use ($rangos) {
            foreach ($rangos as $r) {
                $q->orWhere(function ($sq) use ($r) {
                    $sq->where('pfp.TRANSDATE', '>=', $r['inicio'])
                       ->where('pfp.TRANSDATE', '<=', $r['fin']);
                });
            }
        };

        try {
            $resultados = DB::connection(self::CONN)
                ->table('dbo.TwPronosticosFlogs as pfp')
                ->where('pfp.TIPOPEDIDO', 2)
                ->whereRaw('ISNUMERIC(pfp.ITEMTYPEID) = 1 AND (CAST(pfp.ITEMTYPEID AS INT) >= 10 AND CAST(pfp.ITEMTYPEID AS INT) <= 19)')
                ->where(function ($q) use ($rangoPf) { $rangoPf($q); })
                ->join('dbo.TwFlogsItemLine as il', function ($j) {
                    $j->where('il.EstadoLinea', 0)
                      ->where('il.IdFlog', 'LIKE', 'RS%')
                      ->on('il.PurchBarcode', '=', 'pfp.CodigoBarras');
                })
                ->join('dbo.TWFLOGBOMID as b', function ($j) {
                    $j->on('b.IdFlog', '=', 'il.IdFlog')
                      ->on('b.refRecId', '=', 'il.RecId');
                })
                ->join('dbo.TWFLOGSTABLE as f', function ($j) {
                    $j->on('f.IdFlog', '=', 'b.IdFlog');
                })
                ->select(
                    'il.IdFlog as IDFLOG',
                    'f.EstadoFlog as ESTADO',
                    'f.NameProyect as NOMBREPROYECTO',
                    'pfp.CUSTNAME as CUSTNAME',
                    DB::raw("'NAC - 1' as CATEGORIACALIDAD"),
                    DB::raw('CAST(b.ANCHO AS REAL) as ANCHO'),
                    DB::raw('CAST(b.LARGO AS REAL) as LARGO'),
                    'b.ITEMID as ITEMID',
                    DB::raw('b.ITEMNAME as ITEMNAME'),
                    'b.INVENTSIZEID as INVENTSIZEID',
                    'b.TIPOHILOID as TIPOHILOID',
                    'il.VALORAGREGADO as VALORAGREGADO',
                    DB::raw('CAST(pfp.TRANSDATE AS DATE) as FECHACANCELACION'),
                    DB::raw('ROUND(SUM(ISNULL(pfp.INVENTQTY,0) * ISNULL(CAST(b.BomQty AS DECIMAL(18,4)),0)), 0) as CANTIDAD'),
                    'pfp.ITEMTYPEID as ITEMTYPEID',
                    'b.RASURADO as RASURADO'
                )
                ->groupBy(
                    'il.IdFlog',
                    'f.EstadoFlog',
                    'f.NameProyect',
                    'pfp.CUSTNAME',
                    'b.ANCHO',
                    'b.LARGO',
                    'b.ITEMID',
                    'b.ITEMNAME',
                    'b.INVENTSIZEID',
                    'b.TIPOHILOID',
                    'il.VALORAGREGADO',
                    'pfp.TRANSDATE',
                    'pfp.ITEMTYPEID',
                    'b.RASURADO'
                )
                ->get()
                ->toArray();
            return $resultados;
        } catch (\Throwable $e) {
            Log::error('Pronosticos.obtenerBatas 500', [
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }
}
