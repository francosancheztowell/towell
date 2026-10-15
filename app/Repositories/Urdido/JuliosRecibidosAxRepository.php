<?php

declare(strict_types=1);

namespace App\Repositories\Urdido;

use Illuminate\Support\Facades\DB;

/**
 * Julios que AX recibió en el mes, por folio: recepciones de JULIO-URDIDO (TRANSTYPE 2) en el almacén
 * A-JUL/URD. El folio es el lote (INVENTBATCHID) y la máquina la localidad (MC1, MC2, MC3, KM1). Una
 * recepción reversada resta (QTY negativa). Por máquina suma lo mismo que ENT*KG de InvKardexAJULURD;
 * aquí se baja al folio para saber cuál está en otra máquina que la del programa.
 */
class JuliosRecibidosAxRepository
{
    private const CONN = 'sqlsrv_ti';

    private const DATAAREA = 'PRO';

    /**
     * @return array<string, array{loc: string, kg: float}> folio (5 dígitos) => localidad con más kg y kg recibidos
     */
    public function porFolio(int $anio, int $mes): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-d', (int) strtotime($desde.' +1 month'));

        $filas = DB::connection(self::CONN)
            ->table('INVENTTRANS as t')
            ->join('INVENTDIM as d', fn ($j) => $j->on('d.INVENTDIMID', '=', 't.INVENTDIMID')->on('d.DATAAREAID', '=', 't.DATAAREAID'))
            ->where('t.DATAAREAID', self::DATAAREA)
            ->where('t.TRANSTYPE', 2)
            ->where('t.ITEMID', 'JULIO-URDIDO')
            ->where('d.INVENTLOCATIONID', 'A-JUL/URD')
            ->where('t.DATEPHYSICAL', '>=', $desde)
            ->where('t.DATEPHYSICAL', '<', $hasta)
            ->groupBy('d.INVENTBATCHID', 'd.WMSLOCATIONID')
            ->selectRaw('d.INVENTBATCHID AS lote, d.WMSLOCATIONID AS loc, SUM(t.QTY) AS kg') // SUM: el builder no lo expresa sin raw
            ->get();

        $porFolio = [];
        foreach ($filas as $f) {
            $lote = trim((string) $f->lote);
            $folio = ctype_digit($lote) ? str_pad($lote, 5, '0', STR_PAD_LEFT) : $lote;
            $actual = $porFolio[$folio] ?? ['loc' => '', 'kg' => 0.0, 'locKg' => 0.0];
            $kg = (float) $f->kg;
            $porFolio[$folio] = [
                'loc' => $kg > $actual['locKg'] ? trim((string) $f->loc) : $actual['loc'],
                'kg' => $actual['kg'] + $kg,
                'locKg' => max($kg, $actual['locKg']),
            ];
        }

        return array_map(fn ($f) => ['loc' => $f['loc'], 'kg' => $f['kg']], $porFolio);
    }
}
