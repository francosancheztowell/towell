<?php

declare(strict_types=1);

namespace App\Services\Planeacion\Liberar;

use App\Helpers\StringTruncator;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\Planeacion\TelarSalonResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Persistencia de celdas editables en Liberar órdenes.
 *
 * Extraído de LiberarOrdenesController::guardarCamposEditables.
 * Misma validación, mismos side effects en ReqProgramaTejido + CatCodificados
 * vía LiberarCatCodificadosWriter. No toca CreaProd.
 */
final class LiberarCamposEditablesService
{
    public function __construct(
        private readonly LiberarCatCodificadosWriter $catCodificadosWriter = new LiberarCatCodificadosWriter,
    ) {}

    public function guardar(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'id' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')],
                'field' => 'required|string|in:MtsRollo,PzasRollo,TotalRollos,TotalPzas,Repeticiones,SaldoMarbete,Densidad,CombinaTrama,NoTiras',
                'value' => 'nullable',
            ]);

            $id = (int) $data['id'];
            $field = $data['field'];
            $value = $data['value'];

            DB::beginTransaction();

            try {
                /** @var ReqProgramaTejido|null $registro */
                $registro = ReqProgramaTejido::lockForUpdate()->find($id);
                if (! $registro) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Registro no encontrado.',
                    ], 404);
                }

                // Las tiras solo se capturan a mano en Karl Mayer; en los demás salones vienen
                // del artículo y editarlas movería toda la cadena de marbetes sin control.
                if ($field === 'NoTiras' && ! $this->esKarlMayer($registro)) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Las tiras solo se pueden editar en órdenes de Karl Mayer.',
                    ], 422);
                }

                // Validar y convertir el valor según el tipo de campo
                if ($field === 'NoTiras') {
                    $tiras = $value !== null && $value !== '' ? (int) round((float) $value) : 0;
                    if ($tiras <= 0) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => 'Las tiras deben ser mayores a cero.',
                        ], 422);
                    }
                    // El observer recalcula repeticiones, marbetes y rollos al guardar.
                    $registro->NoTiras = $tiras;
                } elseif ($field === 'CombinaTrama') {
                    // Campo string
                    $registro->CombinaTram = $value !== null ? trim((string) $value) : null;
                } elseif ($field === 'SaldoMarbete') {
                    $registro->SaldoMarbete = $value !== null && $value !== '' ? (int) round((float) $value) : null;
                } elseif ($field === 'Repeticiones') {
                    $registro->Repeticiones = $value !== null && $value !== '' ? (int) (float) $value : null;
                } elseif ($field === 'Densidad') {
                    // Densidad es float con 4 decimales
                    $registro->Densidad = $value !== null ? round((float) $value, 4) : null;
                } elseif ($field === 'TotalRollos') {
                    // TotalRollos es float, redondear hacia arriba si hay decimal
                    $registro->TotalRollos = $value !== null ? (float) ceil((float) $value) : null;
                } else {
                    // MtsRollo, PzasRollo, TotalPzas son float
                    $registro->{$field} = $value !== null ? (float) $value : null;
                }

                StringTruncator::truncateModelAttributes($registro);
                $registro->save();

                $this->catCodificadosWriter->actualizarCampo($registro, $field, $value);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Campo actualizado correctamente.',
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error al guardar campo editable', [
                    'id' => $id,
                    'field' => $field,
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar el campo: '.$e->getMessage(),
                ], 500);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error general al guardar campo editable', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error inesperado al guardar el campo.',
            ], 500);
        }
    }

    private function esKarlMayer(?ReqProgramaTejido $registro): bool
    {
        return $registro !== null && TelarSalonResolver::esKarlMayer(
            $registro->SalonTejidoId ?? null,
            $registro->NoTelarId ?? null
        );
    }
}
