<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Actions\Planeacion\ProgramaTejido\MutacionRechazada;
use App\Helpers\AuditoriaHelper;
use App\Helpers\StringTruncator;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\UtilityHelpers;
use App\Http\Requests\Planeacion\ProgramaTejido\ActualizarProgramaTejidoRequest;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\EdicionProgramaTejido;
use App\Support\Planeacion\NumeroPrograma;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * PUT legacy de la edición inline: adaptador HTTP sobre {@see EdicionProgramaTejido}.
 * Valida con las reglas de ActualizarProgramaTejidoRequest y convierte MutacionRechazada
 * y los fallos de persistencia en el JSON de siempre (422 / 500).
 */
class UpdateTejido
{
    public static function actualizar(Request $request, int $id): JsonResponse
    {
        AuditoriaHelper::contexto('EDITAR');

        $registro = ReqProgramaTejido::findOrFail($id);

        foreach (ActualizarProgramaTejidoRequest::CAMPOS_VACIO_A_NULL as $k) {
            if ($request->has($k) && is_string($request->input($k)) && trim($request->input($k)) === '') {
                $request->merge([$k => null]);
            }
        }

        $data = $request->validate(ActualizarProgramaTejidoRequest::reglas());

        // Snapshot
        $fechaFinalAntes = (string) ($registro->FechaFinal ?? '');
        $horasProdAntes = (float) ($registro->HorasProd ?? 0);
        $cantidadAntes = NumeroPrograma::sanitizeNumber($registro->SaldoPedido ?? $registro->Produccion ?? $registro->TotalPedido ?? 0);

        try {
            $flags = EdicionProgramaTejido::aplicarCambios($registro, $data);
        } catch (MutacionRechazada $e) {
            return response()->json($e->cuerpo, $e->status);
        }

        EdicionProgramaTejido::recalcularDerivados($registro, $flags, $horasProdAntes, $cantidadAntes);

        // Truncar strings antes de guardar (evitar error "String or binary data would be truncated")
        StringTruncator::truncateModelAttributes($registro);
        self::logCamposActualizados($registro);

        // Transacción única: pedido, fórmulas, CatCodificados y cascada se confirman juntos.
        DBFacade::beginTransaction();
        try {
            EdicionProgramaTejido::persistir($registro, $flags, $fechaFinalAntes, estricto: false);

            DBFacade::commit();
        } catch (\Throwable $e) {
            DBFacade::rollBack();
            LogFacade::error('UpdateTejido: transacción fallida, cambios revertidos', [
                'id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo actualizar el programa de tejido (cascada de fechas o regeneración de líneas falló): '.$e->getMessage(),
            ], 500);
        }

        $registro = $registro->fresh(); // para devolver lo definitivo

        return response()->json([
            'success' => true,
            'message' => 'Programa de tejido actualizado',
            'data' => UtilityHelpers::extractResumen($registro),
        ]);
    }

    private static function logCamposActualizados(ReqProgramaTejido $registro): void
    {
        $dirty = $registro->getDirty();
        if (empty($dirty)) {
            return;
        }

        $campos = array_keys($dirty);
        LogFacade::info('UpdateTejido: campos actualizados', [
            'id' => $registro->Id,
            'campos' => $campos,
            'valores' => $dirty,
            'orden_produccion_no_actualizada' => ! in_array('OrdPrincipal', $campos, true),
            'calibre_trama' => [
                'CalibreTrama' => $registro->CalibreTrama ?? null,
                'CalibreTrama2' => $registro->CalibreTrama2 ?? null,
            ],
            'color_pie' => [
                'CodColorCtaPie' => $registro->CodColorCtaPie ?? null,
                'NombreCPie' => $registro->NombreCPie ?? null,
            ],
        ]);
    }
}
