<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Helpers\AuditoriaHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\CatalogoPlaneacion\CatCalendarios\CalendarioController;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * @file ProgramaTejidoCalendariosController.php
 * @description Controlador de calendarios para Programa Tejido. Actualización masiva de calendarios,
 *              reprogramar registro, recalcular fechas. Regla: EnProceso no permite edición de fechas.
 * @dependencies CalendarioController, BalancearTejido, ReqProgramaTejido, ReqProgramaTejidoObserver
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
                'data' => $registros
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los registros: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarCalendariosMasivo(Request $request)
    {
        set_time_limit(300);

        try {
            $request->validate([
                'calendario_id' => 'required|string',
                'registros_ids' => 'required|array|min:1',
                'registros_ids.*' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')]
            ]);

            $calendarioId = $request->input('calendario_id');
            $registrosIds = array_unique($request->input('registros_ids', []));

            $dispatcher = ReqProgramaTejido::getEventDispatcher();
            ReqProgramaTejido::unsetEventDispatcher();

            $t0 = microtime(true);
            $procesados = 0;
            $actualizados = 0;
            $errores = 0;

            DBFacade::beginTransaction();

            try {
                $actualizados = ReqProgramaTejido::whereIn('Id', $registrosIds)
                    ->update(['CalendarioId' => $calendarioId]);

                $registros = ReqProgramaTejido::whereIn('Id', $registrosIds)
                    ->whereNotNull('FechaInicio')
                    ->orderBy('SalonTejidoId')
                    ->orderBy('NoTelarId')
                    ->orderBy('Posicion', 'asc')
                    ->orderBy('FechaInicio', 'asc')
                    ->orderBy('Id', 'asc')
                    ->get([
                        'Id',
                        'CalendarioId',
                        'SalonTejidoId',
                        'NoTelarId',
                        'FechaInicio',
                        'FechaFinal',
                        'HorasProd',
                        'SaldoPedido',
                        'Produccion',
                        'TotalPedido',
                        'PesoCrudo',
                        'DiasEficiencia',
                        'StdHrsEfect',
                        'ProdKgDia2',
                        'DiasJornada',
                        'Ultimo',
                        'EnProceso'
                    ]);

                $calendarioController = new CalendarioController();
                $prevFin = null;
                $prevTelar = null;
                $observer = new ReqProgramaTejidoObserver();

                foreach ($registros as $p) {
                    try {
                        if (empty($p->FechaInicio)) {
                            $errores++;
                            continue;
                        }

                        $inicioOriginal = Carbon::parse($p->FechaInicio);
                        $inicio = $inicioOriginal->copy();

                        $esPrimerRegistroTelar = ($prevTelar === null ||
                            ($prevTelar->SalonTejidoId !== $p->SalonTejidoId ||
                             $prevTelar->NoTelarId !== $p->NoTelarId));

                        $esEnProceso = ($p->EnProceso == 1 || $p->EnProceso === true);

                        if ($esEnProceso) {
                            $inicio = Carbon::now();
                        } elseif ($esPrimerRegistroTelar) {
                            $inicio = $inicioOriginal->copy();
                        } else {
                            if ($prevFin) {
                                if (!$prevFin->equalTo($inicioOriginal)) {
                                    $inicio = $prevFin->copy();
                                }
                            }
                            $snap = $calendarioController->snapInicioAlCalendario($calendarioId, $inicio);
                            if ($snap && !$snap->equalTo($inicio)) {
                                $inicio = $snap;
                            }
                        }

                        $horas = (float)($p->HorasProd ?? 0);
                        if ($horas <= 0) {
                            $horas = $calendarioController->calcularHorasProd($p);
                            if ($horas > 0) {
                                $p->HorasProd = $horas;
                            }
                        }
                        if ($horas <= 0) {
                            $errores++;
                            continue;
                        }

                        $fin = BalancearTejido::calcularFechaFinalDesdeInicio($calendarioId, $inicio, $horas);
                        if (!$fin) {
                            $fin = $inicio->copy()->addSeconds((int)round($horas * 3600));
                        }
                        if ($fin->lt($inicio)) {
                            $fin = $inicio->copy();
                        }

                        $inicioStr = $inicio->format('Y-m-d H:i:s');
                        $finStr = $fin->format('Y-m-d H:i:s');

                        $oldInicioStr = null;
                        try {
                            $oldInicioStr = Carbon::parse($p->FechaInicio)->format('Y-m-d H:i:s');
                        } catch (\Throwable $e) {
                        }
                        $oldFinStr = null;
                        if (!empty($p->FechaFinal)) {
                            try {
                                $oldFinStr = Carbon::parse($p->FechaFinal)->format('Y-m-d H:i:s');
                            } catch (\Throwable $e) {
                            }
                        }

                        $cambio = (!$esEnProceso && $oldInicioStr !== $inicioStr) || ($oldFinStr !== $finStr);

                        if (!$esEnProceso && $oldInicioStr !== $inicioStr) {
                            AuditoriaHelper::logCambioFechaInicio(
                                'ReqProgramaTejido',
                                $p->Id,
                                $oldInicioStr,
                                $inicioStr,
                                'Actualizar Calendarios',
                                $request,
                                false
                            );
                        }

                        if (!$esEnProceso) {
                            $p->FechaInicio = $inicioStr;
                        }
                        $p->FechaFinal = $finStr;

                        $deps = $calendarioController->calcularFormulasDependientesDeFechas($p, $inicio, $fin, $horas);
                        foreach ($deps as $campo => $valor) {
                            $p->{$campo} = $valor;
                        }

                        $p->saveQuietly();
                        $procesados++;

                        if ($cambio) {
                            $actualizados++;
                        }

                        // regenerarLineas() bypassa el guard del observer (modelos refetcheados no tienen isDirty)
                        ReqProgramaTejido::regenerarLineas([$p]);

                        $prevFin = $fin->copy();
                        $prevTelar = $p;
                    } catch (\Throwable $e) {
                        $errores++;
                        LogFacade::error('Error recalculando registro', [
                            'registro_id' => $p->Id ?? null,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                DBFacade::commit();

                if ($dispatcher) {
                    ReqProgramaTejido::setEventDispatcher($dispatcher);
                }

                $tiempo = round(microtime(true) - $t0, 2);

                return response()->json([
                    'success' => true,
                    'message' => "Se actualizaron {$actualizados} registro(s) con el calendario {$calendarioId} en {$tiempo}s",
                    'data' => [
                        'actualizados' => $actualizados,
                        'procesados' => $procesados,
                        'errores' => $errores,
                        'tiempo_segundos' => $tiempo
                    ]
                ]);
            } catch (\Exception $e) {
                DBFacade::rollBack();
                if ($dispatcher) {
                    ReqProgramaTejido::setEventDispatcher($dispatcher);
                }
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            LogFacade::error('Error en actualizarCalendariosMasivo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar calendarios: ' . $e->getMessage()
            ], 500);
        }
    }

    public function actualizarReprogramar(Request $request, int $id)
    {
        try {
            $request->validate([
                'reprogramar' => 'nullable|string|in:1,2'
            ]);

            $registro = ReqProgramaTejido::findOrFail($id);

            if ($registro->EnProceso != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se puede actualizar Reprogramar en registros que están en proceso'
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
                'reprogramar' => $registro->Reprogramar
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado'
            ], 404);
        } catch (\Exception $e) {
            LogFacade::error('Error al actualizar Reprogramar', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar Reprogramar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function recalcularFechas(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            Artisan::call('programa-tejido:recalcular-fechas-produccion', ['--all' => true]);
            $output = Artisan::output();
            return response()->json(['ok' => true, 'message' => trim($output) ?: 'Recálculo completado.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
