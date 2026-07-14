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
     * Estatus de AtaMontadoTelas que se consideran "atado finalizado" para
     * efectos de buscar julios previos del mismo Telar y Tipo. Un atado pasa
     * por Terminado -> Calificado -> Autorizado; los históricos casi siempre
     * ya están en Calificado o Autorizado, por lo que hay que incluir los tres.
     */
    private const JULIO_ESTATUS_FINALIZADOS = ['Terminado', 'Calificado', 'Autorizado'];

    /**
     * Julios atados (AtaMontadoTelas) de un Telar, filtrados por el mismo Tipo
     * del atado actual y en estatus finalizado, ordenados del más reciente al
     * más antiguo. Se sugiere el más reciente, excluyendo explícitamente el
     * atado que se está trabajando actualmente (exclude_id) para no auto-
     * sugerirse a sí mismo cuando ya alcanzó un estatus finalizado.
     */
    public function julios(Request $request): JsonResponse
    {
        $data = $request->validate([
            'telar' => ['required', 'string', 'max:20'],
            'tipo' => ['nullable', 'string', 'max:20'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        try {
            $fechaMinima = Carbon::now('America/Mexico_City')->subDays(20)->startOfDay();
            $query = AtaMontadoTelasModel::where('NoTelarId', $data['telar'])
                ->whereIn('Estatus', self::JULIO_ESTATUS_FINALIZADOS)
                ->where('Fecha', '>=', $fechaMinima->toDateString());

            if (!empty($data['tipo'])) {
                $query->where('Tipo', $data['tipo']);
            }

            if (!empty($data['exclude_id'])) {
                $query->where('Id', '!=', $data['exclude_id']);
            }

            $registros = $query->orderByDesc('Fecha')
                ->orderByDesc('Turno')
                ->orderByDesc('Id')
                ->get();

            $julios = $registros->pluck('NoJulio')->filter()->unique()->values();

            $registroSugerido = $registros->first();
            $sugerido = $registroSugerido->NoJulio ?? null;

            // Cuenta y Calibre viven en TejHistorialInventarioTelares, donde se
            // insertan (Status = 'Completado') cuando el atado pasa a Autorizado.
            // El Hilo, en cambio, se toma directo de AtaMontadoTelas.ConfigId.
            $cuenta = null;
            $calibre = null;
            $hilo = $registroSugerido->ConfigId ?? null;

            if ($registroSugerido) {
                $historial = DB::connection('sqlsrv')
                    ->table('TejHistorialInventarioTelares')
                    ->where('NoTelarId', $data['telar'])
                    ->where('NoJulio', $registroSugerido->NoJulio)
                    ->where('NoProduccion', $registroSugerido->NoProduccion)
                    ->where('Status', 'Completado')
                    ->orderByDesc('FechaAtado')
                    ->first();

                if ($historial) {
                    $cuenta = $historial->Cuenta;
                    $calibre = $historial->Calibre;
                }
            }

            return response()->json([
                'ok' => true,
                'julios' => $julios,
                'sugerido' => $sugerido,
                'cuenta' => $cuenta,
                'calibre' => $calibre,
                'hilo' => $hilo,
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
     * Registra o actualiza una devolución asociada a un proceso de atado (AtaMontadoTelas).
     *
     * El registro se vincula al montado mediante RefId. NoProduccion guarda el
     * lote de devolución con prefijo "Dev" y LoteOriginal guarda el mismo lote
     * sin ese prefijo.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['ok' => false, 'message' => 'No autenticado'], 401);
        }

        $data = $request->validate([
            'ref_id' => ['required', 'integer'],
            'telar' => ['required', 'string', 'max:10'],
            'no_julio' => ['required', 'string', 'max:20'],
            'no_produccion' => ['required', 'string', 'max:20'],
            'kilos' => ['required', 'numeric', 'gt:0'],
            'metros' => ['required', 'numeric', 'gt:0'],
            'ubicacion' => ['required', 'string', 'max:10'],
            'fecha_devol' => ['required', 'date'],
            'cuenta' => ['required', 'string', 'max:10'],
            'calibre' => ['required', 'string', 'max:10'],
            'hilo' => ['required', 'string', 'max:20'],
            'tipo' => ['required', 'string', 'max:5'],
            'obs' => ['nullable', 'string', 'max:255'],
            'config_id' => ['nullable', 'string', 'max:10'],
            'invent_size_id' => ['nullable', 'string', 'max:10'],
            'invent_color_id' => ['nullable', 'string', 'max:10'],
        ]);

        $montado = AtaMontadoTelasModel::find($data['ref_id']);
        if (!$montado) {
            return response()->json(['ok' => false, 'message' => 'No se encontró el atado asociado a la devolución'], 404);
        }

        $kilos = $data['kilos'] ?? null;
        $metros = $data['metros'] ?? null;

        // El "Lote" de la devolución se almacena en la columna NoProduccion con el
        // prefijo "Dev" seguido del número de orden (evitando duplicar el prefijo).
        $ordenBase = trim((string) ($data['no_produccion'] ?? $montado->NoProduccion ?? ''));
        $loteOriginal = preg_replace('/^Dev/i', '', $ordenBase) ?? '';
        $loteOriginal = trim($loteOriginal);
        $loteDev = $ordenBase !== ''
            ? 'Dev' . $loteOriginal
            : null;
        if ($loteDev !== null && strlen($loteDev) > 20) {
            return response()->json([
                'ok' => false,
                'message' => 'El lote de devolución excede los 20 caracteres permitidos.',
            ], 422);
        }

        // LoteOriginal conserva el lote base usado para formar NoProduccion sin el prefijo "Dev".
        $noJulio = $data['no_julio'] ?? $montado->NoJulio;
        $loteOriginal = $loteOriginal !== '' ? $loteOriginal : null;

        try {
            $payload = [
                'RefId' => $montado->Id,
                'NoTelarId' => $data['telar'] ?? $montado->NoTelarId,
                'NoJulio' => $noJulio,
                'NoProduccion' => $loteDev,
                'LoteOriginal' => $loteOriginal,
                // Siempre 0 al registrar la devolución.
                'integer' => 0,
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
            ];

            $devolucion = AtaDevolucionesModel::where('RefId', $montado->Id)
                ->orderByDesc('Id')
                ->first();

            if ($devolucion) {
                $devolucion->fill($payload);
                $devolucion->save();
            } else {
                $devolucion = AtaDevolucionesModel::create($payload);
            }
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
            'message' => 'Devolución guardada correctamente',
            'id' => $devolucion->Id,
        ]);
    }
}
