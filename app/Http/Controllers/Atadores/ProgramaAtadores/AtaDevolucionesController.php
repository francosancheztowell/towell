<?php

namespace App\Http\Controllers\Atadores\ProgramaAtadores;

use App\Http\Controllers\Controller;
use App\Models\Atadores\AtaDevolucionesModel;
use App\Models\Atadores\AtaMontadoTelasModel;
use App\Models\Inventario\InvTelasReservadas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
            $fechaMinima = Carbon::now('America/Mexico_City')->subDays(30)->startOfDay();
            $query = AtaMontadoTelasModel::where('NoTelarId', $data['telar'])
                ->whereIn('Estatus', self::JULIO_ESTATUS_FINALIZADOS)
                ->where('Fecha', '>=', $fechaMinima->toDateString());

            if (! empty($data['tipo'])) {
                $query->where('Tipo', $data['tipo']);
            }

            if (! empty($data['exclude_id'])) {
                $query->where('Id', '!=', $data['exclude_id']);
            }

            $registros = $query->orderByDesc('Fecha')
                ->orderByDesc('Turno')
                ->orderByDesc('Id')
                ->get();

            // Cuenta y Calibre viven en TejHistorialInventarioTelares, donde se
            // insertan (Status = 'Completado') cuando el atado pasa a Autorizado.
            // Se consultan todos los julios en bloque para poder completar el
            // formulario al elegir cualquiera de las sugerencias.
            $historiales = collect();
            $juliosRegistrados = $registros->pluck('NoJulio')->filter()->unique()->values();
            if ($juliosRegistrados->isNotEmpty()) {
                $historiales = DB::connection('sqlsrv')
                    ->table('TejHistorialInventarioTelares')
                    ->where('NoTelarId', $data['telar'])
                    ->whereIn('NoJulio', $juliosRegistrados)
                    ->where('Status', 'Completado')
                    ->orderByDesc('FechaAtado')
                    ->get(['NoJulio', 'NoProduccion', 'Cuenta', 'Calibre', 'FechaAtado']);
            }

            $historialPorJulioYLote = $historiales
                ->groupBy(fn ($historial) => $this->claveJulioYLote($historial->NoJulio ?? null, $historial->NoProduccion ?? null))
                ->map(fn ($historialesDelJulio) => $historialesDelJulio->first());

            $datosPorJulio = $registros
                ->filter(fn ($registro) => filled($registro->NoJulio))
                ->map(function ($registro) use ($historialPorJulioYLote) {
                    $historial = $historialPorJulioYLote->get(
                        $this->claveJulioYLote($registro->NoJulio, $registro->NoProduccion)
                    );

                    return [
                        'julio' => trim((string) $registro->NoJulio),
                        'cuenta' => $historial->Cuenta ?? null,
                        'calibre' => $historial->Calibre ?? null,
                        'hilo' => $registro->ConfigId ?? null,
                        'lote' => $this->formatearLoteDevolucion($registro->NoProduccion),
                        'tipo' => $registro->Tipo ?? null,
                    ];
                })
                // La consulta ya viene del más reciente al más antiguo.
                ->unique('julio')
                ->values();

            $registroSugerido = $datosPorJulio->first();
            $julios = $datosPorJulio->pluck('julio')->values();

            return response()->json([
                'ok' => true,
                'julios' => $julios,
                'registros' => $datosPorJulio,
                'sugerido' => $registroSugerido['julio'] ?? null,
                'cuenta' => $registroSugerido['cuenta'] ?? null,
                'calibre' => $registroSugerido['calibre'] ?? null,
                'hilo' => $registroSugerido['hilo'] ?? null,
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
     * Devuelve los límites que aún pueden capturarse para la devolución de un
     * julio. La reserva es la fuente de entrada; las devoluciones anteriores
     * del mismo origen se descuentan para impedir excedentes acumulados.
     */
    public function disponibilidad(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ref_id' => ['nullable', 'integer'],
            'telar' => ['required', 'string', 'max:10'],
            'no_julio' => ['required', 'string', 'max:20'],
            'tipo' => ['required', 'string', 'max:20'],
        ]);

        $reserva = $this->buscarReservaOrigen($data);
        if (! $reserva) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró la entrada de inventario para el Telar, Julio y Tipo seleccionados.',
            ], 422);
        }

        $devolucionActualId = null;
        if (! empty($data['ref_id'])) {
            $devolucionActualId = AtaDevolucionesModel::where('RefId', $data['ref_id'])
                ->orderByDesc('Id')
                ->value('Id');
        }

        return response()->json([
            'ok' => true,
            'disponibilidad' => $this->calcularDisponibilidad($reserva, $devolucionActualId),
        ]);
    }

    /**
     * Registra o actualiza una devolución asociada a un proceso de atado (AtaMontadoTelas).
     *
     * El registro se vincula al montado mediante RefId. NoProduccion guarda el
     * lote de devolución con prefijo "DEV" y LoteOriginal guarda el mismo lote
     * sin ese prefijo.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
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
        if (! $montado) {
            return response()->json(['ok' => false, 'message' => 'No se encontró el atado asociado a la devolución'], 404);
        }

        $kilos = $data['kilos'] ?? null;
        $metros = $data['metros'] ?? null;

        // El "Lote" de la devolución se almacena en la columna NoProduccion con el
        // prefijo "DEV" seguido del número de orden (evitando duplicar el prefijo).
        $ordenBase = trim((string) ($data['no_produccion'] ?? $montado->NoProduccion ?? ''));
        $loteOriginal = $this->extraerLoteOriginal($ordenBase);
        $loteDev = $this->formatearLoteDevolucion($ordenBase);
        if ($loteDev !== null && strlen($loteDev) > 20) {
            return response()->json([
                'ok' => false,
                'message' => 'El lote de devolución excede los 20 caracteres permitidos.',
            ], 422);
        }

        // LoteOriginal conserva el lote base usado para formar NoProduccion sin el prefijo "DEV".
        $noJulio = $data['no_julio'] ?? $montado->NoJulio;

        try {
            [$devolucion, $disponibilidad] = DB::connection('sqlsrv')->transaction(function () use ($data, $montado, $noJulio, $loteDev, $loteOriginal, $kilos, $metros) {
                $devolucion = AtaDevolucionesModel::where('RefId', $montado->Id)
                    ->orderByDesc('Id')
                    ->lockForUpdate()
                    ->first();

                if ($this->estaBloqueadaPorAx($devolucion)) {
                    throw ValidationException::withMessages([
                        // 'devolucion' => ['Esta devolución ya fue procesada en AX y no se puede modificar.'],
                    ]);
                }

                /*
                 * VALIDACIÓN TEMPORALMENTE DESHABILITADA A PETICIÓN DEL USUARIO.
                 *
                 * $reserva = $this->buscarReservaOrigen($data, true);
                 * if (! $reserva) {
                 *     throw ValidationException::withMessages([
                 *         'no_julio' => ['No se encontró la entrada de inventario para el Telar, Julio y Tipo seleccionados.'],
                 *     ]);
                 * }
                 *
                 * $disponibilidad = $this->calcularDisponibilidad($reserva, $devolucion?->Id);
                 * if ((float) $kilos > $disponibilidad['kilos_disponibles'] + 0.0001) {
                 *     throw ValidationException::withMessages([
                 *         'kilos' => [sprintf('Los kilos a devolver exceden el disponible (%.4f kg).', $disponibilidad['kilos_disponibles'])],
                 *     ]);
                 * }
                 *
                 * if ((float) $metros > $disponibilidad['metros_disponibles'] + 0.0001) {
                 *     throw ValidationException::withMessages([
                 *         'metros' => [sprintf('Los metros a devolver exceden el disponible (%.4f m).', $disponibilidad['metros_disponibles'])],
                 *     ]);
                 * }
                 */
                $disponibilidad = null;

                $payload = [
                    'RefId' => $montado->Id,
                    // Se conserva el vínculo ya existente mientras la validación está pausada.
                    'InvTelasReservadaId' => $devolucion?->InvTelasReservadaId,
                    'NoTelarId' => $data['telar'],
                    'NoJulio' => $noJulio,
                    'NoProduccion' => $loteDev,
                    'LoteOriginal' => $loteOriginal,
                    // Siempre 0 al registrar la devolución.
                    'integer' => 0,
                    'Kilos' => $kilos,
                    'Metros' => $metros,
                    'Ubicacion' => $data['ubicacion'],
                    'FechaDevol' => $data['fecha_devol'] ?? Carbon::now('America/Mexico_City')->toDateString(),
                    'Cuenta' => $data['cuenta'],
                    'Calibre' => $data['calibre'],
                    'Hilo' => $data['hilo'],
                    'Tipo' => $data['tipo'],
                    'Obs' => $data['obs'] ?? null,
                    'ConfigId' => $data['config_id'] ?? $montado->ConfigId,
                    'InventSizeId' => $data['invent_size_id'] ?? $montado->InventSizeId,
                    'InventColorId' => $data['invent_color_id'] ?? $montado->InventColorId,
                    // El Estatus queda ligado al del atado padre (AtaMontadoTelas) desde su creación.
                    'Estatus' => $montado->Estatus ?: 'Activo',
                ];

                if ($devolucion) {
                    $devolucion->fill($payload);
                    $devolucion->save();
                } else {
                    $devolucion = AtaDevolucionesModel::create([
                        ...$payload,
                        'AX' => 0,
                    ]);
                }

                return [$devolucion, $disponibilidad];
            });
        } catch (ValidationException $e) {
            $errores = $e->errors();
            $bloqueadoPorAx = array_key_exists('devolucion', $errores);

            return response()->json([
                'ok' => false,
                'message' => collect($errores)->flatten()->first() ?? 'No se pudo validar la devolución.',
                'bloqueado_ax' => $bloqueadoPorAx,
            ], $bloqueadoPorAx ? 423 : 422);
        } catch (\Throwable $e) {
            Log::error('Error al registrar devolución de atadores', [
                'ref_id' => $montado->Id,
                'no_julio' => $montado->NoJulio,
                'no_orden' => $montado->NoProduccion,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo registrar la devolución: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Devolución guardada correctamente',
            'id' => $devolucion->Id,
            'disponibilidad' => $disponibilidad,
        ]);
    }

    /**
     * Localiza la entrada original. El NoJulio de AtaMontadoTelas puede ser
     * distinto del InventSerialId del inventario (por ejemplo, un julio de
     * proceso contra el serial físico). Por ello se intenta primero el serial
     * exacto y, si no coincide, la reserva activa del mismo Telar y Tipo.
     */
    private function buscarReservaOrigen(array $data, bool $bloquear = false): ?InvTelasReservadas
    {
        $telar = trim((string) ($data['telar'] ?? ''));
        $julio = trim((string) ($data['no_julio'] ?? ''));
        $tipo = mb_strtolower(trim((string) ($data['tipo'] ?? '')), 'UTF-8');

        $query = InvTelasReservadas::query()
            ->where('NoTelarId', $telar)
            ->whereRaw('LOWER(LTRIM(RTRIM(Tipo))) = ?', [$tipo]);

        if ($bloquear) {
            $query->lockForUpdate();
        }

        // En instalaciones donde NoJulio e InventSerialId sí son iguales,
        // mantener esa coincidencia como la opción más precisa.
        $porSerial = (clone $query)
            ->where('InventSerialId', $julio)
            ->where('Status', 'Reservado')
            ->orderByDesc('Id')
            ->first()
            ?? (clone $query)
                ->where('InventSerialId', $julio)
                ->orderByDesc('Id')
                ->first();

        if ($porSerial) {
            return $porSerial;
        }

        // En el flujo de atadores, el Julio mostrado puede ser el de proceso;
        // la entrada física se identifica por la reserva activa del Telar/Tipo.
        return (clone $query)
            ->where('Status', 'Reservado')
            ->orderByDesc('Id')
            ->first()
            ?? $query->orderByDesc('Id')->first();
    }

    /**
     * Calcula lo que queda disponible. Incluye devoluciones antiguas sin FK
     * para que los registros existentes con InvTelasReservadaId = NULL también
     * cuenten contra el límite.
     */
    private function calcularDisponibilidad(InvTelasReservadas $reserva, ?int $excluirDevolucionId = null): array
    {
        $tipo = mb_strtolower(trim((string) $reserva->Tipo), 'UTF-8');

        $devoluciones = AtaDevolucionesModel::query()
            ->where(function ($query) use ($reserva, $tipo) {
                $query->where('InvTelasReservadaId', $reserva->Id)
                    ->orWhere(function ($legacy) use ($reserva, $tipo) {
                        $legacy->whereNull('InvTelasReservadaId')
                            ->where('NoTelarId', $reserva->NoTelarId)
                            ->where('NoJulio', $reserva->InventSerialId)
                            ->whereRaw('LOWER(LTRIM(RTRIM(Tipo))) = ?', [$tipo]);
                    });
            });

        if ($excluirDevolucionId) {
            $devoluciones->where('Id', '!=', $excluirDevolucionId);
        }

        $kilosDevueltos = (float) (clone $devoluciones)->sum('Kilos');
        $metrosDevueltos = (float) $devoluciones->sum('Metros');
        $kilosIngresados = (float) $reserva->InventQty;
        $metrosIngresados = (float) $reserva->Metros;

        return [
            'inv_telas_reservada_id' => (int) $reserva->Id,
            'invent_serial_id' => trim((string) $reserva->InventSerialId),
            'kilos_ingresados' => $kilosIngresados,
            'metros_ingresados' => $metrosIngresados,
            'kilos_ya_devueltos' => $kilosDevueltos,
            'metros_ya_devueltos' => $metrosDevueltos,
            'kilos_disponibles' => max(0, $kilosIngresados - $kilosDevueltos),
            'metros_disponibles' => max(0, $metrosIngresados - $metrosDevueltos),
        ];
    }

    private function extraerLoteOriginal(?string $lote): ?string
    {
        $loteOriginal = trim((string) (preg_replace('/^DEV/i', '', trim((string) $lote)) ?? ''));

        return $loteOriginal !== '' ? $loteOriginal : null;
    }

    private function formatearLoteDevolucion(?string $lote): ?string
    {
        $loteOriginal = $this->extraerLoteOriginal($lote);

        return $loteOriginal !== null ? 'DEV'.$loteOriginal : null;
    }

    private function claveJulioYLote(?string $julio, ?string $lote): string
    {
        return mb_strtoupper(trim((string) $julio), 'UTF-8').'|'.mb_strtoupper(trim((string) $lote), 'UTF-8');
    }

    private function estaBloqueadaPorAx(?AtaDevolucionesModel $devolucion): bool
    {
        return (int) ($devolucion?->AX ?? 0) === 1;
    }
}
