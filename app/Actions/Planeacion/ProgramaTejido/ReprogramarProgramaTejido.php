<?php

declare(strict_types=1);

namespace App\Actions\Planeacion\ProgramaTejido;

use App\Data\Planeacion\ProgramaTejido\Reprogramacion;
use App\Helpers\AuditoriaHelper;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Support\Facades\DB;

/**
 * Reprogramar v2 (PT-05): misma regla (solo órdenes en proceso) con lock y transacción.
 */
final class ReprogramarProgramaTejido
{
    /**
     * @throws MutacionRechazada si la orden no está en proceso
     */
    public function ejecutar(Reprogramacion $reprogramacion): ReqProgramaTejido
    {
        AuditoriaHelper::contexto('REPROGRAMAR');

        return DB::transaction(function () use ($reprogramacion) {
            $registro = ReqProgramaTejido::query()->lockForUpdate()->findOrFail($reprogramacion->id);

            if ($registro->EnProceso != 1) {
                throw MutacionRechazada::con([
                    'success' => false,
                    'message' => 'Solo se puede actualizar Reprogramar en registros que están en proceso',
                ]);
            }

            $registro->Reprogramar = $reprogramacion->valor;
            $registro->save();

            return $registro;
        });
    }
}
