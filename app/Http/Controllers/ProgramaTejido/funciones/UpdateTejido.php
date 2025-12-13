<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Models\ReqProgramaTejido;
use App\Http\Controllers\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\ProgramaTejido\helper\UpdateHelpers;
use App\Http\Controllers\ProgramaTejido\helper\UtilityHelpers;
use App\Models\ReqModelosCodificados;
use App\Http\Controllers\ProgramaTejido\funciones\DuplicarTejido;
use App\Observers\ReqProgramaTejidoObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LogFacade;

class UpdateTejido
{
    /**
     * Actualizar un registro de programa de tejido
     *
     * Campos editables permitidos:
     * - Hilo (FibraRizo)
     * - Jornada (CalendarioId)
     * - Clave Modelo (TamanoClave)
     * - Rasurado
     * - Pedido (TotalPedido) - SaldoPedido = TotalPedido - Produccion
     * - Dia Scheduling (ProgramarProd)
     * - Id Flog (FlogsId)
     * - Aplicaciones (AplicacionId)
     * - Tiras (NoTiras)
     * - Pei (Peine)
     * - Lcr (LargoCrudo)
     * - Luc (Luchaje)
     * - Pcr (PesoCrudo)
     * - Fecha Compromiso Prod (EntregaProduc)
     * - Fecha Compromiso Pt (EntregaPT)
     * - Entrega (EntregaCte)
     * - Dif vs Compromiso (PTvsCte)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public static function actualizar(Request $request, int $id)
    {
        $registro = ReqProgramaTejido::findOrFail($id);

        // Solo se permiten editar estos campos específicos
        $data = $request->validate([
            'hilo' => ['nullable','string'],                    // Hilo (FibraRizo)
            'calendario_id' => ['nullable','string'],          // Jornada (CalendarioId)
            'tamano_clave' => ['nullable','string'],            // Clave Modelo
            'rasurado' => ['nullable','string'],                // Rasurado
            'pedido' => ['nullable','numeric','min:0'],         // Pedido (TotalPedido)
            'programar_prod' => ['nullable','date'],            // Dia Scheduling
            'idflog' => ['nullable','string'],                  // Id Flog
            'descripcion' => ['nullable','string'],             // NombreProyecto / descripción
            'aplicacion_id' => ['nullable','string'],           // Aplicaciones
            'no_tiras' => ['nullable','numeric'],               // Tiras
            'peine' => ['nullable','numeric'],                  // Pei
            'largo_crudo' => ['nullable','numeric'],            // Lcr
            'luchaje' => ['nullable','numeric'],                // Luc
            'peso_crudo' => ['nullable','numeric'],             // Pcr
            'entrega_produc' => ['nullable','date'],            // Fecha Compromiso Prod
            'entrega_pt' => ['nullable','date'],                // Fecha Compromiso Pt
            'entrega_cte' => ['nullable','date'],               // Entrega
            'pt_vs_cte' => ['nullable','numeric'],              // Dif vs Compromiso
            'fecha_final' => ['nullable','date'],               // FechaFinal directa
        ]);

        $afectaFormulas = false;
        $afectaCalendario = false;
        $fechaFinalCambiada = false;
        $fechaFinalAntes = $registro->FechaFinal;
        $fechaInicioBase = $registro->FechaInicio ? Carbon::parse($registro->FechaInicio) : null;
        $duracionOriginalSeg = ($fechaInicioBase && $fechaFinalAntes)
            ? Carbon::parse($fechaFinalAntes)->diffInSeconds($fechaInicioBase, false)
            : 0;
        $saldoAntes = (float) ($registro->SaldoPedido ?? ($registro->TotalPedido ?? 0));
        $totalAntes = (float) ($registro->TotalPedido ?? 0);
        $produccionActual = (float) ($registro->Produccion ?? 0);

        // 1) Hilo (FibraRizo)
        if (array_key_exists('hilo', $data)) {
            $registro->FibraRizo = $data['hilo'] ?: null;
            $afectaFormulas = true;
        }

        // 2) Jornada (CalendarioId)
        if (array_key_exists('calendario_id', $data)) {
            $registro->CalendarioId = $data['calendario_id'] ?: null;
            $afectaCalendario = true;
        }

        // 3) Clave Modelo (TamanoClave)
        if (array_key_exists('tamano_clave', $data)) {
            $registro->TamanoClave = $data['tamano_clave'] ?: null;
            $afectaFormulas = true;

            // Refrescar datos del modelo codificado para campos que intervienen en fórmulas
            if (!empty($registro->TamanoClave)) {
                $modelo = ReqModelosCodificados::where('TamanoClave', $registro->TamanoClave)->first();
                if ($modelo) {
                    $registro->NoTiras = $modelo->NoTiras ?? $registro->NoTiras;
                    $registro->Luchaje = $modelo->Luchaje ?? $registro->Luchaje;
                    $registro->Repeticiones = $modelo->Repeticiones ?? $registro->Repeticiones;
                    $registro->Total = $modelo->Total ?? $registro->Total;
                }
            }
        }

        // 4) Rasurado
        if (array_key_exists('rasurado', $data)) {
            $registro->Rasurado = $data['rasurado'] ?: null;
        }

        // 5) Pedido (TotalPedido) y recalcular SaldoPedido = TotalPedido - Produccion
        if (array_key_exists('pedido', $data) && $data['pedido'] !== null) {
            $totalPedido = (float) $data['pedido'];
            $registro->TotalPedido = $totalPedido;
            // Recalcular SaldoPedido = TotalPedido - Produccion
            $produccion = (float) ($registro->Produccion ?? 0);
            $registro->SaldoPedido = $totalPedido - $produccion;
            $afectaFormulas = true;
        }

        // 6) Dia Scheduling (ProgramarProd)
        if (array_key_exists('programar_prod', $data) && !empty($data['programar_prod'])) {
            DateHelpers::setSafeDate($registro, 'ProgramarProd', $data['programar_prod']);
        }

        // 7) Id Flog (FlogsId) y TipoPedido
        UpdateHelpers::applyFlogYTipoPedido($registro, $data['idflog'] ?? null);

        // 7b) Descripción / NombreProyecto
        if (array_key_exists('descripcion', $data)) {
            $registro->NombreProyecto = $data['descripcion'] ?: null;
        }

        // 8) Aplicaciones (AplicacionId)
        if (array_key_exists('aplicacion_id', $data)) {
            $registro->AplicacionId = ($data['aplicacion_id'] === 'NA' || $data['aplicacion_id'] === '') ? null : $data['aplicacion_id'];
            $afectaFormulas = true;
        }

        // 9) Tiras (NoTiras)
        if (array_key_exists('no_tiras', $data)) {
            $registro->NoTiras = $data['no_tiras'] !== null ? (int) $data['no_tiras'] : null;
            $afectaFormulas = true;
        }

        // 10) Pei (Peine)
        if (array_key_exists('peine', $data)) {
            $registro->Peine = $data['peine'] !== null ? (int) $data['peine'] : null;
        }

        // 11) Lcr (LargoCrudo)
        if (array_key_exists('largo_crudo', $data)) {
            $registro->LargoCrudo = $data['largo_crudo'] !== null ? (float) $data['largo_crudo'] : null;
        }

        // 12) Luc (Luchaje)
        if (array_key_exists('luchaje', $data)) {
            $registro->Luchaje = $data['luchaje'] !== null ? (float) $data['luchaje'] : null;
            $afectaFormulas = true;
        }

        // 13) Pcr (PesoCrudo)
        if (array_key_exists('peso_crudo', $data)) {
            $registro->PesoCrudo = $data['peso_crudo'] !== null ? (float) $data['peso_crudo'] : null;
            $afectaFormulas = true;
        }

        // 14) Fecha Compromiso Prod (EntregaProduc)
        if (array_key_exists('entrega_produc', $data) && !empty($data['entrega_produc'])) {
            DateHelpers::setSafeDate($registro, 'EntregaProduc', $data['entrega_produc']);
        }

        // 15) Fecha Compromiso Pt (EntregaPT)
        if (array_key_exists('entrega_pt', $data) && !empty($data['entrega_pt'])) {
            DateHelpers::setSafeDate($registro, 'EntregaPT', $data['entrega_pt']);
        }

        // 16) Entrega (EntregaCte)
        if (array_key_exists('entrega_cte', $data) && !empty($data['entrega_cte'])) {
            DateHelpers::setSafeDate($registro, 'EntregaCte', $data['entrega_cte']);
        }

        // 17) Dif vs Compromiso (PTvsCte)
        if (array_key_exists('pt_vs_cte', $data)) {
            $registro->PTvsCte = $data['pt_vs_cte'] !== null ? (int) $data['pt_vs_cte'] : null;
        }

        // 18) FechaFinal directa (si se edita manualmente)
        if (array_key_exists('fecha_final', $data) && !empty($data['fecha_final'])) {
            $registro->FechaFinal = Carbon::parse($data['fecha_final']);
            $fechaFinalCambiada = true;
        }

        // Recalcular fórmulas y, si aplica, la FechaFinal
        if ($afectaFormulas || $afectaCalendario) {
            $formulas = self::calcularFormulasEficiencia($registro);
            foreach ($formulas as $campo => $valor) {
                $registro->{$campo} = $valor;
            }

            // Si hay fecha de inicio, intentar recalcular la fecha final con base en horas necesarias
            if ($fechaInicioBase && !$fechaFinalCambiada) {
                $horasNecesarias = $formulas['HorasProd'] ?? null;

                // Fallback: si no hay HorasProd, escalar la duración original por factor de saldo/pedido
                if (!$horasNecesarias || $horasNecesarias <= 0) {
                    $saldoDespues = (float) ($registro->SaldoPedido ?? ($registro->TotalPedido ?? 0));
                    $factor = null;
                    if ($saldoAntes > 0 && $saldoDespues >= 0) {
                        $factor = $saldoDespues / $saldoAntes;
                    } elseif ($totalAntes > 0 && $registro->TotalPedido >= 0) {
                        $factor = $registro->TotalPedido / $totalAntes;
                    }
                    if ($factor !== null && $duracionOriginalSeg !== 0) {
                        $horasNecesarias = abs($duracionOriginalSeg) / 3600.0 * $factor;
                    } elseif (!empty($registro->FechaFinal)) {
                        $horasNecesarias = Carbon::parse($registro->FechaFinal)->diffInSeconds($fechaInicioBase) / 3600.0;
                    }
                }

                if ($horasNecesarias && $horasNecesarias > 0) {
                    if (!empty($registro->CalendarioId)) {
                        $nuevoFin = DuplicarTejido::calcularFechaFinalDesdeInicio(
                            $registro->CalendarioId,
                            $fechaInicioBase,
                            $horasNecesarias
                        );
                        if ($nuevoFin) {
                            $registro->FechaFinal = $nuevoFin->format('Y-m-d H:i:s');
                        }
                    } else {
                        $registro->FechaFinal = $fechaInicioBase->copy()->addSeconds((int) round($horasNecesarias * 3600))->format('Y-m-d H:i:s');
                    }
                    $fechaFinalCambiada = $fechaFinalCambiada || ($registro->FechaFinal != $fechaFinalAntes);
                }
            }
        }

        // Log útil
        LogFacade::info('UPDATE payload (campos limitados)', [
            'Id' => $registro->Id,
            'keys' => array_keys($data),
        ]);

        $registro->save();

        // Si cambió la fecha final, aplicar cascada y regenerar líneas de siguientes registros
        if ($fechaFinalCambiada) {
            try {
                DateHelpers::cascadeFechas($registro);
            } catch (\Throwable $e) {
                LogFacade::warning('UpdateTejido: Error en cascadeFechas', [
                    'registro_id' => $registro->Id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Regenerar líneas del propio registro para reflejar nuevas fórmulas/fechas
        try {
            $observer = new ReqProgramaTejidoObserver();
            $observer->saved($registro);
        } catch (\Throwable $e) {
            LogFacade::warning('UpdateTejido: Error al regenerar líneas del programa', [
                'registro_id' => $registro->Id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Programa de tejido actualizado',
            'data' => UtilityHelpers::extractResumen($registro),
        ]);
    }

    /**
     * Recalcula las fórmulas principales (StdToaHra, StdDia, HorasProd, etc.)
     * Esta lógica replica el cálculo de eficiencia usado en balanceo/observer.
     */
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        // Inicializar en null para evitar que queden valores viejos cuando no se puedan recalcular
        $formulas = [
            'StdToaHra'     => null,
            'StdDia'        => null,
            'HorasProd'     => null,
            'DiasJornada'   => null,
            'DiasEficiencia'=> null,
            'StdHrsEfect'   => null,
            'ProdKgDia'     => null,
            'ProdKgDia2'    => null,
        ];

        try {
            $vel = (float) ($programa->VelocidadSTD ?? 0);
            $eficRaw = $programa->getAttribute('EficienciaSTD') ?? $programa->EficienciaSTD ?? 0;
            $efic = $eficRaw !== null ? (float) $eficRaw : 0;

            if ($efic > 1) {
                $efic = $efic / 100;
            }

            $cantidad = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

            // Datos de modelo codificado
            $noTiras = (float) ($programa->NoTiras ?? 0);
            $luchaje = (float) ($programa->Luchaje ?? 0);
            $repeticiones = (float) ($programa->Repeticiones ?? 0);
            $total = (float) ($programa->Total ?? 0);

            // Fechas
            $inicio = $programa->FechaInicio ? Carbon::parse($programa->FechaInicio) : null;
            $fin = $programa->FechaFinal ? Carbon::parse($programa->FechaFinal) : null;
            $diffSegundos = ($inicio && $fin) ? abs($fin->getTimestamp() - $inicio->getTimestamp()) : 0;
            $diffDias = $diffSegundos > 0 ? $diffSegundos / 86400.0 : 0.0;

            // StdToaHra
            $stdToaHra = 0.0;
            if ($noTiras > 0 && $total > 0 && $luchaje > 0 && $vel > 0) {
                $reps = $repeticiones > 0 ? $repeticiones : 1;
                $parte1 = $total / 1;
                $parte2 = (($luchaje * 0.5) / 0.0254) / $reps;
                $denominador = ($parte1 + $parte2) / $vel;
                if ($denominador > 0) {
                    $stdToaHra = ($noTiras * 60) / $denominador;
                    $formulas['StdToaHra'] = (float) $stdToaHra;
                }
            }

            // StdDia
            if ($stdToaHra > 0 && $efic > 0) {
                $stdDia = $stdToaHra * $efic * 24;
                $formulas['StdDia'] = (float) $stdDia;
            }

            // HorasProd
            if ($stdToaHra > 0 && $efic > 0) {
                $horasProd = $cantidad / ($stdToaHra * $efic);
                $formulas['HorasProd'] = (float) $horasProd;
                $formulas['DiasJornada'] = (float) ($horasProd / 24);
            }

            // DiasEficiencia
            if ($diffDias > 0) {
                $formulas['DiasEficiencia'] = (float) round($diffDias, 2);
                $stdHrsEfect = ($cantidad / $diffDias) / 24;
                $formulas['StdHrsEfect'] = (float) $stdHrsEfect;
                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia2'] = (float) ((($pesoCrudo * $stdHrsEfect) * 24) / 1000);
                }
            }

            // ProdKgDia
            if (!empty($formulas['StdDia']) && $pesoCrudo > 0) {
                $formulas['ProdKgDia'] = (float) (($formulas['StdDia'] * $pesoCrudo) / 1000);
            }

        } catch (\Throwable $e) {
            LogFacade::warning('UpdateTejido: Error al recalcular fórmulas', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }
}

