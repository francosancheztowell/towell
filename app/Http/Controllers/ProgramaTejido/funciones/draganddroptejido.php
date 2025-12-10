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
     *
     * @param Request $request
     * @param int $id ID del registro a mover
     * @return \Illuminate\Http\JsonResponse
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
            // Forzar limpieza de selección en front (bandera)
            $desSeleccionar = true;

            $resultado = self::moverAposicion($registro, (int) $data['new_position']);

            return response()->json([
                'success'          => true,
                'message'          => 'Prioridad actualizada correctamente',
                'cascaded_records' => count($resultado['detalles']),
                'detalles'         => $resultado['detalles'],
                'registro_id'      => $registro->Id,
                'deseleccionar'    => $desSeleccionar,
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
     * @param ReqProgramaTejido $registro
     * @param int $nuevaPosicion
     * @return array{success: bool, detalles: array}
     */
    private static function moverAposicion(ReqProgramaTejido $registro, int $nuevaPosicion): array
    {
        DB::beginTransaction();

        try {
            // 1) Obtener registros del mismo salón/telar bloqueados para reordenar
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

            // Guardar IDs de los registros afectados para regenerar líneas después
            $idsAfectados = $registros->pluck('Id')->toArray();

            // 3) Reordenar colección en memoria
            $registrosReordenados = self::reordenarColeccion($registros, $idxActual, $nuevaPosicion);

            // 4) Deshabilitar observers para evitar regeneración duplicada
            ReqProgramaTejido::unsetEventDispatcher();

            // 5) Recalcular fechas para toda la secuencia
            [$updates, $detalles] = DateHelpers::recalcularFechasSecuencia($registrosReordenados, $inicioOriginal);

            foreach ($updates as $idU => $data) {
                DB::table('ReqProgramaTejido')
                    ->where('Id', $idU)
                    ->update($data);
            }

            DB::commit();

            // 6) Re-habilitar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // 7) Regenerar líneas para TODOS los registros afectados
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsAfectados as $idAct) {
                if ($r = ReqProgramaTejido::find($idAct)) {
                    $observer->saved($r);
                }
            }



            return [
                'success'  => true,
                'detalles' => $detalles,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            // Aseguramos que el observer quede registrado aunque haya error
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            Log::error('moverAposicion error', [
                'id'            => $registro->Id ?? null,
                'nueva_posicion'=> $nuevaPosicion,
                'msg'           => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
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
            ->get();
    }

    /**
     * Deben existir al menos 2 registros para poder reordenar.
     */
    private static function validarCantidadRegistros(Collection $registros): void
    {
        if ($registros->count() < 2) {
            throw new \RuntimeException('Se requieren al menos dos registros para reordenar la prioridad.');
        }
    }

    /**
     * Obtiene la fecha de inicio del primer registro, validando que sea correcta.
     */
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

    /**
     * Obtiene el índice actual del registro dentro de la colección.
     */
    private static function obtenerIndiceRegistro(Collection $registros, ReqProgramaTejido $registro): int
    {
        $idxActual = $registros->search(fn ($r) => $r->Id === $registro->Id);

        if ($idxActual === false) {
            throw new \RuntimeException('No se encontró el registro a reordenar dentro del telar.');
        }

        return (int) $idxActual;
    }

    /**
     * No se puede colocar antes de un registro en proceso.
     */
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
            // Nota: mensaje idéntico al original (sumando +1 para versión "humana")
            throw new \RuntimeException(
                'No se puede colocar un registro antes de uno que está en proceso. La posición mínima permitida es ' . ($posicionMinima + 1) . '.'
            );
        }
    }

    /**
     * Valida que la nueva posición esté dentro del rango de la colección.
     */
    private static function validarRangoPosicion(Collection $registros, int $nuevaPosicion): void
    {
        if ($nuevaPosicion < 0 || $nuevaPosicion >= $registros->count()) {
            throw new \RuntimeException('La nueva posición está fuera del rango válido.');
        }
    }

    /**
     * Reordena la colección moviendo el registro desde idxActual a nuevaPosicion.
     */
    private static function reordenarColeccion(Collection $registros, int $idxActual, int $nuevaPosicion): Collection
    {
        // Extraer el registro actual
        $registroMovido = $registros->splice($idxActual, 1)->first();

        // Insertarlo en la nueva posición
        $registros->splice($nuevaPosicion, 0, [$registroMovido]);

        return $registros->values();
    }

}
