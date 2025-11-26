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

}

