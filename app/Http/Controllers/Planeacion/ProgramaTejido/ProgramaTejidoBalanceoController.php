<?php

namespace App\Http\Controllers\Planeacion\ProgramaTejido;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Planeacion\ProgramaTejido\funciones\BalancearTejido;
use App\Models\Planeacion\ReqProgramaTejido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * @file ProgramaTejidoBalanceoController.php
 *
 * @description Controlador de balanceo para Programa Tejido. Vista de balanceo por OrdCompartida,
 *              preview de fechas, actualizar pedidos, balanceo automático. Regla: snap al calendario.
 *
 * @dependencies BalancearTejido, ReqProgramaTejido
 */
class ProgramaTejidoBalanceoController extends Controller
{
    public function balancear()
    {
        $registrosCompartidos = ReqProgramaTejido::query()
            ->select([
                'Id',
                'SalonTejidoId',
                'NoTelarId',
                'ItemId',
                'NombreProducto',
                'TamanoClave',
                'TotalPedido',
                'PorcentajeSegundos',
                'SaldoPedido',
                'Produccion',
                'FechaInicio',
                'FechaFinal',
                'OrdCompartida',
                'VelocidadSTD',
                'EficienciaSTD',
                'NoTiras',
                'Luchaje',
                'PesoCrudo',
            ])
            ->whereNotNull('OrdCompartida')
            ->whereRaw("LTRIM(RTRIM(CAST(OrdCompartida AS NVARCHAR(50)))) <> ''")
            ->orderBy('OrdCompartida')
            ->orderBy('FechaInicio', 'asc')
            ->orderBy('NoTelarId', 'asc')
            ->get();

        $gruposCompartidos = $registrosCompartidos->groupBy(function ($item) {
            return (string) ((int) $item->OrdCompartida);
        });

        return view('modulos.programa-tejido.balancear', [
            'gruposCompartidos' => $gruposCompartidos,
        ]);
    }

    public function detallesBalanceo($id)
    {
        try {
            $registro = ReqProgramaTejido::find($id);

            if (! $registro) {
                LogFacade::warning('detallesBalanceo: registro no encontrado', [
                    'id' => $id,
                    'table' => ReqProgramaTejido::tableName(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado',
                ], 404);
            }

            $registro = $registro->fresh();

            if (! $registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado después de refrescar',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'registro' => $registro,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles: '.$e->getMessage(),
            ], 500);
        }
    }

    public function previewFechasBalanceo(Request $request)
    {
        return BalancearTejido::previewFechas($request);
    }

    public function actualizarPedidosBalanceo(Request $request)
    {
        return BalancearTejido::actualizarPedidos($request);
    }

    public function balancearAutomatico(Request $request)
    {
        return BalancearTejido::balancearAutomatico($request);
    }

    /**
     * Misma respuesta JSON que registros-ord-compartida; expone la ruta histórica
     * ver-detalles-grupo-balanceo sin usar una clase no-controlador como acción.
     */
    public function verDetallesGrupoBalanceo($ordCompartida)
    {
        $normalized = $this->normalizeOrdCompartidaRouteParam($ordCompartida);
        if ($normalized === null) {
            return response()->json([
                'success' => false,
                'message' => 'OrdCompartida inválida',
            ], 400);
        }

        return BalancearTejido::getRegistrosPorOrdCompartida($normalized);
    }

    private function normalizeOrdCompartidaRouteParam($value): ?int
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }
}
