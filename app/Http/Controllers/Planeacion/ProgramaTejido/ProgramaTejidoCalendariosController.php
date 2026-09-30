<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Actions\Planeacion\ProgramaTejido\CambiarCalendario;
use App\Actions\Planeacion\ProgramaTejido\FalloEnRegistro;
use App\Actions\Planeacion\ProgramaTejido\MutacionRechazada;
use App\Actions\Planeacion\ProgramaTejido\ReprogramarProgramaTejido;
use App\Data\Planeacion\ProgramaTejido\CambioCalendario;
use App\Data\Planeacion\ProgramaTejido\Reprogramacion;
use App\Helpers\AuditoriaHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planeacion\ProgramaTejido\CambiarCalendarioRequest;
use App\Http\Requests\Planeacion\ProgramaTejido\ReprogramarProgramaTejidoRequest;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Services\Planeacion\ProgramaTejido\MutacionesV2;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * @file ProgramaTejidoCalendariosController.php
 *
 * @description Controlador de calendarios para Programa Tejido. Actualización masiva de calendarios,
 *              reprogramar registro, recalcular fechas. Regla: EnProceso no permite edición de fechas.
 *
 * @dependencies CambiarCalendario, ReprogramarProgramaTejido (Actions PT-05), MutacionesV2
 */
class ProgramaTejidoCalendariosController extends Controller
{
    public function getAllRegistrosJson()
    {
        try {
            $registros = ReqProgramaTejido::query()
                ->orderBy('NoTelarId')
                ->orderBy('Id')
                ->get(['Id', 'NoTelarId', 'NombreProducto']);

            return response()->json([
                'success' => true,
                'data' => $registros,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los registros: '.$e->getMessage(),
            ], 500);
        }
    }

    public function actualizarCalendariosMasivo(Request $request)
    {
        if (MutacionesV2::activa('calendarios')) {
            return MutacionesV2::medir('calendarios', 'v2', fn () => $this->actualizarCalendariosMasivoV2(
                app(CambiarCalendarioRequest::class), app(CambiarCalendario::class)
            ));
        }

        return MutacionesV2::medir('calendarios', 'legacy', fn () => $this->actualizarCalendariosMasivoLegacy($request));
    }

    public function actualizarReprogramar(Request $request, int $id)
    {
        if (MutacionesV2::activa('reprogramar')) {
            return MutacionesV2::medir('reprogramar', 'v2', fn () => $this->actualizarReprogramarV2(
                app(ReprogramarProgramaTejidoRequest::class), $id, app(ReprogramarProgramaTejido::class)
            ));
        }

        return MutacionesV2::medir('reprogramar', 'legacy', fn () => $this->actualizarReprogramarLegacy($request, $id));
    }

    /**
     * v2 (PT-05): todo o nada. Si una fila truena no se confirma ninguna (antes se contaba
     * en 'errores' y el resto quedaba confirmado con el calendario nuevo).
     */
    private function actualizarCalendariosMasivoV2(CambiarCalendarioRequest $request, CambiarCalendario $accion): JsonResponse
    {
        set_time_limit(300);
        $t0 = microtime(true);
        $cambio = CambioCalendario::desdeRequest($request);

        try {
            $resultado = $accion->ejecutar($cambio, estricto: true);
        } catch (FalloEnRegistro $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => "No se actualizó ningún calendario: falló el registro {$e->registroId}. Los cambios se revirtieron.",
                'registro_id' => $e->registroId,
            ], 500);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'No se actualizó ningún calendario. Los cambios se revirtieron.',
            ], 500);
        }

        return $this->respuestaCalendarios($cambio->calendarioId, $resultado, $t0);
    }

    private function actualizarCalendariosMasivoLegacy(Request $request): JsonResponse
    {
        set_time_limit(300);

        try {
            $request->validate([
                'calendario_id' => 'required|string',
                'registros_ids' => 'required|array|min:1',
                'registros_ids.*' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')],
            ]);

            $t0 = microtime(true);
            $cambio = CambioCalendario::desdeRequest($request);
            $resultado = (new CambiarCalendario)->ejecutar($cambio, estricto: false);

            return $this->respuestaCalendarios($cambio->calendarioId, $resultado, $t0);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            LogFacade::error('Error en actualizarCalendariosMasivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar calendarios: '.$e->getMessage(),
            ], 500);
        }
    }

    /** @param  array{actualizados: int, procesados: int, errores: int}  $resultado */
    private function respuestaCalendarios(string $calendarioId, array $resultado, float $t0): JsonResponse
    {
        $tiempo = round(microtime(true) - $t0, 2);

        return response()->json([
            'success' => true,
            'message' => "Se actualizaron {$resultado['actualizados']} registro(s) con el calendario {$calendarioId} en {$tiempo}s",
            'data' => [
                'actualizados' => $resultado['actualizados'],
                'procesados' => $resultado['procesados'],
                'errores' => $resultado['errores'],
                'tiempo_segundos' => $tiempo,
            ],
        ]);
    }

    private function actualizarReprogramarV2(ReprogramarProgramaTejidoRequest $request, int $id, ReprogramarProgramaTejido $accion): JsonResponse
    {
        try {
            $registro = $accion->ejecutar(Reprogramacion::desdeRequest($request, $id));
        } catch (MutacionRechazada $e) {
            return $e->respuesta;
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado',
            ], 404);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar Reprogramar. No se guardó el cambio.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reprogramar actualizado correctamente',
            'reprogramar' => $registro->Reprogramar,
        ]);
    }

    private function actualizarReprogramarLegacy(Request $request, int $id): JsonResponse
    {
        AuditoriaHelper::contexto('REPROGRAMAR');

        try {
            $request->validate([
                'reprogramar' => 'nullable|string|in:1,2',
            ]);

            $registro = ReqProgramaTejido::findOrFail($id);

            if ($registro->EnProceso != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede actualizar Reprogramar en registros que están en proceso',
                ], 422);
            }

            $reprogramar = $request->input('reprogramar');
            if ($reprogramar === null || $reprogramar === '') {
                $reprogramar = null;
            }

            $registro->Reprogramar = $reprogramar;
            $registro->save();

            return response()->json([
                'success' => true,
                'message' => 'Reprogramar actualizado correctamente',
                'reprogramar' => $registro->Reprogramar,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado',
            ], 404);
        } catch (\Exception $e) {
            LogFacade::error('Error al actualizar Reprogramar', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar Reprogramar: '.$e->getMessage(),
            ], 500);
        }
    }

    public function recalcularFechas(Request $request): JsonResponse
    {
        AuditoriaHelper::contexto('RECALCULO');

        try {
            Artisan::call('programa-tejido:recalcular-fechas-produccion', ['--all' => true]);
            $output = Artisan::output();

            return response()->json(['ok' => true, 'message' => trim($output) ?: 'Recálculo completado.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Error: '.$e->getMessage()], 500);
        }
    }
}
