<?php

namespace App\Http\Controllers\ProgramaTejido\funciones;

use App\Models\ReqProgramaTejido;
use App\Http\Controllers\ProgramaTejido\helper\DateHelpers;
use App\Http\Controllers\ProgramaTejido\helper\UpdateHelpers;
use App\Http\Controllers\ProgramaTejido\helper\UtilityHelpers;
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
        ]);

        // 1) Hilo (FibraRizo)
        if (array_key_exists('hilo', $data)) {
            $registro->FibraRizo = $data['hilo'] ?: null;
        }

        // 2) Jornada (CalendarioId)
        if (array_key_exists('calendario_id', $data)) {
            $registro->CalendarioId = $data['calendario_id'] ?: null;
        }

        // 3) Clave Modelo (TamanoClave)
        if (array_key_exists('tamano_clave', $data)) {
            $registro->TamanoClave = $data['tamano_clave'] ?: null;
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
        }

        // 6) Dia Scheduling (ProgramarProd)
        if (array_key_exists('programar_prod', $data) && !empty($data['programar_prod'])) {
            DateHelpers::setSafeDate($registro, 'ProgramarProd', $data['programar_prod']);
        }

        // 7) Id Flog (FlogsId) y TipoPedido
        UpdateHelpers::applyFlogYTipoPedido($registro, $data['idflog'] ?? null);

        // 8) Aplicaciones (AplicacionId)
        if (array_key_exists('aplicacion_id', $data)) {
            $registro->AplicacionId = ($data['aplicacion_id'] === 'NA' || $data['aplicacion_id'] === '') ? null : $data['aplicacion_id'];
        }

        // 9) Tiras (NoTiras)
        if (array_key_exists('no_tiras', $data)) {
            $registro->NoTiras = $data['no_tiras'] !== null ? (int) $data['no_tiras'] : null;
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
        }

        // 13) Pcr (PesoCrudo)
        if (array_key_exists('peso_crudo', $data)) {
            $registro->PesoCrudo = $data['peso_crudo'] !== null ? (float) $data['peso_crudo'] : null;
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

        // Log útil
        LogFacade::info('UPDATE payload (campos limitados)', [
            'Id' => $registro->Id,
            'keys' => array_keys($data),
        ]);

        $registro->save();

        return response()->json([
            'success' => true,
            'message' => 'Programa de tejido actualizado',
            'data' => UtilityHelpers::extractResumen($registro),
        ]);
    }
}

