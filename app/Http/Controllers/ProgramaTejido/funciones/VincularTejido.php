<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Helpers\StringTruncator;
use App\Http\Controllers\ProgramaTejido\funciones\BalancearTejido;
use App\Models\ReqEficienciaStd;
use App\Models\ReqModelosCodificados;
use App\Models\ReqProgramaTejido;
use App\Models\ReqVelocidadStd;
use App\Observers\ReqProgramaTejidoObserver;
use App\Http\Controllers\ProgramaTejido\helper\TejidoHelpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB as DBFacade;
use Illuminate\Support\Facades\Log as LogFacade;

class VincularTejido
{
    /** Cache de modelo para no pegar a ReqModelosCodificados por cada registro */
    private static array $modeloCache = [];

    /** Valores válidos en catálogo */
    private const DENSIDAD_NORMAL = 'Normal';
    private const DENSIDAD_ALTA   = 'Alta';

    /**
     * Vincular tejidos nuevos desde cero con un OrdCompartida
     * Permite crear registros nuevos y asignarles un OrdCompartida existente o crear uno nuevo
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public static function vincular(Request $request)
    {
        $data = $request->validate([
            'salon_tejido_id' => 'required|string',
            'no_telar_id'     => 'required|string',
            'destinos'        => 'required|array|min:1',
            'destinos.*.telar'  => 'required|string',
            'destinos.*.pedido' => 'nullable|string',
            'destinos.*.pedido_tempo' => 'nullable|string',
            'destinos.*.saldo' => 'nullable|string',
            'destinos.*.observaciones' => 'nullable|string|max:500',
            'destinos.*.porcentaje_segundos' => 'nullable|numeric|min:0',

            'tamano_clave'   => 'nullable|string|max:100',
            'invent_size_id' => 'nullable|string|max:100',
            'cod_articulo'   => 'nullable|string|max:100',
            'producto'       => 'nullable|string|max:255',
            'custname'       => 'nullable|string|max:255',

            'salon_destino' => 'nullable|string',
            'hilo'          => 'nullable|string',
            'pedido'        => 'nullable|string',
            'flog'          => 'nullable|string',
            'aplicacion'    => 'nullable|string',
            'descripcion'   => 'nullable|string',
            'ord_compartida_existente' => 'nullable|integer|min:1', // OrdCompartida existente para vincular (solo si es > 0)
            'registro_id_original' => 'nullable|integer',
        ]);

        $salonOrigen  = $data['salon_tejido_id'];
        $telarOrigen  = $data['no_telar_id'];
        $salonDestino = $data['salon_destino'] ?? $salonOrigen;
        $destinos     = $data['destinos'];

        $pedidoGlobal   = self::sanitizeNumber($data['pedido'] ?? null);
        $inventSizeId   = $data['invent_size_id'] ?? null;
        $tamanoClave    = $data['tamano_clave'] ?? null;
        $codArticulo    = $data['cod_articulo'] ?? null;
        $producto       = $data['producto'] ?? null;
        $custname       = $data['custname'] ?? null;
        $hilo           = $data['hilo'] ?? null;
        $flog           = $data['flog'] ?? null;
        $aplicacion     = $data['aplicacion'] ?? null;
        $descripcion    = $data['descripcion'] ?? null;
        $ordCompartidaExistente = $data['ord_compartida_existente'] ?? null;
        $registroIdOriginal = $data['registro_id_original'] ?? null;

        // Guardar y restaurar dispatcher para no romper otros flujos
        $dispatcher = ReqProgramaTejido::getEventDispatcher();
        ReqProgramaTejido::unsetEventDispatcher();

        DBFacade::beginTransaction();

        try {
            // Determinar el OrdCompartida a usar
            // En modo "vincular" siempre se debe crear uno nuevo, no usar existente
            $ordCompartidaAVincular = null;

            // Normalizar el valor: convertir strings vacíos a null
            $ordCompartidaExistente = ($ordCompartidaExistente === '' || $ordCompartidaExistente === null)
                ? null
                : $ordCompartidaExistente;

            if ($ordCompartidaExistente !== null && $ordCompartidaExistente > 0) {
                // Verificar que el OrdCompartida existente realmente exista en la BD
                $existe = ReqProgramaTejido::where('OrdCompartida', (int)$ordCompartidaExistente)->exists();
                if (!$existe) {
                    DBFacade::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "El OrdCompartida {$ordCompartidaExistente} no existe en la base de datos.",
                    ], 404);
                }
                // Usar OrdCompartida existente
                $ordCompartidaAVincular = (int) $ordCompartidaExistente;
            } else {
                // Crear un nuevo OrdCompartida verificando que no esté en uso
                $ordCompartidaAVincular = self::obtenerNuevoOrdCompartidaDisponible();
            }
            // Obtener el registro específico a vincular, o como fallback el último del telar
            $original = null;
            if ($registroIdOriginal) {
                $original = ReqProgramaTejido::find($registroIdOriginal);
                // Verificar que el registro encontrado pertenece al telar y salón correctos
                if ($original && ($original->SalonTejidoId !== $salonOrigen || $original->NoTelarId !== $telarOrigen)) {
                    $original = null; // No es del telar correcto, usar fallback
                }
            }

            // Fallback: obtener el último registro del telar
            if (!$original) {
                $original = self::obtenerUltimoRegistroTelar($salonOrigen, $telarOrigen);
            }

            if (!$original) {
                DBFacade::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros para vincular',
                ], 404);
            }

            $idsParaObserver = [];
            $totalVinculados = 0;

            foreach ($destinos as $destino) {
                $telarDestino = $destino['telar'];
                $pedidoDestinoRaw = $destino['pedido'] ?? null;
                $pedidoTempoDestino = $destino['pedido_tempo'] ?? null;
                $saldoDestinoRaw = $destino['saldo'] ?? null;
                $observacionesDestino = $destino['observaciones'] ?? null;
                $porcentajeSegundosDestino = isset($destino['porcentaje_segundos']) && $destino['porcentaje_segundos'] !== null && $destino['porcentaje_segundos'] !== ''
                    ? (float)$destino['porcentaje_segundos']
                    : null;

                $ultimoDestino = ReqProgramaTejido::query()
                    ->salon($salonDestino)
                    ->telar($telarDestino)
                    ->orderBy('FechaInicio', 'desc')
                    ->first();

                if ($ultimoDestino && (int)$ultimoDestino->Ultimo === 1) {
                    ReqProgramaTejido::where('Id', $ultimoDestino->Id)->update(['Ultimo' => 0]);
                }

                // FechaInicio = EXACTAMENTE FechaFinal del último programa del telar destino
                $fechaInicioBase = $ultimoDestino && $ultimoDestino->FechaFinal
                    ? Carbon::parse($ultimoDestino->FechaFinal)
                    : ($original->FechaInicio ? Carbon::parse($original->FechaInicio) : Carbon::now());

                $nuevo = $original->replicate();

                // ===== Básicos =====
                $nuevo->SalonTejidoId = $salonDestino;
                $nuevo->NoTelarId     = $telarDestino;
                $nuevo->EnProceso     = 0;
                $nuevo->Ultimo        = 1;
                $nuevo->CambioHilo    = 0;

                $nuevo->Maquina = self::construirMaquina($original->Maquina ?? null, $salonDestino, $telarDestino);

                // Limpiar campos
                $nuevo->Produccion    = null;
                $nuevo->Programado    = null;
                $nuevo->NoProduccion  = null;

                // ===== ORDCOMPARTIDA: Asignar el OrdCompartida (existente o nuevo) =====
                $nuevo->OrdCompartida = $ordCompartidaAVincular;

                // ===== ORDCOMPARTIDALIDER: Solo el primer registro vinculado tiene OrdCompartidaLider = 1 =====
                if ($totalVinculados === 0) {
                    $nuevo->OrdCompartidaLider = 1;
                } else {
                    $nuevo->OrdCompartidaLider = null;
                }

                $nuevo->ProgramarProd = Carbon::now()->format('Y-m-d');

                // ===== Overrides =====
                if ($inventSizeId) $nuevo->InventSizeId = $inventSizeId;
                if ($tamanoClave)  $nuevo->TamanoClave  = $tamanoClave;
                if ($codArticulo)  $nuevo->ItemId       = $codArticulo;
                if ($producto)     $nuevo->NombreProducto = StringTruncator::truncate('NombreProducto', $producto);
                if ($custname)     $nuevo->CustName = StringTruncator::truncate('CustName', $custname);
                if ($hilo)         $nuevo->FibraRizo = $hilo;
                if ($flog)         $nuevo->FlogsId = $flog;
                if ($aplicacion)   $nuevo->AplicacionId = StringTruncator::truncate('AplicacionId', $aplicacion);
                if ($descripcion)  $nuevo->NombreProyecto = StringTruncator::truncate('NombreProyecto', $descripcion);

                // ===== PedidoTempo, Observaciones y PorcentajeSegundos =====
                if ($pedidoTempoDestino !== null && $pedidoTempoDestino !== '') {
                    $nuevo->PedidoTempo = $pedidoTempoDestino;
                }
                if ($observacionesDestino !== null && $observacionesDestino !== '') {
                    $nuevo->Observaciones = StringTruncator::truncate('Observaciones', $observacionesDestino);
                }
                if ($porcentajeSegundosDestino !== null) {
                    $nuevo->PorcentajeSegundos = $porcentajeSegundosDestino;
                }

                // ===== TotalPedido / SaldoPedido =====
                // TotalPedido = pedido (sin % de segundas)
                $pedidoDestino = ($pedidoDestinoRaw !== null && $pedidoDestinoRaw !== '') ? self::sanitizeNumber($pedidoDestinoRaw) : null;

                if ($pedidoDestino !== null) {
                    $nuevo->TotalPedido = $pedidoDestino;
                } elseif (!empty($pedidoGlobal)) {
                    $nuevo->TotalPedido = $pedidoGlobal;
                } elseif (!empty($original->TotalPedido)) {
                    $nuevo->TotalPedido = self::sanitizeNumber($original->TotalPedido);
                } elseif (!empty($original->SaldoPedido)) {
                    $nuevo->TotalPedido = self::sanitizeNumber($original->SaldoPedido);
                } else {
                    $nuevo->TotalPedido = 0;
                }

                // SaldoPedido = saldo (con % de segundas aplicado)
                $saldoDestino = ($saldoDestinoRaw !== null && $saldoDestinoRaw !== '') ? self::sanitizeNumber($saldoDestinoRaw) : null;

                if ($saldoDestino !== null) {
                    $nuevo->SaldoPedido = $saldoDestino;
                } else {
                    // Si no viene saldo, calcularlo basado en TotalPedido y % de segundas
                    $porcentajeSegundos = $porcentajeSegundosDestino ?? 0;
                    $nuevo->SaldoPedido = $nuevo->TotalPedido * (1 + $porcentajeSegundos / 100);
                }

                // ===== FORZAR STD DESDE CATÁLOGOS (SMITH/JACQUARD + Normal/Alta) =====
                self::aplicarStdDesdeCatalogos($nuevo);

                // ===== FECHA INICIO: SIEMPRE la FechaFinal del último registro del telar destino =====
                // NO hacer snap al calendario, usar exactamente la fecha final del último registro
                $nuevo->FechaInicio = $fechaInicioBase->format('Y-m-d H:i:s');
                $inicio = $fechaInicioBase->copy();

                // ===== CALCULAR FECHA FINAL desde la fecha inicio exacta =====
                $horasNecesarias = self::calcularHorasProd($nuevo);

                // fallback proporcional si por alguna razón horas=0 pero existe HorasProd en original
                if ($horasNecesarias <= 0 && !empty($original->HorasProd)) {
                    $cantOrig = self::sanitizeNumber($original->SaldoPedido ?? $original->TotalPedido ?? 0);
                    $cantNew  = self::sanitizeNumber($nuevo->SaldoPedido ?? $nuevo->TotalPedido ?? 0);
                    if ($cantOrig > 0 && $cantNew > 0) {
                        $horasNecesarias = (float)$original->HorasProd * ($cantNew / $cantOrig);
                    }
                }

                if ($horasNecesarias <= 0) {
                    $nuevo->FechaFinal = $inicio->copy()->addDays(30)->format('Y-m-d H:i:s');
                } else {
                    // Calcular FechaFinal desde la fecha inicio exacta (sin snap)
                    if (!empty($nuevo->CalendarioId)) {
                        $fin = BalancearTejido::calcularFechaFinalDesdeInicio($nuevo->CalendarioId, $inicio, $horasNecesarias);
                        if (!$fin) {
                            $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
                        }
                        $nuevo->FechaFinal = $fin->format('Y-m-d H:i:s');
                    } else {
                        $nuevo->FechaFinal = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600))->format('Y-m-d H:i:s');
                    }
                }

                LogFacade::info('VincularTejido: recalculo exacto nuevo registro', [
                    'id' => $nuevo->Id ?? 'nuevo',
                    'telar_destino' => $telarDestino,
                    'cantidad' => $nuevo->TotalPedido,
                    'horas' => $horasNecesarias,
                    'inicio' => $nuevo->FechaInicio,
                    'fin' => $nuevo->FechaFinal,
                    'calendario_id' => $nuevo->CalendarioId ?? null,
                    'no_tiras' => $nuevo->NoTiras ?? null,
                    'luchaje' => $nuevo->Luchaje ?? null,
                    'rep' => $nuevo->Repeticiones ?? null,
                    'vel_std' => $nuevo->VelocidadSTD ?? null,
                    'efi_std' => $nuevo->EficienciaSTD ?? null,
                ]);

                if (!empty($nuevo->FechaFinal) && !empty($nuevo->FechaInicio)) {
                    if (Carbon::parse($nuevo->FechaFinal)->lt(Carbon::parse($nuevo->FechaInicio))) {
                        $nuevo->FechaFinal = $nuevo->FechaInicio;
                    }
                }

                // ===== CambioHilo =====
                if ($ultimoDestino) {
                    $nuevo->CambioHilo = (trim((string)$nuevo->FibraRizo) !== trim((string)$ultimoDestino->FibraRizo)) ? '1' : '0';
                }

                // ===== Fórmulas =====
                if (!empty($nuevo->FechaInicio) && !empty($nuevo->FechaFinal)) {
                    $formulas = self::calcularFormulasEficiencia($nuevo);
                    foreach ($formulas as $campo => $valor) {
                        $nuevo->{$campo} = $valor;
                    }
                }

                // Truncar strings
                foreach ([
                    'Maquina','NombreProyecto','CustName','AplicacionId','NombreProducto',
                    'FlogsId','TipoPedido','Observaciones','FibraTrama','FibraComb1','FibraComb2',
                    'FibraComb3','FibraComb4','FibraComb5','FibraPie','SalonTejidoId','NoTelarId',
                    'Rasurado','TamanoClave'
                ] as $campoStr) {
                    if (isset($nuevo->{$campoStr})) {
                        $nuevo->{$campoStr} = StringTruncator::truncate($campoStr, $nuevo->{$campoStr});
                    }
                }

                $nuevo->CreatedAt = now();
                $nuevo->UpdatedAt = now();
                $nuevo->save();

                $idsParaObserver[] = $nuevo->Id;
                $totalVinculados++;
            }

            DBFacade::commit();

            // Restaurar dispatcher y observer
            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Disparar observer manualmente
            $observer = new ReqProgramaTejidoObserver();
            foreach ($idsParaObserver as $id) {
                if ($registro = ReqProgramaTejido::find($id)) {
                    $observer->saved($registro);
                }
            }

            // asegurarse
            ReqProgramaTejido::whereIn('Id', $idsParaObserver)->update(['EnProceso' => 0]);

            $primer = !empty($idsParaObserver) ? ReqProgramaTejido::find($idsParaObserver[0]) : null;

            return response()->json([
                'success' => true,
                'message' => "Telar vinculado correctamente. Se crearon {$totalVinculados} registro(s) con OrdCompartida: {$ordCompartidaAVincular}.",
                'registros_vinculados' => $totalVinculados,
                'ord_compartida' => $ordCompartidaAVincular,
                'registro_id' => $primer?->Id,
                'salon_destino' => $primer?->SalonTejidoId,
                'telar_destino' => $primer?->NoTelarId,
            ]);

        } catch (\Throwable $e) {
            DBFacade::rollBack();

            if ($dispatcher) {
                ReqProgramaTejido::setEventDispatcher($dispatcher);
            }
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            LogFacade::error('VincularTejido error', [
                'salon' => $salonOrigen,
                'telar' => $telarOrigen,
                'msg'   => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al vincular el telar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene un nuevo OrdCompartida disponible verificando que no esté en uso
     *
     * @return int
     */
    private static function obtenerNuevoOrdCompartidaDisponible(): int
    {
        // Obtener el máximo OrdCompartida existente
        $maxOrdCompartida = ReqProgramaTejido::max('OrdCompartida') ?? 0;

        // Empezar desde el siguiente número
        $candidato = $maxOrdCompartida + 1;

        // Verificar que no esté en uso (buscar hasta encontrar uno disponible)
        // Límite de seguridad para evitar loops infinitos
        $intentos = 0;
        $maxIntentos = 1000;

        while ($intentos < $maxIntentos) {
            // Verificar si el OrdCompartida candidato ya existe
            $existe = ReqProgramaTejido::where('OrdCompartida', $candidato)->exists();

            if (!$existe) {
                // Este OrdCompartida está disponible
                LogFacade::info('VincularTejido: Nuevo OrdCompartida asignado', [
                    'ord_compartida' => $candidato,
                    'max_existente' => $maxOrdCompartida,
                ]);
                return $candidato;
            }

            // Si existe, probar el siguiente
            $candidato++;
            $intentos++;
        }

        // Si llegamos aquí, algo está mal (muchos gaps en la secuencia)
        // Usar el máximo + 1 de todas formas y loggear advertencia
        LogFacade::warning('VincularTejido: No se encontró OrdCompartida disponible después de múltiples intentos', [
            'max_ord_compartida' => $maxOrdCompartida,
            'candidato_final' => $candidato,
        ]);

        return $candidato;
    }

    private static function obtenerUltimoRegistroTelar(string $salon, string $telar): ?ReqProgramaTejido
    {
        return ReqProgramaTejido::query()
            ->salon($salon)
            ->telar($telar)
            ->where('Ultimo', 1)
            ->orderBy('FechaInicio', 'desc')
            ->first()
            ?? ReqProgramaTejido::query()
                ->salon($salon)
                ->telar($telar)
                ->orderBy('FechaInicio', 'desc')
                ->first();
    }

    // =========================================================
    // FECHAS EXACTAS (IGUAL QUE DUPLICAR)
    // =========================================================

    private static function calcularInicioFinExactos(ReqProgramaTejido $r): array
    {
        if (empty($r->FechaInicio)) {
            return [null, null, 0.0];
        }

        $inicio = Carbon::parse($r->FechaInicio);

        if (!empty($r->CalendarioId)) {
            $snap = self::snapInicioAlCalendario($r->CalendarioId, $inicio);
            if ($snap) $inicio = $snap;
        }

        $horasNecesarias = self::calcularHorasProd($r);

        if ($horasNecesarias <= 0) {
            $fin = $inicio->copy()->addDays(30);
            return [$inicio, $fin, 0.0];
        }

        if (!empty($r->CalendarioId)) {
            $fin = BalancearTejido::calcularFechaFinalDesdeInicio($r->CalendarioId, $inicio, $horasNecesarias);
            if (!$fin) {
                $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
            }
            return [$inicio, $fin, $horasNecesarias];
        }

        $fin = $inicio->copy()->addSeconds((int)($horasNecesarias * 3600));
        return [$inicio, $fin, $horasNecesarias];
    }

    private static function snapInicioAlCalendario(string $calendarioId, Carbon $fechaInicio): ?Carbon
    {
        return TejidoHelpers::snapInicioAlCalendario($calendarioId, $fechaInicio);
    }

    // =========================================================
    // HORAS (CORREGIDO: toma NoTiras/Luchaje/Rep del modelo si faltan)
    // =========================================================

    private static function calcularHorasProd(ReqProgramaTejido $p): float
    {
        $vel   = (float) ($p->VelocidadSTD ?? 0);
        $efic  = (float) ($p->EficienciaSTD ?? 0);
        $cantidad = self::sanitizeNumber($p->SaldoPedido ?? $p->Produccion ?? $p->TotalPedido ?? 0);

        $m = self::getModeloParams($p->TamanoClave ?? null, $p);

        return TejidoHelpers::calcularHorasProd(
            $vel,
            $efic,
            $cantidad,
            (float)($m['no_tiras'] ?? 0),
            (float)($m['total'] ?? 0),
            (float)($m['luchaje'] ?? 0),
            (float)($m['repeticiones'] ?? 0)
        );
    }

    private static function getModeloParams(?string $tamanoClave, ReqProgramaTejido $p): array
    {
        $noTiras = (float)($p->NoTiras ?? 0);
        $luchaje = (float)($p->Luchaje ?? 0);
        $rep     = (float)($p->Repeticiones ?? 0);

        $key = trim((string)$tamanoClave);
        if ($key === '') {
            return [
                'total' => 0.0,
                'no_tiras' => $noTiras,
                'luchaje' => $luchaje,
                'repeticiones' => $rep,
            ];
        }

        if (!isset(self::$modeloCache[$key])) {
            $m = ReqModelosCodificados::where('TamanoClave', $key)->first();
            self::$modeloCache[$key] = $m ? [
                'total' => (float)($m->Total ?? 0),
                'no_tiras' => (float)($m->NoTiras ?? 0),
                'luchaje' => (float)($m->Luchaje ?? 0),
                'repeticiones' => (float)($m->Repeticiones ?? 0),
            ] : [
                'total' => 0.0,
                'no_tiras' => 0.0,
                'luchaje' => 0.0,
                'repeticiones' => 0.0,
            ];
        }

        $base = self::$modeloCache[$key];

        return [
            'total' => (float)($base['total'] ?? 0),
            'no_tiras' => $noTiras > 0 ? $noTiras : (float)($base['no_tiras'] ?? 0),
            'luchaje' => $luchaje > 0 ? $luchaje : (float)($base['luchaje'] ?? 0),
            'repeticiones' => $rep > 0 ? $rep : (float)($base['repeticiones'] ?? 0),
        ];
    }

    // =========================
    // STD DESDE CATÁLOGOS
    // =========================

    private static function aplicarStdDesdeCatalogos(ReqProgramaTejido $p): void
    {
        TejidoHelpers::aplicarStdDesdeCatalogos($p);
    }

    private static function resolverTipoTelarStd(?string $maquina, ?string $salonTejidoId): string
    {
        return TejidoHelpers::resolverTipoTelarStd($maquina, $salonTejidoId);
    }

    private static function resolverDensidadStd(ReqProgramaTejido $p): string
    {
        return TejidoHelpers::resolverDensidadStd($p->Densidad ?? null);
    }

    private static function buscarStdVelocidad(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqVelocidadStd
    {
        return TejidoHelpers::buscarStdVelocidad($tipoTelar, $telar, $fibraId, $densidad);
    }

    private static function buscarStdEficiencia(string $tipoTelar, string $telar, string $fibraId, string $densidad): ?ReqEficienciaStd
    {
        return TejidoHelpers::buscarStdEficiencia($tipoTelar, $telar, $fibraId, $densidad);
    }

    // =========================
    // FÓRMULAS (se quedan como tenías)
    // =========================

    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $formulas = [];

        try {
            $vel = (float) ($programa->VelocidadSTD ?? 0);
            $efic = (float) ($programa->EficienciaSTD ?? 0);
            $cantidad = self::sanitizeNumber($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

            if ($efic > 1) $efic = $efic / 100;

            $m = self::getModeloParams($programa->TamanoClave ?? null, $programa);

            $inicio = Carbon::parse($programa->FechaInicio);
            $fin    = Carbon::parse($programa->FechaFinal);
            $diffSeg = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffDias = $diffSeg / 86400;

            $stdToaHra = 0;
            if ($m['no_tiras'] > 0 && $m['total'] > 0 && $m['luchaje'] > 0 && $m['repeticiones'] > 0 && $vel > 0) {
                $parte1 = $m['total'];
                $parte2 = (($m['luchaje'] * 0.5) / 0.0254) / $m['repeticiones'];
                $den = ($parte1 + $parte2) / $vel;
                if ($den > 0) {
                    $stdToaHra = ($m['no_tiras'] * 60) / $den;
                    $formulas['StdToaHra'] = (float) round($stdToaHra, 2);
                }
            }

            // PesoGRM2
            $largoToalla = (float) ($programa->LargoToalla ?? 0);
            $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
            }

            // DiasEficiencia (tu regla)
            if ($diffDias > 0) {
                $formulas['DiasEficiencia'] = (float) round($diffDias, 2);
            }

            // StdDia / ProdKgDia
            if ($stdToaHra > 0 && $efic > 0) {
                $stdDia = $stdToaHra * $efic * 24;
                $formulas['StdDia'] = (float) round($stdDia, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia'] = (float) round(($stdDia * $pesoCrudo) / 1000, 2);
                }
            }

            // StdHrsEfect / ProdKgDia2 (depende de diffDias)
            if ($diffDias > 0) {
                $stdHrsEfect = ($cantidad / $diffDias) / 24;
                $formulas['StdHrsEfect'] = (float) round($stdHrsEfect, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
                }
            }

            // HorasProd / DiasJornada
            if ($stdToaHra > 0 && $efic > 0) {
                $horasProd = $cantidad / ($stdToaHra * $efic);
                $formulas['HorasProd'] = (float) round($horasProd, 2);

                $formulas['DiasJornada'] = (float) round($horasProd / 24, 2);
            }

            // EntregaCte = FechaFinal + 12 días
            $entregaCteCalculada = null;
            if (!empty($programa->FechaFinal)) {
                try {
                    $fechaFinal = Carbon::parse($programa->FechaFinal);
                    $entregaCteCalculada = $fechaFinal->copy()->addDays(12);
                    $formulas['EntregaCte'] = $entregaCteCalculada->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    // Si hay error al parsear, no establecer EntregaCte
                }
            }

            // PTvsCte = EntregaCte - EntregaPT (diferencia en días)
            if (!empty($programa->EntregaPT)) {
                try {
                    $entregaPT = Carbon::parse($programa->EntregaPT);
                    // Usar EntregaCte calculada si existe, sino usar la del programa si existe
                    $entregaCteParaCalcular = $entregaCteCalculada
                        ?: (!empty($programa->EntregaCte) ? Carbon::parse($programa->EntregaCte) : null);

                    if ($entregaCteParaCalcular) {
                        $diferenciaDias = $entregaCteParaCalcular->diffInDays($entregaPT, false);
                        $formulas['PTvsCte'] = (float) round($diferenciaDias, 2);
                    }
                } catch (\Throwable $e) {
                    // Si hay error al parsear, no establecer PTvsCte
                }
            }

        } catch (\Throwable $e) {
            LogFacade::warning('VincularTejido: Error al calcular fórmulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }

    private static function construirMaquina(?string $maquinaBase, ?string $salon, $telar): string
    {
        return TejidoHelpers::construirMaquinaConBase($maquinaBase, $salon, $telar);
    }

    private static function sanitizeNumber($value): float
    {
        return TejidoHelpers::sanitizeNumber($value);
    }
}
