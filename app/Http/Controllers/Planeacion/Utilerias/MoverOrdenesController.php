<?php

/**
 * @file MoverOrdenesController.php
 * @description Controlador para mover órdenes de producción entre telares.
 * @dependencies ReqProgramaTejido, TejidoHelpers
 * @relatedFiles FinalizarOrdenesController.php, resources/views/planeacion/utileria/mover-ordenes.blade.php
 *
 * ! REPORTE DE FUNCIONALIDAD - Mover Órdenes
 * * -----------------------------------------------
 * * 1. Obtiene la lista de todos los telares (NoTelarId) que tienen registros en ReqProgramaTejido
 * * 2. Al seleccionar un telar ORIGEN, carga todos los registros de ese telar
 * * 3. Al seleccionar un telar DESTINO, carga todos los registros de ese telar
 * * 4. El usuario selecciona registros del telar origen y los "arrastra" al telar destino
 * *    mediante una interfaz de tipo drag-and-drop con botones de flecha
 * * 5. Al confirmar el movimiento:
 * *    - Se actualiza NoTelarId y SalonTejidoId de cada registro movido al telar destino
 * *    - Se asigna Posicion al final del telar destino
 * *    - Se recalculan las posiciones de ambos telares (origen y destino)
 * * 6. EnProceso se normaliza al final: solo Posicion = 1 queda EnProceso = 1.
 * * -----------------------------------------------
 */

namespace App\Http\Controllers\Planeacion\Utilerias;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\QueryHelpers;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Http\Controllers\Tejedores\Desarrolladores\Funciones\MovimientoDesarrolladorService;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Support\Planeacion\TelarSalonResolver;
use App\Support\Http\Concerns\HandlesApiErrors;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MoverOrdenesController extends Controller
{
    use HandlesApiErrors;

    /**
     * Obtiene todos los telares que tienen al menos un registro con NoProduccion (no orden).
     */
    public function getTelares(): JsonResponse
    {
        try {
            $telares = \App\Models\Planeacion\ReqTelares::query()
                ->select('SalonTejidoId', 'NoTelarId')
                ->whereNotNull('NoTelarId')
                ->where('NoTelarId', '!=', '')
                ->get()
                ->map(function ($t) {
                    $telar = TelarSalonResolver::normalizeTelar($t->NoTelarId);
                    $salon = TelarSalonResolver::normalizeSalon($t->SalonTejidoId, $telar);

                    return [
                        'salon' => $salon,
                        'telar' => $telar,
                        'label' => $salon . ' - ' . $telar,
                    ];
                })
                ->filter(fn (array $telar) => $telar['telar'] !== '')
                ->unique(fn (array $telar) => $telar['salon'] . '|' . $telar['telar'])
                ->sortBy(fn (array $telar) => $telar['salon'] . '|' . TelarSalonResolver::telarSortKey($telar['telar']))
                ->values();

            return response()->json(['success' => true, 'telares' => $telares]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse(
                $e,
                'Utileria/Mover - Error al obtener telares',
                'Error al obtener telares',
                500,
                ['action' => __METHOD__]
            );
        }
    }

    /**
     * Obtiene los registros con NoProduccion (no orden) de un telar específico.
     * Incluye enProceso para que la UI muestre cuáles están en proceso.
     */
    public function getRegistrosByTelar(Request $request): JsonResponse
    {
        try {
            $noTelarId = TelarSalonResolver::normalizeTelar($request->query('telar'));
            $salonId = TelarSalonResolver::normalizeSalon($request->query('salon'), $noTelarId);

            if (!$salonId || !$noTelarId) {
                return $this->apiClientErrorResponse(
                    'Salon y telar son requeridos',
                    422,
                    [
                        'action' => __METHOD__,
                        'salon' => $salonId,
                        'telar' => $noTelarId,
                    ]
                );
            }

            $registros = TelarSalonResolver::applyTelarFilter(
                ReqProgramaTejido::query()
                ->select('Id', 'NoProduccion', 'TamanoClave', 'NombreProducto', 'SalonTejidoId', 'NoTelarId', 'Posicion', 'EnProceso', 'Produccion')
                ->whereNotNull('NoProduccion')
                ->where('NoProduccion', '!=', '')
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc'),
                $salonId,
                $noTelarId
            )->get()
                ->map(function (ReqProgramaTejido $r) {
                    $modelo = trim((string) ($r->NombreProducto ?? ''));
                    $esRepaso1 = $modelo !== '' && stripos($modelo, 'repaso1') !== false;
                    return [
                        'id' => $r->Id,
                        'noOrden' => $r->NoProduccion ?? '',
                        'tamanoClave' => $r->TamanoClave ?? '',
                        'modelo' => $modelo,
                        'posicion' => $r->Posicion ?? 0,
                        'enProceso' => (bool) $r->EnProceso,
                        'esRepaso1' => $esRepaso1,
                        'produccion' => $r->Produccion ?? 0,
                        'telar' => $r->NoTelarId ?? '',
                    ];
                });

            return response()->json(['success' => true, 'registros' => $registros]);
        } catch (\Throwable $e) {
            return $this->apiErrorResponse(
                $e,
                'Utileria/Mover - Error al obtener registros',
                'Error al obtener registros del telar',
                500,
                [
                    'action' => __METHOD__,
                    'salon' => (string) $request->query('salon', ''),
                    'telar' => (string) $request->query('telar', ''),
                ]
            );
        }
    }

    /**
     * Mueve registros y actualiza el orden en base a los arrays de IDs enviados.
     *
     * Por cada telar afectado:
     *  1. Evita colisiones en el índice único (Posicion + 10000 antes de reasignar).
     *  2. Guarda posición, telar y salón definitivos.
     *     Si el registro cambia de salón, actualiza también Maquina, EficienciaSTD,
     *     VelocidadSTD, CuentaRizo y CuentaPie según el salón destino.
     *  3. Recalcula la cadena de fechas (FechaInicio/FechaFinal) con DateHelpers.
     *  4. Dispara ReqProgramaTejidoObserver para regenerar líneas diarias y fórmulas.
     *  5. Sincroniza CatCodificados para los registros que cambiaron de salón.
     */
    public function moverOrdenes(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ordenes_origen'      => 'nullable|array',
            'ordenes_origen.*'    => 'integer',
            'origen_salon'        => 'nullable|string',
            'origen_telar'        => 'nullable|string',
            'ordenes_destino'     => 'nullable|array',
            'ordenes_destino.*'   => 'integer',
            'destino_salon'       => 'nullable|string',
            'destino_telar'       => 'nullable|string',
            'solo_reorden_origen' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->apiClientErrorResponse(
                'Datos de entrada invalidos',
                422,
                ['action' => __METHOD__, 'errors' => $validator->errors()->toArray()],
                ['errors' => $validator->errors()->toArray()]
            );
        }

        $soloReordenOrigen = $request->boolean('solo_reorden_origen', false);
        $telares = [
            ['salon' => $request->input('origen_salon'), 'telar' => $request->input('origen_telar'), 'ordenes' => $request->input('ordenes_origen', [])],
        ];
        if (!$soloReordenOrigen) {
            $telares[] = ['salon' => $request->input('destino_salon'), 'telar' => $request->input('destino_telar'), 'ordenes' => $request->input('ordenes_destino', [])];
        }

        $idsSolicitados = array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge($request->input('ordenes_origen', []), $request->input('ordenes_destino', []))
        ))));

        $dispatcher     = ReqProgramaTejido::getEventDispatcher();
        $idsAfectados   = [];
        $idsCambioSalon = [];
        $tabla          = ReqProgramaTejido::tableName();

        DB::beginTransaction();
        try {
            $ahora               = Carbon::now();
            $inicioBasePorTelar  = [];
            $telaresAfectados    = [];
            $estadoOriginalPorId = [];

            foreach ($telares as $cfg) {
                $this->registrarInicioBaseTelar((string) ($cfg['salon'] ?? ''), (string) ($cfg['telar'] ?? ''), $inicioBasePorTelar);
            }

            if (!empty($idsSolicitados)) {
                $this->capturarEstadoOriginal($idsSolicitados, $estadoOriginalPorId, $inicioBasePorTelar);
            }

            // Paso 1: Guardar posicion, telar y salon con control de colisiones.
            foreach ($telares as $cfg) {
                $this->procesarMovimientoPorTelar($cfg, $tabla, $ahora, $estadoOriginalPorId, $telaresAfectados, $idsAfectados, $idsCambioSalon);
            }

            // Paso 2: Recalcular fechas encadenadas por telar.
            ReqProgramaTejido::unsetEventDispatcher();
            foreach ($telaresAfectados as $telarKey => $cfg) {
                $this->recalcularFechasPorTelar((string) $telarKey, (array) $cfg, $tabla, $inicioBasePorTelar, $idsAfectados);
            }

            // Paso 2.5: Normalizar EnProceso — solo Posicion=1 queda activo.
            foreach ($telaresAfectados as $telarKey => $cfg) {
                $this->normalizarEnProceso((string) $telarKey, (array) $cfg, $tabla, $idsAfectados);
            }

            // Paso 3: Restaurar dispatcher y confirmar.
            ReqProgramaTejido::setEventDispatcher($dispatcher);
            DB::commit();

        } catch (\Throwable $e) {
            ReqProgramaTejido::setEventDispatcher($dispatcher);
            DB::rollBack();
            return $this->apiErrorResponse(
                $e,
                'Utileria/Mover - Error al mover ordenes',
                'Error al guardar los cambios',
                500,
                [
                    'action'        => __METHOD__,
                    'origen_salon'  => (string) $request->input('origen_salon', ''),
                    'origen_telar'  => (string) $request->input('origen_telar', ''),
                    'destino_salon' => (string) $request->input('destino_salon', ''),
                    'destino_telar' => (string) $request->input('destino_telar', ''),
                ]
            );
        }

        // Paso 4: Disparar observer fuera de la transaccion.
        $this->dispararObserver($idsAfectados);

        // Paso 5: Sincronizar CatCodificados solo para cambios de salon.
        $this->sincronizarCatCodificados($idsCambioSalon);

        return response()->json(['success' => true, 'message' => 'Se guardaron los cambios correctamente.']);
    }

    /**
     * Normaliza un valor a string sin espacios extremos.
     */
    private function normalizar(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    /**
     * Registra la FechaInicio del primer registro del telar como ancla para el recálculo.
     *
     * @param  array<string, Carbon|null>  &$inicioBasePorTelar
     */
    private function registrarInicioBaseTelar(string $salon, string $telar, array &$inicioBasePorTelar): void
    {
        $telarNorm = TelarSalonResolver::normalizeTelar($telar);
        $salonNorm = TelarSalonResolver::normalizeSalon($salon, $telarNorm);
        if ($salonNorm === '' || $telarNorm === '') {
            return;
        }
        $telarKey = $salonNorm . '|' . $telarNorm;
        if (array_key_exists($telarKey, $inicioBasePorTelar)) {
            return;
        }
        $fechaBase = TelarSalonResolver::applyTelarFilter(
            ReqProgramaTejido::query()
            ->whereNotNull('FechaInicio')
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->lockForUpdate(),
            $salonNorm,
            $telarNorm
        )->value('FechaInicio');
        $inicioBasePorTelar[$telarKey] = $fechaBase ? Carbon::parse($fechaBase) : null;
    }

    /**
     * Bloquea y captura el estado original (salon, telar, enProceso) de cada id solicitado.
     *
     * @param  int[]                       $idsSolicitados
     * @param  array<int, array>           &$estadoOriginalPorId
     * @param  array<string, Carbon|null>  &$inicioBasePorTelar
     */
    private function capturarEstadoOriginal(array $idsSolicitados, array &$estadoOriginalPorId, array &$inicioBasePorTelar): void
    {
        $registrosOriginales = ReqProgramaTejido::query()
            ->select('Id', 'SalonTejidoId', 'NoTelarId', 'EnProceso')
            ->whereIn('Id', $idsSolicitados)
            ->lockForUpdate()
            ->get();

        foreach ($registrosOriginales as $ro) {
            $telarOriginal = TelarSalonResolver::normalizeTelar($ro->NoTelarId);
            $salonOriginal = TelarSalonResolver::normalizeSalon($ro->SalonTejidoId, $telarOriginal);
            $estadoOriginalPorId[(int) $ro->Id] = [
                'salon'     => $salonOriginal,
                'telar'     => $telarOriginal,
                'enProceso' => (bool) $ro->EnProceso,
            ];
            $this->registrarInicioBaseTelar($salonOriginal, $telarOriginal, $inicioBasePorTelar);
        }
    }

    /**
     * Paso 1: Asigna posición, telar y salón definitivos a los registros de un telar,
     * controlando colisiones en el índice único (NoTelarId, Posicion).
     *
     * @param  array<string, mixed>  $cfg               ['salon', 'telar', 'ordenes']
     * @param  array<int, array>     $estadoOriginalPorId
     * @param  array<string, array>  &$telaresAfectados
     * @param  array<string, int[]>  &$idsAfectados
     * @param  int[]                 &$idsCambioSalon
     */
    private function procesarMovimientoPorTelar(
        array $cfg,
        string $tabla,
        Carbon $ahora,
        array $estadoOriginalPorId,
        array &$telaresAfectados,
        array &$idsAfectados,
        array &$idsCambioSalon
    ): void {
        $telar   = TelarSalonResolver::normalizeTelar($cfg['telar'] ?? null);
        $salon   = TelarSalonResolver::normalizeSalon($cfg['salon'] ?? null, $telar);
        $ordenes = $cfg['ordenes'] ?? [];

        if ($salon === '' || $telar === '' || empty($ordenes)) {
            return;
        }

        $telarKey = $salon . '|' . $telar;
        $telaresAfectados[$telarKey] = ['salon' => $salon, 'telar' => $telar];

        // Bump temporal para evitar violación del índice único (NoTelarId, Posicion).
        // Se hace sobre TODOS los registros del telar para que los que no están en $ordenes
        // tampoco colisionen al reasignar.
        TelarSalonResolver::applyTelarFilter(DB::table($tabla), $salon, $telar)
            ->update(['Posicion' => DB::raw('ISNULL(Posicion, 0) + 10000')]);

        foreach ($ordenes as $index => $id) {
            $registro = ReqProgramaTejido::query()->whereKey($id)->lockForUpdate()->first();
            if (!$registro) {
                continue;
            }

            $original      = $estadoOriginalPorId[(int) $id] ?? null;
            $telarOriginal = TelarSalonResolver::normalizeTelar($original['telar'] ?? $registro->NoTelarId ?? '');
            $salonOriginal = TelarSalonResolver::normalizeSalon($original['salon'] ?? $registro->SalonTejidoId ?? '', $telarOriginal);

            $cambiaSalon     = ($salonOriginal !== $salon);
            $cambiaUbicacion = $cambiaSalon || ($telarOriginal !== $telar);

            if ($salonOriginal !== '' && $telarOriginal !== '') {
                $origenKey = $salonOriginal . '|' . $telarOriginal;
                $telaresAfectados[$origenKey] = ['salon' => $salonOriginal, 'telar' => $telarOriginal];
            }

            $registro->SalonTejidoId = $salon;
            $registro->NoTelarId     = $telar;
            $registro->Posicion      = $index + 1;
            $registro->UpdatedAt     = $ahora;

            if ($cambiaUbicacion) {
                $this->aplicarCambioUbicacion($registro, $salon, $telar, $cambiaSalon, (int) $id, $idsCambioSalon);
            }

            $registro->saveQuietly();
            $idsAfectados[$telarKey][] = (int) $id;
        }
    }

    /**
     * Aplica cambios de ubicación a un registro: EnProceso, Maquina, STD y cuentas del salón destino.
     *
     * @param  ReqProgramaTejido  $registro
     * @param  int[]  &$idsCambioSalon
     */
    private function aplicarCambioUbicacion(
        ReqProgramaTejido $registro,
        string $salon,
        string $telar,
        bool $cambiaSalon,
        int $id,
        array &$idsCambioSalon
    ): void {
        // En cruces de telar se normaliza al final.
        $registro->EnProceso = 0;
        $registro->Maquina   = TejidoHelpers::construirMaquinaConSalon($registro->Maquina, $salon, $telar);

        $tamanoClave   = trim((string) ($registro->TamanoClave ?? ''));
        $modeloDestino = $tamanoClave !== ''
            ? ReqModelosCodificados::query()->where('TamanoClave', $tamanoClave)->where('SalonTejidoId', $salon)->first()
            : null;

        $stdResult = QueryHelpers::resolverStdSegunTelar($registro, $modeloDestino, $telar, $salon);
        $nuevaEficiencia = $stdResult[0] ?? null;
        $nuevaVelocidad = $stdResult[1] ?? null;
        if (!is_null($nuevaEficiencia)) {
            $registro->EficienciaSTD = round((float) $nuevaEficiencia, 2);
        }
        if (!is_null($nuevaVelocidad)) {
            $registro->VelocidadSTD = (float) $nuevaVelocidad;
        }

        if ($modeloDestino) {
            if (!is_null($modeloDestino->CuentaRizo)) {
                $registro->CuentaRizo = (string) $modeloDestino->CuentaRizo;
            }
            if (!is_null($modeloDestino->CuentaPie)) {
                $registro->CuentaPie = (string) $modeloDestino->CuentaPie;
            }
        }

        if ($cambiaSalon) {
            $idsCambioSalon[] = $id;
        }
    }

    /**
     * Paso 2: Recalcula la cadena de fechas (FechaInicio/FechaFinal) de un telar.
     *
     * @param  array<string, Carbon|null>  $inicioBasePorTelar
     * @param  array<string, int[]>        &$idsAfectados
     */
    private function recalcularFechasPorTelar(
        string $telarKey,
        array $cfg,
        string $tabla,
        array $inicioBasePorTelar,
        array &$idsAfectados
    ): void {
        $telar = TelarSalonResolver::normalizeTelar((string) ($cfg['telar'] ?? ''));
        $salon = TelarSalonResolver::normalizeSalon((string) ($cfg['salon'] ?? ''), $telar);

        $registros = TelarSalonResolver::applyTelarFilter(
            ReqProgramaTejido::query()
            ->orderBy('Posicion', 'asc')
            ->orderBy('FechaInicio', 'asc')
            ->lockForUpdate(),
            $salon,
            $telar
        )->get();

        if ($registros->isEmpty()) {
            return;
        }

        $inicioBase     = $inicioBasePorTelar[$telarKey] ?? null;
        $inicioOriginal = $inicioBase instanceof Carbon ? $inicioBase->copy() : Carbon::now();

        // Primer registro se trata como EnProceso (usa now() y NO actualiza FechaInicio).
        // El resto queda fuera de proceso y se encadena desde el primero.
        $registrosParaCalculo = $registros->values()->map(function (ReqProgramaTejido $r, int $index) {
            $copia = clone $r;
            $copia->EnProceso = $index === 0 ? 1 : 0;
            return $copia;
        });

        [$updates] = DateHelpers::recalcularFechasSecuencia($registrosParaCalculo, $inicioOriginal, true);

        if (empty($updates)) {
            return;
        }

        // No sobrescribir EnProceso; la normalización final ocurre en el paso 2.5.
        foreach ($updates as &$upd) {
            unset($upd['EnProceso']);
        }
        unset($upd);

        // Bump temporal antes de actualizar fechas (evita colisiones de Posicion).
        TelarSalonResolver::applyTelarFilter(
            DB::table($tabla)->whereIn('Id', array_keys($updates)),
            $salon,
            $telar
        )->update(['Posicion' => DB::raw('ISNULL(Posicion, 0) + 10000')]);

        foreach ($updates as $idU => $dataU) {
            if (isset($dataU['Posicion'])) {
                $dataU['Posicion'] = (int) $dataU['Posicion'];
            }
            DB::table($tabla)
                ->where('Id', $idU)
                ->update($dataU);

            $idsAfectados[$telarKey][] = (int) $idU;
        }
    }

    /**
     * Paso 2.5: Normaliza EnProceso — solo el registro en Posicion=1 queda activo.
     *
     * @param  array<string, int[]>  &$idsAfectados
     */
    private function normalizarEnProceso(string $telarKey, array $cfg, string $tabla, array &$idsAfectados): void
    {
        $telar = TelarSalonResolver::normalizeTelar((string) ($cfg['telar'] ?? ''));
        $salon = TelarSalonResolver::normalizeSalon((string) ($cfg['salon'] ?? ''), $telar);

        if ($salon === '' || $telar === '') {
            return;
        }

        $idPosicionUno = TelarSalonResolver::applyTelarFilter(
            ReqProgramaTejido::query()
                ->orderBy('Posicion', 'asc')
                ->orderBy('FechaInicio', 'asc'),
            $salon,
            $telar
        )->value('Id');
        $idPosicionUno = $idPosicionUno ? (int) $idPosicionUno : null;

        TelarSalonResolver::applyTelarFilter(DB::table($tabla), $salon, $telar)->update(['EnProceso' => 0]);

        if ($idPosicionUno) {
            DB::table($tabla)
                ->where('Id', $idPosicionUno)
                ->update(['EnProceso' => 1]);
        }

        $idsTelar = TelarSalonResolver::applyTelarFilter(
            ReqProgramaTejido::query(),
            $salon,
            $telar
        )
            ->pluck('Id')
            ->map(fn ($idDb) => (int) $idDb)
            ->all();

        if (!empty($idsTelar)) {
            $key = (string) $telarKey;
            $existentes = $idsAfectados[$key] ?? [];
            $idsAfectados[$key] = array_merge($existentes, $idsTelar);
        }
    }

    /**
     * Paso 4: Dispara el observer de ReqProgramaTejido para regenerar líneas diarias y fórmulas.
     *
     * @param  array<string, int[]>  $idsAfectados
     */
    private function dispararObserver(array $idsAfectados): void
    {
        $todosIds = array_unique(array_merge(...array_values($idsAfectados ?: [[]])));
        if (empty($todosIds)) {
            return;
        }
        ReqProgramaTejido::regenerarLineas(
            ReqProgramaTejido::query()->whereIn('Id', $todosIds)->get()
        );
    }

    /**
     * Paso 5: Sincroniza CatCodificados para los registros que cambiaron de salón.
     *
     * @param  int[]  $idsCambioSalon
     */
    private function sincronizarCatCodificados(array $idsCambioSalon): void
    {
        if (empty($idsCambioSalon)) {
            return;
        }
        $movimientoService = new MovimientoDesarrolladorService();
        $registrosMovidos  = ReqProgramaTejido::query()->whereIn('Id', array_unique($idsCambioSalon))->get();
        /** @var ReqProgramaTejido $regMovido */
        foreach ($registrosMovidos as $regMovido) {
            $movimientoService->actualizarReqModelosDesdePrograma($regMovido);
            $movimientoService->actualizarFechasArranqueFinaliza($regMovido, null, null);
        }
    }
}
