<?php

namespace App\Http\Controllers\Atadores\ProgramaAtadores;

use App\Http\Controllers\Controller;
use App\Models\Atadores\AtaDevolucionesModel;
use App\Models\Atadores\AtaMontadoTelasModel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AtaDevolucionesController extends Controller
{
    /**
     * Ubicación fija del catálogo WMSLocation (TI-PRO) usada para el
     * combo de Ubicación en el panel de Devolución.
     */
    private const UBICACION_INVENT_LOCATION_ID = 'A-JUL/TELA';
    private const UBICACION_DATA_AREA_ID = 'PRO';

    /**
     * Catálogo de ubicaciones (WMSLocationId) para el combo de Ubicación del
     * panel de Devolución. Se consulta en vivo contra TI-PRO (conexión
     * sqlsrv_ti) filtrando InventLocationId y dataAreaId fijos.
     */
    public function ubicaciones(): JsonResponse
    {
        try {
            $ubicaciones = DB::connection('sqlsrv_ti')
                ->table('WMSLocation')
                ->where('InventLocationId', self::UBICACION_INVENT_LOCATION_ID)
                ->where('dataAreaId', self::UBICACION_DATA_AREA_ID)
                ->distinct()
                ->orderBy('wMSLocationId')
                ->pluck('wMSLocationId')
                ->filter()
                ->values();

            return response()->json(['ok' => true, 'ubicaciones' => $ubicaciones]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar WMSLocation (TI-PRO) para ubicaciones de devolución', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo consultar el catálogo de ubicaciones en TI-PRO.',
            ], 500);
        }
    }

    /**
     * Estatus de AtaMontadoTelas que se considera "atado finalizado" para
     * efectos de buscar julios previos del mismo Telar y Tipo.
     */
    private const JULIO_ESTATUS_FINALIZADO = 'Terminado';

    /**
     * Julios atados (AtaMontadoTelas) de un Telar, filtrados por el mismo Tipo
     * del atado actual y en estatus Terminado, ordenados del más reciente al
     * más antiguo. Se sugiere el "penúltimo" (segundo de la lista) porque el
     * más reciente suele ser el atado que se está calificando actualmente.
     */
    public function julios(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telar' => ['required', 'string', 'max:20'],
            'tipo' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $query = AtaMontadoTelasModel::where('NoTelarId', $data['telar'])
                ->where('Estatus', self::JULIO_ESTATUS_FINALIZADO);

            if (!empty($data['tipo'])) {
                $query->where('Tipo', $data['tipo']);
            }

            $julios = $query->orderByDesc('Fecha')
                ->orderByDesc('Turno')
                ->orderByDesc('Id')
                ->pluck('NoJulio')
                ->filter()
                ->unique()
                ->values();

            // "Penúltimo": el segundo elemento de la lista (se salta el más reciente).
            $sugerido = $julios->count() >= 2 ? $julios->get(1) : $julios->first();

            return response()->json([
                'ok' => true,
                'julios' => $julios,
                'sugerido' => $sugerido,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al consultar julios atados por telar para devolución', [
                'telar' => $data['telar'],
                'tipo' => $data['tipo'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo consultar los julios atados de ese telar.',
            ], 500);
        }
    }

    /**
     * Registra una devolución asociada a un proceso de atado (AtaMontadoTelas).
     *
     * El registro se vincula al montado mediante RefId. Los campos Telar y Lote
     * del formulario son informativos (viven en el montado padre) y no se
     * persisten porque la tabla AtaDevoluciones no los contempla.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        $data = $request->validate([
            'ref_id' => ['required', 'integer'],
            'no_julio' => ['nullable', 'string', 'max:20'],
            'no_produccion' => ['nullable', 'string', 'max:20'],
            'kilos' => ['nullable', 'numeric', 'min:0'],
            'metros' => ['nullable', 'numeric', 'min:0'],
            'ubicacion' => ['nullable', 'string', 'max:10'],
            'fecha_devol' => ['nullable', 'date'],
            'cuenta' => ['nullable', 'string', 'max:10'],
            'calibre' => ['nullable', 'string', 'max:10'],
            'hilo' => ['nullable', 'string', 'max:20'],
            'tipo' => ['nullable', 'string', 'max:5'],
            'obs' => ['nullable', 'string', 'max:255'],
            'config_id' => ['nullable', 'string', 'max:10'],
            'invent_size_id' => ['nullable', 'string', 'max:10'],
            'invent_color_id' => ['nullable', 'string', 'max:10'],
        ]);

        $montado = AtaMontadoTelasModel::find($data['ref_id']);
        if (!$montado) {
            return response()->json(['ok' => false, 'message' => 'No se encontró el atado asociado a la devolución'], 404);
        }

        // Exigir al menos un dato cuantitativo para evitar devoluciones vacías.
        $kilos = $data['kilos'] ?? null;
        $metros = $data['metros'] ?? null;
        if (($kilos === null || (float) $kilos <= 0) && ($metros === null || (float) $metros <= 0)) {
            return response()->json([
                'ok' => false,
                'message' => 'Captura al menos Kilos o Metros para registrar la devolución.',
            ], 422);
        }

        // El "Lote" de la devolución se almacena en la columna NoProduccion con el
        // prefijo "Dev" seguido del número de orden (evitando duplicar el prefijo).
        $ordenBase = trim((string) ($data['no_produccion'] ?? $montado->NoProduccion ?? ''));
        $loteDev = $ordenBase !== ''
            ? (str_starts_with($ordenBase, 'Dev') ? $ordenBase : 'Dev' . $ordenBase)
            : null;

        try {
            $devolucion = AtaDevolucionesModel::create([
                'RefId' => $montado->Id,
                'NoJulio' => $data['no_julio'] ?? $montado->NoJulio,
                'NoProduccion' => $loteDev,
                'Kilos' => $kilos,
                'Metros' => $metros,
                'Ubicacion' => $data['ubicacion'] ?? null,
                'FechaDevol' => $data['fecha_devol'] ?? Carbon::now('America/Mexico_City')->toDateString(),
                'Cuenta' => $data['cuenta'] ?? null,
                'Calibre' => $data['calibre'] ?? null,
                'Hilo' => $data['hilo'] ?? null,
                'Tipo' => $data['tipo'] ?? $montado->Tipo,
                'Obs' => $data['obs'] ?? null,
                'ConfigId' => $data['config_id'] ?? $montado->ConfigId,
                'InventSizeId' => $data['invent_size_id'] ?? $montado->InventSizeId,
                'InventColorId' => $data['invent_color_id'] ?? $montado->InventColorId,
                // El Estatus queda ligado al del atado padre (AtaMontadoTelas) desde su creación.
                'Estatus' => $montado->Estatus ?: 'Activo',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al registrar devolución de atadores', [
                'ref_id' => $montado->Id,
                'no_julio' => $montado->NoJulio,
                'no_orden' => $montado->NoProduccion,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo registrar la devolución: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Devolución registrada correctamente',
            'id' => $devolucion->Id,
        ]);
    }
}
