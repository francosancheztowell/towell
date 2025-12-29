<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Http\Controllers\ProgramaTejido\helper\DateHelpers;
use App\Models\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EliminarTejido
{
    /**
     * Eliminar un registro de programa de tejido
     * Si el registro tiene Reprogramar = '1', lo mueve al siguiente registro
     * Si el registro tiene Reprogramar = '2', lo mueve al último registro
     * Solo elimina si Reprogramar es null o vacío
     *
     * @param int $id ID del registro a eliminar
     * @return \Illuminate\Http\JsonResponse
     */
    public static function eliminar(int $id)
    {
        DB::beginTransaction();
        try {
            $registro = ReqProgramaTejido::findOrFail($id);
            if ($registro->EnProceso == 1) throw new \RuntimeException('No se puede eliminar un registro que está en proceso.');

            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;

            $registros = ReqProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->lockForUpdate()->get();
            $idx = $registros->search(fn($r) => $r->Id === $registro->Id);
            if ($idx === false) throw new \RuntimeException('No se encontró el registro a eliminar dentro del telar.');

            // Verificar si tiene Reprogramar
            $reprogramar = $registro->Reprogramar;

            // Si tiene Reprogramar, mover en lugar de eliminar
            if (!empty($reprogramar) && ($reprogramar == '1' || $reprogramar == '2')) {
                return self::moverEnLugarDeEliminar($registro, $registros, $idx, $reprogramar);
            }

            // Si no tiene Reprogramar, proceder con la eliminación normal
            $primero = $registros->first();
            $inicioOriginal = $primero->FechaInicio ? Carbon::parse($primero->FechaInicio) : null;
            if (!$inicioOriginal) throw new \RuntimeException('El primer registro debe tener una fecha de inicio válida.');

            // Eliminar registro (las líneas se eliminan por ON DELETE CASCADE en BD)
            $registro->delete();

            $restantes = ReqProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->get();
            if ($restantes->isEmpty()) {
                DB::commit();
                return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente']);
            }

            // Deshabilitar observers
            ReqProgramaTejido::unsetEventDispatcher();

            [$updates,$detalles] = DateHelpers::recalcularFechasSecuencia($restantes, $inicioOriginal);

            foreach ($updates as $idU => $data) {
                DB::table('ReqProgramaTejido')->where('Id',$idU)->update($data);
            }

            DB::commit();

            // Re-habilitar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Regenerar líneas
            $observer = new ReqProgramaTejidoObserver();
            foreach (array_column($detalles,'Id') as $idAct) {
                if ($r = ReqProgramaTejido::find($idAct)) $observer->saved($r);
            }

            Log::info('destroy OK', ['id'=>$id,'salon'=>$salon,'telar'=>$telar,'n'=>count($detalles)]);
            return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente','cascaded_records'=>count($detalles),'detalles'=>$detalles]);

        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            Log::error('destroy error', ['id'=>$id,'msg'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>$e->getMessage()], $e instanceof \RuntimeException ? 422 : 500);
        }
    }

    /**
     * Mover registro en lugar de eliminarlo según el valor de Reprogramar
     *
     * @param ReqProgramaTejido $registro
     * @param \Illuminate\Support\Collection $registros
     * @param int $idx
     * @param string $reprogramar
     * @return \Illuminate\Http\JsonResponse
     */
    private static function moverEnLugarDeEliminar($registro, $registros, $idx, $reprogramar)
    {
        try {
            // Validar que hay al menos 2 registros
            if ($registros->count() < 2) {
                throw new \RuntimeException('Se requieren al menos dos registros para mover.');
            }

            $primero = $registros->first();
            $inicioOriginal = $primero->FechaInicio ? Carbon::parse($primero->FechaInicio) : null;
            if (!$inicioOriginal) throw new \RuntimeException('El primer registro debe tener una fecha de inicio válida.');

            // Reordenar colección en memoria
            $registroMovido = $registros->splice($idx, 1)->first();

            // Calcular la posición de inserción después de remover el elemento
            if ($reprogramar == '1') {
                // Mover al siguiente registro
                // Después de remover, el siguiente elemento está en $idx
                // Para insertar DESPUÉS del siguiente, insertamos en $idx + 1
                $posicionAjustada = $idx + 1;
                // Si ya era el último o penúltimo, insertar al final
                if ($posicionAjustada > $registros->count()) {
                    $posicionAjustada = $registros->count();
                }
            } elseif ($reprogramar == '2') {
                // Mover al último registro (insertar al final de la colección después de remover)
                $posicionAjustada = $registros->count();
            }

            $registros->splice($posicionAjustada, 0, [$registroMovido]);
            $registrosReordenados = $registros->values();

            // Deshabilitar observers
            ReqProgramaTejido::unsetEventDispatcher();

            // Recalcular fechas para toda la secuencia (solo del telar actual)
            [$updates, $detalles] = DateHelpers::recalcularFechasSecuencia($registrosReordenados, $inicioOriginal);

            // Actualizar solo los registros de este telar
            foreach ($updates as $idU => $data) {
                DB::table('ReqProgramaTejido')->where('Id', $idU)->update($data);
            }

            DB::commit();

            // Re-habilitar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Regenerar líneas solo para los registros de este telar
            $observer = new ReqProgramaTejidoObserver();
            $idsAfectados = array_column($detalles, 'Id');

            // Obtener solo los registros de este telar que fueron afectados
            $registrosAfectados = ReqProgramaTejido::query()
                ->salon($registro->SalonTejidoId)
                ->telar($registro->NoTelarId)
                ->whereIn('Id', $idsAfectados)
                ->get();

            foreach ($registrosAfectados as $r) {
                $observer->saved($r);
            }

            $mensaje = $reprogramar == '1'
                ? 'Registro movido al siguiente correctamente (Reprogramar = 1)'
                : 'Registro movido al último correctamente (Reprogramar = 2)';

            Log::info('mover en lugar de eliminar OK', [
                'id' => $registro->Id,
                'salon' => $registro->SalonTejidoId,
                'telar' => $registro->NoTelarId,
                'reprogramar' => $reprogramar,
                'posicion_original' => $idx,
                'posicion_final' => $posicionAjustada,
                'n' => count($detalles)
            ]);

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'cascaded_records' => count($detalles),
                'detalles' => $detalles
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            Log::error('mover en lugar de eliminar error', [
                'id' => $registro->Id ?? null,
                'reprogramar' => $reprogramar,
                'msg' => $e->getMessage()
            ]);
            throw $e;
        }
    }

}

