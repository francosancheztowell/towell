<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DragAndDropTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DuplicarTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\DividirTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\VincularTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\QueryHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * @file ProgramaTejidoOperacionesController.php
 * @description Controlador de operaciones para Programa Tejido. Cambio de telar, mover posición,
 *              duplicar, dividir, vincular registros, desvincular. Regla: OrdCompartida agrupa
 *              registros vinculados; OrdCompartidaLider marca el líder del grupo.
 * @dependencies DragAndDropTejido, DuplicarTejido, DividirTejido, VincularTejido, QueryHelpers
 */
class ProgramaTejidoOperacionesController extends Controller
{
    public function verificarCambioTelar(Request $request, int $id)
    {
        $request->validate([
            'nuevo_salon' => 'required|string',
            'nuevo_telar' => 'required|string'
        ]);

        try {
            $registro = ReqProgramaTejido::findOrFail($id);
            $nuevoSalon = $request->input('nuevo_salon');
            $nuevoTelar = $request->input('nuevo_telar');

            if ($registro->SalonTejidoId === $nuevoSalon && $registro->NoTelarId === $nuevoTelar) {
                return response()->json([
                    'puede_mover' => true,
                    'requiere_confirmacion' => false,
                    'mensaje' => 'Mismo telar'
                ]);
            }

            $modeloDestino = QueryHelpers::findModeloDestino($nuevoSalon, $registro);

            if (!$modeloDestino) {
                return response()->json([
                    'puede_mover' => false,
                    'requiere_confirmacion' => false,
                    'mensaje' => 'La clave modelo no existe en el telar destino. No se puede realizar el cambio.',
                    'clave_modelo' => $registro->TamanoClave,
                    'telar_destino' => $nuevoTelar,
                    'salon_destino' => $nuevoSalon
                ]);
            }

            [$nuevaEficiencia, $nuevaVelocidad] = QueryHelpers::resolverStdSegunTelar($registro, $modeloDestino, $nuevoTelar, $nuevoSalon);
            $cambios = [];

            if ($registro->SalonTejidoId !== $nuevoSalon) {
                $cambios[] = ['campo' => 'Salón', 'actual' => $registro->SalonTejidoId, 'nuevo' => $nuevoSalon];
            }
            if ($registro->NoTelarId !== $nuevoTelar) {
                $cambios[] = ['campo' => 'Telar', 'actual' => $registro->NoTelarId, 'nuevo' => $nuevoTelar];
            }
            if ($registro->Ultimo == 1 || $registro->Ultimo == 'UL' || $registro->Ultimo == '1') {
                $cambios[] = ['campo' => 'Último', 'actual' => $registro->Ultimo, 'nuevo' => '0'];
            }
            if ($registro->CambioHilo != 0 && $registro->CambioHilo != null) {
                $cambios[] = ['campo' => 'Cambio Hilo', 'actual' => $registro->CambioHilo, 'nuevo' => '0'];
            }
            if ($modeloDestino->AnchoToalla && $registro->Ancho != $modeloDestino->AnchoToalla) {
                $cambios[] = ['campo' => 'Ancho', 'actual' => $registro->Ancho ?? 'N/A', 'nuevo' => $modeloDestino->AnchoToalla];
            }
            if ($nuevaEficiencia !== null && $registro->EficienciaSTD != $nuevaEficiencia) {
                $cambios[] = ['campo' => 'Eficiencia STD', 'actual' => $registro->EficienciaSTD ?? 'N/A', 'nuevo' => number_format($nuevaEficiencia, 2)];
            }
            if ($nuevaVelocidad !== null && $registro->VelocidadSTD != $nuevaVelocidad) {
                $cambios[] = ['campo' => 'Velocidad STD', 'actual' => $registro->VelocidadSTD ?? 'N/A', 'nuevo' => $nuevaVelocidad];
            }
            if (isset($modeloDestino->CalibreRizo2) && $modeloDestino->CalibreRizo2 !== null) {
                $calibreRizoActual = $registro->CalibreRizo2 ?? $registro->CalibreRizo ?? null;
                if ($calibreRizoActual != $modeloDestino->CalibreRizo2) {
                    $cambios[] = ['campo' => 'Calibre Rizo', 'actual' => $calibreRizoActual ?? 'N/A', 'nuevo' => $modeloDestino->CalibreRizo2];
                }
            }
            if (isset($modeloDestino->CalibrePie2) && $modeloDestino->CalibrePie2 !== null) {
                $calibrePieActual = $registro->CalibrePie2 ?? $registro->CalibrePie ?? null;
                if ($calibrePieActual != $modeloDestino->CalibrePie2) {
                    $cambios[] = ['campo' => 'Calibre Pie', 'actual' => $calibrePieActual ?? 'N/A', 'nuevo' => $modeloDestino->CalibrePie2];
                }
            }
            $cambios[] = [
                'campo' => 'Fecha Inicio',
                'actual' => $registro->FechaInicio ? Carbon::parse($registro->FechaInicio)->format('d/m/Y H:i') : 'N/A',
                'nuevo' => 'Se recalculará'
            ];
            $cambios[] = [
                'campo' => 'Fecha Final',
                'actual' => $registro->FechaFinal ? Carbon::parse($registro->FechaFinal)->format('d/m/Y H:i') : 'N/A',
                'nuevo' => 'Se recalculará'
            ];
            if ($registro->Calc4 || $registro->Calc5 || $registro->Calc6) {
                $cambios[] = ['campo' => 'Cálculos (Calc4, Calc5, Calc6)', 'actual' => 'Tienen valores', 'nuevo' => 'Se recalcularán'];
            }

            return response()->json([
                'puede_mover' => true,
                'requiere_confirmacion' => true,
                'mensaje' => 'Se moverá el registro a otro telar. Se aplicarán los siguientes cambios:',
                'clave_modelo' => $registro->TamanoClave,
                'telar_origen' => $registro->NoTelarId,
                'salon_origen' => $registro->SalonTejidoId,
                'telar_destino' => $nuevoTelar,
                'salon_destino' => $nuevoSalon,
                'cambios' => $cambios
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'puede_mover' => false,
                'mensaje' => 'Error al verificar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cambiarTelar(Request $request, int $id)
    {
        $request->validate([
            'nuevo_salon' => 'required|string',
            'nuevo_telar' => 'required|string',
            'target_position' => 'required|integer|min:0'
        ]);

        $registro = ReqProgramaTejido::findOrFail($id);
        $antes = [
            'salon' => $registro->SalonTejidoId,
            'telar' => $registro->NoTelarId,
            'posicion' => $registro->Posicion ?? 0
        ];

        if ($registro->EnProceso == 1) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede mover un registro en proceso. Debe finalizar el proceso antes de moverlo.'
            ], 422);
        }

        $nuevoSalon = $request->input('nuevo_salon');
        $nuevoTelar = $request->input('nuevo_telar');
        $targetPosition = max(0, (int)$request->input('target_position'));

        if ($registro->SalonTejidoId === $nuevoSalon && $registro->NoTelarId === $nuevoTelar) {
            return response()->json([
                'success' => false,
                'message' => 'Selecciona un telar diferente para aplicar el cambio.'
            ], 422);
        }

        $modeloDestino = QueryHelpers::findModeloDestino($nuevoSalon, $registro);
        if (!$modeloDestino) {
            return response()->json([
                'success' => false,
                'message' => 'La clave modelo no existe en el telar destino.'
            ], 422);
        }

        DBFacade::beginTransaction();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            $idsAfectados = [];
            $detallesTotales = [];
            $updatesOrigen = [];
            $updatesDestino = [];
            $origenSalon = $registro->SalonTejidoId;
            $origenTelar = $registro->NoTelarId;

            DBFacade::table(ReqProgramaTejido::tableName())
                ->where('Id', $registro->Id)
                ->where('SalonTejidoId', $origenSalon)
                ->where('NoTelarId', $origenTelar)
                ->update(['Posicion' => -1 * (int)$registro->Id]);

            $origenRegistros = ReqProgramaTejido::query()
                ->salon($origenSalon)
                ->telar($origenTelar)
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();

            $inicioOrigen = $origenRegistros->first()?->FechaInicio;
            $origenSin = $origenRegistros->reject(fn($item) => $item->Id === $registro->Id)->values();

            $destRegistrosOriginal = ReqProgramaTejido::query()
                ->salon($nuevoSalon)
                ->telar($nuevoTelar)
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();

            $destInicio = $destRegistrosOriginal->first()?->FechaInicio ?? $registro->FechaInicio ?? now();

            $ultimoEnProcesoIndex = -1;
            foreach ($destRegistrosOriginal as $index => $regDestino) {
                if ($regDestino->EnProceso == 1) {
                    $ultimoEnProcesoIndex = $index;
                }
            }
            if ($ultimoEnProcesoIndex !== -1) {
                $posicionMinima = $ultimoEnProcesoIndex + 1;
                if ($targetPosition < $posicionMinima) {
                    DBFacade::rollBack();
                    ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede colocar un registro antes de uno que está en proceso. La posición mínima permitida es ' . ($posicionMinima + 1) . '.'
                    ], 422);
                }
            }

            $registro->SalonTejidoId = $nuevoSalon;
            $registro->NoTelarId = $nuevoTelar;
            [$nuevaEficiencia, $nuevaVelocidad] = QueryHelpers::resolverStdSegunTelar($registro, $modeloDestino, $nuevoTelar, $nuevoSalon);
            if (!is_null($nuevaEficiencia)) {
                $registro->EficienciaSTD = round($nuevaEficiencia, 2);
            }
            if (!is_null($nuevaVelocidad)) {
                $registro->VelocidadSTD = $nuevaVelocidad;
            }
            $registro->Maquina = $this->construirMaquinaSegunSalon($registro->Maquina ?? '', $nuevoSalon, $nuevoTelar);
            if ($modeloDestino && $modeloDestino->AnchoToalla) {
                $registro->Ancho = $modeloDestino->AnchoToalla;
                $registro->AnchoToalla = $modeloDestino->AnchoToalla;
            }

            $cambioHilo = 0;
            if ($destRegistrosOriginal->count() > 0) {
                $registroAnterior = null;
                if ($targetPosition > 0 && $targetPosition <= $destRegistrosOriginal->count()) {
                    $registroAnterior = $destRegistrosOriginal->get($targetPosition - 1);
                }
                if ($registroAnterior) {
                    $fibraRizoActual = trim((string) ($registro->FibraRizo ?? ''));
                    $fibraRizoAnterior = trim((string) ($registroAnterior->FibraRizo ?? ''));
                    $cambioHilo = ($fibraRizoActual !== $fibraRizoAnterior && $fibraRizoAnterior !== '') ? 1 : 0;
                }
            }
            $registro->CambioHilo = $cambioHilo;

            $destRegistros = $destRegistrosOriginal->values();
            $targetPosition = min(max($targetPosition, 0), $destRegistros->count());
            $destRegistros->splice($targetPosition, 0, [$registro]);
            $destRegistros = $destRegistros->values();

            if ($origenSin->count() > 0) {
                $inicioOrigenCarbon = Carbon::parse($inicioOrigen ?? $registro->FechaInicio ?? now());
                [$updatesOrigen, $detallesOrigen] = DateHelpers::recalcularFechasSecuencia($origenSin, $inicioOrigenCarbon);
                foreach ($origenSin->values() as $index => $r) {
                    $idRegistro = (int)$r->Id;
                    $nuevaPosicion = $index + 1;
                    if (!isset($updatesOrigen[$idRegistro])) {
                        $updatesOrigen[$idRegistro] = [];
                    }
                    $updatesOrigen[$idRegistro]['Posicion'] = $nuevaPosicion;
                }
                if (!empty($updatesOrigen)) {
                    DBFacade::table(ReqProgramaTejido::tableName())
                        ->whereIn('Id', array_keys($updatesOrigen))
                        ->where('SalonTejidoId', $origenSalon)
                        ->where('NoTelarId', $origenTelar)
                        ->update(['Posicion' => DBFacade::raw('Posicion + 10000')]);
                }
                foreach ($updatesOrigen as $idU => $data) {
                    if (isset($data['Posicion'])) {
                        $data['Posicion'] = (int)$data['Posicion'];
                    }
                    DBFacade::table(ReqProgramaTejido::tableName())
                        ->where('Id', $idU)
                        ->where('SalonTejidoId', $origenSalon)
                        ->where('NoTelarId', $origenTelar)
                        ->update($data);
                    $idsAfectados[] = $idU;
                }
                $detallesTotales = array_merge($detallesTotales, $detallesOrigen);
            }

            $destInicioCarbon = Carbon::parse($destInicio ?? now());
            [$updatesDestino, $detallesDestino] = DateHelpers::recalcularFechasSecuencia($destRegistros, $destInicioCarbon);
            foreach ($destRegistros->values() as $index => $r) {
                $idRegistro = (int)$r->Id;
                $nuevaPosicion = $index + 1;
                if (!isset($updatesDestino[$idRegistro])) {
                    $updatesDestino[$idRegistro] = [];
                }
                $updatesDestino[$idRegistro]['Posicion'] = $nuevaPosicion;
            }

            $updateRegistroMovido = [
                'SalonTejidoId' => $nuevoSalon,
                'NoTelarId' => $nuevoTelar,
                'EficienciaSTD' => $registro->EficienciaSTD,
                'VelocidadSTD' => $registro->VelocidadSTD,
                'Maquina' => $registro->Maquina,
                'CambioHilo' => $registro->CambioHilo,
                'UpdatedAt' => now(),
            ];
            if ($modeloDestino && $modeloDestino->AnchoToalla) {
                $updateRegistroMovido['Ancho'] = $modeloDestino->AnchoToalla;
                $updateRegistroMovido['AnchoToalla'] = $modeloDestino->AnchoToalla;
            }

            if (!isset($updatesDestino[$registro->Id])) {
                $updatesDestino[$registro->Id] = [];
            }
            $updatesDestino[$registro->Id] = array_merge($updatesDestino[$registro->Id], [
                'SalonTejidoId' => $nuevoSalon,
                'NoTelarId' => $nuevoTelar,
                'EficienciaSTD' => $registro->EficienciaSTD,
                'VelocidadSTD' => $registro->VelocidadSTD,
                'Maquina' => $registro->Maquina,
                'CambioHilo' => $registro->CambioHilo,
            ]);
            if ($modeloDestino && $modeloDestino->AnchoToalla) {
                $updatesDestino[$registro->Id]['Ancho'] = $modeloDestino->AnchoToalla;
                $updatesDestino[$registro->Id]['AnchoToalla'] = $modeloDestino->AnchoToalla;
            }

            DBFacade::table(ReqProgramaTejido::tableName())
                ->where('SalonTejidoId', $nuevoSalon)
                ->where('NoTelarId', $nuevoTelar)
                ->update(['Posicion' => DBFacade::raw('Id + 1000000')]);

            DBFacade::table(ReqProgramaTejido::tableName())
                ->where('Id', $registro->Id)
                ->where('SalonTejidoId', $origenSalon)
                ->where('NoTelarId', $origenTelar)
                ->update([
                    'SalonTejidoId' => $nuevoSalon,
                    'NoTelarId'     => $nuevoTelar,
                    'Posicion'      => DBFacade::raw('Id + 1000000'),
                ]);

            $updatesDestinoOrdenados = [];
            foreach ($updatesDestino as $idU => $data) {
                $posicion = isset($data['Posicion']) ? (int)$data['Posicion'] : 999999;
                $updatesDestinoOrdenados[] = ['id' => (int)$idU, 'posicion' => $posicion, 'data' => $data];
            }
            usort($updatesDestinoOrdenados, fn($a, $b) => $a['posicion'] <=> $b['posicion']);

            foreach ($updatesDestinoOrdenados as $item) {
                $idU  = (int)$item['id'];
                $data = $item['data'];
                if (isset($data['Posicion'])) {
                    $data['Posicion'] = (int)$data['Posicion'];
                }
                if ($idU === (int)$registro->Id) {
                    $data = array_merge($data, $updateRegistroMovido);
                }
                DBFacade::table(ReqProgramaTejido::tableName())
                    ->where('Id', $idU)
                    ->where('SalonTejidoId', $nuevoSalon)
                    ->where('NoTelarId', $nuevoTelar)
                    ->update($data);
                $idsAfectados[] = $idU;
            }
            $detallesTotales = array_merge($detallesTotales, $detallesDestino);

            DBFacade::commit();

            $idsAfectados = array_values(array_unique($idsAfectados));
            $updatesMerged = [];
            foreach ($updatesOrigen as $idU => $data) {
                $updatesMerged[(string) $idU] = $data;
            }
            foreach ($updatesDestino as $idU => $data) {
                $updatesMerged[(string) $idU] = array_merge($updatesMerged[(string) $idU] ?? [], $data);
            }

            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsAfectados as $idAfectado) {
                if ($r = ReqProgramaTejido::find($idAfectado)) {
                    $r->refresh();
                    $observer->saved($r);
                }
            }

            $despues = [
                'salon' => $registro->SalonTejidoId,
                'telar' => $registro->NoTelarId,
                'posicion' => $registro->Posicion ?? $targetPosition
            ];
            \App\Helpers\AuditoriaHelper::logDragDrop(
                'ReqProgramaTejido',
                $registro->Id,
                $antes,
                $despues,
                $request
            );

            return response()->json([
                'success' => true,
                'message' => 'Telar actualizado correctamente',
                'registros_afectados' => count($idsAfectados),
                'detalles' => $detallesTotales,
                'updates' => $updatesMerged,
                'registro_id' => $registro->Id
            ]);
        } catch (\Throwable $e) {
            DBFacade::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            LogFacade::error('cambiarTelar error', [
                'id' => $id ?? null,
                'mensaje' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar de telar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function moveToPosition(Request $request, int $id)
    {
        return DragAndDropTejido::mover($request, $id);
    }

    public function duplicarTelar(Request $request)
    {
        return DuplicarTejido::duplicar($request);
    }

    public function dividirTelar(Request $request)
    {
        $request->validate([
            'salon_tejido_id' => 'required|string',
            'no_telar_id' => 'required|string',
            'posicion_division' => 'required|integer|min:0',
            'nuevo_telar' => 'required|string',
            'nuevo_salon' => 'nullable|string',
        ]);

        $salon = $request->input('salon_tejido_id');
        $telar = $request->input('no_telar_id');
        $posicionDivision = (int) $request->input('posicion_division');
        $nuevoTelar = $request->input('nuevo_telar');
        $nuevoSalon = $request->input('nuevo_salon') ?? $salon;

        DBFacade::beginTransaction();
        ReqProgramaTejido::unsetEventDispatcher();

        try {
            $registros = ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('FechaInicio', 'asc')
                ->lockForUpdate()
                ->get();

            if ($registros->count() < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se requieren al menos 2 registros para dividir un telar'
                ], 422);
            }

            if ($posicionDivision < 0 || $posicionDivision >= $registros->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La posición de división está fuera del rango válido'
                ], 422);
            }

            $registrosOriginales = $registros->take($posicionDivision);
            $registrosNuevos = $registros->skip($posicionDivision);

            $idsActualizados = [];
            foreach ($registrosNuevos as $registro) {
                $registro->SalonTejidoId = $nuevoSalon;
                $registro->NoTelarId = $nuevoTelar;
                $registro->Posicion = TejidoHelpers::obtenerSiguientePosicionDisponible($nuevoSalon, $nuevoTelar);
                $registro->CambioHilo = 0;
                $registro->Ultimo = 0;
                $registro->EnProceso = 0;
                $registro->UpdatedAt = now();
                $registro->save();
                $idsActualizados[] = $registro->Id;
            }

            if ($registrosOriginales->count() > 0) {
                $inicioOriginal = $registrosOriginales->first()->FechaInicio
                    ? Carbon::parse($registrosOriginales->first()->FechaInicio)
                    : now();
                [$updatesOriginales, $detallesOriginales] = DateHelpers::recalcularFechasSecuencia($registrosOriginales, $inicioOriginal);
                foreach ($updatesOriginales as $idU => $data) {
                    DBFacade::table(ReqProgramaTejido::tableName())->where('Id', $idU)->update($data);
                }
            }

            if ($registrosNuevos->count() > 0) {
                $inicioNuevo = $registrosNuevos->first()->FechaInicio
                    ? Carbon::parse($registrosNuevos->first()->FechaInicio)
                    : now();
                [$updatesNuevos, $detallesNuevos] = DateHelpers::recalcularFechasSecuencia($registrosNuevos, $inicioNuevo);
                foreach ($updatesNuevos as $idU => $data) {
                    DBFacade::table(ReqProgramaTejido::tableName())->where('Id', $idU)->update($data);
                }
            }

            DBFacade::commit();

            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsActualizados as $idAct) {
                if ($r = ReqProgramaTejido::find($idAct)) {
                    $observer->saved($r);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Telar dividido correctamente. Se movieron {$registrosNuevos->count()} registro(s) al nuevo telar.",
                'registros_movidos' => count($idsActualizados),
                'nuevo_telar' => $nuevoTelar,
                'nuevo_salon' => $nuevoSalon
            ]);
        } catch (\Throwable $e) {
            DBFacade::rollBack();
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);
            LogFacade::error('dividirTelar error', [
                'salon' => $salon,
                'telar' => $telar,
                'posicion_division' => $posicionDivision,
                'msg' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al dividir el telar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function dividirSaldo(Request $request)
    {
        return DividirTejido::dividir($request);
    }

    public function vincularTelar(Request $request)
    {
        $request->merge(['vincular' => true]);
        return DuplicarTejido::duplicar($request);
    }

    public function vincularRegistrosExistentes(Request $request)
    {
        return VincularTejido::vincularRegistrosExistentes($request);
    }

    public function desvincularRegistro(Request $request, $id)
    {
        return VincularTejido::desvincularRegistro($request, $id);
    }

    public function getRegistrosPorOrdCompartida($ordCompartida)
    {
        $ordCompartida = $this->normalizeOrdCompartida($ordCompartida);
        if ($ordCompartida === null) {
            return response()->json([
                'success' => false,
                'message' => 'OrdCompartida invalida'
            ], 400);
        }

        return BalancearTejido::getRegistrosPorOrdCompartida($ordCompartida);
    }

    private function normalizeOrdCompartida($value): ?int
    {
        if (is_null($value)) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '' || !is_numeric($trimmed)) {
            return null;
        }
        return (int) $trimmed;
    }

    private function construirMaquinaSegunSalon(string $maquinaBase, ?string $salon, $nuevoTelar): string
    {
        $salonNorm = strtoupper(trim((string) $salon));

        if ($salonNorm !== '') {
            if (preg_match('/SMI(T)?/i', $salonNorm)) {
                $prefijo = 'SMI';
            } elseif (preg_match('/JAC/i', $salonNorm)) {
                $prefijo = 'JAC';
            }
        }

        if (!isset($prefijo)) {
            if (preg_match('/^([A-Za-z]+)/', $maquinaBase, $m)) {
                $prefijo = $m[1];
            }
        }

        if (!isset($prefijo)) {
            $prefijo = substr($salonNorm, 0, 4);
            $prefijo = rtrim($prefijo, '0123456789');
        }

        return trim($prefijo) . ' ' . $nuevoTelar;
    }
}
