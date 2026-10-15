<?php

declare(strict_types=1);

namespace App\Repositories\Urdido;

use Illuminate\Support\Facades\DB;

/**
 * Consumo de hilo de las órdenes de urdido en AX, por folio: las líneas de producción (TRANSTYPE 8)
 * que salieron del almacén A-EP-URDID en el mes. El folio es el lote (INVENTBATCHID) de la recepción
 * de JULIO-URDIDO (TRANSTYPE 2) de la misma orden. Por máquina suma lo mismo que SALPROD*KG de
 * InvKardexURDID; aquí se baja al folio para saber cuál no cuadra con sus julios.
 */
class ConsumoHiloAxRepository
{
    private const CONN = 'sqlsrv_ti';

    private const DATAAREA = 'PRO';

    /**
     * @return array<string, array{loc: string, kg: float}> folio (5 dígitos) => localidad con más consumo y kg
     */
    public function porFolio(int $anio, int $mes): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-d', (int) strtotime($desde.' +1 month'));

        $lineas = DB::connection(self::CONN)
            ->table('INVENTTRANS as t')
            ->join('INVENTDIM as d', fn ($j) => $j->on('d.INVENTDIMID', '=', 't.INVENTDIMID')->on('d.DATAAREAID', '=', 't.DATAAREAID'))
            ->where('t.DATAAREAID', self::DATAAREA)
            ->where('t.TRANSTYPE', 8)
            ->where('d.INVENTLOCATIONID', 'A-EP-URDID')
            ->where('t.DATEPHYSICAL', '>=', $desde)
            ->where('t.DATEPHYSICAL', '<', $hasta)
            ->groupBy('t.TRANSREFID', 'd.WMSLOCATIONID')
            ->selectRaw('t.TRANSREFID AS orden, d.WMSLOCATIONID AS loc, -SUM(t.QTY) AS kg') // SUM con signo: el builder no lo expresa
            ->get();

        $folios = $this->foliosDeOrdenes($lineas->pluck('orden')->map(fn ($o) => trim((string) $o))->unique()->values()->all());

        $porFolio = [];
        foreach ($lineas as $l) {
            $folio = $folios[trim((string) $l->orden)] ?? 'Orden '.trim((string) $l->orden);
            $actual = $porFolio[$folio] ?? ['loc' => '', 'kg' => 0.0, 'locKg' => 0.0];
            $kg = (float) $l->kg;
            $porFolio[$folio] = [
                'loc' => $kg > $actual['locKg'] ? trim((string) $l->loc) : $actual['loc'],
                'kg' => $actual['kg'] + $kg,
                'locKg' => max($kg, $actual['locKg']),
            ];
        }

        return array_map(fn ($f) => ['loc' => $f['loc'], 'kg' => $f['kg']], $porFolio);
    }

    /**
     * @param  list<string>  $ordenes
     * @return array<string, string> orden de producción => folio
     */
    private function foliosDeOrdenes(array $ordenes): array
    {
        $folios = [];
        // Lotes de 1000: SQL Server no acepta más de 2100 parámetros por consulta.
        foreach (array_chunk($ordenes, 1000) as $lote) {
            DB::connection(self::CONN)
                ->table('INVENTTRANS as t')
                ->join('INVENTDIM as d', fn ($j) => $j->on('d.INVENTDIMID', '=', 't.INVENTDIMID')->on('d.DATAAREAID', '=', 't.DATAAREAID'))
                ->where('t.DATAAREAID', self::DATAAREA)
                ->where('t.TRANSTYPE', 2)
                ->where('t.ITEMID', 'JULIO-URDIDO')
                ->whereIn('t.TRANSREFID', $lote)
                ->distinct()
                ->get(['t.TRANSREFID', 'd.INVENTBATCHID'])
                ->each(function ($r) use (&$folios) {
                    $lote = trim((string) $r->INVENTBATCHID);
                    $folios[trim((string) $r->TRANSREFID)] = ctype_digit($lote) ? str_pad($lote, 5, '0', STR_PAD_LEFT) : $lote;
                });
        }

        return $folios;
    }
}
