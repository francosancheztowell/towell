<?php

namespace App\Services\Atadores;

use App\Models\Atadores\AtaDevolucionesModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mantiene AtaDevoluciones.Estatus alineado con AtaMontadoTelas.Estatus
 * mediante RefId (AtaDevoluciones.RefId → AtaMontadoTelas.Id).
 */
class AtaDevolucionesEstatusSyncService
{
    /**
     * Propaga el Estatus del atado padre a todas las devoluciones vinculadas.
     *
     * @return int Filas de AtaDevoluciones actualizadas
     */
    public function syncFromRefId(?int $refId, ?string $estatus): int
    {
        if (!$refId || $estatus === null || $estatus === '') {
            return 0;
        }

        try {
            return AtaDevolucionesModel::where('RefId', $refId)
                ->where(function ($q) use ($estatus) {
                    $q->whereNull('Estatus')
                        ->orWhere('Estatus', '<>', $estatus);
                })
                ->update(['Estatus' => $estatus]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo sincronizar el Estatus de AtaDevoluciones con el atado padre', [
                'ref_id' => $refId,
                'estatus' => $estatus,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Corrige devoluciones cuyo Estatus no coincide con el del atado padre.
     *
     * @return int Filas corregidas
     */
    public function syncDesfasados(): int
    {
        try {
            $desfasados = DB::connection('sqlsrv')
                ->table('AtaDevoluciones as d')
                ->join('AtaMontadoTelas as m', 'd.RefId', '=', 'm.Id')
                ->whereNotNull('m.Estatus')
                ->where(function ($q) {
                    $q->whereNull('d.Estatus')
                        ->orWhereColumn('d.Estatus', '<>', 'm.Estatus');
                })
                ->select('d.Id', 'd.RefId', 'd.Estatus as EstatusDevolucion', 'm.Estatus as EstatusMontado')
                ->get();

            $actualizados = 0;

            foreach ($desfasados->groupBy('RefId') as $refId => $filas) {
                $estatusMontado = (string) $filas->first()->EstatusMontado;
                $actualizados += $this->syncFromRefId((int) $refId, $estatusMontado);
            }

            return $actualizados;
        } catch (\Throwable $e) {
            Log::error('Error al corregir Estatus desfasados en AtaDevoluciones', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cuenta devoluciones desfasadas respecto a su atado padre (sin corregir).
     */
    public function contarDesfasados(): int
    {
        return (int) DB::connection('sqlsrv')
            ->table('AtaDevoluciones as d')
            ->join('AtaMontadoTelas as m', 'd.RefId', '=', 'm.Id')
            ->whereNotNull('m.Estatus')
            ->where(function ($q) {
                $q->whereNull('d.Estatus')
                    ->orWhereColumn('d.Estatus', '<>', 'm.Estatus');
            })
            ->count();
    }
}
