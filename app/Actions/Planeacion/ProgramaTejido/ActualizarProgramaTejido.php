<?php

declare(strict_types=1);

namespace App\Actions\Planeacion\ProgramaTejido;

use App\Data\Planeacion\ProgramaTejido\CambiosProgramaTejido;
use App\Helpers\AuditoriaHelper;
use App\Helpers\StringTruncator;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\UpdateTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Edición inline v2 (PT-05 · PT-MUT-01). Reusa la lógica de campos, fechas y derivados de
 * UpdateTejido; lo que cambia frente al legacy:
 * - la fila se lee con lockForUpdate dentro de la transacción (dos PUT a la misma fila ya
 *   no se pisan: CR-05 del lado del servidor);
 * - velocidad_std/eficiencia_std recalculan duración, FechaFinal y cascada (CR-03);
 * - un fallo al actualizar la aplicación en las líneas revierte todo (WR-08).
 */
final class ActualizarProgramaTejido
{
    /**
     * @throws MutacionRechazada 422 legacy (clave modelo inexistente, orden cerrada en AX)
     */
    public function ejecutar(CambiosProgramaTejido $cambios): ReqProgramaTejido
    {
        AuditoriaHelper::contexto('EDITAR');

        return DB::transaction(function () use ($cambios) {
            $registro = ReqProgramaTejido::query()->lockForUpdate()->findOrFail($cambios->id);

            $fechaFinalAntes = (string) ($registro->FechaFinal ?? '');
            $horasProdAntes = (float) ($registro->HorasProd ?? 0);
            $cantidadAntes = TejidoHelpers::sanitizeNumber($registro->SaldoPedido ?? $registro->Produccion ?? $registro->TotalPedido ?? 0);

            $flags = UpdateTejido::aplicarCambios($registro, $cambios->campos);
            if ($flags instanceof JsonResponse) {
                throw new MutacionRechazada($flags);
            }
            if ($cambios->cambiaStd()) {
                $flags['afectaDuracion'] = true;
                $flags['afectaFormulas'] = true;
            }

            UpdateTejido::recalcularDerivados($registro, $flags, $horasProdAntes, $cantidadAntes);
            StringTruncator::truncateModelAttributes($registro);
            UpdateTejido::persistir($registro, $flags, $fechaFinalAntes, estricto: true);

            return $registro->fresh();
        });
    }
}
