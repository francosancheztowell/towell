<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Models\ReqProgramaTejido;
use App\Http\Controllers\ProgramaTejido\helper\DateHelpers;
use App\Observers\ReqProgramaTejidoObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BalancearTejido
{
    /**
     * Actualizar los pedidos y fechas desde la pantalla de balanceo
     * Si se actualiza la fecha final, recalcula fórmulas y actualiza filas siguientes del mismo telar
     */
    public static function actualizarPedidos(Request $request)
    {
        try {
            $request->validate([
                'cambios' => 'required|array',
                'cambios.*.id' => 'required|integer',
                'cambios.*.total_pedido' => 'required|numeric|min:0',
                'cambios.*.fecha_final' => 'nullable|string',
                'ord_compartida' => 'required|integer'
            ]);

            $cambios = $request->input('cambios');
            $actualizados = 0;
            $registrosConFechaActualizada = [];

            // Desactivar observer temporalmente para evitar loops
            ReqProgramaTejido::unsetEventDispatcher();

            foreach ($cambios as $cambio) {
                $registro = ReqProgramaTejido::find($cambio['id']);
                if ($registro) {
                    $totalPedido = (float) $cambio['total_pedido'];
                    $produccion = (float) ($registro->Produccion ?? 0);
                    $saldoPedido = $totalPedido - $produccion;

                    $registro->TotalPedido = $totalPedido;
                    $registro->SaldoPedido = max(0, $saldoPedido);

                    // Actualizar fecha final si se proporcionó
                    $fechaFinalCambiada = false;
                    if (!empty($cambio['fecha_final'])) {
                        $fechaFinalAnterior = $registro->FechaFinal;
                        $registro->FechaFinal = $cambio['fecha_final'];
                        $fechaFinalCambiada = ($fechaFinalAnterior !== $registro->FechaFinal);
                    }

                    // Recalcular fórmulas de eficiencia para este registro
                    if ($registro->FechaInicio && $registro->FechaFinal) {
                        $formulas = self::calcularFormulasEficiencia($registro);
                        foreach ($formulas as $campo => $valor) {
                            $registro->{$campo} = $valor;
                        }
                    }

                    $registro->save();
                    $actualizados++;

                    // Si cambió la fecha final, guardar para cascada posterior
                    if ($fechaFinalCambiada) {
                        $registrosConFechaActualizada[] = $registro;
                    }
                }
            }

            // Re-activar observer
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            // Aplicar cascada de fechas para registros con fecha actualizada
            // Esto actualiza las fechas de las filas siguientes del mismo telar
            foreach ($registrosConFechaActualizada as $registroActualizado) {
                try {
                    DateHelpers::cascadeFechas($registroActualizado);
                } catch (\Exception $e) {
                    Log::warning('BalancearTejido: Error en cascadeFechas', [
                        'registro_id' => $registroActualizado->Id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Se actualizaron {$actualizados} registro(s) correctamente",
                'actualizados' => $actualizados
            ]);
        } catch (\Exception $e) {
            // Asegurar que el observer se reactive incluso en caso de error
            ReqProgramaTejido::observe(ReqProgramaTejidoObserver::class);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar los pedidos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular fórmulas de eficiencia (similar a ReqProgramaTejidoObserver)
     */
    private static function calcularFormulasEficiencia(ReqProgramaTejido $programa): array
    {
        $formulas = [];

        try {
            $vel = (float) ($programa->VelocidadSTD ?? 0);
            $efic = (float) ($programa->EficienciaSTD ?? 0);
            $cantidad = (float) ($programa->SaldoPedido ?? $programa->Produccion ?? $programa->TotalPedido ?? 0);
            $pesoCrudo = (float) ($programa->PesoCrudo ?? 0);

            if ($efic > 1) {
                $efic = $efic / 100;
            }

            $noTiras = 0;
            $luchaje = 0;
            $repeticiones = 0;
            $total = 0;

            if ($programa->TamanoClave) {
                $modelo = \App\Models\ReqModelosCodificados::where('TamanoClave', $programa->TamanoClave)->first();
                if ($modelo) {
                    $total = (float) ($modelo->Total ?? 0);
                    $noTiras = (float) ($modelo->NoTiras ?? 0);
                    $luchaje = (float) ($modelo->Luchaje ?? 0);
                    $repeticiones = (float) ($modelo->Repeticiones ?? 0);
                }
            }

            $inicio = \Carbon\Carbon::parse($programa->FechaInicio);
            $fin = \Carbon\Carbon::parse($programa->FechaFinal);
            $diffSegundos = abs($fin->getTimestamp() - $inicio->getTimestamp());
            $diffDias = $diffSegundos / (60 * 60 * 24);

            // Calcular StdToaHra
            $stdToaHra = 0;
            if ($noTiras > 0 && $total > 0 && $luchaje > 0 && $repeticiones > 0 && $vel > 0) {
                $parte1 = $total / 1;
                $parte2 = (($luchaje * 0.5) / 0.0254) / $repeticiones;
                $denominador = ($parte1 + $parte2) / $vel;
                if ($denominador > 0) {
                    $stdToaHra = ($noTiras * 60) / $denominador;
                    $formulas['StdToaHra'] = (float) round($stdToaHra, 2);
                }
            }

            // Calcular PesoGRM2
            $largoToalla = (float) ($programa->LargoToalla ?? 0);
            $anchoToalla = (float) ($programa->AnchoToalla ?? 0);
            if ($pesoCrudo > 0 && $largoToalla > 0 && $anchoToalla > 0) {
                $formulas['PesoGRM2'] = (float) round(($pesoCrudo * 10000) / ($largoToalla * $anchoToalla), 2);
            }

            // Calcular DiasEficiencia
            if ($diffDias > 0) {
                $formulas['DiasEficiencia'] = (float) round($diffDias, 2);
            }

            // Calcular StdDia y ProdKgDia
            if ($stdToaHra > 0 && $efic > 0) {
                $stdDia = $stdToaHra * $efic * 24;
                $formulas['StdDia'] = (float) round($stdDia, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia'] = (float) round(($stdDia * $pesoCrudo) / 1000, 2);
                }
            }

            // Calcular StdHrsEfect y ProdKgDia2
            if ($diffDias > 0) {
                $stdHrsEfect = ($cantidad / $diffDias) / 24;
                $formulas['StdHrsEfect'] = (float) round($stdHrsEfect, 2);

                if ($pesoCrudo > 0) {
                    $formulas['ProdKgDia2'] = (float) round((($pesoCrudo * $stdHrsEfect) * 24) / 1000, 2);
                }
            }

            // Calcular HorasProd y DiasJornada
            $horasProd = 0;
            if ($stdToaHra > 0 && $efic > 0) {
                $horasProd = $cantidad / ($stdToaHra * $efic);
                $formulas['HorasProd'] = (float) round($horasProd, 2);
            }

            if ($horasProd > 0) {
                $formulas['DiasJornada'] = (float) round($horasProd / 24, 2);
            }

        } catch (\Throwable $e) {
            Log::warning('BalancearTejido: Error al calcular fórmulas de eficiencia', [
                'error' => $e->getMessage(),
                'programa_id' => $programa->Id ?? null,
            ]);
        }

        return $formulas;
    }
}

