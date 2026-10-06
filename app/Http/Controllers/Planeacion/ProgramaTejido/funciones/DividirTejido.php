<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido\funciones;

use App\Helpers\AuditoriaHelper;
use App\Helpers\StringTruncator;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\OrdCompartidaHelper;
use App\Http\Controllers\Planeacion\ProgramaTejido\helper\TejidoHelpers;
use App\Models\Planeacion\ReqModelosCodificados;
use App\Models\Planeacion\ReqProgramaTejido;
use App\Observers\ReqProgramaTejidoObserver;
use App\Support\Planeacion\TelarSalonResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

class DividirTejido
{
    /** Redondeo admitido al cuadrar Σ saldos contra el saldo a repartir. */
    private const TOLERANCIA_SALDO = 0.5;

    /**
     * Dividir el SALDO pendiente de un registro entre varios telares. El destino 0 es el
     * original (se queda con su saldo capturado y lo producido); los demás son filas nuevas.
     * La suma de saldos capturados debe ser exactamente el SaldoPedido del original.
     * Todos comparten OrdCompartida = NoProduccion del original.
     *
     * Recibe datos ya validados con DividirSaldoRequest::rules() (HTTP o Livewire).
     *
     * @param  array<string, mixed>  $data
     */
    public static function dividir(array $data): JsonResponse
    {
        AuditoriaHelper::contexto('DIVIDIR');

        // El front manda 'KM'; en BD el salon es 'KARL MAYER'. Sin normalizar, la
        // comparacion contra el registro original falla y se divide otra fila.
        $telarOrigen = (string) $data['no_telar_id'];
        $salonOrigen = TelarSalonResolver::normalizeSalon($data['salon_tejido_id'], $telarOrigen);
        $salonDestino = TelarSalonResolver::normalizeSalon(($data['salon_destino'] ?? null) ?: $salonOrigen);
        $destinos = array_map(static function (array $d) use ($salonDestino) {
            $d['salon_destino'] = TelarSalonResolver::normalizeSalon(($d['salon_destino'] ?? null) ?: $salonDestino, $d['telar'] ?? null);

            return $d;
        }, array_values($data['destinos'] ?? []));
        $hilo = $data['hilo'] ?? null;
        $aplicacion = $data['aplicacion'] ?? null;
        $globales = self::globales($data);
        $registroIdOriginal = $data['registro_id_original'] ?? null;

        // Redistribución solo si hay un grupo real (2+ registros); un OrdCompartida huérfano en una fila no aplica
        $ordCompartidaExistente = $data['ord_compartida_existente'] ?? null;
        $esRedistribucion = false;
        if (! empty($ordCompartidaExistente) && $ordCompartidaExistente !== '0') {
            $esRedistribucion = ReqProgramaTejido::where('OrdCompartida', (int) $ordCompartidaExistente)->count() >= 2;
        }

        // Guardar y restaurar dispatcher para no romper otros flujos (igual que DuplicarTejido)
        $dispatcher = ReqProgramaTejido::suppressObservers();

        DBFacade::beginTransaction();

        try {
            if ($esRedistribucion) {
                return self::redistribuirGrupoExistente($data, (int) $ordCompartidaExistente, $destinos, $salonDestino, $hilo, $dispatcher);
            }

            // Obtener el registro específico a dividir, bloqueado hasta el commit (otro planeador
            // dividiendo la misma fila leería el mismo saldo y lo repartiría dos veces):
            // 1) Si viene registro_id_original, usar ese.
            // 2) Si no, usar el último del telar (fallback anterior).
            $registroOriginal = null;
            if (! empty($registroIdOriginal)) {
                $registroOriginal = ReqProgramaTejido::query()->lockForUpdate()->find($registroIdOriginal);
                $mismoTelar = $registroOriginal
                    && TelarSalonResolver::normalizeSalon($registroOriginal->SalonTejidoId, $registroOriginal->NoTelarId) === $salonOrigen
                    && TelarSalonResolver::normalizeTelar($registroOriginal->NoTelarId) === TelarSalonResolver::normalizeTelar($telarOrigen);

                // Antes caia al ultimo del telar: dividia una fila distinta a la que se pidio.
                if (! $mismoTelar) {
                    return self::abortar($dispatcher, 'El registro a dividir no pertenece al telar indicado.', 404);
                }
            } else {
                // Sin registro_id_original (llamadas viejas): el ultimo del telar.
                $registroOriginal = ReqProgramaTejido::query()
                    ->salon($salonOrigen)
                    ->telar($telarOrigen)
                    ->whereIn('Ultimo', ReqProgramaTejido::VALORES_ULTIMO)
                    ->orderBy('FechaInicio', 'desc')
                    ->lockForUpdate()
                    ->first()
                    ?? ReqProgramaTejido::query()
                        ->salon($salonOrigen)
                        ->telar($telarOrigen)
                        ->orderBy('FechaInicio', 'desc')
                        ->lockForUpdate()
                        ->first();
            }

            if (! $registroOriginal) {
                return self::abortar($dispatcher, 'No se encontro el registro para dividir', 404);
            }

            // OrdCompartida = NoProduccion del registro original (líder natural del grupo dividido)
            $nuevoOrdCompartida = OrdCompartidaHelper::obtenerOrdCompartidaDesdeRegistro($registroOriginal);
            // El original es quien cede saldo y da nombre al grupo: necesita NoProduccion. Las partes
            // nuevas no (lo reciben al liberar). Sin esta guarda, where('OrdCompartida', null) se
            // vuelve whereNull y el bloque del líder toca todas las filas sin grupo de la tabla.
            if ($nuevoOrdCompartida === null) {
                return self::abortar($dispatcher, 'No se puede dividir: el registro origen no tiene No. de producción. Libéralo primero.', 422);
            }

            // Se reparte SOLO el saldo pendiente: Σ saldos capturados == SaldoPedido del original.
            $saldos = array_map([self::class, 'saldoDe'], $destinos);
            $error = self::sinPartes(array_slice($saldos, 1))
                ?? self::descuadre($saldos, (float) ($registroOriginal->SaldoPedido ?? 0))
                ?? TejidoHelpers::claveFaltanteEnSalon(self::paresOtroSalon(array_slice($destinos, 1), $salonOrigen, $registroOriginal->getAttribute('TamanoClave')));
            if ($error !== null) {
                return self::abortar($dispatcher, $error, 422);
            }

            $destinoOriginal = $destinos[0];
            $destinosNuevos = array_slice($destinos, 1);
            $porcentajeSegundosOriginal = self::porcentajeDe($destinoOriginal);

            $idsParaObserver = [];
            $registrosParaObserver = []; // Modelos con datos en memoria para generar ReqProgramaTejidoLine
            $totalDivididos = 0;

            // === PASO 1: Actualizar el registro original ===
            // Conserva lo producido: TotalPedido = (saldo + Produccion) / (1 + %seg/100).
            $registroOriginal->OrdCompartida = $nuevoOrdCompartida;
            if ($porcentajeSegundosOriginal !== null) {
                $registroOriginal->PorcentajeSegundos = $porcentajeSegundosOriginal;
            }
            $registroOriginal->SaldoPedido = $saldos[0];
            $registroOriginal->TotalPedido = TejidoHelpers::pedidoDesdeSaldo($saldos[0], $registroOriginal);

            // PedidoTempo y Observaciones del destino 0 (antes tomaba el pedido_tempo del ÚLTIMO destino)
            if (($destinoOriginal['pedido_tempo'] ?? null) !== null && $destinoOriginal['pedido_tempo'] !== '') {
                $registroOriginal->PedidoTempo = $destinoOriginal['pedido_tempo'];
            }
            if (($destinoOriginal['observaciones'] ?? null) !== null && $destinoOriginal['observaciones'] !== '') {
                $registroOriginal->Observaciones = StringTruncator::truncate('Observaciones', $destinoOriginal['observaciones']);
            }
            // Ajustar Maquina al telar origen seleccionado
            $registroOriginal->Maquina = self::construirMaquina(
                $registroOriginal->Maquina ?? null,
                $salonOrigen,
                $telarOrigen
            );

            // ===== FORZAR STD DESDE CATÁLOGOS (SMITH/JACQUARD + Normal/Alta) =====
            TejidoHelpers::aplicarStdDesdeCatalogos($registroOriginal);

            $registroOriginal->UpdatedAt = now();

            // ===== RECALCULAR FECHA FINAL desde la fecha inicio existente (sin cambiar fecha inicio) =====
            if (! empty($registroOriginal->FechaInicio)) {
                $inicio = Carbon::parse($registroOriginal->FechaInicio);
                $horasNecesarias = self::calcularHorasProd($registroOriginal);

                $registroOriginal->FechaFinal = TejidoHelpers::resolverFechaFinal($inicio, $horasNecesarias, $registroOriginal->CalendarioId)->format('Y-m-d H:i:s');

            }

            // Recalcular fórmulas del registro original
            if ($registroOriginal->FechaInicio && $registroOriginal->FechaFinal) {
                $formulas = self::calcularFormulasEficiencia($registroOriginal);
                foreach ($formulas as $campo => $valor) {
                    $registroOriginal->{$campo} = $valor;
                }
            }

            $registroOriginal->save();
            $idsParaObserver[] = $registroOriginal->Id;
            $registrosParaObserver[] = $registroOriginal;
            $totalDivididos++;

            // Datos de cada registro nuevo para la respuesta (evita depender de find() tras commit, que puede fallar en SQL Server)
            $registrosDatosParaRespuesta = [];

            // PT-PERF-02: posiciones de todos los telares destino en una consulta, no una por destino.
            $reservarPosicion = TejidoHelpers::reservadorDePosiciones(array_map(
                fn ($d) => [(string) $d['salon_destino'], (string) $d['telar']],
                $destinosNuevos
            ));

            // === PASO 2: Crear los nuevos registros para los telares destino ===
            $inicioSiTelarVacio = $registroOriginal->FechaInicio ? Carbon::parse($registroOriginal->FechaInicio) : Carbon::now();
            foreach ($destinosNuevos as $destino) {
                $nuevo = self::crearParte($registroOriginal, $destino, (int) $nuevoOrdCompartida, $inicioSiTelarVacio, $hilo, $aplicacion, $globales, $salonOrigen, $reservarPosicion);
                $idsParaObserver[] = $nuevo->Id;
                $registrosDatosParaRespuesta[(string) $nuevo->Id] = $nuevo->toArray();
                $registrosParaObserver[] = $nuevo;
                $totalDivididos++;
            }

            // Líder: FechaInicio más antigua entre los que tienen NoProduccion (misma regla en todo PT).
            OrdCompartidaHelper::recalcularLiderYOrdPrincipalPorOrdCompartida($nuevoOrdCompartida);

            // Asegurar que los registros divididos mantengan EnProceso=0 (dentro de la misma transacción)
            if (! empty($idsParaObserver)) {
                $idsNuevos = array_slice($idsParaObserver, 1);
                if (! empty($idsNuevos)) {
                    ReqProgramaTejido::whereIn('Id', $idsNuevos)->update(['EnProceso' => 0]);
                }
            }

            DBFacade::commit();
            LogFacade::info('DividirTejido::dividir COMMIT realizado', ['ids_para_observer' => $idsParaObserver, 'total_divididos' => $totalDivididos]);

            // Restaurar dispatcher y re-habilitar observer
            ReqProgramaTejido::restoreObservers($dispatcher);

            // Reconectar para garantizar visibilidad de los registros recién commiteados.
            try {
                DBFacade::reconnect();
            } catch (\Throwable $ignored) {
            }

            // Generar ReqProgramaTejidoLine tras el commit, con las instancias en memoria (evita reconsulta que no ve el registro en SQL Server)
            ReqProgramaTejido::regenerarLineas($registrosParaObserver);

            // Los saves corrieron con observers suprimidos y la división cambió TotalPedido/SaldoPedido:
            // recalcular marbetes (Repeticiones→…→SaldoMarbete/NoMarbete) para el original Y los nuevos.
            // Las instancias en memoria tienen los valores finales (incluido el Id real);
            // recalcularFormulasProduccion trae try/catch y logging propios.
            $observerFormulas = new ReqProgramaTejidoObserver;
            foreach ($registrosParaObserver as $regFormulas) {
                $observerFormulas->recalcularFormulasProduccion($regFormulas);
            }

            // Re-capturar datos tras observer (las instancias en memoria ya tienen fechas recalculadas)
            foreach ($registrosParaObserver as $registro) {
                if ($registro && $registro->Id) {
                    $registrosDatosParaRespuesta[(string) $registro->Id] = $registro->toArray();
                }
            }

            // Primer registro nuevo para respuesta (desde memoria; find() tras commit puede fallar en SQL Server)
            $primerNuevoCreado = count($registrosParaObserver) > 1 ? $registrosParaObserver[1] : $registrosParaObserver[0];

            // IDs de registros nuevos creados (excluyendo el original)
            $idsNuevosCreados = array_slice($idsParaObserver, 1);

            // Si el slice quedó vacío pero hay datos en memoria por Id, el front necesita registros_ids para no caer en navegación/recarga
            if ($idsNuevosCreados === [] && ! empty($registrosDatosParaRespuesta)) {
                $origId = (int) $registroOriginal->Id;
                foreach (array_keys($registrosDatosParaRespuesta) as $kid) {
                    $nid = (int) $kid;
                    if ($nid > 0 && $nid !== $origId) {
                        $idsNuevosCreados[] = $nid;
                    }
                }
            }

            $registrosDatos = $registrosDatosParaRespuesta;

            return response()->json([
                'success' => true,
                'message' => "Registro dividido correctamente. OrdCompartida: {$nuevoOrdCompartida}. Se crearon/actualizaron {$totalDivididos} registro(s).",
                'registros_divididos' => $totalDivididos,
                'ord_compartida' => $nuevoOrdCompartida,
                'registro_id' => $primerNuevoCreado?->Id,
                'registros_ids' => $idsNuevosCreados,
                'registros_datos' => $registrosDatos,
                'registro_id_original' => $registroOriginal->Id,
                'registro_original' => [
                    'TotalPedido' => $registroOriginal->TotalPedido,
                    'SaldoPedido' => $registroOriginal->SaldoPedido,
                    'FechaInicio' => $registroOriginal->FechaInicio,
                    'FechaFinal' => $registroOriginal->FechaFinal,
                    'EntregaProduc' => $registroOriginal->EntregaProduc,
                    'EntregaPT' => $registroOriginal->EntregaPT,
                    'EntregaCte' => $registroOriginal->EntregaCte,
                    'ProgramarProd' => $registroOriginal->ProgramarProd,
                    'HorasProd' => $registroOriginal->HorasProd ?? null,
                    'DiasJornada' => $registroOriginal->DiasJornada ?? null,
                    'StdDia' => $registroOriginal->StdDia ?? null,
                    'ProdKgDia' => $registroOriginal->ProdKgDia ?? null,
                ],
                'salon_destino' => $primerNuevoCreado?->SalonTejidoId,
                'telar_destino' => $primerNuevoCreado?->NoTelarId,
                'modo' => 'dividir',
            ]);

        } catch (\Throwable $e) {
            report($e);

            return self::abortar($dispatcher, 'Error al dividir el telar. Intenta de nuevo; si persiste, avisa a Sistemas.', 500);
        }
    }

    /** Rollback + restaurar observers + JSON de error: toda salida temprana pasa por aquí. */
    private static function abortar($dispatcher, string $mensaje, int $status): JsonResponse
    {
        DBFacade::rollBack();
        ReqProgramaTejido::restoreObservers($dispatcher);

        return response()->json(['success' => false, 'message' => $mensaje], $status);
    }

    /**
     * Crea una parte nueva (dividir o redistribuir) al final de la cola del telar destino:
     * réplica de $base sin producción, en el grupo $ord, con el saldo capturado y el
     * TotalPedido derivado, fechas, fórmulas y posición. Guarda y la devuelve.
     *
     * @param  array<string, mixed>  $destino  con salon_destino ya normalizado
     * @param  array<string, mixed>  $globales
     * @param  callable(string, string): int  $reservarPosicion
     */
    private static function crearParte(ReqProgramaTejido $base, array $destino, int $ord, Carbon $inicioSiTelarVacio, $hilo, $aplicacion, array $globales, ?string $salonOrigen, callable $reservarPosicion): ReqProgramaTejido
    {
        $salon = (string) $destino['salon_destino'];
        $telar = (string) $destino['telar'];
        $ultimo = ReqProgramaTejido::query()->salon($salon)->telar($telar)->orderBy('FechaInicio', 'desc')->first();
        if ($ultimo && $ultimo->esUltimo()) {
            ReqProgramaTejido::where('Id', $ultimo->Id)->update(['Ultimo' => 0]);
        }

        $nuevo = $base->replicate();
        $inicial = [
            'SalonTejidoId' => $salon, 'NoTelarId' => $telar, 'EnProceso' => 0, 'Ultimo' => 1, 'CambioHilo' => 0,
            'Produccion' => null, 'Programado' => null, 'NoProduccion' => null, 'ProgramarProd' => null, 'SaldoMarbete' => null,
        ];
        foreach ($inicial as $columna => $valor) {
            $nuevo->setAttribute($columna, $valor); // sin fill(): no depende de $fillable
        }
        $nuevo->OrdCompartida = $ord;
        $nuevo->SaldoPedido = self::saldoDe($destino);
        $nuevo->Maquina = self::construirMaquina($base->Maquina ?? null, $salon, $telar);
        $nuevo->FibraRizo = $hilo ?: $nuevo->FibraRizo;
        if ($aplicacion) {
            $nuevo->AplicacionId = $aplicacion;
        }
        self::aplicarDatosDestino($nuevo, $destino, $globales, $salonOrigen);
        TejidoHelpers::aplicarStdDesdeCatalogos($nuevo);

        self::aplicarFila($nuevo, $destino);
        // Parte nueva sin producción: TotalPedido = saldo / (1 + %seg/100).
        $nuevo->TotalPedido = TejidoHelpers::pedidoDesdeSaldo(self::saldoDe($destino), $nuevo);

        self::programarParte($nuevo, $ultimo, $inicioSiTelarVacio);
        unset($nuevo->Repeticiones); // no es columna de la tabla

        $nuevo->Posicion = $reservarPosicion($salon, $telar);
        $nuevo->CreatedAt = now();
        $nuevo->UpdatedAt = now();
        $nuevo->save();

        return $nuevo;
    }

    /** PedidoTempo, observaciones y %seg capturados en la fila (vacío = se queda el del original). */
    private static function aplicarFila(ReqProgramaTejido $nuevo, array $destino): void
    {
        $nuevo->PedidoTempo = self::textoDe($destino, 'pedido_tempo') ?? $nuevo->PedidoTempo;
        $observaciones = self::textoDe($destino, 'observaciones');
        $nuevo->Observaciones = $observaciones !== null ? StringTruncator::truncate('Observaciones', $observaciones) : $nuevo->Observaciones;
        $nuevo->PorcentajeSegundos = self::porcentajeDe($destino) ?? $nuevo->PorcentajeSegundos;
    }

    /** Fechas (al final de la cola del telar destino), cambio de hilo y fórmulas de la parte nueva. */
    private static function programarParte(ReqProgramaTejido $nuevo, ?ReqProgramaTejido $ultimo, Carbon $inicioSiTelarVacio): void
    {
        // Arranca donde termina el último del telar destino (sin snap al calendario).
        $inicio = $ultimo && $ultimo->FechaFinal ? Carbon::parse($ultimo->FechaFinal) : $inicioSiTelarVacio->copy();
        $nuevo->FechaInicio = $inicio->format('Y-m-d H:i:s');
        $nuevo->FechaFinal = TejidoHelpers::resolverFechaFinal($inicio->copy(), self::calcularHorasProd($nuevo), $nuevo->CalendarioId)->format('Y-m-d H:i:s');
        if ($ultimo) {
            $nuevo->CambioHilo = trim((string) $nuevo->FibraRizo) !== trim((string) $ultimo->FibraRizo) ? '1' : '0';
        }
        foreach (self::calcularFormulasEficiencia($nuevo) as $campo => $valor) {
            $nuevo->{$campo} = $valor;
        }
    }

    /** Texto de la fila, o null si viene vacío. */
    private static function textoDe(array $destino, string $llave): ?string
    {
        $valor = $destino[$llave] ?? null;

        return $valor === null || $valor === '' ? null : (string) $valor;
    }

    /** Saldo capturado en la fila (sin separador de miles). Clientes viejos solo mandan 'pedido'. */
    private static function saldoDe(array $destino): float
    {
        return TejidoHelpers::sanitizeNumber($destino['saldo'] ?? $destino['pedido'] ?? 0);
    }

    /**
     * Datos de producto del modal (fallback cuando la fila no trae los suyos), con los
     * nombres de llave de cada destino.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function globales(array $data): array
    {
        return [
            'itemId' => $data['cod_articulo'] ?? null,
            'producto' => $data['producto'] ?? null,
            'flog' => $data['flog'] ?? null,
            'descripcion' => $data['descripcion'] ?? null,
            'custName' => $data['custname'] ?? null,
            'inventSizeId' => $data['invent_size_id'] ?? null,
        ];
    }

    /**
     * Fila nueva: producto/flog/descripción/cliente/artículo de la fila (o los globales) y, si
     * la clave cambia, TODOS los técnicos de Modelos en el servidor. Misma regla al dividir y al
     * redistribuir un grupo (antes la redistribución ignoraba lo capturado en la fila).
     *
     * @param  array<string, mixed>  $destino  con salon_destino ya normalizado
     * @param  array<string, mixed>  $globales
     * @param  string|null  $salonOrigen  null = aplicar siempre el modelo del salón destino
     */
    private static function aplicarDatosDestino(ReqProgramaTejido $nuevo, array $destino, array $globales, ?string $salonOrigen): void
    {
        $columnas = [
            'producto' => 'NombreProducto', 'flog' => 'FlogsId', 'descripcion' => 'NombreProyecto',
            'custName' => 'CustName', 'itemId' => 'ItemId', 'inventSizeId' => 'InventSizeId',
        ];
        foreach ($columnas as $llave => $columna) {
            $valor = trim((string) ($destino[$llave] ?? '')) ?: trim((string) ($globales[$llave] ?? ''));
            if ($valor !== '') {
                $nuevo->{$columna} = $valor;
            }
        }

        $salon = (string) $destino['salon_destino'];
        $clave = trim((string) ($destino['tamano_clave'] ?? ''));
        if ($clave !== '' && $clave !== trim((string) $nuevo->getAttribute('TamanoClave'))) {
            $nuevo->setAttribute('TamanoClave', $clave);
            self::aplicarModeloCodificadoPorSalon($nuevo, $salon, $clave);
            DuplicarTejido::aplicarDatosModeloCodificado($nuevo, $clave, $salon);
        } elseif ($salonOrigen === null || $salon !== $salonOrigen) {
            self::aplicarModeloCodificadoPorSalon($nuevo, $salon);
        }
    }

    private static function porcentajeDe(array $destino): ?float
    {
        $valor = $destino['porcentaje_segundos'] ?? null;

        return $valor === null || $valor === '' ? null : (float) $valor;
    }

    /**
     * Dividir sin grupo: cada fila nueva tiene que recibir saldo (si no, cuadra pero crea un
     * registro vacío o solo marca el original como grupo).
     *
     * @param  array<int, float>  $saldosNuevos
     */
    private static function sinPartes(array $saldosNuevos): ?string
    {
        if ($saldosNuevos === []) {
            return 'Agrega al menos un telar con saldo para dividir.';
        }

        return min($saldosNuevos) <= 0 ? 'Cada telar nuevo necesita un saldo mayor a 0.' : null;
    }

    /**
     * Mensaje 422 si algún saldo es negativo o si Σ saldos no cuadra con el saldo a repartir
     * (tolerancia 0.5 por redondeo). Sin escalado proporcional ni faltante silencioso.
     *
     * @param  array<int, float>  $saldos
     */
    private static function descuadre(array $saldos, float $saldoARepartir): ?string
    {
        if ($saldos !== [] && min($saldos) < 0) {
            return 'Los saldos no pueden ser negativos.';
        }
        $suma = array_sum($saldos);
        if (abs($suma - $saldoARepartir) > self::TOLERANCIA_SALDO) {
            return sprintf(
                'La suma de saldos (%s) no cuadra con el saldo a repartir (%s). Diferencia: %s.',
                number_format($suma, 2),
                number_format($saldoARepartir, 2),
                number_format($suma - $saldoARepartir, 2)
            );
        }

        return null;
    }

    /**
     * Pares [salón, clave modelo] de las filas que van a OTRO salón (regla: la clave debe
     * existir en Modelos para ese salón).
     *
     * @param  array<int, array<string, mixed>>  $destinos  con salon_destino ya normalizado
     * @return array<int, array{0: string, 1: ?string}>
     */
    private static function paresOtroSalon(array $destinos, string $salonOrigen, ?string $claveOriginal): array
    {
        $pares = [];
        foreach ($destinos as $d) {
            if ($d['salon_destino'] !== $salonOrigen) {
                $clave = trim((string) ($d['tamano_clave'] ?? ''));
                $pares[] = [$d['salon_destino'], $clave !== '' ? $clave : $claveOriginal];
            }
        }

        return $pares;
    }

    /**
     * Calculate efficiency formulas for DividirTejido operations.
     * Uses includeEntregaCte=true and includePTvsCte=true for full calculation.
     * Uses fallbackEntregaCte=true because divided records inherit client delivery dates.
     */
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        return TejidoHelpers::calcularFormulasEficienciaPorContexto(
            $programa,
            TejidoHelpers::FORMULAS_CTX_PEDIDO_INHERIT,
            fn (?string $tamanoClave, ?string $salonTejidoId) => self::obtenerModeloCodificadoPorSalon($tamanoClave, $salonTejidoId)
        );
    }

    private static function obtenerModeloCodificadoPorSalon(?string $tamanoClave, ?string $salonTejidoId): ?ReqModelosCodificados
    {
        $clave = trim((string) $tamanoClave);
        if ($clave === '') {
            return null;
        }

        $salon = trim((string) $salonTejidoId);

        $q = ReqModelosCodificados::query()
            ->where(function ($builder) use ($clave) {
                $builder->where('TamanoClave', $clave)
                    ->orWhere('ClaveModelo', $clave);
            });
        if ($salon !== '') {
            $q->where('SalonTejidoId', $salon);
        }

        $modelo = $q->orderByDesc('FechaTejido')->first();
        if ($modelo || $salon === '') {
            return $modelo;
        }

        return ReqModelosCodificados::query()
            ->where(function ($builder) use ($clave) {
                $builder->where('TamanoClave', $clave)
                    ->orWhere('ClaveModelo', $clave);
            })
            ->orderByDesc('FechaTejido')
            ->first();
    }

    private static function aplicarModeloCodificadoPorSalon(ReqProgramaTejido $registro, string $salonDestino, ?string $tamanoClave = null): void
    {
        // ⚡ MEJORA: Usar tamanoClave específico si se proporciona, sino usar el del registro
        $claveParaBuscar = $tamanoClave ?? $registro->TamanoClave;
        $modelo = self::obtenerModeloCodificadoPorSalon($claveParaBuscar, $salonDestino);
        if (! $modelo) {
            return;
        }

        // Si se proporcionó tamanoClave específico, actualizarlo en el registro
        if ($tamanoClave) {
            $registro->TamanoClave = trim((string) $tamanoClave);
        }

        if (! empty($modelo->ItemId)) {
            $registro->ItemId = (string) $modelo->ItemId;
        }
        if (! empty($modelo->InventSizeId)) {
            $registro->InventSizeId = (string) $modelo->InventSizeId;
        }
        if (! empty($modelo->Nombre)) {
            $registro->NombreProducto = StringTruncator::truncate('NombreProducto', (string) $modelo->Nombre);
        }
        if (! empty($modelo->NombreProyecto)) {
            $registro->NombreProyecto = StringTruncator::truncate('NombreProyecto', (string) $modelo->NombreProyecto);
        }
        if (! empty($modelo->FlogsId)) {
            $registro->FlogsId = StringTruncator::truncate('FlogsId', (string) $modelo->FlogsId);
        }

        // Solo asignar FibraRizo del modelo si no hay un valor ya asignado (respetar el hilo del usuario)
        if (empty($registro->FibraRizo)) {
            $fibraRizo = $modelo->FibraRizo ?? $modelo->FibraId ?? null;
            if (! empty($fibraRizo)) {
                $registro->FibraRizo = (string) $fibraRizo;
            }
        }

        if ($modelo->CalibreRizo !== null) {
            $registro->CalibreRizo = (float) $modelo->CalibreRizo;
        }
        if ($modelo->CalibreRizo2 !== null) {
            $registro->CalibreRizo2 = (float) $modelo->CalibreRizo2;
        }
        if ($modelo->CalibrePie !== null) {
            $registro->CalibrePie = (float) $modelo->CalibrePie;
        }
        if ($modelo->CalibrePie2 !== null) {
            $registro->CalibrePie2 = (float) $modelo->CalibrePie2;
        }
        // CalibreTrama: mismo criterio que DuplicarTejido/UpdateTejido (modelo ↔ registro cruzado)
        if ($modelo->CalibreTrama !== null) {
            $registro->CalibreTrama2 = (float) $modelo->CalibreTrama;
        }
        if ($modelo->CalibreTrama2 !== null) {
            $registro->CalibreTrama = (float) $modelo->CalibreTrama2;
        }

        if ($modelo->NoTiras !== null) {
            $registro->NoTiras = (float) $modelo->NoTiras;
        }
        if ($modelo->Luchaje !== null) {
            $registro->Luchaje = (float) $modelo->Luchaje;
        }
        // Repeticiones no existe en la tabla ReqProgramaTejido, se elimina la asignación
        // if ($modelo->Repeticiones !== null) {
        //     $registro->Repeticiones = (float) $modelo->Repeticiones;
        // }
        if ($modelo->PesoCrudo !== null) {
            $registro->PesoCrudo = (float) $modelo->PesoCrudo;
        }
        if ($modelo->MedidaPlano !== null) {
            $registro->MedidaPlano = (int) $modelo->MedidaPlano;
        }
        if ($modelo->Peine !== null) {
            $registro->Peine = (int) $modelo->Peine;
        }
        if ($modelo->AnchoToalla !== null) {
            $registro->AnchoToalla = (float) $modelo->AnchoToalla;
            $registro->Ancho = (float) $modelo->AnchoToalla;
        }
        // ⚡ MEJORA: LargoCrudo se obtiene de LargoToalla del modelo codificado
        if ($modelo->LargoToalla !== null) {
            $registro->LargoCrudo = (float) $modelo->LargoToalla;
        }

        if ($modelo->FibraTrama !== null) {
            $registro->FibraTrama = (string) $modelo->FibraTrama;
        }
        if ($modelo->FibraPie !== null) {
            $registro->FibraPie = (string) $modelo->FibraPie;
        }
        if ($modelo->CuentaRizo !== null) {
            $registro->CuentaRizo = (string) $modelo->CuentaRizo;
        }
        if ($modelo->CuentaPie !== null) {
            $registro->CuentaPie = (string) $modelo->CuentaPie;
        }
        if ($modelo->CodColorTrama !== null) {
            $registro->CodColorTrama = (string) $modelo->CodColorTrama;
        }
        if ($modelo->ColorTrama !== null) {
            $registro->ColorTrama = (string) $modelo->ColorTrama;
        }
    }

    /**
     * Redistribuir el saldo de un grupo OrdCompartida existente: actualiza las filas del grupo
     * y crea las nuevas. Σ saldos del grupo antes == después (tolerancia 0.5).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $destinos  con salon_destino ya normalizado
     */
    private static function redistribuirGrupoExistente(array $data, int $ordCompartida, array $destinos, string $salonDestino, $hilo, $dispatcher): JsonResponse
    {
        $registroIdOriginal = $data['registro_id_original'] ?? null;

        try {
            // Grupo bloqueado hasta el commit: el Σ de saldos "antes" no puede cambiar por debajo.
            $registrosExistentes = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->orderBy('FechaInicio')
                ->lockForUpdate()
                ->get();

            if ($registrosExistentes->isEmpty()) {
                return self::abortar($dispatcher, 'No se encontraron registros para el grupo OrdCompartida: '.$ordCompartida, 404);
            }

            $primerRegistro = $registrosExistentes->first();
            $fechaInicioBase = $primerRegistro->FechaInicio ? Carbon::parse($primerRegistro->FechaInicio) : Carbon::now();

            $idsParaObserver = [];
            $registrosParaObserver = [];
            $totalActualizados = 0;
            $totalCreados = 0;

            // Mapear destinos por registro_id para actualizaciones
            $destinosPorId = [];
            $destinosNuevos = [];
            $idsExistentes = $registrosExistentes->pluck('Id')->map(fn ($id) => (string) $id)->toArray();

            foreach ($destinos as $destino) {
                $registroId = (string) ($destino['registro_id'] ?? '');
                $esExistente = ! empty($destino['es_existente']);

                if ($registroId !== '' && $esExistente && in_array($registroId, $idsExistentes, true)) {
                    $destinosPorId[(int) $registroId] = $destino;
                } else {
                    $destinosNuevos[] = $destino;
                }
            }

            // Fila nueva sin telar o con saldo 0: no se crea. Si traía saldo, el cuadre la delata.
            $destinosNuevos = array_values(array_filter(
                $destinosNuevos,
                fn ($d) => ! empty($d['telar']) && self::saldoDe($d) != 0.0
            ));

            $error = self::descuadre(
                self::saldosRedistribuidos($registrosExistentes, $destinosPorId, $destinosNuevos),
                (float) $registrosExistentes->sum(fn ($r) => (float) ($r->SaldoPedido ?? 0))
            ) ?? TejidoHelpers::claveFaltanteEnSalon(self::paresOtroSalon(
                $destinosNuevos,
                TelarSalonResolver::normalizeSalon($primerRegistro->SalonTejidoId, $primerRegistro->NoTelarId),
                $primerRegistro->getAttribute('TamanoClave')
            ));
            if ($error !== null) {
                return self::abortar($dispatcher, $error, 422);
            }

            // Actualizar registros existentes
            foreach ($registrosExistentes as $registro) {
                $destino = $destinosPorId[(int) $registro->Id] ?? null;
                if ($destino === null || ! self::traeSaldo($destino)) {
                    continue;
                }

                $porcentajeSegundosDestino = self::porcentajeDe($destino);
                if ($porcentajeSegundosDestino !== null) {
                    $registro->PorcentajeSegundos = $porcentajeSegundosDestino;
                }
                $registro->SaldoPedido = self::saldoDe($destino);
                $registro->TotalPedido = TejidoHelpers::pedidoDesdeSaldo(self::saldoDe($destino), $registro);

                if (($destino['pedido_tempo'] ?? null) !== null && $destino['pedido_tempo'] !== '') {
                    $registro->PedidoTempo = $destino['pedido_tempo'];
                }
                if (($destino['observaciones'] ?? null) !== null && $destino['observaciones'] !== '') {
                    $registro->Observaciones = StringTruncator::truncate('Observaciones', $destino['observaciones']);
                }

                // Ajustar Maquina al telar (si se recibe telar en destino existente)
                $registro->Maquina = self::construirMaquina(
                    $registro->Maquina ?? null,
                    $registro->SalonTejidoId ?? $salonDestino,
                    $destino['telar'] ?? $registro->NoTelarId
                );

                // ===== FORZAR STD DESDE CATÁLOGOS (SMITH/JACQUARD + Normal/Alta) =====
                TejidoHelpers::aplicarStdDesdeCatalogos($registro);

                // ===== RECALCULAR FECHA FINAL desde la fecha inicio existente (sin cambiar fecha inicio) =====
                if (! empty($registro->FechaInicio)) {
                    $inicio = Carbon::parse($registro->FechaInicio);
                    $horasNecesarias = self::calcularHorasProd($registro);

                    $registro->FechaFinal = TejidoHelpers::resolverFechaFinal($inicio, $horasNecesarias, $registro->CalendarioId)->format('Y-m-d H:i:s');
                }

                if ($registro->FechaInicio && $registro->FechaFinal) {
                    foreach (self::calcularFormulasEficiencia($registro) as $campo => $valor) {
                        $registro->{$campo} = $valor;
                    }
                }

                $registro->UpdatedAt = now();
                $registro->save();
                $idsParaObserver[] = $registro->Id;
                $registrosParaObserver[] = $registro;
                $totalActualizados++;
            }

            // PT-PERF-02: posiciones de todos los telares destino en una consulta, no una por destino.
            $reservarPosicion = TejidoHelpers::reservadorDePosiciones(array_map(
                fn ($d) => [(string) $d['salon_destino'], (string) $d['telar']],
                $destinosNuevos
            ));

            // Crear nuevos registros
            foreach ($destinosNuevos as $destino) {
                $nuevo = self::crearParte($primerRegistro, $destino, (int) $ordCompartida, $fechaInicioBase, $hilo, null, self::globales($data), null, $reservarPosicion);
                $idsParaObserver[] = $nuevo->Id;
                $registrosParaObserver[] = $nuevo;
                $totalCreados++;
            }

            // Generar ReqProgramaTejidoLine ANTES del commit (igual que DuplicarTejido).
            // Dentro de la transacción, los registros recién guardados SÍ son visibles
            // en la misma conexión — evita el lag de visibilidad de pdo_sqlsrv/ODBC post-commit.
            ReqProgramaTejido::regenerarLineas($registrosParaObserver);

            LogFacade::info('redistribuirGrupoExistente: ANTES commit', [
                'transaction_level' => DBFacade::transactionLevel(),
                'nuevos_ids' => array_map(fn ($r) => $r->Id ?? 'sin-id', $registrosParaObserver),
            ]);

            DBFacade::commit();

            LogFacade::info('redistribuirGrupoExistente: DESPUES commit', [
                'transaction_level' => DBFacade::transactionLevel(),
            ]);

            // Restaurar dispatcher y re-habilitar observer (igual que DuplicarTejido)
            ReqProgramaTejido::restoreObservers($dispatcher);

            // Reconectar para garantizar visibilidad post-commit para queries subsecuentes.
            try {
                DBFacade::reconnect();
                LogFacade::info('redistribuirGrupoExistente: reconnect OK');
            } catch (\Throwable $reconnectErr) {
                LogFacade::warning('redistribuirGrupoExistente: reconnect FALLO', ['error' => $reconnectErr->getMessage()]);
            }

            // Los saves corrieron con observers suprimidos y la redistribución cambió
            // TotalPedido/SaldoPedido: recalcular marbetes (TotalRollos/SaldoMarbete/NoMarbete)
            // para los registros actualizados y los nuevos (instancias con valores finales).
            // Se hace ANTES del refetch del grupo para que la respuesta lleve los valores nuevos.
            // recalcularFormulasProduccion trae try/catch y logging propios.
            $observerFormulas = new ReqProgramaTejidoObserver;
            foreach ($registrosParaObserver as $regFormulas) {
                $observerFormulas->recalcularFormulasProduccion($regFormulas);
            }

            // Líder: FechaInicio más antigua entre los que tienen NoProduccion (misma regla en todo PT).
            OrdCompartidaHelper::recalcularLiderYOrdPrincipalPorOrdCompartida((int) $ordCompartida);
            $registrosConOrdCompartida = ReqProgramaTejido::where('OrdCompartida', $ordCompartida)
                ->get();

            // Usar $registrosConOrdCompartida (query fresca tras commit+reconnect) como fuente de verdad
            $registrosDatosParaRespuesta = [];
            $allGroupIds = [];
            foreach ($registrosConOrdCompartida as $reg) {
                $registrosDatosParaRespuesta[(string) $reg->Id] = $reg->toArray();
                $allGroupIds[] = (int) $reg->Id;
            }

            // IDs para el front: todos los del grupo excepto el original (que se actualiza con registro_original)
            $origId = $registroIdOriginal ? (int) $registroIdOriginal : null;
            $idsNuevosCreados = array_values(array_filter($allGroupIds, fn ($nid) => $origId === null || $nid !== $origId));

            // Primer registro nuevo para redirigir
            $primerNuevoCreado = $totalCreados > 0
                ? $registrosConOrdCompartida->firstWhere('Id', end($idsParaObserver))
                : $registrosConOrdCompartida->first();

            // Datos del registro original (frescos desde BD)
            $registroOriginalObj = $origId
                ? $registrosConOrdCompartida->firstWhere('Id', $origId)
                : $registrosConOrdCompartida->first();

            return response()->json([
                'success' => true,
                'message' => "Redistribución completada. Actualizados: {$totalActualizados}, Nuevos: {$totalCreados}.",
                'registros_divididos' => $totalActualizados + $totalCreados,
                'registros_actualizados' => $totalActualizados,
                'registros_creados' => $totalCreados,
                'ord_compartida' => $ordCompartida,
                'registro_id' => $primerNuevoCreado?->Id,
                'registros_ids' => $idsNuevosCreados,
                'registros_datos' => $registrosDatosParaRespuesta,
                'registro_id_original' => $registroOriginalObj?->Id,
                'registro_original' => $registroOriginalObj ? [
                    'TotalPedido' => $registroOriginalObj->TotalPedido,
                    'SaldoPedido' => $registroOriginalObj->SaldoPedido,
                    'FechaInicio' => $registroOriginalObj->FechaInicio,
                    'FechaFinal' => $registroOriginalObj->FechaFinal,
                    'EntregaProduc' => $registroOriginalObj->EntregaProduc,
                    'EntregaPT' => $registroOriginalObj->EntregaPT,
                    'EntregaCte' => $registroOriginalObj->EntregaCte,
                    'ProgramarProd' => $registroOriginalObj->ProgramarProd,
                    'HorasProd' => $registroOriginalObj->HorasProd ?? null,
                    'DiasJornada' => $registroOriginalObj->DiasJornada ?? null,
                    'StdDia' => $registroOriginalObj->StdDia ?? null,
                    'ProdKgDia' => $registroOriginalObj->ProdKgDia ?? null,
                ] : null,
                'salon_destino' => $primerNuevoCreado?->SalonTejidoId,
                'telar_destino' => $primerNuevoCreado?->NoTelarId,
                'modo' => 'dividir',
            ]);

        } catch (\Throwable $e) {
            report($e);

            return self::abortar($dispatcher, 'Error al redistribuir el grupo. Intenta de nuevo; si persiste, avisa a Sistemas.', 500);
        }
    }

    /** La fila trae saldo capturado (las existentes sin saldo conservan el suyo). */
    private static function traeSaldo(array $destino): bool
    {
        $valor = $destino['saldo'] ?? $destino['pedido'] ?? null;

        return $valor !== null && $valor !== '';
    }

    /**
     * Saldos del grupo DESPUÉS de redistribuir: existentes (capturado o el que ya tenían) + nuevas.
     *
     * @param  Collection<int, ReqProgramaTejido>  $registros
     * @return array<int, float>
     */
    private static function saldosRedistribuidos($registros, array $destinosPorId, array $destinosNuevos): array
    {
        $existentes = $registros->map(function ($r) use ($destinosPorId) {
            $destino = $destinosPorId[(int) $r->Id] ?? null;

            return $destino !== null && self::traeSaldo($destino) ? self::saldoDe($destino) : (float) ($r->SaldoPedido ?? 0);
        })->all();

        return array_values([...$existentes, ...array_map([self::class, 'saldoDe'], $destinosNuevos)]);
    }

    /**
     * Construye el valor de Maquina usando un prefijo del salón o del valor base y el número de telar
     */
    private static function construirMaquina(?string $maquinaBase, ?string $salon, $telar): string
    {
        return TejidoHelpers::construirMaquinaConSalon($maquinaBase, $salon, $telar);
    }

    /**
     * Calcular horas de producción necesarias (delegado a TejidoHelpers con callback por salón)
     */
    private static function calcularHorasProd(ReqProgramaTejido $p): float
    {
        return TejidoHelpers::calcularHorasProd(
            $p,
            fn (?string $k, ?string $s) => self::obtenerModeloCodificadoPorSalon($k, $s)
        );
    }
}
