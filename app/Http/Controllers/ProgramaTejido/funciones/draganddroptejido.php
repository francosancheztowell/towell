<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Http\Controllers\ProgramaTejido\helper\DateHelpers;
use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DragAndDropTejido
{
    /**
     * Mover registro a una posición específica (drag and drop)
     */
    public static function mover(Request $request, int $id)
    {
        $data = $request->validate([
            'new_position' => 'required|integer|min:0',
        ]);

        /** @var ReqProgramaTejido $registro */
        $registro = ReqProgramaTejido::findOrFail($id);

        // Validación: Registro no debe estar en proceso
        if ((int) $registro->EnProceso === 1) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede mover un registro en proceso. Debe finalizar el proceso antes de moverlo.',
            ], 422);
        }

        try {
            $resultado = self::moverAposicion($registro, (int) $data['new_position']);

            return response()->json([
                'success'          => true,
                'message'          => 'Prioridad actualizada correctamente',
                'cascaded_records' => count($resultado['detalles']),
                'detalles'         => $resultado['detalles'],
                'registro_id'      => $registro->Id,
                'deseleccionar'    => true,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e instanceof \RuntimeException ? 422 : 500);
        }
    }

    /**
     * Mover un registro a una posición específica y recalcular fechas
     *
     * @return array{success: bool, detalles: array}
     */
    private static function moverAposicion(ReqProgramaTejido $registro, int $nuevaPosicion): array
    {
        // IMPORTANTE: guardar/restaurar dispatcher (evita duplicar observers)
        $dispatcher = ReqProgramaTejido::getEventDispatcher();

        try {
            $resultado = DB::transaction(function () use ($registro, $nuevaPosicion) {
                // 1) Obtener registros del mismo salón/telar bloqueados
                $registros = self::obtenerRegistrosBloqueadosPorTelar($registro);

                // 2) Validaciones de negocio
                self::validarCantidadRegistros($registros);

                $inicioOriginal = self::obtenerInicioOriginal($registros);
                $idxActual      = self::obtenerIndiceRegistro($registros, $registro);

                self::validarPosicionPermitida($registros, $nuevaPosicion);
                self::validarRangoPosicion($registros, $nuevaPosicion);

                if ($idxActual === $nuevaPosicion) {
                    throw new \RuntimeException('El registro ya está en esa posición.');
                }

                // 3) Reordenar colección en memoria
                $registrosReordenados = self::reordenarColeccion($registros, $idxActual, $nuevaPosicion);

                // 4) Deshabilitar eventos de Eloquent (evita regeneración duplicada)
                ReqProgramaTejido::unsetEventDispatcher();

                // 5) Recalcular fechas para toda la secuencia
                [$updates, $detalles] = DateHelpers::recalcularFechasSecuencia($registrosReordenados, $inicioOriginal);

                // Solo regenerar lo que realmente se actualizó
                $idsAfectados = array_map('intval', array_keys($updates));

                // Updates (120-200 está OK así; si quieres lo convertimos a 1 query con VALUES)
                foreach ($updates as $idU => $dataU) {
                    DB::table('ReqProgramaTejido')
                        ->where('Id', $idU)
                        ->update($dataU);
                }

                return [
                    'detalles'     => $detalles,
                    'idsAfectados' => $idsAfectados,
                ];
            }, 3);

            // Restaurar dispatcher SIEMPRE
            ReqProgramaTejido::setEventDispatcher($dispatcher);

            // 6) Regenerar líneas (fuera del lock/transaction para no alargar bloqueos)
            //    OPTIMIZADO: un solo query en vez de N finds
            $idsAfectados = $resultado['idsAfectados'] ?? [];
            if (!empty($idsAfectados)) {
                $observer = new ReqProgramaTejidoObserver();

                $modelos = ReqProgramaTejido::query()
                    ->whereIn('Id', $idsAfectados)
                    ->get();

                foreach ($modelos as $m) {
                    $observer->saved($m);
                }
            }

            return [
                'success'  => true,
                'detalles' => $resultado['detalles'] ?? [],
            ];
        } catch (\Throwable $e) {
            // Restaurar dispatcher aunque explote
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }

            Log::error('moverAposicion error', [
                'id'             => $registro->Id ?? null,
                'nueva_posicion' => $nuevaPosicion,
                'msg'            => $e->getMessage(),
                // Nota: el trace pesa; si quieres, solo loguearlo en local:
                // 'trace'       => app()->environment('local') ? $e->getTraceAsString() : null,
            ]);

            throw $e;
        }
    }

    /* =========================================================
     *  HELPERS DE MOVER
     * =======================================================*/

    /**
     * Obtiene todos los registros del mismo salón/telar bloqueados para update.
     */
    private static function obtenerRegistrosBloqueadosPorTelar(ReqProgramaTejido $registro): Collection
    {
        return ReqProgramaTejido::query()
            ->salon($registro->SalonTejidoId)
            ->telar($registro->NoTelarId)
            ->orderBy('FechaInicio', 'asc')
            ->lockForUpdate()
            // Si DateHelpers NO necesita todas las columnas, reduce payload:
            // ->select(['Id','SalonTejidoId','NoTelarId','FechaInicio','FechaFinal','EnProceso', ...])
            ->get();
    }

    private static function validarCantidadRegistros(Collection $registros): void
    {
        if ($registros->count() < 2) {
            throw new \RuntimeException('Se requieren al menos dos registros para reordenar la prioridad.');
        }
    }

    private static function obtenerInicioOriginal(Collection $registros): Carbon
    {
        $primero = $registros->first();

        $inicioOriginal = ($primero && $primero->FechaInicio)
            ? Carbon::parse($primero->FechaInicio)
            : null;

        if (!$inicioOriginal) {
            throw new \RuntimeException('El primer registro debe tener una fecha de inicio válida.');
        }

        return $inicioOriginal;
    }

    private static function obtenerIndiceRegistro(Collection $registros, ReqProgramaTejido $registro): int
    {
        $idxActual = $registros->search(fn ($r) => $r->Id === $registro->Id);

        if ($idxActual === false) {
            throw new \RuntimeException('No se encontró el registro a reordenar dentro del telar.');
        }

        return (int) $idxActual;
    }

    private static function validarPosicionPermitida(Collection $registros, int $nuevaPosicion): void
    {
        $ultimoEnProcesoIndex = -1;

        foreach ($registros as $index => $reg) {
            if ((int) $reg->EnProceso === 1) {
                $ultimoEnProcesoIndex = $index;
            }
        }

        if ($ultimoEnProcesoIndex === -1) {
            return;
        }

        $posicionMinima = $ultimoEnProcesoIndex + 1;

        if ($nuevaPosicion < $posicionMinima) {
            throw new \RuntimeException(
                'No se puede colocar un registro antes de uno que está en proceso. La posición mínima permitida es ' . ($posicionMinima + 1) . '.'
            );
        }
    }

    private static function validarRangoPosicion(Collection $registros, int $nuevaPosicion): void
    {
        if ($nuevaPosicion < 0 || $nuevaPosicion >= $registros->count()) {
            throw new \RuntimeException('La nueva posición está fuera del rango válido.');
        }
    }

    private static function reordenarColeccion(Collection $registros, int $idxActual, int $nuevaPosicion): Collection
    {
        $registroMovido = $registros->splice($idxActual, 1)->first();
        $registros->splice($nuevaPosicion, 0, [$registroMovido]);

        return $registros->values();
    }
}
