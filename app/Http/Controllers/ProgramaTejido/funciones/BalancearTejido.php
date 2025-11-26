<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Models\ReqProgramaTejido;
use Illuminate\Http\Request;

class BalancearTejido
{
    /**
     * Actualizar los pedidos y fechas desde la pantalla de balanceo
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

            foreach ($cambios as $cambio) {
                $registro = ReqProgramaTejido::find($cambio['id']);
                if ($registro) {
                    $totalPedido = $cambio['total_pedido'];
                    $produccion = $registro->Produccion ?? 0;
                    $saldoPedido = $totalPedido - $produccion;

                    $registro->TotalPedido = $totalPedido;
                    $registro->SaldoPedido = max(0, $saldoPedido);

                    // Actualizar fecha final si se proporcionó
                    if (!empty($cambio['fecha_final'])) {
                        $registro->FechaFinal = $cambio['fecha_final'];
                    }

                    $registro->save();
                    $actualizados++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Se actualizaron {$actualizados} registro(s) correctamente",
                'actualizados' => $actualizados
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar los pedidos: ' . $e->getMessage()
            ], 500);
        }
    }
}

