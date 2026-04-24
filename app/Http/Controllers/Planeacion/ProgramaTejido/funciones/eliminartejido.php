<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\ProgramaTejidoSecuenciaHelper;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\MovimientoDesarrolladorService;
use App\Models\Planeacion\OrdenFinalizadaAuditoria;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        $dispatcher = null;
        DB::beginTransaction();
        try {
            $registro = ReqProgramaTejido::findOrFail($id);
            if ($registro->EnProceso == 1) throw new \RuntimeException('No se puede eliminar un registro que está en proceso.');

            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;

            // Optimizado: usar Posicion primero para aprovechar índices
            $registros = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('Posicion', 'asc') // Aprovecha índice IX_ReqProgramaTejido_Telar_Posicion
                ->orderBy('FechaInicio', 'asc') // Fallback
                ->lockForUpdate()
                ->get();
            $idx = $registros->search(fn($r) => $r->Id === $registro->Id);
            if ($idx === false) throw new \RuntimeException('No se encontró el registro a eliminar dentro del telar.');

            // Verificar si tiene Reprogramar
            $reprogramar = $registro->Reprogramar;

            // Si tiene Reprogramar, mover en lugar de eliminar
            if (!empty($reprogramar) && ($reprogramar == '1' || $reprogramar == '2')) {
                return self::moverEnLugarDeEliminar($registro, $registros, $idx, $reprogramar);
            }

            // Verificar si tiene OrdCompartida
            $ordCompartida = $registro->OrdCompartida;
            if (!empty($ordCompartida)) {
                return self::eliminarConOrdCompartida($registro, $registros, $idx);
            }

            // Si no tiene Reprogramar ni OrdCompartida, proceder con la eliminación normal
            $primero = $registros->first();
            $inicioOriginal = $primero->FechaInicio ? Carbon::parse($primero->FechaInicio) : null;
            if (!$inicioOriginal) throw new \RuntimeException('El primer registro debe tener una fecha de inicio válida.');

            // Guardar valor de Ultimo antes de eliminar
            $tieneUltimo = ($registro->Ultimo == 1 || $registro->Ultimo === '1' || $registro->Ultimo === 'UL' || $registro->Ultimo === 1);

            // Eliminar registro (las líneas se eliminan por ON DELETE CASCADE en BD)
            $registro->delete();

            // Optimizado: usar Posicion primero para aprovechar índices
            $restantes = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('Posicion', 'asc') // Aprovecha índice IX_ReqProgramaTejido_Telar_Posicion
                ->orderBy('FechaInicio', 'asc') // Fallback
                ->get();
            if ($restantes->isEmpty()) {
                DB::commit();
                return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente']);
            }

            // Recalcular posiciones solo si el registro eliminado NO tenía Ultimo = 1
            if (!$tieneUltimo) {
                TejidoHelpers::recalcularPosicionesPorTelar($salon, $telar);
            }

            // Deshabilitar observers
            $dispatcher = ReqProgramaTejido::suppressObservers();

            [$updates,$detalles] = DateHelpers::recalcularFechasSecuencia($restantes, $inicioOriginal);

            ProgramaTejidoSecuenciaHelper::aplicarUpdatesDesdeRecalculo($updates);

            DB::commit();

            // Re-habilitar observer
            ReqProgramaTejido::restoreObservers($dispatcher);

            ProgramaTejidoSecuenciaHelper::regenerarLineasDesdeDetalles($detalles);

            return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente','cascaded_records'=>count($detalles),'detalles'=>$detalles]);

        } catch (ModelNotFoundException) {
            DB::rollBack();
            ReqProgramaTejido::restoreObservers($dispatcher);
            Log::warning('destroy registro no encontrado', ['id' => $id, 'table' => ReqProgramaTejido::tableName()]);

            return response()->json([
                'success' => false,
                'codigo' => 'registro_no_encontrado',
                'message' => 'El registro ya no existe en el programa. Actualice la vista e intente de nuevo.',
            ], 404);
        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::restoreObservers($dispatcher);
            Log::error('destroy error', ['id'=>$id,'msg'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>$e->getMessage()], $e instanceof \RuntimeException ? 422 : 500);
        }
    }

    /**
     * Eliminar el registro que está en proceso (EnProceso = 1).
     * El siguiente en la cola del telar pasa a ser el nuevo EnProceso.
     * Se recalcula toda la secuencia del telar.
     *
     * @param int $id ID del registro en proceso a eliminar
     * @return \Illuminate\Http\JsonResponse
     */
    public static function eliminarEnProceso(int $id)
    {
        $dispatcher = null;
        DB::beginTransaction();
        try {
            $registro = ReqProgramaTejido::findOrFail($id);

            if ($registro->EnProceso != 1) {
                throw new \RuntimeException('El registro seleccionado no está en proceso.');
            }

            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;

            $registros = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();

            $idx = $registros->search(fn($r) => $r->Id === $registro->Id);
            if ($idx === false) {
                throw new \RuntimeException('No se encontró el registro dentro del telar.');
            }

            $ahora = Carbon::now();
            $registro->FechaFinaliza = $ahora;

            // Mantener consistencia con utilería: persistir FechaFinaliza y sincronizar a CatCodificados cuando aplique.
            try {
                $actualizoFechas = (new MovimientoDesarrolladorService())
                    ->actualizarFechasArranqueFinaliza($registro, null, $ahora);

                if (!$actualizoFechas && $registro->exists && $registro->isDirty('FechaFinaliza')) {
                    $registro->saveQuietly();
                }
            } catch (\Throwable $e) {
                if ($registro->exists && $registro->isDirty('FechaFinaliza')) {
                    $registro->saveQuietly();
                }

                Log::warning('eliminarEnProceso: no se pudo sincronizar FechaFinaliza antes de eliminar', [
                    'id' => $registro->Id ?? null,
                    'msg' => $e->getMessage(),
                ]);
            }

            $salonAuditoria = TelarSalonResolver::normalizeSalon($salon, $telar);
            $telarAuditoria = TelarSalonResolver::normalizeTelar($telar);
            try {
                OrdenFinalizadaAuditoria::registrarUtileriaFinalizar(
                    (int) $registro->Id,
                    trim((string) ($registro->NoProduccion ?? '')),
                    $salonAuditoria,
                    $telarAuditoria,
                    ['en_proceso' => true, 'origen' => 'programa_destroy_en_proceso']
                );
            } catch (\Throwable $e) {
                Log::warning('eliminarEnProceso: no se pudo registrar auditoría', [
                    'id' => $registro->Id ?? null,
                    'msg' => $e->getMessage(),
                ]);
            }

            // Eliminar el registro en proceso (líneas se eliminan por ON DELETE CASCADE)
            $registro->delete();

            // Obtener los registros restantes
            $restantes = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->get();

            if ($restantes->isEmpty()) {
                DB::commit();
                return response()->json([
                    'success'  => true,
                    'message'  => 'Registro en proceso eliminado. No quedan más registros en el telar.',
                ]);
            }

            // Recalcular posiciones (el en-proceso era el primero, así que siempre aplica)
            TejidoHelpers::recalcularPosicionesPorTelar($salon, $telar);

            // El nuevo EnProceso arranca desde ahora
            $inicioOriginal = Carbon::now();

            // Deshabilitar observers durante la actualización masiva
            $dispatcher = ReqProgramaTejido::suppressObservers();

            [$updates, $detalles] = DateHelpers::recalcularFechasSecuencia($restantes, $inicioOriginal);

            ProgramaTejidoSecuenciaHelper::aplicarUpdatesDesdeRecalculo($updates);

            DB::commit();

            // Re-habilitar observer
            ReqProgramaTejido::restoreObservers($dispatcher);

            ProgramaTejidoSecuenciaHelper::regenerarLineasDesdeDetalles($detalles);

            return response()->json([
                'success'          => true,
                'message'          => 'Registro en proceso eliminado. El siguiente registro ahora está en proceso y el telar fue recalculado.',
                'cascaded_records' => count($detalles),
                'detalles'         => $detalles,
                'registros_ids'    => array_column($detalles, 'Id'),
            ]);

        } catch (ModelNotFoundException) {
            DB::rollBack();
            ReqProgramaTejido::restoreObservers($dispatcher);
            Log::warning('eliminarEnProceso registro no encontrado', ['id' => $id, 'table' => ReqProgramaTejido::tableName()]);

            return response()->json([
                'success' => false,
                'codigo' => 'registro_no_encontrado',
                'message' => 'El registro ya no existe en el programa. Actualice la vista e intente de nuevo.',
            ], 404);
        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::restoreObservers($dispatcher);
            Log::error('eliminarEnProceso error', ['id' => $id, 'msg' => $e->getMessage()]);
            return response()->json(
                ['success' => false, 'message' => $e->getMessage()],
                $e instanceof \RuntimeException ? 422 : 500
            );
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
        $dispatcher = null;
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
            $dispatcher = ReqProgramaTejido::suppressObservers();

            // Recalcular fechas para toda la secuencia (solo del telar actual)
            [$updates, $detalles] = DateHelpers::recalcularFechasSecuencia($registrosReordenados, $inicioOriginal);

            ProgramaTejidoSecuenciaHelper::aplicarUpdatesDesdeRecalculo($updates);

            DB::commit();

            // Re-habilitar observer
            ReqProgramaTejido::restoreObservers($dispatcher);

            $idsAfectados = array_column($detalles, 'Id');
            $registrosAfectados = ReqProgramaTejido::query()
                ->salon($registro->SalonTejidoId)
                ->telar($registro->NoTelarId)
                ->whereIn('Id', $idsAfectados)
                ->get();

            ReqProgramaTejido::regenerarLineas($registrosAfectados);

            $mensaje = $reprogramar == '1'
                ? 'Registro movido al siguiente correctamente (Reprogramar = 1)'
                : 'Registro movido al último correctamente (Reprogramar = 2)';



            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'cascaded_records' => count($detalles),
                'detalles' => $detalles
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::restoreObservers($dispatcher);
            Log::error('mover en lugar de eliminar error', [
                'id' => $registro->Id ?? null,
                'reprogramar' => $reprogramar,
                'msg' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Manejar eliminación de registro con OrdCompartida
     * Transfiere TotalPedido al líder o al receptor según corresponda
     *
     * @param ReqProgramaTejido $registro
     * @param \Illuminate\Support\Collection $registros
     * @param int $idx
     * @return \Illuminate\Http\JsonResponse
     */
    private static function eliminarConOrdCompartida($registro, $registros, $idx)
    {
        $dispatcher = null;
        try {
            $ordCompartida = $registro->OrdCompartida;

            // Buscar todos los registros con el mismo OrdCompartida
            $registrosCompartidos = ReqProgramaTejido::query()
                ->where('OrdCompartida', $ordCompartida)
                ->orderBy('FechaInicio', 'asc')
                ->orderBy('NoTelarId', 'asc')
                ->lockForUpdate()
                ->get();

            // Si es el último registro compartido, permitir eliminación normal (sin transferencia)
            if ($registrosCompartidos->count() < 2) {
                // Proceder con eliminación normal sin transferencia
                $salon = $registro->SalonTejidoId;
                $telar = $registro->NoTelarId;

                $primero = $registros->first();
                $inicioOriginal = $primero->FechaInicio ? Carbon::parse($primero->FechaInicio) : null;
                if (!$inicioOriginal) throw new \RuntimeException('El primer registro debe tener una fecha de inicio válida.');

                // Guardar valor de Ultimo antes de eliminar
                $tieneUltimo = ($registro->Ultimo == 1 || $registro->Ultimo === '1' || $registro->Ultimo === 'UL' || $registro->Ultimo === 1);

                // Eliminar registro
                $registro->delete();

                $restantes = ReqProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio','asc')->get();
                if ($restantes->isEmpty()) {
                    DB::commit();
                    return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente']);
                }

                // Recalcular posiciones solo si el registro eliminado NO tenía Ultimo = 1
                if (!$tieneUltimo) {
                    TejidoHelpers::recalcularPosicionesPorTelar($salon, $telar);
                }

                // Deshabilitar observers
                $dispatcher = ReqProgramaTejido::suppressObservers();

                [$updates,$detalles] = DateHelpers::recalcularFechasSecuencia($restantes, $inicioOriginal);

                ProgramaTejidoSecuenciaHelper::aplicarUpdatesDesdeRecalculo($updates);

                DB::commit();

                // Re-habilitar observer
                ReqProgramaTejido::restoreObservers($dispatcher);

                ProgramaTejidoSecuenciaHelper::regenerarLineasDesdeDetalles($detalles);

                return response()->json(['success'=>true,'message'=>'Registro eliminado correctamente','cascaded_records'=>count($detalles),'detalles'=>$detalles]);
            }

            // Identificar el líder
            $lider = $registrosCompartidos->firstWhere('OrdCompartidaLider', 1);
            if (!$lider) {
                throw new \RuntimeException('No se encontró el registro líder del grupo compartido.');
            }

            $esLider = ($registro->Id === $lider->Id);
            $totalPedidoAEliminar = (float)($registro->TotalPedido ?? 0);

            if ($esLider) {
                // Si es el líder, transferir al otro registro o al último
                if ($registrosCompartidos->count() == 2) {
                    // Si hay solo 2, transferir al otro
                    $receptor = $registrosCompartidos->firstWhere('Id', '!=', $registro->Id);
                } else {
                    // Si hay más de 2, transferir al último (que no sea el que se elimina)
                    $receptor = $registrosCompartidos->filter(function($r) use ($registro) {
                        return $r->Id !== $registro->Id;
                    })->last();
                }

                if (!$receptor || $receptor->Id === $registro->Id) {
                    throw new \RuntimeException('No se encontró el registro receptor para transferir el pedido.');
                }

                // Transferir TotalPedido
                $totalPedidoReceptor = (float)($receptor->TotalPedido ?? 0);
                $nuevoTotalPedido = $totalPedidoReceptor + $totalPedidoAEliminar;

                // Calcular nuevo SaldoPedido considerando la producción
                $produccionReceptor = (float)($receptor->Produccion ?? 0);
                $nuevoSaldoPedido = max(0, $nuevoTotalPedido - $produccionReceptor);

                // Actualizar receptor: TotalPedido, SaldoPedido y convertirlo en líder
                $receptorActualizado = ReqProgramaTejido::find($receptor->Id);
                $receptorActualizado->TotalPedido = $nuevoTotalPedido;
                $receptorActualizado->SaldoPedido = $nuevoSaldoPedido;
                $receptorActualizado->OrdCompartidaLider = 1;
                $receptorActualizado->saveQuietly();

                // Recalcular fechas del receptor
                self::recalcularFechasYFormulas($receptorActualizado);
                } else {
                // Si NO es el líder, transferir al líder
                $totalPedidoLider = (float)($lider->TotalPedido ?? 0);
                $nuevoTotalPedido = $totalPedidoLider + $totalPedidoAEliminar;

                // Calcular nuevo SaldoPedido considerando la producción
                $produccionLider = (float)($lider->Produccion ?? 0);
                $nuevoSaldoPedido = max(0, $nuevoTotalPedido - $produccionLider);

                // Actualizar líder: TotalPedido y SaldoPedido
                $liderActualizado = ReqProgramaTejido::find($lider->Id);
                $liderActualizado->TotalPedido = $nuevoTotalPedido;
                $liderActualizado->SaldoPedido = $nuevoSaldoPedido;
                $liderActualizado->saveQuietly();

                // Recalcular fechas del líder
                self::recalcularFechasYFormulas($liderActualizado);
            }

            // Obtener telares afectados (receptor/líder y el telar del registro eliminado)
            $telaresAfectados = [];
            if ($esLider && isset($receptor)) {
                $telaresAfectados[] = [
                    'salon' => $receptor->SalonTejidoId,
                    'telar' => $receptor->NoTelarId,
                    'id_registro' => $receptor->Id
                ];
            } elseif (!$esLider && isset($lider)) {
                $telaresAfectados[] = [
                    'salon' => $lider->SalonTejidoId,
                    'telar' => $lider->NoTelarId,
                    'id_registro' => $lider->Id
                ];
            }

            // Agregar el telar del registro a eliminar si es diferente
            $salon = $registro->SalonTejidoId;
            $telar = $registro->NoTelarId;
            $telarYaIncluido = false;
            foreach ($telaresAfectados as $ta) {
                if ($ta['salon'] === $salon && $ta['telar'] === $telar) {
                    $telarYaIncluido = true;
                    break;
                }
            }
            if (!$telarYaIncluido) {
                $telaresAfectados[] = [
                    'salon' => $salon,
                    'telar' => $telar,
                    'id_registro' => null
                ];
            }

            // Guardar valor de Ultimo antes de eliminar
            $tieneUltimo = ($registro->Ultimo == 1 || $registro->Ultimo === '1' || $registro->Ultimo === 'UL' || $registro->Ultimo === 1);

            // Eliminar registro
            $registro->delete();

            // Recalcular posiciones del telar eliminado solo si NO tenía Ultimo = 1
            if (!$tieneUltimo) {
                TejidoHelpers::recalcularPosicionesPorTelar($salon, $telar);
            }

            // Recalcular fechas y regenerar líneas para cada telar afectado
            $dispatcher = ReqProgramaTejido::suppressObservers();
            $idsRegenerados = [];

            foreach ($telaresAfectados as $ta) {
                $registrosTelar = ReqProgramaTejido::query()
                    ->salon($ta['salon'])
                    ->telar($ta['telar'])
                    ->orderBy('FechaInicio', 'asc')
                    ->get();

                if ($registrosTelar->isEmpty()) {
                    continue;
                }

                $primero = $registrosTelar->first();
                $inicioOriginal = $primero->FechaInicio ? Carbon::parse($primero->FechaInicio) : null;
                if (!$inicioOriginal) continue;

                // Recalcular fechas de la secuencia
                [$updates, $detalles] = DateHelpers::recalcularFechasSecuencia($registrosTelar, $inicioOriginal);

                ProgramaTejidoSecuenciaHelper::aplicarUpdatesDesdeRecalculo($updates);

                $modelos = ReqProgramaTejido::findMany(array_column($detalles, 'Id'));
                ReqProgramaTejido::regenerarLineas($modelos);
                $idsRegenerados = array_merge($idsRegenerados, $modelos->pluck('Id')->all());
            }

            DB::commit();

            // Re-habilitar observer
            ReqProgramaTejido::restoreObservers($dispatcher);

            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado correctamente. TotalPedido transferido al ' . ($esLider ? 'receptor' : 'líder') . '. Fechas y líneas recalculadas.',
                'cascaded_records' => count($idsRegenerados),
                'telares_afectados' => count($telaresAfectados)
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            ReqProgramaTejido::restoreObservers($dispatcher);
            Log::error('eliminar con OrdCompartida error', [
                'id' => $registro->Id ?? null,
                'ord_compartida' => $ordCompartida ?? null,
                'msg' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Recalcular fechas y fórmulas de un registro después de cambiar TotalPedido
     * Recalcula la secuencia completa del telar y regenera líneas
     *
     * @param ReqProgramaTejido $registro
     * @return void
     */
    private static function recalcularFechasYFormulas(ReqProgramaTejido $registro)
    {
        $salon = $registro->SalonTejidoId;
        $telar = $registro->NoTelarId;

        $registrosTelar = ReqProgramaTejido::query()
            ->salon($salon)
            ->telar($telar)
            ->orderBy('FechaInicio', 'asc')
            ->get();

        if ($registrosTelar->isEmpty()) {
            return;
        }

        $primero = $registrosTelar->first();
        $inicioOriginal = $primero->FechaInicio ? Carbon::parse($primero->FechaInicio) : null;
        if (!$inicioOriginal) {
            return;
        }

        // Recalcular fechas de toda la secuencia del telar
        [$updates, $detalles] = DateHelpers::recalcularFechasSecuencia($registrosTelar, $inicioOriginal);

        ProgramaTejidoSecuenciaHelper::aplicarUpdatesDesdeRecalculo($updates);

        ProgramaTejidoSecuenciaHelper::regenerarLineasDesdeDetalles($detalles);
    }

}
