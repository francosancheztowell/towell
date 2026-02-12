<?php

namespace App\Http\Controllers\ProgramaUrdEng\ReservarProgramar;

use App\Http\Controllers\Controller;
use App\Services\ProgramaUrdEng\InventarioReservasService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Acciones sobre reservas de inventario (solo POST): reservar y cancelar.
 */
class ReservaInventarioController extends Controller
{
    public function __construct(
        private InventarioReservasService $reservasService
    ) {}

    /** POST reservar pieza (idempotente por índice único). */
    public function reservar(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'NoTelarId'        => ['required', 'string', 'max:10'],
                'SalonTejidoId'    => ['nullable', 'string', 'max:20'],
                'ItemId'           => ['required', 'string', 'max:50'],
                'ConfigId'         => ['nullable', 'string', 'max:30'],
                'InventSizeId'     => ['nullable', 'string', 'max:10'],
                'InventColorId'    => ['nullable', 'string', 'max:10'],
                'InventLocationId' => ['nullable', 'string', 'max:10'],
                'InventBatchId'    => ['nullable', 'string', 'max:20'],
                'WMSLocationId'    => ['nullable', 'string', 'max:10'],
                'InventSerialId'   => ['nullable', 'string', 'max:20'],
                'NoProveedor'       => ['nullable', 'string', 'max:50'],
                'Tipo'             => ['nullable', 'string', 'max:20'],
                'Metros'            => ['nullable', 'numeric'],
                'InventQty'        => ['nullable', 'numeric'],
                'ProdDate'          => ['nullable', 'date'],
                'fecha'             => ['nullable', 'date'],
                'turno'             => ['nullable', 'integer', 'min:1', 'max:3'],
                'tej_inventario_telares_id' => ['required', 'integer'],
                'NumeroEmpleado'   => ['nullable', 'string', 'max:20'],
                'NombreEmpl'       => ['nullable', 'string', 'max:120'],
            ]);

            $data['Fecha'] = $this->parseFecha($data['fecha'] ?? null, $data['ProdDate'] ?? null);
            $data['Turno'] = isset($data['turno']) && is_numeric($data['turno']) ? (int) $data['turno'] : null;

            if (!isset($data['tej_inventario_telares_id']) || !is_numeric($data['tej_inventario_telares_id'])) {
                Log::error('Reservar: tej_inventario_telares_id faltante o inválido', ['request_data' => $request->all()]);
                throw new \InvalidArgumentException('El ID del registro de tej_inventario_telares es requerido para reservar');
            }
            $data['TejInventarioTelaresId'] = (int) $data['tej_inventario_telares_id'];
            $data['Status'] = 'Reservado';

            $usuario = Auth::user();
            $data['NumeroEmpleado'] = $usuario->numero_empleado ?? null;
            $data['NombreEmpl'] = $usuario->nombre ?? null;
            if (!$usuario) {
                Log::warning('Reservar: No hay usuario autenticado', ['request' => $request->all()]);
            }

            $dimFields = [
                'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId',
                'InventLocationId', 'InventBatchId', 'WMSLocationId', 'InventSerialId',
            ];
            foreach ($dimFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = $this->reservasService->normalizeDimValue($data[$field]);
                }
            }

            $result = $this->reservasService->ejecutarReserva($data);

            return response()->json([
                'success' => true,
                'created' => $result['created'],
                'message' => $result['message'],
            ]);
        } catch (Throwable $e) {
            Log::error('ReservaInventario.reservar error', ['msg' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al reservar la pieza: ' . $e->getMessage(),
            ], 500);
        }
    }

    /** POST cancelar por Id o por clave dimensional + telar. */
    public function cancelar(Request $request): JsonResponse
    {
        $request->validate([
            'Id'               => ['nullable', 'integer'],
            'NoTelarId'        => ['required_without:Id', 'string'],
            'ItemId'           => ['required_without:Id', 'string'],
            'ConfigId'         => ['nullable', 'string'],
            'InventSizeId'     => ['nullable', 'string'],
            'InventColorId'    => ['nullable', 'string'],
            'InventLocationId' => ['nullable', 'string'],
            'InventBatchId'    => ['nullable', 'string'],
            'WMSLocationId'    => ['nullable', 'string'],
            'InventSerialId'   => ['nullable', 'string'],
        ]);

        $input = $request->only([
            'Id', 'NoTelarId', 'ItemId', 'ConfigId', 'InventSizeId', 'InventColorId',
            'InventLocationId', 'InventBatchId', 'WMSLocationId', 'InventSerialId',
        ]);
        $result = $this->reservasService->ejecutarCancelar($input);

        return response()->json(['success' => true, 'updated' => $result['updated']]);
    }

    private function parseFecha($fecha, $prodDate): ?string
    {
        if ($fecha !== null) {
            try {
                $parsed = Carbon::parse($fecha);
                if ($parsed->year === 1900 && $parsed->month === 1 && $parsed->day === 1) {
                    return null;
                }
                return $parsed->format('Y-m-d');
            } catch (\Exception $e) {
                return $this->parseFecha($prodDate, null);
            }
        }
        if ($prodDate !== null) {
            try {
                $parsed = Carbon::parse($prodDate);
                if ($parsed->year === 1900 && $parsed->month === 1 && $parsed->day === 1) {
                    return null;
                }
                return $parsed->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }
}
