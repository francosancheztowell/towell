<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
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
                'updates'          => $resultado['updates'] ?? [],
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
     * @return array{success: bool, detalles: array, updates?: array}
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

                // IMPORTANTE: Asegurar que TODOS los registros del telar tengan su posición actualizada
                // Aunque recalcularFechasSecuencia debería incluir todos, vamos a asegurarnos
                // agregando explícitamente la posición para cada registro según su orden en la colección reordenada
                $idsAfectados = [];
                foreach ($registrosReordenados->values() as $index => $r) {
                    $idRegistro = (int)$r->Id;
                    $nuevaPosicion = $index + 1;

                    // Asegurar que el update incluya la posición correcta
                    if (!isset($updates[$idRegistro])) {
                        $updates[$idRegistro] = [];
                    }
                    // Forzar la actualización de la posición según el nuevo orden
                    $updates[$idRegistro]['Posicion'] = $nuevaPosicion;
                    $idsAfectados[] = $idRegistro;
                }

                if (!empty($idsAfectados)) {
                    // Evitar colisiones temporales con el índice único (telar+posición)
                    // IMPORTANTE: Solo actualizar registros del mismo telar para evitar afectar otros telares
                    DB::table('ReqProgramaTejido')
                        ->whereIn('Id', $idsAfectados)
                        ->where('SalonTejidoId', $registro->SalonTejidoId)
                        ->where('NoTelarId', $registro->NoTelarId)
                        ->update(['Posicion' => DB::raw('Posicion + 10000')]);
                }

                // Updates: Asegurar que solo se actualicen registros del mismo telar
                // IMPORTANTE: Actualizar TODOS los registros del telar con sus nuevas posiciones
                foreach ($updates as $idU => $dataU) {
                    // Verificar que el registro pertenece al mismo telar antes de actualizar
                    // IMPORTANTE: Asegurar que Posicion esté presente y sea un entero
                    if (isset($dataU['Posicion'])) {
                        $dataU['Posicion'] = (int)$dataU['Posicion'];
                    }

                    DB::table('ReqProgramaTejido')
                        ->where('Id', $idU)
                        ->where('SalonTejidoId', $registro->SalonTejidoId)
                        ->where('NoTelarId', $registro->NoTelarId)
                        ->update($dataU);
                }

                $updatesById = [];
                foreach ($updates as $idU => $dataU) {
                    $updatesById[(string) $idU] = $dataU;
                }

                return [
                    'detalles'     => $detalles,
                    'idsAfectados' => $idsAfectados,
                    'updates'      => $updatesById,
                ];
            }, 3);

            // Restaurar dispatcher SIEMPRE
            ReqProgramaTejido::setEventDispatcher($dispatcher);

            // 6) Regenerar líneas (fuera del lock/transaction para no alargar bloqueos)
            //    OPTIMIZADO: un solo query en vez de N finds
            $idsAfectados = $resultado['idsAfectados'] ?? [];
            if (!empty($idsAfectados)) {
                $observer = new ReqProgramaTejidoObserver();

                // IMPORTANTE: Refrescar los modelos desde la BD para tener los valores actualizados (incluyendo Posicion)
                $modelos = ReqProgramaTejido::query()
                    ->whereIn('Id', $idsAfectados)
                    ->where('SalonTejidoId', $registro->SalonTejidoId)
                    ->where('NoTelarId', $registro->NoTelarId)
                    ->get();

                foreach ($modelos as $m) {
                    // Refrescar el modelo para asegurar que tiene los valores más recientes de la BD
                    $m->refresh();
                    $observer->saved($m);
                }
            }

            return [
                'success'  => true,
                'detalles' => $resultado['detalles'] ?? [],
                'updates'  => $resultado['updates'] ?? [],
            ];
        } catch (\Throwable $e) {
            // Restaurar dispatcher aunque explote
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }




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
            ->orderBy('Posicion', 'asc')
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
