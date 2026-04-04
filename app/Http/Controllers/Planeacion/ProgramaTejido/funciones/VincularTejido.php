<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Http\Controllers\Planeacion\ProgramaTejido\helper\OrdCompartidaHelper;
use App\Models\Planeacion\Catalogos\CatCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class VincularTejido
{
    /**
     * Vincular registros existentes de ReqProgramaTejido
     * sin importar el salón o la diferencia de clave modelo
     */
    public static function vincularRegistrosExistentes(Request $request)
    {
        $request->validate([
            'registros_ids' => 'required|array|min:2',
            'registros_ids.*' => ['required', 'integer', Rule::exists(ReqProgramaTejido::tableName(), 'Id')],
        ]);

        $registrosIds = $request->input('registros_ids');

        // Verificar que todos los registros existan
        $registros = ReqProgramaTejido::whereIn('Id', $registrosIds)->get();

        if ($registros->count() !== count($registrosIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Uno o más registros no fueron encontrados',
            ], 404);
        }

        // Obtener el primer registro (el primero en el array de IDs - orden de selección)
        // El array viene ordenado según el orden de selección del usuario
        $primerId = $registrosIds[0];
        $primerRegistro = $registros->firstWhere('Id', $primerId);

        if (! $primerRegistro) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el primer registro seleccionado',
            ], 404);
        }

        // Determinar el OrdCompartida a usar
        $ordCompartidaAVincular = null;
        $primerOrdCompartidaRaw = $primerRegistro->OrdCompartida;
        $primerTieneOrdCompartida = ! empty($primerOrdCompartidaRaw) && trim((string) $primerOrdCompartidaRaw) !== '';

        if ($primerTieneOrdCompartida) {
            // Si el primer registro ya tiene OrdCompartida, usar ese
            $ordCompartidaAVincular = (int) trim((string) $primerOrdCompartidaRaw);

            // Validar que los demás registros no tengan un OrdCompartida diferente
            $otrosRegistros = $registros->reject(fn ($r) => $r->Id === $primerId);
            $conOrdCompartidaDiferente = $otrosRegistros->filter(function ($registro) use ($ordCompartidaAVincular) {
                $ordRegistro = ! empty($registro->OrdCompartida) ? (int) trim((string) $registro->OrdCompartida) : null;

                return $ordRegistro !== null && $ordRegistro !== $ordCompartidaAVincular;
            });

            if ($conOrdCompartidaDiferente->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden vincular: El primer registro seleccionado tiene OrdCompartida '.$ordCompartidaAVincular.', pero algunos registros tienen un OrdCompartida diferente. Registros con OrdCompartida diferente: '.$conOrdCompartidaDiferente->pluck('Id')->implode(', '),
                ], 422);
            }
        } else {
            // Si el primer registro NO tiene OrdCompartida, validar que ninguno de los otros tenga
            $otrosRegistros = $registros->reject(fn ($r) => $r->Id === $primerId);
            $conOrdCompartida = $otrosRegistros->filter(function ($registro) {
                return ! empty($registro->OrdCompartida) && trim((string) $registro->OrdCompartida) !== '';
            });

            if ($conOrdCompartida->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden vincular: El primer registro seleccionado (ID: '.$primerId.') no tiene OrdCompartida, pero los siguientes registros sí lo tienen: '.$conOrdCompartida->pluck('Id')->implode(', ').'. Por favor, selecciona primero un registro que ya tenga OrdCompartida si deseas vincular con otros que también lo tengan.',
                ], 422);
            }

            // Crear un nuevo OrdCompartida disponible
            $ordCompartidaAVincular = OrdCompartidaHelper::obtenerNuevoOrdCompartidaDisponible();
        }

        DBFacade::beginTransaction();
        $dispatcher = ReqProgramaTejido::suppressObservers();

        try {
            // PASO 1: Primero, quitar OrdCompartidaLider de todos los registros que se van a vincular
            // (excepto el primero, que se actualizará después)
            $otrosIds = array_filter($registrosIds, fn ($id) => $id != $primerId);
            if (count($otrosIds) > 0) {
                ReqProgramaTejido::whereIn('Id', $otrosIds)
                    ->update([
                        'OrdCompartidaLider' => null,
                        'UpdatedAt' => now(),
                    ]);
            }

            // PASO 2: Quitar OrdCompartidaLider de todos los registros que ya tienen el mismo OrdCompartida
            // (para asegurar que solo haya un líder)
            ReqProgramaTejido::where('OrdCompartida', $ordCompartidaAVincular)
                ->where('Id', '!=', $primerId)
                ->update([
                    'OrdCompartidaLider' => null,
                    'UpdatedAt' => now(),
                ]);

            // PASO 3: Actualizar todos los registros con el OrdCompartida determinado
            // Solo actualizar los que no tienen OrdCompartida o tienen uno diferente
            $actualizados = ReqProgramaTejido::whereIn('Id', $registrosIds)
                ->where(function ($query) use ($ordCompartidaAVincular) {
                    $query->whereNull('OrdCompartida')
                        ->orWhere('OrdCompartida', '!=', $ordCompartidaAVincular)
                        ->orWhereRaw("LTRIM(RTRIM(CAST(OrdCompartida AS NVARCHAR(50)))) = ''");
                })
                ->update([
                    'OrdCompartida' => $ordCompartidaAVincular,
                    'UpdatedAt' => now(),
                ]);

            // PASO 4: Asignar OrdCompartidaLider = 1 al registro con fecha inicio más antigua
            // Obtener todos los registros con este OrdCompartida (incluyendo los que ya lo tenían)
            $registrosConOrdCompartida = ReqProgramaTejido::where('OrdCompartida', $ordCompartidaAVincular)
                ->get();

            if ($registrosConOrdCompartida->count() > 0) {
                // Ordenar por FechaInicio (más antigua primero)
                $registrosOrdenados = $registrosConOrdCompartida->sortBy(function ($registro) {
                    return $registro->FechaInicio ? Carbon::parse($registro->FechaInicio)->timestamp : PHP_INT_MAX;
                });

                // El primero es el líder (fecha más antigua)
                $idLider = $registrosOrdenados->first()->Id;

                // Quitar OrdCompartidaLider de todos
                ReqProgramaTejido::where('OrdCompartida', $ordCompartidaAVincular)
                    ->update([
                        'OrdCompartidaLider' => null,
                        'UpdatedAt' => now(),
                    ]);

                // Asignar OrdCompartidaLider = 1 solo al registro con fecha más antigua
                ReqProgramaTejido::where('Id', $idLider)
                    ->update([
                        'OrdCompartidaLider' => 1,
                        'UpdatedAt' => now(),
                    ]);

                // PASO 4.1: Actualizar OrdPrincipal con el ItemId del líder en todos los registros compartidos
                self::actualizarOrdPrincipalPorOrdCompartida($ordCompartidaAVincular);
            }

            // PASO 5: Actualizar OrdCompartida y OrdCompartidaLider en CatCodificados si NoProduccion y Programado están llenos
            // Obtener todos los registros vinculados con sus valores actualizados
            // Los valores ya están actualizados en la transacción, así que los obtenemos frescos de la BD
            $registrosVinculadosActualizados = ReqProgramaTejido::where('OrdCompartida', $ordCompartidaAVincular)
                ->get();

            foreach ($registrosVinculadosActualizados as $registro) {
                if ($registro) {
                    // Obtener valores frescos usando fresh() para asegurar que tenemos los valores correctos de la BD
                    $registroFresco = $registro->fresh();
                    if ($registroFresco) {
                        self::actualizarOrdCompartidaEnCatCodificados($registroFresco);
                    }
                }
            }

            DBFacade::commit();

            // Reactivar observer
            ReqProgramaTejido::restoreObservers($dispatcher);

            // Disparar observer para recalcular fórmulas si es necesario
            ReqProgramaTejido::regenerarLineas(
                ReqProgramaTejido::findMany($registrosIds)
            );

            $mensaje = $primerTieneOrdCompartida
                ? "Se vincularon {$actualizados} registro(s) usando el OrdCompartida existente: {$ordCompartidaAVincular}"
                : "Se vincularon {$actualizados} registro(s) con nuevo OrdCompartida: {$ordCompartidaAVincular}";

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'ord_compartida' => $ordCompartidaAVincular,
                'registros_vinculados' => $actualizados,
                'registros_ids' => $registrosIds,
            ]);

        } catch (\Throwable $e) {
            DBFacade::rollBack();
            ReqProgramaTejido::restoreObservers($dispatcher);

            LogFacade::error('vincularRegistrosExistentes error', [
                'registros_ids' => $registrosIds,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al vincular los registros: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desvincular un registro de su OrdCompartida
     *
     * - Si hay 2 registros con la misma OrdCompartida: ambos se ponen OrdCompartida = null y OrdCompartidaLider = null
     * - Si hay más de 2: solo el seleccionado se pone OrdCompartida = null, y de los restantes se busca el que tiene la fecha más antigua para ponerlo como líder (OrdCompartidaLider = 1)
     */
    public static function desvincularRegistro(Request $request, $id)
    {
        $dispatcher = null;
        try {
            $registro = ReqProgramaTejido::find($id);

            if (! $registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado',
                ], 404);
            }

            $ordCompartida = $registro->OrdCompartida;

            if (! $ordCompartida || trim((string) $ordCompartida) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro no tiene OrdCompartida asignada',
                ], 400);
            }

            // Normalizar OrdCompartida
            $ordCompartidaNormalizada = (int) $ordCompartida;

            // Obtener todos los registros con la misma OrdCompartida
            $registrosConOrdCompartida = ReqProgramaTejido::where('OrdCompartida', $ordCompartidaNormalizada)
                ->get();

            $totalRegistros = $registrosConOrdCompartida->count();

            if ($totalRegistros < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros con esta OrdCompartida',
                ], 400);
            }

            DBFacade::beginTransaction();

            // Desactivar observer temporalmente
            $dispatcher = ReqProgramaTejido::suppressObservers();

            $idsAfectados = [];
            $idsDesvinculados = []; // IDs de registros desvinculados para obtener frescos después
            $registrosRestantesActualizados = []; // Para actualizar en CatCodificados cuando hay más de 2

            if ($totalRegistros === 2) {
                // Si hay 2 registros: ambos se ponen OrdCompartida = null y OrdCompartidaLider = null
                $registrosConOrdCompartida->each(function ($reg) use (&$idsAfectados, &$idsDesvinculados) {
                    $reg->OrdCompartida = null;
                    $reg->OrdCompartidaLider = null;
                    $reg->UpdatedAt = now();
                    $reg->save();
                    $idsAfectados[] = $reg->Id;
                    $idsDesvinculados[] = $reg->Id; // Guardar ID para obtener registro fresco después
                });
            } else {
                // Si hay más de 2: solo el seleccionado se pone OrdCompartida = null
                $registro->OrdCompartida = null;
                $registro->OrdCompartidaLider = null;
                $registro->UpdatedAt = now();
                $registro->save();
                $idsAfectados[] = $registro->Id;
                $idsDesvinculados[] = $registro->Id; // Guardar ID para obtener registro fresco después

                // De los registros restantes, buscar el que tiene la fecha más antigua para ponerlo como líder
                $registrosRestantes = $registrosConOrdCompartida->filter(function ($reg) use ($id) {
                    return $reg->Id != $id;
                });

                if ($registrosRestantes->count() > 0) {
                    // Ordenar por FechaInicio (más antigua primero)
                    $registrosOrdenados = $registrosRestantes->sortBy(function ($registro) {
                        return $registro->FechaInicio ? Carbon::parse($registro->FechaInicio)->timestamp : PHP_INT_MAX;
                    });

                    // Quitar OrdCompartidaLider de todos los registros restantes
                    $idsRestantes = $registrosRestantes->pluck('Id')->toArray();
                    ReqProgramaTejido::whereIn('Id', $idsRestantes)
                        ->update([
                            'OrdCompartidaLider' => null,
                            'UpdatedAt' => now(),
                        ]);

                    // Asignar OrdCompartidaLider = 1 al registro con fecha más antigua
                    $idLider = $registrosOrdenados->first()->Id;
                    ReqProgramaTejido::where('Id', $idLider)
                        ->update([
                            'OrdCompartidaLider' => 1,
                            'UpdatedAt' => now(),
                        ]);

                    // Actualizar OrdPrincipal con el ItemId del líder en todos los registros compartidos
                    self::actualizarOrdPrincipalPorOrdCompartida($ordCompartidaNormalizada);

                    $idsAfectados = array_merge($idsAfectados, $idsRestantes);

                    // Obtener todos los registros restantes con sus valores actualizados para actualizar en CatCodificados
                    $registrosRestantesActualizados = ReqProgramaTejido::whereIn('Id', $idsRestantes)
                        ->get();
                }
            }

            // Obtener registros desvinculados frescos de la BD (con OrdCompartida = null)
            // para limpiar en CatCodificados si NoProduccion y Programado están llenos
            if (count($idsDesvinculados) > 0) {
                $registrosDesvinculadosFrescos = ReqProgramaTejido::whereIn('Id', $idsDesvinculados)
                    ->whereNull('OrdCompartida')
                    ->get();

                foreach ($registrosDesvinculadosFrescos as $regDesvinculado) {
                    if ($regDesvinculado) {
                        self::limpiarOrdCompartidaEnCatCodificados($regDesvinculado);
                    }
                }
            }

            // Actualizar OrdCompartida y OrdCompartidaLider en CatCodificados para los registros restantes
            // (los que mantienen OrdCompartida pero tienen un nuevo OrdCompartidaLider)
            foreach ($registrosRestantesActualizados as $regRestante) {
                if ($regRestante) {
                    $regRestanteFresco = $regRestante->fresh();
                    if ($regRestanteFresco) {
                        self::actualizarOrdCompartidaEnCatCodificados($regRestanteFresco);
                    }
                }
            }

            DBFacade::commit();

            // Reactivar observer
            ReqProgramaTejido::restoreObservers($dispatcher);

            // Disparar observer para recalcular fórmulas si es necesario
            ReqProgramaTejido::regenerarLineas(
                ReqProgramaTejido::findMany($idsAfectados)
            );

            $mensaje = $totalRegistros === 2
                ? 'Se desvincularon ambos registros correctamente'
                : 'Registro desvinculado correctamente';

            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'registros_ids' => $idsAfectados,
                'total_registros_afectados' => count($idsAfectados),
            ]);

        } catch (\Throwable $e) {
            DBFacade::rollBack();

            ReqProgramaTejido::restoreObservers($dispatcher);

            LogFacade::error('desvincularRegistro error', [
                'registro_id' => $id,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desvincular el registro: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Query base CatCodificados por orden de tejido + telar (columnas dinámicas en BD).
     *
     * @param  bool  $omitirFiltroTelarSiVacio  Si true (p. ej. OrdPrincipal), no filtra por telar cuando viene vacío.
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: string, 2: array<int, string>}
     */
    private static function buildCatCodificadosQueryOrdenTelar(string $noProduccion, string $noTelarId, bool $omitirFiltroTelarSiVacio = false): array
    {
        $modelo = new CatCodificados;
        $table = $modelo->getTable();
        $columns = Schema::getColumnListing($table);
        $query = CatCodificados::query();
        $hasKeyFilter = false;

        if (in_array('OrdenTejido', $columns, true)) {
            $query->where('OrdenTejido', $noProduccion);
            $hasKeyFilter = true;
        } elseif (in_array('NumOrden', $columns, true)) {
            $query->where('NumOrden', $noProduccion);
            $hasKeyFilter = true;
        }

        $aplicarTelar = ! $omitirFiltroTelarSiVacio || $noTelarId !== '';
        if ($aplicarTelar) {
            if (in_array('TelarId', $columns, true)) {
                $query->where('TelarId', $noTelarId);
            } elseif (in_array('NoTelarId', $columns, true)) {
                $query->where('NoTelarId', $noTelarId);
            }
        }

        if (! $hasKeyFilter) {
            $query->where('NoProduccion', $noProduccion);
        }

        return [$query, $table, $columns];
    }

    /**
     * Actualiza OrdCompartida y OrdCompartidaLider en CatCodificados basándose en los valores de ReqProgramaTejido
     * Solo si NoProduccion y Programado están llenos
     */
    private static function actualizarOrdCompartidaEnCatCodificados(ReqProgramaTejido $registro): void
    {
        $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
        try {
            $programado = $registro->Programado ?? null;

            if (empty($noProduccion) || empty($programado)) {
                return;
            }

            $noTelarId = trim((string) ($registro->NoTelarId ?? ''));
            [$query, $table] = array_slice(self::buildCatCodificadosQueryOrdenTelar($noProduccion, $noTelarId), 0, 2);
            $registroCodificado = $query->first();

            if (! $registroCodificado) {
                return;
            }

            $ordCompartida = $registro->OrdCompartida;
            $ordCompartidaLider = $registro->OrdCompartidaLider;

            $ordCompartidaValue = null;
            if ($ordCompartida !== null && $ordCompartida !== '' && trim((string) $ordCompartida) !== '') {
                $ordCompartidaValue = (int) trim((string) $ordCompartida);
            }

            $ordCompartidaLiderValue = null;
            if ($ordCompartidaLider === 1 || $ordCompartidaLider === '1' || $ordCompartidaLider === true) {
                $ordCompartidaLiderValue = 1;
            }

            DBFacade::table($table)
                ->where('Id', $registroCodificado->Id)
                ->update([
                    'OrdCompartida' => $ordCompartidaValue,
                    'OrdCompartidaLider' => $ordCompartidaLiderValue,
                ]);
        } catch (\Throwable $e) {
            LogFacade::warning('Error al actualizar OrdCompartida en CatCodificados', [
                'registro_id' => $registro->Id ?? null,
                'no_produccion' => $noProduccion !== '' ? $noProduccion : null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Limpia OrdCompartida y OrdCompartidaLider en CatCodificados si NoProduccion y Programado están llenos
     * Se usa solo en desvincular para poner los valores en null
     */
    private static function limpiarOrdCompartidaEnCatCodificados(ReqProgramaTejido $registro): void
    {
        $noProduccion = trim((string) ($registro->NoProduccion ?? ''));
        try {
            $programado = $registro->Programado ?? null;

            if (empty($noProduccion) || empty($programado)) {
                return;
            }

            $noTelarId = trim((string) ($registro->NoTelarId ?? ''));
            [$query, $table] = array_slice(self::buildCatCodificadosQueryOrdenTelar($noProduccion, $noTelarId), 0, 2);
            $registroCodificado = $query->first();

            if ($registroCodificado) {
                DBFacade::table($table)
                    ->where('Id', $registroCodificado->Id)
                    ->update([
                        'OrdCompartida' => null,
                        'OrdCompartidaLider' => null,
                    ]);
            }
        } catch (\Throwable $e) {
            LogFacade::warning('Error al limpiar OrdCompartida en CatCodificados', [
                'registro_id' => $registro->Id ?? null,
                'no_produccion' => $noProduccion !== '' ? $noProduccion : null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Actualiza OrdPrincipal con el ItemId del líder en todos los registros que comparten el mismo OrdCompartida.
     * OrdPrincipal = ItemId (Clave AX) del registro líder.
     * Función pública estática para poder ser llamada desde otras clases (DividirTejido, etc.)
     */
    public static function actualizarOrdPrincipalPorOrdCompartida(int $ordCompartida): void
    {
        try {
            // Obtener el registro líder (OrdCompartidaLider = 1)
            $lider = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->where('OrdCompartidaLider', 1)
                ->first(['Id', 'ItemId']);

            if (! $lider || empty($lider->ItemId)) {
                // Si no hay líder o no tiene ItemId, no actualizar OrdPrincipal
                return;
            }

            $itemIdLider = trim((string) $lider->ItemId);
            if ($itemIdLider === '') {
                return;
            }

            // Actualizar OrdPrincipal con el ItemId del líder en TODOS los registros con este OrdCompartida
            ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->update([
                    'OrdPrincipal' => $itemIdLider,
                    'UpdatedAt' => now(),
                ]);

            // También actualizar en CatCodificados para todos los registros con este OrdCompartida
            $registrosCompartidos = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->get(['Id', 'NoProduccion', 'NoTelarId']);

            foreach ($registrosCompartidos as $registro) {
                if ($registro && $registro->NoProduccion) {
                    $noProduccion = trim((string) $registro->NoProduccion);
                    $noTelarId = trim((string) ($registro->NoTelarId ?? ''));

                    if ($noProduccion !== '') {
                        [$query, $table, $columns] = self::buildCatCodificadosQueryOrdenTelar($noProduccion, $noTelarId, true);
                        $registroCodificado = $query->first();
                        if ($registroCodificado && in_array('OrdPrincipal', $columns, true)) {
                            DBFacade::table($table)
                                ->where('Id', $registroCodificado->Id)
                                ->update(['OrdPrincipal' => $itemIdLider]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Loggear error pero no fallar la operación principal
            LogFacade::warning('Error al actualizar OrdPrincipal por OrdCompartida', [
                'ord_compartida' => $ordCompartida,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
