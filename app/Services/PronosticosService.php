<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PronosticosService
{
    private const CONN = 'sqlsrv_ti';

    /**
     * Punto único para obtener ambos: [batas, otros]
     * @param array $meses ['2025-08','2025-09']
     * @return array{0: array, 1: array} [batas, otros]
     */
    public function obtenerPronosticos(array $meses): array
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
     * Convierte meses a rangos Y-m-d
     */
    private function construirRangos(array $meses): array
    {
        $rangos = [];
        
        foreach ($meses as $m) {
            $c = Carbon::createFromFormat('Y-m', $m);
            $rangos[] = [
                'inicio' => $c->copy()->startOfMonth()->format('Y-m-d'),
                'fin'    => $c->copy()->endOfMonth()->format('Y-m-d'),
            ];
        }

        return $rangos;
    }

    /**
     * Otros (no batas): ITEMTYPEID NOT BETWEEN 10 AND 19
     * Usa Query Builder.
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

        $mesAgg = "
            CASE 
                WHEN YEAR(MIN(pf.TRANSDATE)) = YEAR(MAX(pf.TRANSDATE))
                 AND MONTH(MIN(pf.TRANSDATE)) = MONTH(MAX(pf.TRANSDATE))
                THEN CASE MONTH(MIN(pf.TRANSDATE))
                    WHEN 1 THEN 'ENERO' WHEN 2 THEN 'FEBRERO' WHEN 3 THEN 'MARZO'
                    WHEN 4 THEN 'ABRIL' WHEN 5 THEN 'MAYO'    WHEN 6 THEN 'JUNIO'
                    WHEN 7 THEN 'JULIO' WHEN 8 THEN 'AGOSTO'  WHEN 9 THEN 'SEPTIEMBRE'
                    WHEN 10 THEN 'OCTUBRE' WHEN 11 THEN 'NOVIEMBRE' WHEN 12 THEN 'DICIEMBRE'
                END
                ELSE 'VARIOS MESES'
            END
        ";

        $idflogAgg = "'Pronóstico de ' + $mesAgg";
        $yearExpr  = 'YEAR(pf.TRANSDATE)';
        $monthExpr = 'MONTH(pf.TRANSDATE)';

        return DB::connection(self::CONN)
            ->table('TwPronosticosFlogs as pf')
            ->where('pf.TIPOPEDIDO', 2)
            ->where(function ($q) use ($rangoWhere) { 
                $rangoWhere($q); 
            })
            ->where(function ($q) {
                $q->where('pf.ITEMTYPEID', '<', 10)
                  ->orWhere('pf.ITEMTYPEID', '>', 19);
            })
            ->groupBy(
                DB::raw($yearExpr),
                DB::raw($monthExpr),
                'pf.CUSTNAME',
                'pf.ITEMID',
                'pf.INVENTSIZEID'
            )
            ->selectRaw("$idflogAgg as IDFLOG")
            ->addSelect([
                'pf.CUSTNAME',
                'pf.ITEMID',
                'pf.INVENTSIZEID',
            ])
            ->selectRaw('MIN(pf.ITEMNAME)      as ITEMNAME')
            ->selectRaw('MIN(pf.TIPOHILOID)    as TIPOHILOID')
            ->selectRaw('MIN(pf.RASURADOCRUDO) as RASURADOCRUDO')
            ->selectRaw('MIN(pf.VALORAGREGADO) as VALORAGREGADO')
            ->selectRaw('MIN(pf.ANCHO)         as ANCHO')
            ->selectRaw('SUM(pf.INVENTQTY)     as PORENTREGAR')
            ->selectRaw('MIN(pf.ITEMTYPEID)    as ITEMTYPEID')
            ->selectRaw('MIN(pf.CODIGOBARRAS)  as CODIGOBARRAS')
            ->selectRaw("$yearExpr  as ANIO")
            ->selectRaw("$monthExpr as MES")
            ->orderBy(DB::raw($yearExpr))
            ->orderBy(DB::raw($monthExpr))
            ->get()
            ->toArray();
    }

    /**
     * Batas (ITEMTYPEID BETWEEN 10 AND 19).
     * Usa SQL crudo con CTEs y bindings.
     */
    private function obtenerBatas(array $rangos): array
    {
        [$whereFechas, $fechaBindings] = $this->compilarWhereFechas($rangos);
        
        $mesAgg = "
            CASE 
                WHEN YEAR(MIN(pf.TRANSDATE)) = YEAR(MAX(pf.TRANSDATE))
                 AND MONTH(MIN(pf.TRANSDATE)) = MONTH(MAX(pf.TRANSDATE))
                THEN CASE MONTH(MIN(pf.TRANSDATE))
                    WHEN 1 THEN 'ENERO' WHEN 2 THEN 'FEBRERO' WHEN 3 THEN 'MARZO'
                    WHEN 4 THEN 'ABRIL' WHEN 5 THEN 'MAYO'    WHEN 6 THEN 'JUNIO'
                    WHEN 7 THEN 'JULIO' WHEN 8 THEN 'AGOSTO'  WHEN 9 THEN 'SEPTIEMBRE'
                    WHEN 10 THEN 'OCTUBRE' WHEN 11 THEN 'NOVIEMBRE' WHEN 12 THEN 'DICIEMBRE'
                END
                ELSE 'VARIOS MESES'
            END
        ";

        $idflogAgg = "'Pronóstico de ' + $mesAgg";

        $sql = <<<SQL
            WITH PF AS (
                SELECT *
                FROM dbo.TwPronosticosFlogs AS pf
                WHERE ($whereFechas)
                  AND pf.ITEMTYPEID BETWEEN ? AND ?
            ),
            IL_DEDUP AS (
                SELECT
                    il.*,
                    ROW_NUMBER() OVER (
                        PARTITION BY il.IDFLOG, il.PURCHBARCODE
                        ORDER BY il.CREATEDDATE DESC
                    ) AS rn
                FROM dbo.TWFLOGSITEMLINE AS il
                WHERE il.IDFLOG LIKE ?
            )
            SELECT
                pf.CUSTNAME,
                bom.ITEMID,
                bom.INVENTSIZEID,
                SUM(CAST(ISNULL(pf.INVENTQTY, 0) AS DECIMAL(18,4)) *
                    CAST(ISNULL(bom.BOMQTY,   0) AS DECIMAL(18,4))) AS TOTAL_RESULTADO,
                SUM(CAST(ISNULL(pf.INVENTQTY, 0) AS DECIMAL(18,4))) AS TOTAL_INVENTQTY,
                SUM(CAST(ISNULL(bom.BOMQTY, 0) AS DECIMAL(18,4)))   AS SUM_BOMQTY,
                COUNT(*)                                            AS N_FACTORES,
                CAST(
                    SUM(CAST(ISNULL(bom.BOMQTY, 0) AS DECIMAL(18,4))) / NULLIF(COUNT(*), 0)
                    AS DECIMAL(18,4)
                ) AS PROM_BOMQTY,
                MIN(bom.ITEMNAME)       AS ITEMNAME,
                MIN(bom.TIPOHILOID)     AS TIPOHILOID,
                MIN(bom.RASURADO)       AS RASURADOCRUDO,
                MIN(pf.VALORAGREGADO)   AS VALORAGREGADO,
                MIN(bom.ANCHO)          AS ANCHO,
                MIN(PF.ITEMTYPEID)      AS ITEMTYPEID,
                MIN(pf.TRANSDATE)       AS FECHA,
                $idflogAgg              AS IDFLOG
            FROM PF AS pf
            JOIN IL_DEDUP AS il
                ON il.PURCHBARCODE = pf.CODIGOBARRAS
               AND il.rn = 1
            JOIN dbo.TWFLOGBOMID AS bom
                ON bom.IDFLOG    = il.IDFLOG
               AND bom.REFRECID  = il.RECID
               AND bom.BIES      = 0
            GROUP BY
                pf.CUSTNAME,
                bom.ITEMID,
                bom.INVENTSIZEID
            ORDER BY
                pf.CUSTNAME,
                bom.ITEMID,
                bom.INVENTSIZEID;
        SQL;

        $bindings = array_merge(
            $fechaBindings, // (inicio, fin, inicio, fin, ...)
            [10, 19],       // BETWEEN ?
            ['RS%']         // LIKE ?
        );

        return DB::connection(self::CONN)->select($sql, $bindings);
    }

    /**
     * Genera cláusula WHERE (pf.TRANSDATE >= ? AND pf.TRANSDATE <= ?) OR ...
     * + el arreglo de bindings en el mismo orden.
     */
    private function compilarWhereFechas(array $rangos): array
    {
        $clauses = [];
        $bindings = [];

        foreach ($rangos as $r) {
            $clauses[] = '(pf.TRANSDATE >= ? AND pf.TRANSDATE <= ?)';
            $bindings[] = $r['inicio'];
            $bindings[] = $r['fin'];
        }

        return [implode(' OR ', $clauses), $bindings];
    }
}



